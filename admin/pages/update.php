<?php
/**
 * DVD Profiler Liste - Update System
 * Modifiziert für eigenes Update-System (ohne GitHub)
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/version.php';

// Sicherheitsprüfung
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSRF-Token generieren
$csrfToken = generateCSRFToken();

// Variablen initialisieren
$updateApiUrl = getDVDProfilerUpdateConfig()['api_url']; // GEÄNDERT: Eigene API verwenden
$localVersion = DVDPROFILER_VERSION;
$error = '';
$success = '';
$warning = '';

/**
 * Update-API aufrufen (ersetzt GitHub API)
 */
function getLatestReleaseSecure(string $apiUrl): ?array 
{
    $config = getDVDProfilerUpdateConfig();
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: {$config['user_agent']}",
            'timeout' => $config['timeout'],
            'ignore_errors' => true
        ]
    ]);
    
    try {
        $json = @file_get_contents($apiUrl, false, $context);
        
        if ($json === false) {
            $error = error_get_last();
            error_log("Update API failed: " . ($error['message'] ?? 'Unknown'));
            return null;
        }
        
        // HTTP Status prüfen
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (strpos($header, 'HTTP/1.1 404') !== false) {
                    error_log("Update API: Endpoint not found");
                    return null;
                }
                if (strpos($header, 'HTTP/1.1 500') !== false) {
                    error_log("Update API: Server error");
                    return null;
                }
            }
        }
        
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['tag_name'])) {
            error_log("Invalid Update API response");
            return null;
        }
        
        return $data;
        
    } catch (Exception $e) {
        error_log("Update API Exception: " . $e->getMessage());
        return null;
    }
}

/**
 * Update-Download und -Installation mit verbesserter Sicherheit
 */
function downloadAndUpdateSecure(array $release): array 
{
    $zipUrl = $release['zipball_url'] ?? '';
    $version = $release['tag_name'] ?? '';
    
    if (empty($zipUrl) || empty($version)) {
        return ['success' => false, 'message' => 'Ungültige Release-Daten'];
    }
    
    // Temporäre Dateien
    $tmpZip = sys_get_temp_dir() . '/dvd_update_' . uniqid() . '.zip';
    
    try {
        // 1. Backup erstellen
        $backupResult = createBackupBeforeUpdate();
        if (!$backupResult['success']) {
            return $backupResult;
        }
        
        // 2. Update-Datei herunterladen
        $downloadResult = downloadUpdateFile($zipUrl, $tmpZip);
        if (!$downloadResult['success']) {
            return $downloadResult;
        }
        
        // 3. Update installieren
        $installResult = extractAndInstallUpdate($tmpZip, $version);
        if (!$installResult['success']) {
            return $installResult;
        }
        
        // 4. SQL-Updates ausführen
        $sqlResult = executeUpdateSQL();
        if (!$sqlResult['success']) {
            error_log("SQL Update failed: " . $sqlResult['message']);
            // Nicht kritisch, weiter machen
        }
        
        // 5. Cache leeren
        clearUpdateCache();
        
        // 6. Version in Datenbank aktualisieren
        setSetting('version', ltrim($version, 'v'));
        setSetting('last_update', date('Y-m-d H:i:s'));
        
        // 7. Temporäre Datei löschen
        @unlink($tmpZip);
        
        return [
            'success' => true,
            'message' => "Update auf Version {$version} erfolgreich! Backup: " . basename($backupResult['file'] ?? 'unbekannt'),
            'version' => $version
        ];
        
    } catch (Exception $e) {
        @unlink($tmpZip);
        error_log('Update failed: ' . $e->getMessage());
        return ['success' => false, 'message' => 'Update fehlgeschlagen: ' . $e->getMessage()];
    }
}

/**
 * Backup vor Update erstellen
 */
function createBackupBeforeUpdate(): array 
{
    $backupDir = BASE_PATH . '/admin/backups';
    
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
    }
    
    $backupFile = $backupDir . '/backup_' . date('Ymd_His') . '.zip';
    
    try {
        $zip = new ZipArchive();
        
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            return ['success' => false, 'message' => 'Backup-Datei konnte nicht erstellt werden'];
        }
        
        // Wichtige Dateien/Verzeichnisse für Backup
        $importantPaths = [
            'config',
            'includes',
            'admin',
            'api',
            'css',
            'js',
            'index.php',
            '.htaccess'
        ];
        
        foreach ($importantPaths as $path) {
            $fullPath = BASE_PATH . '/' . $path;
            
            if (is_file($fullPath)) {
                $zip->addFile($fullPath, $path);
            } elseif (is_dir($fullPath)) {
                addDirectoryToZip($zip, $fullPath, $path);
            }
        }
        
        $zip->close();
        
        error_log("Backup created: $backupFile");
        return ['success' => true, 'message' => 'Backup erstellt', 'file' => $backupFile];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Backup fehlgeschlagen: ' . $e->getMessage()];
    }
}

/**
 * Verzeichnis rekursiv zum ZIP hinzufügen
 */
function addDirectoryToZip(ZipArchive $zip, string $dir, string $zipPath): void 
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filePath = $file->getRealPath();
            $relativePath = $zipPath . '/' . substr($filePath, strlen($dir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
}

/**
 * Update-Datei herunterladen
 */
function downloadUpdateFile(string $url, string $tmpFile): array 
{
    $config = getDVDProfilerUpdateConfig();
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: {$config['user_agent']}",
            'timeout' => 300, // 5 Minuten für große Downloads
        ]
    ]);
    
    $data = @file_get_contents($url, false, $context);
    
    if ($data === false) {
        $error = error_get_last();
        return ['success' => false, 'message' => 'Download fehlgeschlagen: ' . ($error['message'] ?? 'Unbekannt')];
    }
    
    if (file_put_contents($tmpFile, $data) === false) {
        return ['success' => false, 'message' => 'Temporäre Datei konnte nicht geschrieben werden'];
    }
    
    return ['success' => true, 'message' => 'Download erfolgreich'];
}

/**
 * Update extrahieren und installieren
 */
function extractAndInstallUpdate(string $zipFile, string $version): array 
{
    $zip = new ZipArchive();
    
    if ($zip->open($zipFile) !== TRUE) {
        return ['success' => false, 'message' => 'ZIP-Datei konnte nicht geöffnet werden'];
    }
    
    // Repository-Prefix finden
    $repoPrefix = null;
    for ($i = 0; $i < min($zip->numFiles, 10); $i++) {
        $filename = $zip->getNameIndex($i);
        if (strpos($filename, '/') !== false) {
            $repoPrefix = explode('/', $filename)[0];
            break;
        }
    }
    
    if (!$repoPrefix) {
        $zip->close();
        return ['success' => false, 'message' => 'Ungültiges ZIP-Format'];
    }
    
    // Dateien ausschließen, die nicht überschrieben werden sollen
    $excludePatterns = [
        'config/config.php',
        'counter.txt',
        'admin/xml/',
        'admin/backups/',
        'cache/',
        'logs/',
        '.git',
        '.env'
    ];
    
    $extractedFiles = 0;
    
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $filename = $zip->getNameIndex($i);
        $relativePath = preg_replace("#^{$repoPrefix}/#", '', $filename);
        
        // Leer oder identisch? Überspringen
        if (empty($relativePath) || $relativePath === $filename) {
            continue;
        }
        
        // Ausgeschlossene Dateien prüfen
        $skip = false;
        foreach ($excludePatterns as $pattern) {
            if ($relativePath === $pattern || str_starts_with($relativePath, rtrim($pattern, '/') . '/')) {
                $skip = true;
                break;
            }
        }
        
        if ($skip) {
            continue;
        }
        
        $targetPath = BASE_PATH . '/' . $relativePath;
        
        // Verzeichnis erstellen
        if (str_ends_with($filename, '/')) {
            if (!is_dir($targetPath)) {
                @mkdir($targetPath, 0755, true);
            }
        } else {
            // Datei extrahieren
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                @mkdir($targetDir, 0755, true);
            }
            
            $content = $zip->getFromIndex($i);
            if ($content !== false && file_put_contents($targetPath, $content) !== false) {
                $extractedFiles++;
            }
        }
    }
    
    $zip->close();
    
    if ($extractedFiles === 0) {
        return ['success' => false, 'message' => 'Keine Dateien extrahiert'];
    }
    
    error_log("Update installed: $extractedFiles files extracted");
    return ['success' => true, 'message' => "$extractedFiles Dateien aktualisiert"];
}

/**
 * Update-SQL ausführen (falls vorhanden)
 */
function executeUpdateSQL(): array 
{
    global $pdo;
    
    $sqlFile = BASE_PATH . '/update.sql';
    
    if (!file_exists($sqlFile)) {
        return ['success' => true, 'message' => 'Keine SQL-Updates erforderlich'];
    }
    
    try {
        $sql = file_get_contents($sqlFile);
        
        if (!empty(trim($sql))) {
            $pdo->exec($sql);
            error_log("Update SQL executed successfully");
        }
        
        @unlink($sqlFile); // SQL-Datei nach Ausführung löschen
        
        return ['success' => true, 'message' => 'SQL-Updates erfolgreich'];
        
    } catch (PDOException $e) {
        error_log("Update SQL failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'SQL-Update fehlgeschlagen: ' . $e->getMessage()];
    }
}

/**
 * Update-Cache leeren
 */
function clearUpdateCache(): void 
{
    $cacheFiles = [
        BASE_PATH . '/cache/github_cache.json',
        BASE_PATH . '/cache/version_cache.json'
    ];
    
    foreach ($cacheFiles as $file) {
        if (file_exists($file)) {
            @unlink($file);
        }
    }
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
            $latestRelease = getLatestReleaseSecure($updateApiUrl); // GEÄNDERT: Eigene API
            
            if (!$latestRelease) {
                $error = '❌ Update-Informationen konnten nicht geladen werden. Update-Server nicht erreichbar.'; // GEÄNDERT: Text
            } else {
                $updateResult = downloadAndUpdateSecure($latestRelease);
                
                if ($updateResult['success']) {
                    $success = $updateResult['message'];
                    $localVersion = $updateResult['version']; // Version aktualisieren
                } else {
                    $error = '❌ ' . $updateResult['message'];
                }
            }
        }
    }
}

// Aktuelle Update-Informationen laden
$latestRelease = getLatestReleaseSecure($updateApiUrl); // GEÄNDERT: Eigene API
$latestVersion = $latestRelease['tag_name'] ?? 'Unbekannt';

// Version-Vergleich
$isUpdateAvailable = false;
if ($latestRelease && !empty($latestVersion)) {
    $latest = ltrim($latestVersion, 'v');
    $current = ltrim($localVersion, 'v');
    $isUpdateAvailable = version_compare($latest, $current, '>');
}

// Rate Limit Warnung
if (!$latestRelease) {
    $warning = '⚠️ Update-Server nicht erreichbar. Bitte versuchen Sie es später erneut.'; // GEÄNDERT: Text
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-repeat"></i>
                        System-Updates
                    </h5>
                </div>
                <div class="card-body">
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($warning): ?>
                        <div class="alert alert-warning">
                            <?= htmlspecialchars($warning) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6>Aktuelle Version</h6>
                            <div class="version-info">
                                <span class="badge bg-primary fs-6">
                                    <?= htmlspecialchars($localVersion) ?>
                                </span>
                                <div class="version-details">
                                    <small class="text-muted">
                                        Build: <?= DVDPROFILER_BUILD_DATE ?><br>
                                        Codename: "<?= DVDPROFILER_CODENAME ?>"
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Verfügbare Version</h6>
                            <div class="version-info">
                                <?php if ($latestRelease): ?>
                                    <span class="badge fs-6 <?= $isUpdateAvailable ? 'bg-warning' : 'bg-success' ?>">
                                        <?= htmlspecialchars($latestVersion) ?>
                                    </span>
                                    <div class="version-details">
                                        <small class="text-muted">
                                            <?php if (isset($latestRelease['published_at'])): ?>
                                                Veröffentlicht: <?= date('d.m.Y H:i', strtotime($latestRelease['published_at'])) ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <span class="badge bg-secondary fs-6">Nicht verfügbar</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isUpdateAvailable && $latestRelease): ?>
                        <div class="alert alert-info">
                            <h6>
                                <i class="bi bi-info-circle"></i>
                                Update verfügbar: <?= htmlspecialchars($latestVersion) ?>
                            </h6>
                            
                            <?php if (!empty($latestRelease['body'])): ?>
                                <div class="changelog mt-3">
                                    <h6>Changelog:</h6>
                                    <div class="changelog-content">
                                        <?= nl2br(htmlspecialchars(substr($latestRelease['body'], 0, 500))) ?>
                                        <?php if (strlen($latestRelease['body']) > 500): ?>
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
                                        Jetzt aktualisieren
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php elseif ($latestRelease && !$isUpdateAvailable): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i>
                            Sie verwenden bereits die neueste Version.
                        </div>
                        
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" name="check_updates" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-clockwise"></i>
                                Erneut prüfen
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bi bi-info-circle"></i>
                        Update-Informationen
                    </h6>
                </div>
                <div class="card-body">
                    <small>
                        <strong>Update-Server:</strong><br>
                        <a href="<?= htmlspecialchars(getDVDProfilerUpdateConfig()['base_url']) ?>" target="_blank">
                            <?= htmlspecialchars(parse_url(getDVDProfilerUpdateConfig()['api_url'], PHP_URL_HOST)) ?>
                        </a>
                        <br><br>
                        
                        <strong>Update-Prozess:</strong><br>
                        1. Verbindung zum Update-Server<br>
                        2. Automatisches Backup<br>
                        3. Download der neuen Version<br>
                        4. Dateien aktualisieren<br>
                        5. SQL-Updates ausführen<br>
                        6. Cache leeren<br>
                        <br>
                        
                        <strong>Geschützte Dateien:</strong><br>
                        • config/config.php<br>
                        • Ihre Uploads<br>
                        • Datenbank-Backups<br>
                        • Logs und Cache<br>
                    </small>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="debug-update.php" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-bug"></i>
                    Debug-Informationen
                </a>
                
                <a href="?page=settings" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-left"></i>
                    Zurück zu Einstellungen
                </a>
            </div>
        </div>
    </div>
</div>

<script>
// Auto-refresh bei erfolgreichem Update
<?php if ($success): ?>
setTimeout(function() {
    if (confirm('Update erfolgreich! Seite neu laden um Änderungen zu sehen?')) {
        window.location.reload();
    }
}, 2000);
<?php endif; ?>
</script>