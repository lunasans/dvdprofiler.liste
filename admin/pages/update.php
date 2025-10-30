<?php
/**
 * DVD Profiler Liste - GitHub Update System
 * Standalone Update-Seite mit vollständiger GitHub-Integration
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
function generateCSRFToken(): string {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$csrfToken = generateCSRFToken();

// Variablen initialisieren
$error = '';
$success = '';
$warning = '';
$progress = [];

/**
 * GitHub Release sicher abrufen mit Rate-Limiting
 */
function getGitHubReleaseSecure(): ?array {
    return getLatestGitHubRelease();
}

/**
 * Backup vor Update erstellen
 */
function createBackupBeforeUpdate(): array {
    try {
        $backupDir = dirname(__DIR__, 2) . '/admin/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Ymd_His');
        $backupFile = $backupDir . "pre_update_backup_{$timestamp}.zip";
        
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'message' => 'ZipArchive PHP-Erweiterung ist nicht verfügbar'];
        }
        
        $zip = new ZipArchive();
        if ($zip->open($backupFile, ZipArchive::CREATE) !== TRUE) {
            return ['success' => false, 'message' => 'Backup-ZIP-Datei konnte nicht erstellt werden'];
        }
        
        $baseDir = dirname(__DIR__, 2);
        $excludePatterns = [
            'admin/backups/',
            'cache/',
            'logs/',
            '.git/',
            'uploads/temp/',
            'config/config.php',
            '.env',
            'counter.txt'
        ];
        
        // Dateien zum Backup hinzufügen
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        $fileCount = 0;
        foreach ($iterator as $file) {
            $relativePath = str_replace($baseDir . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $relativePath = str_replace('\\', '/', $relativePath);
            
            // Ausschlüsse prüfen
            $skip = false;
            foreach ($excludePatterns as $pattern) {
                if (strpos($relativePath, $pattern) === 0) {
                    $skip = true;
                    break;
                }
            }
            
            if ($skip) continue;
            
            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($file->getPathname(), $relativePath);
                $fileCount++;
            }
        }
        
        $zip->close();
        
        return [
            'success' => true,
            'message' => "Backup erstellt mit {$fileCount} Dateien",
            'file' => $backupFile,
            'size' => filesize($backupFile)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Backup-Fehler: ' . $e->getMessage()];
    }
}

/**
 * Update-Datei von GitHub herunterladen
 */
function downloadGitHubUpdate(array $release): array {
    $downloadUrl = $release['zipball_url'] ?? '';
    if (empty($downloadUrl)) {
        return ['success' => false, 'message' => 'Download-URL nicht verfügbar'];
    }
    
    $tempFile = sys_get_temp_dir() . '/github_update_' . uniqid() . '.zip';
    
    try {
        $config = getGitHubUpdateConfig();
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: {$config['user_agent']}\r\n",
                'timeout' => 300, // 5 Minuten
                'follow_location' => true,
                'max_redirects' => 5
            ]
        ]);
        
        error_log("GitHub Update: Downloading from {$downloadUrl}");
        
        $data = @file_get_contents($downloadUrl, false, $context);
        if ($data === false) {
            $error = error_get_last();
            return ['success' => false, 'message' => 'Download fehlgeschlagen: ' . ($error['message'] ?? 'Unbekannter Fehler')];
        }
        
        if (file_put_contents($tempFile, $data) === false) {
            return ['success' => false, 'message' => 'Temporäre Datei konnte nicht geschrieben werden'];
        }
        
        return [
            'success' => true,
            'message' => 'Download erfolgreich (' . number_format(strlen($data)) . ' Bytes)',
            'file' => $tempFile,
            'size' => strlen($data)
        ];
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Download-Fehler: ' . $e->getMessage()];
    }
}

/**
 * GitHub Update extrahieren und installieren
 */
function extractAndInstallGitHubUpdate(string $zipFile, string $version): array {
    try {
        if (!class_exists('ZipArchive')) {
            return ['success' => false, 'message' => 'ZipArchive PHP-Erweiterung nicht verfügbar'];
        }
        
        $zip = new ZipArchive();
        if ($zip->open($zipFile) !== TRUE) {
            return ['success' => false, 'message' => 'GitHub ZIP-Datei konnte nicht geöffnet werden'];
        }
        
        // GitHub ZIP-Struktur ermitteln (Repository-Ordner)
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
            return ['success' => false, 'message' => 'Ungültiges GitHub ZIP-Format'];
        }
        
        error_log("GitHub Update: Repository prefix detected: {$repoPrefix}");
        
        $baseDir = dirname(__DIR__, 2);
        $extractedFiles = 0;
        $skippedFiles = 0;
        $errors = [];
        
        // Dateien die nicht überschrieben werden sollen
        $protectedFiles = [
            'config/config.php',
            'counter.txt',
            '.env',
            '.htaccess'
        ];
        
        $protectedDirs = [
            'admin/xml/',
            'admin/backups/',
            'cache/',
            'logs/',
            'uploads/'
        ];
        
        // Alle Dateien extrahieren
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            $relativePath = preg_replace("#^{$repoPrefix}/#", '', $filename);
            
            // Leere oder identische Pfade überspringen
            if (empty($relativePath) || $relativePath === $filename) {
                continue;
            }
            
            // Geschützte Dateien prüfen
            $isProtected = false;
            
            // Einzelne geschützte Dateien
            foreach ($protectedFiles as $protected) {
                if ($relativePath === $protected) {
                    $isProtected = true;
                    $skippedFiles++;
                    break;
                }
            }
            
            // Geschützte Verzeichnisse
            if (!$isProtected) {
                foreach ($protectedDirs as $protectedDir) {
                    if (strpos($relativePath, $protectedDir) === 0) {
                        $isProtected = true;
                        $skippedFiles++;
                        break;
                    }
                }
            }
            
            if ($isProtected) {
                continue;
            }
            
            $targetPath = $baseDir . '/' . $relativePath;
            
            try {
                if (substr($filename, -1) === '/') {
                    // Verzeichnis erstellen
                    if (!is_dir($targetPath)) {
                        @mkdir($targetPath, 0755, true);
                    }
                } else {
                    // Datei extrahieren
                    $targetDir = dirname($targetPath);
                    if (!is_dir($targetDir)) {
                        @mkdir($targetDir, 0755, true);
                    }
                    
                    $fileContent = $zip->getFromIndex($i);
                    if ($fileContent !== false) {
                        if (file_put_contents($targetPath, $fileContent) !== false) {
                            $extractedFiles++;
                        } else {
                            $errors[] = "Konnte Datei nicht schreiben: {$relativePath}";
                        }
                    } else {
                        $errors[] = "Konnte Datei nicht aus ZIP lesen: {$relativePath}";
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Fehler bei {$relativePath}: " . $e->getMessage();
            }
        }
        
        $zip->close();
        @unlink($zipFile);
        
        // Ergebnis zusammenstellen
        $result = [
            'success' => $extractedFiles > 0,
            'message' => "Update installiert: {$extractedFiles} Dateien extrahiert, {$skippedFiles} geschützte Dateien übersprungen",
            'extracted' => $extractedFiles,
            'skipped' => $skippedFiles,
            'version' => $version
        ];
        
        if (!empty($errors)) {
            $result['warnings'] = $errors;
            $result['message'] .= ' (mit ' . count($errors) . ' Warnungen)';
        }
        
        return $result;
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Installations-Fehler: ' . $e->getMessage()];
    }
}

/**
 * Nach Update: Cache leeren und System aktualisieren
 */
function postUpdateCleanup(string $newVersion): void {
    try {
        // GitHub API Cache leeren
        clearGitHubAPICache();
        
        // Version in Datenbank speichern
        setSetting('current_version', $newVersion);
        setSetting('last_update', date('Y-m-d H:i:s'));
        
        // Cache-Verzeichnisse leeren
        $cacheDirs = [
            dirname(__DIR__, 2) . '/cache/',
            sys_get_temp_dir() . '/dvd_profiler_cache/'
        ];
        
        foreach ($cacheDirs as $cacheDir) {
            if (is_dir($cacheDir)) {
                $files = glob($cacheDir . '*');
                foreach ($files as $file) {
                    if (is_file($file)) {
                        @unlink($file);
                    }
                }
            }
        }
        
        error_log("GitHub Update: Post-update cleanup completed for version {$newVersion}");
        
    } catch (Exception $e) {
        error_log("GitHub Update: Post-update cleanup error: " . $e->getMessage());
    }
}

// Hauptlogik für Update-Verarbeitung
$latestRelease = getGitHubReleaseSecure();
$localVersion = DVDPROFILER_VERSION;
$isUpdateAvailable = false;

if ($latestRelease) {
    $latestVersion = ltrim($latestRelease['version'], 'v');
    $currentVersion = ltrim($localVersion, 'v');
    $isUpdateAvailable = version_compare($latestVersion, $currentVersion, '>');
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
            $progress[] = "🚀 Update-Prozess gestartet...";
            
            if (!$latestRelease) {
                $error = '❌ GitHub nicht erreichbar oder keine Release-Informationen verfügbar.';
            } else {
                try {
                    $updateVersion = $latestRelease['version'];
                    $progress[] = "📋 Ziel-Version: {$updateVersion}";
                    
                    // 1. Backup erstellen
                    $progress[] = "💾 Erstelle Backup...";
                    $backupResult = createBackupBeforeUpdate();
                    if (!$backupResult['success']) {
                        $error = "❌ Backup fehlgeschlagen: " . $backupResult['message'];
                    } else {
                        $progress[] = "✅ " . $backupResult['message'];
                        
                        // 2. Update herunterladen
                        $progress[] = "⬇️ Lade Update von GitHub herunter...";
                        $downloadResult = downloadGitHubUpdate($latestRelease);
                        if (!$downloadResult['success']) {
                            $error = "❌ Download fehlgeschlagen: " . $downloadResult['message'];
                        } else {
                            $progress[] = "✅ " . $downloadResult['message'];
                            
                            // 3. Update installieren
                            $progress[] = "🔧 Installiere Update...";
                            $installResult = extractAndInstallGitHubUpdate($downloadResult['file'], $updateVersion);
                            if (!$installResult['success']) {
                                $error = "❌ Installation fehlgeschlagen: " . $installResult['message'];
                            } else {
                                $progress[] = "✅ " . $installResult['message'];
                                
                                // 4. Cleanup
                                $progress[] = "🧹 Führe Cleanup durch...";
                                postUpdateCleanup($updateVersion);
                                $progress[] = "✅ Cleanup abgeschlossen";
                                
                                $success = "🎉 Update auf Version {$updateVersion} erfolgreich abgeschlossen!";
                                $localVersion = $updateVersion; // Für Anzeige aktualisieren
                                
                                if (isset($installResult['warnings'])) {
                                    $warning = "⚠️ Update war erfolgreich, aber mit Warnungen:\n" . implode("\n", $installResult['warnings']);
                                }
                            }
                        }
                    }
                } catch (Exception $e) {
                    $error = "❌ Unerwarteter Fehler: " . $e->getMessage();
                    error_log("GitHub Update Error: " . $e->getMessage());
                }
            }
        }
        
        // Cache manuell leeren
        if (isset($_POST['clear_cache'])) {
            clearGitHubAPICache();
            $success = "✅ GitHub API Cache geleert";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GitHub Update System - DVD Profiler</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .github-card {
            background: linear-gradient(135deg, #24292e 0%, #0d1117 100%);
            color: white;
            border: none;
        }
        
        .update-available {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .version-display {
            font-family: 'Monaco', 'Menlo', 'Consolas', monospace;
            font-size: 1.1rem;
            padding: 0.5rem 1rem;
            background: rgba(255,255,255,0.1);
            border-radius: 0.5rem;
            border: 1px solid rgba(255,255,255,0.2);
        }
        
        .progress-log {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
            font-family: monospace;
        }
        
        .github-info {
            background: #f6f8fa;
            border: 1px solid #d0d7de;
            border-radius: 0.5rem;
            padding: 1rem;
        }
        
        .repo-link {
            color: #0969da;
            text-decoration: none;
        }
        
        .repo-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../index.php">Admin</a></li>
                        <li class="breadcrumb-item active">GitHub Update</li>
                    </ol>
                </nav>
                
                <h1 class="mb-4">
                    <i class="bi bi-github"></i> GitHub Update System
                    <small class="text-muted">Automatische Updates direkt von GitHub</small>
                </h1>
            </div>
        </div>
        
        <!-- Status-Meldungen -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= nl2br(htmlspecialchars($error)) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= nl2br(htmlspecialchars($success)) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($warning)): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <?= nl2br(htmlspecialchars($warning)) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Progress Log -->
        <?php if (!empty($progress)): ?>
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-list-check"></i> Update-Progress</h5>
                        </div>
                        <div class="card-body">
                            <div class="progress-log">
                                <?php foreach ($progress as $step): ?>
                                    <?= htmlspecialchars($step) ?><br>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- GitHub Update Hauptpanel -->
            <div class="col-lg-8 mb-4">
                <div class="card github-card <?= $isUpdateAvailable ? 'update-available' : '' ?>">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            <i class="bi bi-download"></i> 
                            <?= $isUpdateAvailable ? 'Update verfügbar!' : 'System aktuell' ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Aktuelle Version:</label>
                                <div class="version-display">
                                    <?= htmlspecialchars($localVersion) ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Neueste Version auf GitHub:</label>
                                <div class="version-display">
                                    <?= htmlspecialchars($latestRelease['version'] ?? 'Unbekannt') ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($isUpdateAvailable): ?>
                            <div class="alert alert-light mb-4">
                                <h5><i class="bi bi-exclamation-circle"></i> Update verfügbar</h5>
                                <p class="mb-0">
                                    Eine neue Version ist auf GitHub verfügbar. Das Update wird automatisch heruntergeladen und installiert.
                                    Ein Backup wird vor der Installation erstellt.
                                </p>
                                <?php if (!empty($latestRelease['name'])): ?>
                                    <hr>
                                    <strong><?= htmlspecialchars($latestRelease['name']) ?></strong>
                                <?php endif; ?>
                            </div>
                            
                            <form method="post" onsubmit="return confirm('Update jetzt installieren? Ein automatisches Backup wird erstellt.')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <button type="submit" name="start_update" class="btn btn-light btn-lg">
                                    <i class="bi bi-download"></i> Update von GitHub installieren
                                </button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-light mb-4">
                                <h5><i class="bi bi-check-circle"></i> System ist aktuell</h5>
                                <p class="mb-0">Sie verwenden bereits die neueste verfügbare Version von GitHub.</p>
                            </div>
                            
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <button type="submit" name="clear_cache" class="btn btn-outline-light">
                                    <i class="bi bi-arrow-clockwise"></i> GitHub Cache aktualisieren
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Repository-Informationen -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-info-circle"></i> Repository-Info</h5>
                    </div>
                    <div class="card-body">
                        <div class="github-info">
                            <h6>GitHub Repository:</h6>
                            <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" class="repo-link">
                                <i class="bi bi-github"></i> <?= htmlspecialchars(DVDPROFILER_REPOSITORY) ?>
                            </a>
                            
                            <hr>
                            
                            <h6>Update-Methode:</h6>
                            <small class="text-muted">GitHub Releases API</small><br>
                            
                            <h6 class="mt-3">Rate Limiting:</h6>
                            <small class="text-muted">
                                Aufrufe: <?= (int)getSetting('github_api_calls_hour', '0') ?>/60 pro Stunde
                            </small>
                            
                            <?php if ($latestRelease && !empty($latestRelease['published_at'])): ?>
                            <hr>
                            <h6>Letzte Veröffentlichung:</h6>
                            <small class="text-muted">
                                <?= date('d.m.Y H:i', strtotime($latestRelease['published_at'])) ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Release Notes -->
        <?php if ($latestRelease && !empty($latestRelease['body']) && $isUpdateAvailable): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-file-text"></i> Release Notes - <?= htmlspecialchars($latestRelease['version']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="changelog">
                            <?= nl2br(htmlspecialchars($latestRelease['body'])) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Hinweise -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-lightbulb"></i> Wichtige Hinweise</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li><strong>Automatisches Backup:</strong> Vor jedem Update wird automatisch ein vollständiges Backup erstellt</li>
                            <li><strong>Geschützte Dateien:</strong> Konfigurationsdateien und Uploads werden nicht überschrieben</li>
                            <li><strong>Rate Limiting:</strong> GitHub API ist auf 60 Aufrufe pro Stunde limitiert (ohne Authentifizierung)</li>
                            <li><strong>Cache:</strong> Release-Informationen werden 1 Stunde zwischengespeichert</li>
                            <li><strong>Rollback:</strong> Bei Problemen können Sie das automatische Backup wiederherstellen</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>