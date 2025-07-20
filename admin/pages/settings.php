<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

// Security: Session prüfen
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// CSRF-Token generieren
$csrfToken = generateCSRFToken();

// Einstellungen laden (bereits in bootstrap.php gecacht)
$localVersion = getSetting('version', '1.0.0');
$error = '';
$success = '';
$changelog = '';

// GitHub Update System
class GitHubUpdater {
    private const CACHE_LIFETIME = 3600; // 1 Stunde
    private const GITHUB_REPO = 'lunasans/dvdprofiler.liste';
    
    private PDO $pdo;
    
    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }
    
    public function getLatestRelease(): ?array {
        // Cache prüfen
        $cached = $this->getCachedRelease();
        if ($cached && time() - $cached['timestamp'] < self::CACHE_LIFETIME) {
            return $cached['data'];
        }
        
        // Von GitHub abrufen
        $apiUrl = "https://api.github.com/repos/" . self::GITHUB_REPO . "/releases/latest";
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: DVD-Profiler-Updater/1.0',
                    'Accept: application/vnd.github.v3+json',
                    'Timeout: 10'
                ],
                'timeout' => 10
            ]
        ]);
        
        $response = @file_get_contents($apiUrl, false, $context);
        if (!$response) {
            return $cached['data'] ?? null; // Fallback auf Cache
        }
        
        $data = json_decode($response, true);
        if (!$data || !isset($data['tag_name'])) {
            return $cached['data'] ?? null;
        }
        
        // Cache aktualisieren
        $this->cacheRelease($data);
        return $data;
    }
    
    private function getCachedRelease(): ?array {
        try {
            $stmt = $this->pdo->prepare("
                SELECT data, timestamp FROM github_cache 
                WHERE cache_key = 'latest_release' 
                LIMIT 1
            ");
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                return [
                    'data' => json_decode($result['data'], true),
                    'timestamp' => (int)$result['timestamp']
                ];
            }
        } catch (PDOException $e) {
            error_log('GitHub cache read failed: ' . $e->getMessage());
        }
        
        return null;
    }
    
    private function cacheRelease(array $data): void {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO github_cache (cache_key, data, timestamp) 
                VALUES ('latest_release', ?, ?) 
                ON DUPLICATE KEY UPDATE data = VALUES(data), timestamp = VALUES(timestamp)
            ");
            $stmt->execute([json_encode($data), time()]);
        } catch (PDOException $e) {
            error_log('GitHub cache write failed: ' . $e->getMessage());
        }
    }
    
    public function performUpdate(array $releaseData): array {
        if (!isset($releaseData['zipball_url'])) {
            return ['success' => false, 'message' => 'Keine gültige Download-URL gefunden.'];
        }
        
        $backupPath = $this->createBackup();
        if (!$backupPath) {
            return ['success' => false, 'message' => 'Backup-Erstellung fehlgeschlagen.'];
        }
        
        try {
            $result = $this->downloadAndExtractUpdate($releaseData['zipball_url']);
            if ($result['success']) {
                $this->runUpdateSQL();
                updateSetting('version', $releaseData['tag_name']);
                
                return ['success' => true, 'message' => '✅ Update erfolgreich installiert.'];
            } else {
                $this->restoreBackup($backupPath);
                return $result;
            }
        } catch (Exception $e) {
            $this->restoreBackup($backupPath);
            error_log('Update failed: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Update fehlgeschlagen: ' . $e->getMessage()];
        }
    }
    
    private function createBackup(): ?string {
        $backupDir = dirname(__DIR__, 2) . '/admin/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $backupFile = $backupDir . 'backup_' . date('Ymd_His') . '.zip';
        $zip = new ZipArchive();
        
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            return null;
        }
        
        $baseDir = dirname(__DIR__, 2);
        $excludeDirs = ['admin/xml/', 'admin/backups/', 'cover/', 'vendor/'];
        $excludeFiles = ['config/config.php', 'counter.txt'];
        
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($files as $file) {
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
    
    private function restoreBackup(string $backupPath): void {
        // Backup-Wiederherstellung implementieren falls nötig
        error_log("Backup available for restore: {$backupPath}");
    }
}

// Update System initialisieren
$updater = new GitHubUpdater($pdo);
$latestData = $updater->getLatestRelease();
$latestVersion = $latestData['tag_name'] ?? 'unbekannt';
$changelog = $latestData['body'] ?? '';

$isUpdateAvailable = $latestData && version_compare($latestVersion, $localVersion, '>');

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
                'base_url' => ['filter' => FILTER_VALIDATE_URL],
                'language' => ['maxlength' => 10],
                'enable_2fa' => ['filter' => FILTER_VALIDATE_BOOLEAN],
                'login_attempts' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1, 'max_range' => 10]],
                'smtp_host' => ['maxlength' => 255],
                'smtp_sender' => ['filter' => FILTER_VALIDATE_EMAIL]
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
                    
                    if (updateSetting($key, $value)) {
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
                $localVersion = $latestVersion; // Version aktualisieren
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

// URL-Validierung für saved Parameter
$showSaved = isset($_GET['saved']) && $_GET['saved'] === '1';
?>

<div class="container-fluid">
    <h3>⚙️ Systemeinstellungen</h3>
    
    <?php if ($showSaved && !$error): ?>
        <div class="alert alert-success">✅ Einstellungen erfolgreich gespeichert!</div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-tabs" id="settingsTabs">
        <li class="nav-item">
            <a class="nav-link active" data-bs-toggle="tab" href="#tab-general">🔧 Allgemein</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-security">🔒 Sicherheit</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-email">📧 E-Mail</a>
        </li>
        <li class="nav-item">
            <a class="nav-link" data-bs-toggle="tab" href="#tab-update">⬆️ Updates</a>
        </li>
    </ul>

    <div class="tab-content mt-3">
        <!-- Tab Allgemein -->
        <div class="tab-pane fade show active" id="tab-general">
            <form method="post" class="card p-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="mb-3">
                    <label for="site_title" class="form-label">Website-Titel *</label>
                    <input type="text" id="site_title" name="site_title" 
                           value="<?= htmlspecialchars(getSetting('site_title', 'Meine DVD-Verwaltung')) ?>" 
                           class="form-control" maxlength="255" required>
                </div>
                
                <div class="mb-3">
                    <label for="base_url" class="form-label">Basis-URL</label>
                    <input type="url" id="base_url" name="base_url" 
                           value="<?= htmlspecialchars(getSetting('base_url')) ?>" 
                           class="form-control" placeholder="https://example.com">
                    <small class="text-muted">Vollständige URL zu Ihrer Installation</small>
                </div>
                
                <div class="mb-3">
                    <label for="language" class="form-label">Sprache</label>
                    <select id="language" name="language" class="form-control">
                        <option value="de" <?= getSetting('language', 'de') === 'de' ? 'selected' : '' ?>>Deutsch</option>
                        <option value="en" <?= getSetting('language', 'de') === 'en' ? 'selected' : '' ?>>English</option>
                    </select>
                </div>
                
                <button type="submit" name="save_settings" class="btn btn-primary">💾 Speichern</button>
            </form>
        </div>

        <!-- Tab Sicherheit -->
        <div class="tab-pane fade" id="tab-security">
            <form method="post" class="card p-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" id="enable_2fa" name="enable_2fa" value="1" 
                               class="form-check-input" <?= getSetting('enable_2fa') ? 'checked' : '' ?>>
                        <label for="enable_2fa" class="form-check-label">Zwei-Faktor-Authentifizierung aktivieren</label>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="login_attempts" class="form-label">Max. Anmeldeversuche</label>
                    <input type="number" id="login_attempts" name="login_attempts" 
                           value="<?= (int)getSetting('login_attempts', '5') ?>" 
                           class="form-control" min="1" max="10">
                </div>
                
                <button type="submit" name="save_settings" class="btn btn-primary">💾 Speichern</button>
            </form>
        </div>

        <!-- Tab E-Mail -->
        <div class="tab-pane fade" id="tab-email">
            <form method="post" class="card p-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                
                <div class="mb-3">
                    <label for="smtp_host" class="form-label">SMTP-Server</label>
                    <input type="text" id="smtp_host" name="smtp_host" 
                           value="<?= htmlspecialchars(getSetting('smtp_host')) ?>" 
                           class="form-control" placeholder="smtp.gmail.com">
                </div>
                
                <div class="mb-3">
                    <label for="smtp_sender" class="form-label">Absender-E-Mail</label>
                    <input type="email" id="smtp_sender" name="smtp_sender" 
                           value="<?= htmlspecialchars(getSetting('smtp_sender')) ?>" 
                           class="form-control" placeholder="noreply@example.com">
                </div>
                
                <button type="submit" name="save_settings" class="btn btn-primary">💾 Speichern</button>
            </form>
        </div>

        <!-- Tab Updates -->
        <div class="tab-pane fade" id="tab-update">
            <div class="card p-4">
                <h5>📦 Version</h5>
                <p><strong>Aktuelle Version:</strong> <?= htmlspecialchars($localVersion) ?></p>
                <p><strong>Neueste Version:</strong> <?= htmlspecialchars($latestVersion) ?></p>
                
                <?php if ($changelog): ?>
                    <div class="card mb-3">
                        <div class="card-header">📋 Changelog</div>
                        <div class="card-body">
                            <pre class="small"><?= htmlspecialchars($changelog) ?></pre>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($isUpdateAvailable): ?>
                    <form method="post" onsubmit="return confirm('Update wirklich installieren? Ein Backup wird automatisch erstellt.')">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <button name="start_update" class="btn btn-success">⬇️ Update installieren</button>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">✅ Ihre Installation ist aktuell.</div>
                <?php endif; ?>
                
                <hr>
                
                <h5>💾 Backups</h5>
                <?php if (empty($backups)): ?>
                    <p class="text-muted">Keine Backups vorhanden.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Datei</th>
                                    <th>Größe</th>
                                    <th>Datum</th>
                                    <th>Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backups as $backup): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($backup['name']) ?></td>
                                        <td><?= number_format($backup['size'] / 1024 / 1024, 1) ?> MB</td>
                                        <td><?= date('d.m.Y H:i', $backup['date']) ?></td>
                                        <td>
                                            <a href="backups/<?= urlencode($backup['name']) ?>" 
                                               class="btn btn-sm btn-outline-primary" download>⬇️ Download</a>
                                            <form method="post" style="display:inline;" 
                                                  onsubmit="return confirm('Backup wirklich löschen?')">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="delete_backup" value="<?= htmlspecialchars($backup['name']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">🗑️</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Tab-Persistierung
document.addEventListener('DOMContentLoaded', function() {
    const hash = window.location.hash;
    if (hash) {
        const tab = document.querySelector(`a[href="${hash}"]`);
        if (tab) {
            new bootstrap.Tab(tab).show();
        }
    }
    
    // Hash bei Tab-Wechsel setzen
    document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', function(e) {
            window.location.hash = e.target.getAttribute('href');
        });
    });
});
</script>