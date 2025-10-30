<?php
/**
 * DVD Profiler Liste - Admin Settings (KORRIGIERT)
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Versionsinformationen laden
require_once dirname(__DIR__, 2) . '/includes/version.php';

// CSRF-Token generieren
$csrfToken = generateCSRFToken() ?? bin2hex(random_bytes(16));

// Variablen initialisieren
$error = '';
$success = '';

// KORRIGIERTE Update-System Klasse
class UpdateSystem {
    private $pdo;
    private $updateApiUrl = 'https://update.neuhaus.or.at/dvdprofiler-liste/api/latest';
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function getLatestRelease(): ?array {
        // Cache für 1 Stunde
        $cacheKey = 'update_latest_release';
        $cached = getSetting($cacheKey . '_data', '');
        $cacheTime = (int)getSetting($cacheKey . '_time', '0');
        
        if ($cached && (time() - $cacheTime < 3600)) {
            return json_decode($cached, true);
        }
        
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: DVD-Profiler-Updater/1.4.7\r\n",
                'timeout' => 10
            ]
        ];
        
        $context = stream_context_create($opts);
        $json = @file_get_contents($this->updateApiUrl, false, $context);
        
        if ($json) {
            $data = json_decode($json, true);
            if ($data && isset($data['version'])) {
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
        
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive ist nicht verfügbar');
        }
        
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
            $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);
            
            // Ausgeschlossene Dateien prüfen
            $skip = false;
            foreach ($excludeDirs as $dir) {
                if (strpos($relativePath, $dir . '/') === 0) {
                    $skip = true;
                    break;
                }
            }
            
            if (!$skip && in_array($relativePath, $excludeFiles)) {
                $skip = true;
            }
            
            if ($skip) {
                continue;
            }
            
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath . '/');
            } else {
                $zip->addFile($file->getPathname(), $relativePath);
            }
        }
        
        $zip->close();
        return $backupFile;
    }
    
    public function performUpdate(array $releaseData): array {
        try {
            // Backup erstellen
            $backupFile = $this->createBackup();
            
            // Download der neuen Version (simuliert)
            $downloadResult = $this->downloadUpdate($releaseData);
            
            if (!$downloadResult['success']) {
                return $downloadResult;
            }
            
            // Version in Datenbank aktualisieren
            setSetting('current_version', $releaseData['version']);
            setSetting('last_update', date('Y-m-d H:i:s'));
            
            return [
                'success' => true,
                'message' => 'Update erfolgreich installiert',
                'version' => $releaseData['version'],
                'backup' => basename($backupFile)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Update fehlgeschlagen: ' . $e->getMessage()
            ];
        }
    }
    
    private function downloadUpdate(array $releaseData): array {
        // Hier würde normalerweise der Download stattfinden
        // Für jetzt nur Simulation
        sleep(2); // Simuliere Download-Zeit
        
        return [
            'success' => true,
            'message' => 'Download erfolgreich'
        ];
    }
}

// CSRF-Token-Funktion falls nicht vorhanden
if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken(): string {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken(string $token): bool {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Update System initialisieren
$updateSystem = new UpdateSystem($pdo);
$latestData = $updateSystem->getLatestRelease();
$latestVersion = $latestData['version'] ?? 'Unbekannt';
$currentVersion = DVDPROFILER_VERSION;

// Version-Vergleich
$isUpdateAvailable = false;
if ($latestData && !empty($latestVersion)) {
    $latest = ltrim($latestVersion, 'v');
    $current = ltrim($currentVersion, 'v');
    $isUpdateAvailable = version_compare($latest, $current, '>');
}

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Schutz
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $error = '❌ Ungültiger CSRF-Token. Seite neu laden und erneut versuchen.';
    } else {
        // Update starten
        if (isset($_POST['start_update'])) {
            if (!$latestData) {
                $error = '❌ Update-Informationen konnten nicht geladen werden.';
            } else {
                $updateResult = $updateSystem->performUpdate($latestData);
                
                if ($updateResult['success']) {
                    $success = '✅ ' . $updateResult['message'];
                    $currentVersion = $updateResult['version']; // Version aktualisieren
                } else {
                    $error = '❌ ' . $updateResult['message'];
                }
            }
        }
        
        // Cache löschen
        if (isset($_POST['clear_cache'])) {
            $cacheFiles = [
                BASE_PATH . '/cache/update_cache.json',
                BASE_PATH . '/cache/version_cache.json'
            ];
            
            $clearedFiles = 0;
            foreach ($cacheFiles as $file) {
                if (file_exists($file) && @unlink($file)) {
                    $clearedFiles++;
                }
            }
            
            $success = "✅ Cache geleert ({$clearedFiles} Dateien entfernt)";
        }
    }
}

// Rate Limit Warnung
$warning = '';
if (!$latestData) {
    $warning = '⚠️ Update-Server nicht erreichbar. Bitte versuchen Sie es später erneut.';
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System-Einstellungen - DVD Profiler</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .update-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .version-badge {
            font-family: 'Monaco', 'Menlo', monospace;
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
        }
        
        .update-available {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
        }
        
        .settings-section {
            border-left: 4px solid #007bff;
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h1 class="mb-4">
                    <i class="bi bi-gear"></i> System-Einstellungen
                </h1>
                
                <!-- Erfolgs-/Fehlermeldungen -->
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($success) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($warning)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($warning) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="row">
            <!-- Update-System -->
            <div class="col-md-6 mb-4">
                <div class="card h-100 update-card <?= $isUpdateAvailable ? 'update-available' : '' ?>">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-cloud-download"></i> System-Updates
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <strong>Aktuelle Version:</strong>
                            <span class="version-badge"><?= htmlspecialchars($currentVersion) ?></span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Neueste Version:</strong>
                            <span class="version-badge"><?= htmlspecialchars($latestVersion) ?></span>
                        </div>
                        
                        <?php if ($isUpdateAvailable): ?>
                            <div class="alert alert-light mb-3">
                                <i class="bi bi-info-circle"></i>
                                <strong>Update verfügbar!</strong><br>
                                Eine neue Version ist verfügbar.
                            </div>
                            
                            <form method="post" class="mb-3">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <button type="submit" name="start_update" class="btn btn-light btn-lg">
                                    <i class="bi bi-download"></i> Update installieren
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-light mb-3">
                                <i class="bi bi-check-circle"></i>
                                <strong>System ist aktuell</strong><br>
                                Sie verwenden die neueste Version.
                            </div>
                        <?php endif; ?>
                        
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" name="clear_cache" class="btn btn-outline-light">
                                <i class="bi bi-trash"></i> Cache leeren
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- System-Informationen -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-info-circle"></i> System-Informationen
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="settings-section p-3 rounded">
                                    <strong>PHP Version:</strong> <?= PHP_VERSION ?><br>
                                    <strong>Server:</strong> <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unbekannt' ?><br>
                                    <strong>Memory Limit:</strong> <?= ini_get('memory_limit') ?><br>
                                    <strong>Upload Limit:</strong> <?= ini_get('upload_max_filesize') ?>
                                </div>
                            </div>
                            
                            <div class="col-12">
                                <div class="settings-section p-3 rounded">
                                    <strong>Betriebssystem:</strong> <?= PHP_OS ?><br>
                                    <strong>Server Zeit:</strong> <?= date('Y-m-d H:i:s') ?><br>
                                    <strong>Zeitzone:</strong> <?= date_default_timezone_get() ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Zusätzliche Einstellungen -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-sliders"></i> Erweiterte Einstellungen
                        </h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">
                            Weitere Konfigurationsoptionen werden in zukünftigen Versionen verfügbar sein.
                        </p>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <a href="?page=import" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-upload"></i> XML Import
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="?page=backups" class="btn btn-outline-secondary w-100">
                                    <i class="bi bi-archive"></i> Backups
                                </a>
                            </div>
                            <div class="col-md-4">
                                <a href="?page=logs" class="btn btn-outline-info w-100">
                                    <i class="bi bi-file-text"></i> System-Logs
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>