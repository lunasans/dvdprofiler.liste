<?php
/**
 * REPARIERTE UPDATE.PHP
 * Alle Fehler behoben und Sicherheit verbessert
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
$githubRepo = DVDPROFILER_REPOSITORY;
$localVersion = DVDPROFILER_VERSION; // ✅ REPARIERT: $config durch Konstante ersetzt
$error = '';
$success = '';
$warning = '';

/**
 * GitHub API-Aufruf mit verbesserter Fehlerbehandlung
 */
function getLatestReleaseSecure(string $repo): ?array 
{
    $apiUrl = "https://api.github.com/repos/$repo/releases/latest";
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: DVD-Profiler-Updater/" . DVDPROFILER_VERSION . "\r\n",
            'timeout' => 15,
            'ignore_errors' => true
        ]
    ]);
    
    try {
        $json = @file_get_contents($apiUrl, false, $context);
        
        if ($json === false) {
            $error = error_get_last();
            error_log("GitHub API failed: " . ($error['message'] ?? 'Unknown'));
            return null;
        }
        
        // HTTP Status prüfen
        if (isset($http_response_header)) {
            foreach ($http_response_header as $header) {
                if (strpos($header, 'HTTP/1.1 403') !== false) {
                    error_log("GitHub Rate Limit exceeded");
                    return null;
                }
            }
        }
        
        $data = json_decode($json, true);
        
        if (!$data || !isset($data['tag_name'])) {
            error_log("Invalid GitHub API response");
            return null;
        }
        
        return $data;
        
    } catch (Exception $e) {
        error_log("GitHub API Exception: " . $e->getMessage());
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
    $backupDir = BASE_PATH . '/admin/backups';
    
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
        
        // 3. Update extrahieren und installieren
        $installResult = extractAndInstallUpdate($tmpZip, $version);
        if (!$installResult['success']) {
            return $installResult;
        }
        
        // 4. Version in Datenbank aktualisieren
        if (!setSetting('current_version', $version)) { // ✅ REPARIERT: setSetting statt updateSetting
            error_log("Failed to update version in database");
            return ['success' => false, 'message' => 'Version konnte nicht in DB gespeichert werden'];
        }
        
        // 5. Update-SQL ausführen (falls vorhanden)
        $sqlResult = executeUpdateSQL();
        if (!$sqlResult['success']) {
            error_log("Update SQL failed: " . $sqlResult['message']);
            // Warnung, aber kein Abbruch
        }
        
        // 6. Cache leeren
        clearUpdateCache();
        
        return [
            'success' => true, 
            'message' => "✅ Update auf Version $version erfolgreich installiert!",
            'version' => $version
        ];
        
    } catch (Exception $e) {
        error_log("Update failed: " . $e->getMessage());
        return ['success' => false, 'message' => 'Update fehlgeschlagen: ' . $e->getMessage()];
        
    } finally {
        // Temporäre Dateien aufräumen
        if (file_exists($tmpZip)) {
            @unlink($tmpZip);
        }
    }
}

/**
 * Backup vor Update erstellen
 */
function createBackupBeforeUpdate(): array 
{
    $backupDir = BASE_PATH . '/admin/backups';
    
    if (!is_dir($backupDir)) {
        if (!mkdir($backupDir, 0755, true)) {
            return ['success' => false, 'message' => 'Backup-Verzeichnis konnte nicht erstellt werden'];
        }
    }
    
    $timestamp = date('Ymd_His');
    $backupFile = $backupDir . "/pre_update_backup_{$timestamp}.zip";
    
    try {
        $zip = new ZipArchive();
        
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            return ['success' => false, 'message' => 'Backup-Datei konnte nicht erstellt werden'];
        }
        
        // Wichtige Dateien sichern
        $filesToBackup = [
            'config/config.php',
            'includes/',
            'partials/',
            'css/',
            'js/',
            'admin/',
            'index.php'
        ];
        
        foreach ($filesToBackup as $path) {
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
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: DVD-Profiler-Updater/" . DVDPROFILER_VERSION . "\r\n",
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
        
        $targetPath = BASE_PATH . '/' . $relativePath; // ✅ REPARIERT: BASE_PATH verwenden
        
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
            $latestRelease = getLatestReleaseSecure($githubRepo);
            
            if (!$latestRelease) {
                $error = '❌ Update-Informationen konnten nicht geladen werden. Möglicherweise GitHub Rate Limit erreicht.';
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
$latestRelease = getLatestReleaseSecure($githubRepo);
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
    $warning = '⚠️ GitHub API nicht erreichbar. Möglicherweise Rate Limit erreicht. <a href="debug-update.php">Debug-Informationen</a>';
}
?>

<div class="update-page">
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-arrow-repeat"></i>
                        System-Update
                    </h5>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?= $error ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i>
                            <?= $success ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($warning): ?>
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?= $warning ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="version-info mb-4">
                        <div class="row">
                            <div class="col-sm-6">
                                <strong>Aktuelle Version:</strong><br>
                                <span class="badge bg-primary fs-6">
                                    <?= htmlspecialchars($localVersion) ?>
                                </span>
                            </div>
                            <div class="col-sm-6">
                                <strong>Neueste Version:</strong><br>
                                <span class="badge bg-<?= $isUpdateAvailable ? 'warning' : 'success' ?> fs-6">
                                    <?= htmlspecialchars($latestVersion) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($isUpdateAvailable && $latestRelease): ?>
                        <div class="alert alert-info">
                            <h6>📦 Update verfügbar!</h6>
                            <p>Version <?= htmlspecialchars($latestVersion) ?> ist verfügbar.</p>
                            
                            <?php if (!empty($latestRelease['body'])): ?>
                                <details class="mt-2">
                                    <summary>Changelog anzeigen</summary>
                                    <div class="mt-2 p-2 bg-light rounded">
                                        <?= nl2br(htmlspecialchars(substr($latestRelease['body'], 0, 1000))) ?>
                                        <?php if (strlen($latestRelease['body']) > 1000): ?>
                                            <p><em>... (gekürzt)</em></p>
                                        <?php endif; ?>
                                    </div>
                                </details>
                            <?php endif; ?>
                        </div>
                        
                        <form method="post" onsubmit="return confirm('Update starten? Ein automatisches Backup wird erstellt.')">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <button type="submit" name="start_update" class="btn btn-warning btn-lg">
                                <i class="bi bi-download"></i>
                                Update auf <?= htmlspecialchars($latestVersion) ?> installieren
                            </button>
                        </form>
                        
                    <?php else: ?>
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i>
                            <strong>System ist aktuell!</strong><br>
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
                        <strong>Repository:</strong><br>
                        <a href="https://github.com/<?= htmlspecialchars($githubRepo) ?>" target="_blank">
                            <?= htmlspecialchars($githubRepo) ?>
                        </a>
                        <br><br>
                        
                        <strong>Update-Prozess:</strong><br>
                        1. Automatisches Backup<br>
                        2. Download der neuen Version<br>
                        3. Dateien aktualisieren<br>
                        4. SQL-Updates ausführen<br>
                        5. Cache leeren<br>
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