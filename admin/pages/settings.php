<?php
/**
 * DVD Profiler Liste - Admin Settings
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.5
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Versionsinformationen laden
require_once dirname(__DIR__, 2) . '/includes/version.php';

// CSRF-Token generieren
$csrfToken = generateCSRFToken();

// Variablen initialisieren
$error = '';
$success = '';

// GitHub Update System
class GitHubUpdater {
    private $pdo;
    private $repo = 'lunasans/dvdprofiler.liste';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getLatestRelease(): ?array {
        // Cache für 1 Stunde
        $cacheKey = 'github_latest_release';
        $cached = getSetting($cacheKey . '_data', '');
        $cacheTime = (int)getSetting($cacheKey . '_time', '0');
        
        if ($cached && (time() - $cacheTime < 3600)) {
            return json_decode($cached, true);
        }
        
        $apiUrl = "https://api.github.com/repos/{$this->repo}/releases/latest";
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: DVD-Profiler-Updater/1.0\r\n",
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($opts);
        $json = @file_get_contents($apiUrl, false, $context);
        
        if ($json) {
            $data = json_decode($json, true);
            if ($data && isset($data['tag_name'])) {
                // Cache speichern
                setSetting($cacheKey . '_data', $json);
                setSetting($cacheKey . '_time', (string)time());
                return $data;
            }
        }
        
        return null;
    }
    
    public function createBackup(): string {
        $backupDir = dirname(__DIR__, 2) . '/admin/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $backupFile = $backupDir . "backup_{$timestamp}.zip";
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            throw new Exception('Backup-Datei konnte nicht erstellt werden.');
        }
        
        $baseDir = dirname(__DIR__, 2);
        $excludeDirs = ['admin/backups', 'uploads', 'cache', 'logs', '.git'];
        $excludeFiles = ['.env', 'config/config.php'];
        
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            $relativePath = str_replace($baseDir . '/', '', $file->getPathname());
            
            // Prüfung auf ausgeschlossene Pfade
            $skip = false;
            foreach ($excludeDirs as $excludeDir) {
                if (str_starts_with($relativePath, $excludeDir)) {
                    $skip = true;
                    break;
                }
            }
            
            if ($skip || in_array($relativePath, $excludeFiles)) {
                continue;
            }
            
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }
        
        $zip->close();
        return $backupFile;
    }
    
    public function performUpdate($releaseData): array {
        try {
            // 1. Backup erstellen
            $backupFile = $this->createBackup();
            
            // 2. Update-Dateien herunterladen
            $result = $this->downloadAndExtractUpdate($releaseData['zipball_url']);
            if (!$result['success']) {
                return $result;
            }
            
            // 3. Datenbank-Updates (falls vorhanden)
            $this->runUpdateSQL();
            
            // 4. Version in Datenbank aktualisieren
            setSetting('version', ltrim($releaseData['tag_name'], 'v'));
            setSetting('last_update', date('Y-m-d H:i:s'));
            
            return [
                'success' => true,
                'message' => "✅ Update erfolgreich! Backup erstellt: " . basename($backupFile)
            ];
            
        } catch (Exception $e) {
            error_log('Update failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => '❌ Update fehlgeschlagen: ' . $e->getMessage()
            ];
        }
    }
    
    private function downloadAndExtractUpdate(string $zipUrl): array {
        $tempFile = sys_get_temp_dir() . '/dvd_update_' . uniqid() . '.zip';
        
        // Download mit Timeout
        $context = stream_context_create([
            'http' => [
                'timeout' => 300, // 5 Minuten
                'user_agent' => 'DVD-Profiler-Updater/1.0'
            ]
        ]);
        
        if (!copy($zipUrl, $tempFile, $context)) {
            return ['success' => false, 'message' => 'Download fehlgeschlagen.'];
        }
        
        $zip = new ZipArchive();
        if ($zip->open($tempFile) !== TRUE) {
            unlink($tempFile);
            return ['success' => false, 'message' => 'ZIP-Datei konnte nicht geöffnet werden.'];
        }
        
        $baseDir = dirname(__DIR__, 2);
        $repoPrefix = null;
        
        // Repository-Prefix ermitteln
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (str_contains($filename, '/')) {
                $repoPrefix = explode('/', $filename)[0];
                break;
            }
        }
        
        if (!$repoPrefix) {
            $zip->close();
            unlink($tempFile);
            return ['success' => false, 'message' => 'Ungültiges ZIP-Format.'];
        }
        
        // Dateien extrahieren
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $relativePath = preg_replace("#^{$repoPrefix}/#", '', $filename);
            
            if (empty($relativePath) || $relativePath === $filename) {
                continue;
            }
            
            $targetPath = $baseDir . '/' . $relativePath;
            
            if (str_ends_with($filename, '/')) {
                @mkdir($targetPath, 0755, true);
            } else {
                @mkdir(dirname($targetPath), 0755, true);
                file_put_contents($targetPath, $zip->getFromIndex($i));
            }
        }
        
        $zip->close();
        unlink($tempFile);
        
        return ['success' => true, 'message' => 'Update-Dateien erfolgreich extrahiert.'];
    }
    
    private function runUpdateSQL(): void {
        $sqlFile = dirname(__DIR__, 2) . '/update.sql';
        if (file_exists($sqlFile)) {
            try {
                $sql = file_get_contents($sqlFile);
                $this->pdo->exec($sql);
                unlink($sqlFile);
            } catch (PDOException $e) {
                error_log('Update SQL failed: ' . $e->getMessage());
                throw $e;
            }
        }
    }
}

// Update System initialisieren
$updater = new GitHubUpdater($pdo);
$latestData = $updater->getLatestRelease();
$latestVersion = $latestData['tag_name'] ?? 'unbekannt';
$localVersion = DVDPROFILER_VERSION;

$isUpdateAvailable = $latestData && version_compare(ltrim($latestVersion, 'v'), $localVersion, '>');

// POST-Handler mit CSRF-Schutz
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token prüfen
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $error = '❌ Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.';
    } else {
        // Einstellungen speichern
        if (isset($_POST['save_settings'])) {
            $allowedSettings = [
                'site_title' => ['maxlength' => 255, 'required' => true],
                'site_description' => ['maxlength' => 500],
                'base_url' => ['filter' => FILTER_VALIDATE_URL],
                'environment' => ['maxlength' => 20],
                'theme' => ['maxlength' => 50],
                'items_per_page' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 5, 'max_range' => 100]],
                'enable_2fa' => ['filter' => FILTER_VALIDATE_BOOLEAN],
                'login_attempts_max' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1, 'max_range' => 10]],
                'login_lockout_time' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 60, 'max_range' => 3600]],
                'session_timeout' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 300, 'max_range' => 86400]],
                'backup_retention_days' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1, 'max_range' => 365]]
            ];
            
            $savedCount = 0;
            foreach ($allowedSettings as $key => $validation) {
                if (isset($_POST[$key])) {
                    $value = trim($_POST[$key]);
                    
                    // Validierung anwenden
                    if (isset($validation['filter'])) {
                        $options = $validation['options'] ?? null;
                        if (!filter_var($value, $validation['filter'], $options)) {
                            $error = "❌ Ungültiger Wert für {$key}.";
                            break;
                        }
                    }
                    
                    if (isset($validation['maxlength']) && strlen($value) > $validation['maxlength']) {
                        $error = "❌ {$key} ist zu lang (max. {$validation['maxlength']} Zeichen).";
                        break;
                    }
                    
                    if (isset($validation['required']) && empty($value)) {
                        $error = "❌ {$key} ist erforderlich.";
                        break;
                    }
                    
                    if (setSetting($key, $value)) {
                        $savedCount++;
                    }
                }
            }
            
            if (!$error) {
                $success = "✅ {$savedCount} Einstellung(en) erfolgreich gespeichert.";
                // Settings neu laden
                header('Location: ?page=settings&saved=1');
                exit;
            }
        }
        
        // Update durchführen
        if (isset($_POST['start_update']) && $latestData) {
            $result = $updater->performUpdate($latestData);
            if ($result['success']) {
                $success = $result['message'];
                $localVersion = ltrim($latestVersion, 'v'); // Version aktualisieren
                $isUpdateAvailable = false;
            } else {
                $error = $result['message'];
            }
        }
        
        // Backup löschen
        if (isset($_POST['delete_backup'])) {
            $backupFile = basename($_POST['delete_backup']); // Path traversal verhindern
            $backupPath = dirname(__DIR__, 2) . '/admin/backups/' . $backupFile;
            
            if (file_exists($backupPath) && str_starts_with($backupFile, 'backup_') && str_ends_with($backupFile, '.zip')) {
                if (unlink($backupPath)) {
                    $success = '✅ Backup erfolgreich gelöscht.';
                } else {
                    $error = '❌ Backup konnte nicht gelöscht werden.';
                }
            } else {
                $error = '❌ Backup-Datei nicht gefunden oder ungültig.';
            }
        }
        
        // Cache leeren
        if (isset($_POST['clear_cache'])) {
            $cacheDir = dirname(__DIR__, 2) . '/cache/';
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '*');
                $deleted = 0;
                foreach ($files as $file) {
                    if (is_file($file) && unlink($file)) {
                        $deleted++;
                    }
                }
                $success = "✅ {$deleted} Cache-Dateien gelöscht.";
            } else {
                $error = '❌ Cache-Verzeichnis nicht gefunden.';
            }
        }
    }
}

// Backup-Dateien sicher auflisten
$backupDir = dirname(__DIR__, 2) . '/admin/backups/';
$backups = [];
if (is_dir($backupDir)) {
    $files = glob($backupDir . 'backup_*.zip');
    foreach ($files as $file) {
        $basename = basename($file);
        if (preg_match('/^backup_\d{8}_\d{6}\.zip$/', $basename)) {
            $backups[] = [
                'name' => $basename,
                'size' => filesize($file),
                'date' => filemtime($file)
            ];
        }
    }
    // Nach Datum sortieren (neueste zuerst)
    usort($backups, fn($a, $b) => $b['date'] - $a['date']);
}

// Build-Info und Features
$buildInfo = getDVDProfilerBuildInfo();
$systemRequirements = checkDVDProfilerSystemRequirements();

// URL-Validierung für saved Parameter
$showSaved = isset($_GET['saved']) && $_GET['saved'] === '1';
?>

<div class="settings-container">
    <div class="settings-header mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="settings-title">
                    <i class="bi bi-gear"></i>
                    Systemeinstellungen
                </h1>
                <p class="settings-subtitle">
                    Konfiguration und Verwaltung von DVD Profiler Liste v<?= DVDPROFILER_VERSION ?> "<?= DVDPROFILER_CODENAME ?>"
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <?php if ($isUpdateAvailable): ?>
                    <div class="update-alert">
                        <i class="bi bi-arrow-up-circle text-warning"></i>
                        <span>Update verfügbar: <?= htmlspecialchars($latestVersion) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($showSaved && !$error): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            Einstellungen erfolgreich gespeichert!
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle"></i>
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Settings Tabs -->
    <div class="settings-tabs">
        <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                    <i class="bi bi-gear"></i> Allgemein
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                    <i class="bi bi-shield-check"></i> Sicherheit
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                    <i class="bi bi-cpu"></i> System
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="updates-tab" data-bs-toggle="tab" data-bs-target="#updates" type="button" role="tab">
                    <i class="bi bi-arrow-up-circle"></i> Updates
                    <?php if ($isUpdateAvailable): ?>
                        <span class="badge bg-warning text-dark ms-1">!</span>
                    <?php endif; ?>
                </button>
            </li>
        </ul>

        <div class="tab-content mt-4" id="settingsTabContent">
            <!-- Tab: Allgemein -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-sliders"></i>
                            Allgemeine Einstellungen
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="site_title" class="form-label">Website-Titel *</label>
                                        <input type="text" name="site_title" id="site_title" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting('site_title', 'DVD Profiler Liste')) ?>" 
                                               required maxlength="255">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="base_url" class="form-label">Base URL</label>
                                        <input type="url" name="base_url" id="base_url" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting('base_url', '')) ?>"
                                               placeholder="https://ihre-domain.de/dvd/">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="site_description" class="form-label">Website-Beschreibung</label>
                                <textarea name="site_description" id="site_description" class="form-control" rows="3" maxlength="500"><?= htmlspecialchars(getSetting('site_description', '')) ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="theme" class="form-label">Theme</label>
                                        <select name="theme" id="theme" class="form-select">
                                            <option value="default" <?= getSetting('theme', 'default') === 'default' ? 'selected' : '' ?>>Standard</option>
                                            <option value="dark" <?= getSetting('theme', 'default') === 'dark' ? 'selected' : '' ?>>Dark Mode</option>
                                            <option value="blue" <?= getSetting('theme', 'default') === 'blue' ? 'selected' : '' ?>>Blau</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="items_per_page" class="form-label">Filme pro Seite</label>
                                        <input type="number" name="items_per_page" id="items_per_page" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting('items_per_page', '12')) ?>" 
                                               min="5" max="100">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="environment" class="form-label">Umgebung</label>
                                        <select name="environment" id="environment" class="form-select">
                                            <option value="production" <?= getSetting('environment', 'production') === 'production' ? 'selected' : '' ?>>Production</option>
                                            <option value="development" <?= getSetting('environment', 'production') === 'development' ? 'selected' : '' ?>>Development</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="save_settings" class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                Einstellungen speichern
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab: Sicherheit -->
            <div class="tab-pane fade" id="security" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-shield-lock"></i>
                            Sicherheitseinstellungen
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="login_attempts_max" class="form-label">Max. Login-Versuche</label>
                                        <input type="number" name="login_attempts_max" id="login_attempts_max" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting('login_attempts_max', '5')) ?>" 
                                               min="1" max="10">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="login_lockout_time" class="form-label">Sperrzeit (Sekunden)</label>
                                        <input type="number" name="login_lockout_time" id="login_lockout_time" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting('login_lockout_time', '900')) ?>" 
                                               min="60" max="3600">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="session_timeout" class="form-label">Session-Timeout (Sekunden)</label>
                                        <input type="number" name="session_timeout" id="session_timeout" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting('session_timeout', '3600')) ?>" 
                                               min="300" max="86400">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3 form-check form-switch mt-4">
                                        <input type="hidden" name="enable_2fa" value="0">
                                        <input type="checkbox" name="enable_2fa" id="enable_2fa" class="form-check-input" value="1" 
                                               <?= getSetting('enable_2fa', '1') === '1' ? 'checked' : '' ?>>
                                        <label for="enable_2fa" class="form-check-label">2FA aktivieren</label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" name="save_settings" class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                Sicherheitseinstellungen speichern
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Tab: System -->
            <div class="tab-pane fade" id="system" role="tabpanel">
                <div class="row">
                    <!-- System-Information -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-info-circle"></i>
                                    System-Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="system-info">
                                    <div class="info-row">
                                        <strong>Version:</strong> <?= getDVDProfilerVersionFull() ?>
                                    </div>
                                    <div class="info-row">
                                        <strong>Build-Datum:</strong> <?= DVDPROFILER_BUILD_DATE ?>
                                    </div>
                                    <div class="info-row">
                                        <strong>Branch:</strong> <?= DVDPROFILER_BRANCH ?>
                                    </div>
                                    <div class="info-row">
                                        <strong>Commit:</strong> <?= DVDPROFILER_COMMIT ?>
                                    </div>
                                    <div class="info-row">
                                        <strong>Repository:</strong> 
                                        <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank"><?= DVDPROFILER_REPOSITORY ?></a>
                                    </div>
                                    <div class="info-row">
                                        <strong>PHP Version:</strong> <?= PHP_VERSION ?>
                                    </div>
                                    <div class="info-row">
                                        <strong>Features aktiv:</strong> <?= count(array_filter(DVDPROFILER_FEATURES)) ?>/<?= count(DVDPROFILER_FEATURES) ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- System-Requirements -->
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-check-circle"></i>
                                    System-Anforderungen
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="requirements-list">
                                    <div class="requirement-item">
                                        <i class="bi bi-<?= $systemRequirements['php'] ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i>
                                        <span>PHP ≥ 7.4.0 (Aktuell: <?= PHP_VERSION ?>)</span>
                                    </div>
                                    <div class="requirement-item">
                                        <i class="bi bi-<?= $systemRequirements['php_recommended'] ? 'check-circle text-success' : 'exclamation-triangle text-warning' ?>"></i>
                                        <span>PHP ≥ 8.0.0 (Empfohlen)</span>
                                    </div>
                                    
                                    <?php foreach ($systemRequirements['extensions'] as $ext => $loaded): ?>
                                        <div class="requirement-item">
                                            <i class="bi bi-<?= $loaded ? 'check-circle text-success' : 'x-circle text-danger' ?>"></i>
                                            <span>PHP Extension: <?= $ext ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cache Management -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-arrow-clockwise"></i>
                            Cache-Verwaltung
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Leeren Sie den Cache bei Problemen oder nach Updates.</p>
                        
                        <form method="post" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" name="clear_cache" class="btn btn-warning" 
                                    onclick="return confirm('Sind Sie sicher, dass Sie den Cache leeren möchten?')">
                                <i class="bi bi-trash"></i>
                                Cache leeren
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Backup Management -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-archive"></i>
                            Backup-Verwaltung
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($backups)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Backup-Datei</th>
                                            <th>Größe</th>
                                            <th>Datum</th>
                                            <th>Aktionen</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($backups as $backup): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($backup['name']) ?></td>
                                                <td><?= formatBytes($backup['size']) ?></td>
                                                <td><?= date('d.m.Y H:i', $backup['date']) ?></td>
                                                <td>
                                                    <form method="post" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                        <input type="hidden" name="delete_backup" value="<?= htmlspecialchars($backup['name']) ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm" 
                                                                onclick="return confirm('Backup wirklich löschen?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted">Keine Backups vorhanden.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Tab: Updates -->
            <div class="tab-pane fade" id="updates" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-arrow-up-circle"></i>
                            Update-System
                            <?php if ($isUpdateAvailable): ?>
                                <span class="badge bg-warning text-dark ms-2">Update verfügbar</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Aktuelle Version</h6>
                                <div class="version-display">
                                    <span class="version-badge current">v<?= DVDPROFILER_VERSION ?></span>
                                    <div class="version-details">
                                        <small>
                                            "<?= DVDPROFILER_CODENAME ?>" | Build <?= DVDPROFILER_BUILD_DATE ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Neueste Version</h6>
                                <div class="version-display">
                                    <?php if ($latestData): ?>
                                        <span class="version-badge <?= $isUpdateAvailable ? 'available' : 'current' ?>">
                                            <?= htmlspecialchars($latestVersion) ?>
                                        </span>
                                        <div class="version-details">
                                            <small>
                                                <?php if (isset($latestData['published_at'])): ?>
                                                    Veröffentlicht: <?= date('d.m.Y', strtotime($latestData['published_at'])) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">Nicht verfügbar</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($isUpdateAvailable && $latestData): ?>
                            <div class="alert alert-info mt-4">
                                <h6>
                                    <i class="bi bi-info-circle"></i>
                                    Update verfügbar: <?= htmlspecialchars($latestVersion) ?>
                                </h6>
                                
                                <?php if (!empty($latestData['body'])): ?>
                                    <div class="changelog mt-3">
                                        <h6>Changelog:</h6>
                                        <div class="changelog-content">
                                            <?= nl2br(htmlspecialchars(substr($latestData['body'], 0, 500))) ?>
                                            <?php if (strlen($latestData['body']) > 500): ?>
                                                <p><em>... (gekürzt)</em></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-3">
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <button type="submit" name="start_update" class="btn btn-warning" 
                                                onclick="return confirm('Update starten? Ein Backup wird automatisch erstellt.')">
                                            <i class="bi bi-download"></i>
                                            Update jetzt installieren
                                        </button>
                                    </form>
                                    
                                    <a href="<?= DVDPROFILER_GITHUB_URL ?>/releases/latest" target="_blank" class="btn btn-outline-info ms-2">
                                        <i class="bi bi-github"></i>
                                        Details auf GitHub
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success mt-4">
                                <i class="bi bi-check-circle"></i>
                                Sie verwenden die neueste Version von DVD Profiler Liste.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Settings-spezifische Styles */
.settings-container {
    padding: 0;
}

.settings-header {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: var(--radius-lg, 16px);
    padding: var(--space-xl, 24px);
}

.settings-title {
    font-size: 2rem;
    margin-bottom: var(--space-sm, 8px);
    color: var(--text-white, #ffffff);
}

.settings-subtitle {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    margin-bottom: 0;
    font-family: monospace;
    font-size: 0.9rem;
}

.update-alert {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border: 1px solid rgba(255, 193, 7, 0.3);
    border-radius: var(--radius-md, 12px);
    padding: var(--space-md, 16px);
    text-align: center;
    font-size: 0.9rem;
    animation: pulse 2s ease-in-out infinite;
}

.settings-tabs .nav-tabs {
    border-bottom: 2px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.settings-tabs .nav-link {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    margin-right: var(--space-sm, 8px);
    border-radius: var(--radius-md, 12px) var(--radius-md, 12px) 0 0;
    transition: all var(--transition-fast, 0.3s);
}

.settings-tabs .nav-link:hover {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    color: var(--text-white, #ffffff);
}

.settings-tabs .nav-link.active {
    background: var(--gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    border-color: transparent;
    color: var(--text-white, #ffffff);
}

.card {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: var(--radius-lg, 16px);
}

.card-header {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: var(--radius-lg, 16px) var(--radius-lg, 16px) 0 0;
}

.system-info .info-row {
    display: flex;
    justify-content: space-between;
    padding: var(--space-sm, 8px) 0;
    border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    font-size: 0.9rem;
    color: var(--text-white, #ffffff);
}

.system-info .info-row:last-child {
    border-bottom: none;
}

.requirements-list .requirement-item {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 8px);
    padding: var(--space-sm, 8px) 0;
    border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    color: var(--text-white, #ffffff);
}

.requirements-list .requirement-item:last-child {
    border-bottom: none;
}

.version-display {
    text-align: center;
    padding: var(--space-md, 16px);
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-md, 12px);
}

.version-badge {
    display: inline-block;
    padding: var(--space-sm, 8px) var(--space-md, 16px);
    border-radius: var(--radius-md, 12px);
    font-weight: 600;
    font-size: 1rem;
    margin-bottom: var(--space-sm, 8px);
}

.version-badge.current {
    background: var(--gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    color: var(--text-white, #ffffff);
}

.version-badge.available {
    background: linear-gradient(135deg, #f39c12 0%, #e74c3c 100%);
    color: var(--text-white, #ffffff);
    animation: pulse 2s ease-in-out infinite;
}

.version-details {
    font-size: 0.8rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.7));
}

.changelog-content {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-sm, 6px);
    padding: var(--space-md, 16px);
    font-size: 0.9rem;
    max-height: 200px;
    overflow-y: auto;
    white-space: pre-line;
}

/* Form Styling */
.form-control,
.form-select {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.3));
    color: var(--text-white, #ffffff);
}

.form-control:focus,
.form-select:focus {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.2));
    border-color: var(--accent-color, #3498db);
    box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25);
    color: var(--text-white, #ffffff);
}

.form-label {
    color: var(--text-white, #ffffff);
    font-weight: 500;
}

.form-check-label {
    color: var(--text-glass, rgba(255, 255, 255, 0.9));
}

/* Table Styling */
.table {
    color: var(--text-white, #ffffff);
}

.table th {
    border-color: var(--glass-border, rgba(255, 255, 255, 0.2));
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.1));
}

.table td {
    border-color: var(--glass-border, rgba(255, 255, 255, 0.1));
}

/* Responsive */
@media (max-width: 768px) {
    .settings-title {
        font-size: 1.5rem;
    }
    
    .system-info .info-row {
        flex-direction: column;
        gap: var(--space-xs, 4px);
    }
    
    .version-display {
        margin-bottom: var(--space-md, 16px);
    }
}

/* Animations */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.7;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation enhancement
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            if (submitBtn && !submitBtn.disabled) {
                submitBtn.disabled = true;
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Verarbeitung...';
                
                // Re-enable after timeout (fallback)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }, 15000);
            }
        });
    });
    
    // Auto-save indicator
    const inputs = document.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            // Visual indicator that changes need to be saved
            this.style.borderColor = '#f39c12';
            setTimeout(() => {
                this.style.borderColor = '';
            }, 2000);
        });
    });
    
    console.log('DVD Profiler Liste Settings v<?= DVDPROFILER_VERSION ?> loaded');
});
</script>