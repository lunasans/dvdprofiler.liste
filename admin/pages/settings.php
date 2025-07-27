<?php
/**
 * DVD Profiler Liste - Systemeinstellungen
 * GEÄNDERT: Verwendet zentralisierte Update-Logik aus version.php
 */
declare(strict_types=1);

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSRF-Token für sicherheitsrelevante Operationen
$csrfToken = generateCSRFToken();

// GEÄNDERT: Verwende zentralisierte Update-Logik
$updateInfo = getDVDProfilerUpdateInfo();
$latestData = $updateInfo['latest_data'];
$latestVersion = $updateInfo['latest_version'] ?? 'unbekannt';
$localVersion = $updateInfo['current_version'];
$isUpdateAvailable = $updateInfo['is_update_available'];

$error = '';
$success = '';

// POST-Handler mit CSRF-Schutz
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token prüfen
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $error = '❌ Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.';
    } else {
        // GEÄNDERT: Update-Weiterleitung zur echten Update-Seite
        if (isset($_POST['start_update'])) {
            header('Location: ?page=update');
            exit;
        }
        
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
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs" id="settingsTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                <i class="bi bi-gear"></i>
                Allgemein
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                <i class="bi bi-shield-check"></i>
                Sicherheit
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                <i class="bi bi-cpu"></i>
                System
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="updates-tab" data-bs-toggle="tab" data-bs-target="#updates" type="button" role="tab">
                <i class="bi bi-arrow-up-circle"></i>
                Updates
                <?php if ($isUpdateAvailable): ?>
                    <span class="badge bg-warning text-dark ms-1">!</span>
                <?php endif; ?>
            </button>
        </li>
    </ul>

    <!-- Tab Content -->
    <div class="tab-content" id="settingsTabsContent">
        
        <!-- Tab: Allgemein -->
        <div class="tab-pane fade show active" id="general" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-gear"></i>
                        Allgemeine Einstellungen
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="site_title" class="form-label">
                                        <i class="bi bi-card-heading"></i>
                                        Website-Titel
                                    </label>
                                    <input type="text" class="form-control" id="site_title" name="site_title" 
                                           value="<?= htmlspecialchars(getSetting('site_title', 'DVD Profiler Liste')) ?>" required>
                                    <div class="form-text">Der Titel wird im Browser-Tab und Header angezeigt</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="base_url" class="form-label">
                                        <i class="bi bi-link"></i>
                                        Base URL
                                    </label>
                                    <input type="url" class="form-control" id="base_url" name="base_url" 
                                           value="<?= htmlspecialchars(getSetting('base_url', '')) ?>" placeholder="https://example.com">
                                    <div class="form-text">Vollständige URL zu Ihrer Installation</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="site_description" class="form-label">
                                <i class="bi bi-card-text"></i>
                                Website-Beschreibung
                            </label>
                            <textarea class="form-control" id="site_description" name="site_description" rows="3" 
                                      placeholder="Beschreibung Ihrer Filmsammlung..."><?= htmlspecialchars(getSetting('site_description', '')) ?></textarea>
                            <div class="form-text">Wird für SEO und Social Media verwendet</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="environment" class="form-label">
                                        <i class="bi bi-gear-wide-connected"></i>
                                        Umgebung
                                    </label>
                                    <select class="form-select" id="environment" name="environment">
                                        <option value="production" <?= getSetting('environment', 'production') === 'production' ? 'selected' : '' ?>>Production</option>
                                        <option value="development" <?= getSetting('environment') === 'development' ? 'selected' : '' ?>>Development</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="theme" class="form-label">
                                        <i class="bi bi-palette"></i>
                                        Theme
                                    </label>
                                    <select class="form-select" id="theme" name="theme">
                                        <option value="default" <?= getSetting('theme', 'default') === 'default' ? 'selected' : '' ?>>Standard</option>
                                        <option value="dark" <?= getSetting('theme') === 'dark' ? 'selected' : '' ?>>Dark</option>
                                        <option value="blue" <?= getSetting('theme') === 'blue' ? 'selected' : '' ?>>Blue</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="items_per_page" class="form-label">
                                        <i class="bi bi-list-ol"></i>
                                        Filme pro Seite
                                    </label>
                                    <input type="number" class="form-control" id="items_per_page" name="items_per_page" 
                                           value="<?= htmlspecialchars(getSetting('items_per_page', '20')) ?>" min="5" max="100">
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="save_settings" class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                Einstellungen speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab: Sicherheit -->
        <div class="tab-pane fade" id="security" role="tabpanel">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-shield-check"></i>
                        Sicherheitseinstellungen
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="login_attempts_max" class="form-label">
                                        <i class="bi bi-shield-exclamation"></i>
                                        Max. Login-Versuche
                                    </label>
                                    <input type="number" class="form-control" id="login_attempts_max" name="login_attempts_max" 
                                           value="<?= htmlspecialchars(getSetting('login_attempts_max', '5')) ?>" min="1" max="10">
                                    <div class="form-text">Anzahl fehlgeschlagener Versuche vor Sperrung</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="login_lockout_time" class="form-label">
                                        <i class="bi bi-clock"></i>
                                        Sperrzeit (Sekunden)
                                    </label>
                                    <input type="number" class="form-control" id="login_lockout_time" name="login_lockout_time" 
                                           value="<?= htmlspecialchars(getSetting('login_lockout_time', '900')) ?>" min="60" max="3600">
                                    <div class="form-text">Dauer der Sperrung nach zu vielen Fehlversuchen</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="session_timeout" class="form-label">
                                        <i class="bi bi-stopwatch"></i>
                                        Session Timeout (Sekunden)
                                    </label>
                                    <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                           value="<?= htmlspecialchars(getSetting('session_timeout', '3600')) ?>" min="300" max="86400">
                                    <div class="form-text">Automatisches Logout nach Inaktivität</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enable_2fa" name="enable_2fa" value="1" 
                                               <?= getSetting('enable_2fa', '0') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="enable_2fa">
                                            <i class="bi bi-key"></i>
                                            Zwei-Faktor-Authentifizierung aktivieren
                                        </label>
                                    </div>
                                    <div class="form-text">Zusätzliche Sicherheitsebene für Admin-Login</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" name="save_settings" class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                Sicherheitseinstellungen speichern
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Tab: System -->
        <div class="tab-pane fade" id="system" role="tabpanel">
            <div class="row">
                <!-- Systeminfos -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-info-circle"></i>
                                Systemstatus
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="system-info-grid">
                                <div class="info-item">
                                    <strong>Version:</strong> <?= DVDPROFILER_VERSION ?> "<?= DVDPROFILER_CODENAME ?>"
                                </div>
                                <div class="info-item">
                                    <strong>Build:</strong> <?= DVDPROFILER_BUILD_DATE ?>
                                </div>
                                <div class="info-item">
                                    <strong>Branch:</strong> <?= DVDPROFILER_BRANCH ?>
                                </div>
                                <div class="info-item">
                                    <strong>Commit:</strong> <?= DVDPROFILER_COMMIT ?>
                                </div>
                                <div class="info-item">
                                    <strong>PHP:</strong> <?= PHP_VERSION ?>
                                </div>
                                <div class="info-item">
                                    <strong>Features:</strong>
                                    <?= count(array_filter(DVDPROFILER_FEATURES)) ?>/<?= count(DVDPROFILER_FEATURES) ?> aktiv
                                </div>
                                <div class="info-item">
                                    <strong>Repository:</strong>
                                    <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" class="text-decoration-none">
                                        <?= DVDPROFILER_REPOSITORY ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wartung -->
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-tools"></i>
                                Wartung
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post" class="mb-3">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <button type="submit" name="clear_cache" class="btn btn-outline-warning btn-sm" 
                                        onclick="return confirm('Cache wirklich leeren?')">
                                    <i class="bi bi-trash"></i>
                                    Cache leeren
                                </button>
                            </form>
                            
                            <div class="maintenance-info">
                                <div class="info-item">
                                    <strong>Backup-Aufbewahrung:</strong>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                        <input type="number" name="backup_retention_days" 
                                               value="<?= htmlspecialchars(getSetting('backup_retention_days', '30')) ?>" 
                                               min="1" max="365" class="form-control form-control-sm d-inline-block" style="width: 80px;">
                                        <span class="small text-muted">Tage</span>
                                        <button type="submit" name="save_settings" class="btn btn-outline-primary btn-sm ms-2">
                                            <i class="bi bi-save"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Backups -->
                    <div class="card mt-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="bi bi-archive"></i>
                                Backup-Dateien
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($backups)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Datei</th>
                                                <th>Größe</th>
                                                <th>Datum</th>
                                                <th></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach (array_slice($backups, 0, 5) as $backup): ?>
                                                <tr>
                                                    <td>
                                                        <small class="font-monospace"><?= htmlspecialchars($backup['name']) ?></small>
                                                    </td>
                                                    <td>
                                                        <small><?= formatFileSize($backup['size']) ?></small>
                                                    </td>
                                                    <td>
                                                        <small><?= date('d.m.Y H:i', $backup['date']) ?></small>
                                                    </td>
                                                    <td>
                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                                            <input type="hidden" name="delete_backup" value="<?= htmlspecialchars($backup['name']) ?>">
                                                            <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                                    onclick="return confirm('Backup <?= htmlspecialchars($backup['name']) ?> wirklich löschen?')">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <?php if (count($backups) > 5): ?>
                                    <small class="text-muted">
                                        ... und <?= count($backups) - 5 ?> weitere Backups
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <p class="text-muted">Keine Backups vorhanden.</p>
                            <?php endif; ?>
                        </div>
                    </div>
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
                                <span class="version-badge current">v<?= htmlspecialchars($localVersion) ?></span>
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
                            
                            <?php if (!empty($latestData['description'])): ?>
                                <div class="changelog mt-3">
                                    <h6>Changelog:</h6>
                                    <div class="changelog-content">
                                        <?= nl2br(htmlspecialchars(substr($latestData['description'], 0, 500))) ?>
                                        <?php if (strlen($latestData['description']) > 500): ?>
                                            <p><em>... (gekürzt)</em></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-3">
                                <a href="?page=update" class="btn btn-warning">
                                    <i class="bi bi-arrow-up-circle"></i>
                                    Zur Update-Seite
                                </a>
                                <small class="text-muted ms-3">
                                    <i class="bi bi-info-circle"></i>
                                    Updates werden auf der dedizierten Update-Seite durchgeführt
                                </small>
                            </div>
                        </div>
                    <?php elseif ($latestData && !$isUpdateAvailable): ?>
                        <div class="alert alert-success mt-4">
                            <i class="bi bi-check-circle"></i>
                            Sie verwenden bereits die neueste Version.
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning mt-4">
                            <i class="bi bi-exclamation-triangle"></i>
                            Update-Server nicht erreichbar. 
                            <a href="?page=update" class="btn btn-sm btn-outline-primary ms-2">
                                Auf Update-Seite prüfen
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <div class="update-info mt-4">
                        <h6>Update-Informationen</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Update-Server:</strong><br>
                                    <?= htmlspecialchars(parse_url(getDVDProfilerUpdateConfig()['api_url'], PHP_URL_HOST)) ?><br><br>
                                    
                                    <strong>Status:</strong><br>
                                    <?php if ($updateInfo['server_reachable']): ?>
                                        ✅ Server erreichbar<br>
                                    <?php else: ?>
                                        ❌ Server nicht erreichbar<br>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">
                                    <strong>Update-Funktionen:</strong><br>
                                    • Automatisches Backup<br>
                                    • Sichere Datei-Installation<br>
                                    • Rollback bei Fehlern<br>
                                    • SQL-Updates<br>
                                    • Cache-Bereinigung
                                </small>
                            </div>
                        </div>
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
}

.update-alert {
    background: var(--glass-bg-warning, rgba(243, 156, 18, 0.2));
    border: 1px solid var(--clr-warning, #f39c12);
    border-radius: var(--radius-md, 12px);
    padding: var(--space-sm, 8px) var(--space-md, 16px);
    color: var(--text-white, #ffffff);
    font-size: 0.9rem;
}

.nav-tabs {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-md, 12px);
    padding: var(--space-sm, 8px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.nav-tabs .nav-link {
    background: transparent;
    border: none;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    border-radius: var(--radius-sm, 8px);
    transition: all var(--transition-fast, 0.3s);
    margin: 0 var(--space-xs, 4px);
}

.nav-tabs .nav-link:hover {
    background: var(--glass-bg-hover, rgba(255, 255, 255, 0.1));
    color: var(--text-white, #ffffff);
}

.nav-tabs .nav-link.active {
    background: var(--gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    color: var(--text-white, #ffffff);
}

.card {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: var(--radius-lg, 16px);
    margin-top: var(--space-lg, 20px);
}

.card-header {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: var(--radius-lg, 16px) var(--radius-lg, 16px) 0 0;
}

.form-control, .form-select {
    background: var(--glass-bg-input, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.3));
    color: var(--text-white, #ffffff);
    border-radius: var(--radius-sm, 8px);
}

.form-control:focus, .form-select:focus {
    background: var(--glass-bg-input, rgba(255, 255, 255, 0.15));
    border-color: var(--clr-accent, #3498db);
    box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
    color: var(--text-white, #ffffff);
}

.form-control::placeholder {
    color: var(--text-glass, rgba(255, 255, 255, 0.6));
}

.version-badge {
    display: inline-block;
    padding: var(--space-sm, 8px) var(--space-md, 16px);
    border-radius: var(--radius-md, 12px);
    font-weight: 600;
    margin-bottom: var(--space-sm, 8px);
}

.version-badge.current {
    background: var(--gradient-success, linear-gradient(135deg, #2ecc71 0%, #27ae60 100%));
    color: var(--text-white, #ffffff);
}

.version-badge.available {
    background: var(--gradient-warning, linear-gradient(135deg, #f39c12 0%, #e67e22 100%));
    color: var(--text-white, #ffffff);
}

.system-info-grid {
    display: grid;
    gap: var(--space-sm, 8px);
}

.info-item {
    padding: var(--space-sm, 8px);
    background: var(--glass-bg-subtle, rgba(255, 255, 255, 0.05));
    border-radius: var(--radius-sm, 8px);
    font-size: 0.9rem;
}

.btn {
    border-radius: var(--radius-sm, 8px);
    transition: all var(--transition-fast, 0.3s);
}

.btn-primary {
    background: var(--gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    border: none;
}

.btn-warning {
    background: var(--gradient-warning, linear-gradient(135deg, #f39c12 0%, #e67e22 100%));
    border: none;
    color: var(--text-white, #ffffff);
}

.alert {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: var(--radius-md, 12px);
    color: var(--text-white, #ffffff);
}

.alert-success {
    background: var(--glass-bg-success, rgba(46, 204, 113, 0.2));
    border-color: var(--clr-success, #2ecc71);
}

.alert-warning {
    background: var(--glass-bg-warning, rgba(243, 156, 18, 0.2));
    border-color: var(--clr-warning, #f39c12);
}

.alert-danger {
    background: var(--glass-bg-danger, rgba(231, 76, 60, 0.2));
    border-color: var(--clr-danger, #e74c3c);
}

.alert-info {
    background: var(--glass-bg-info, rgba(52, 152, 219, 0.2));
    border-color: var(--clr-info, #3498db);
}
</style>

<script>
// Bootstrap Tab Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Tab-Hash-Navigation
    const hash = window.location.hash;
    if (hash) {
        const tabButton = document.querySelector(`button[data-bs-target="${hash}"]`);
        if (tabButton) {
            new bootstrap.Tab(tabButton).show();
        }
    }
    
    // Hash bei Tab-Wechsel setzen
    document.querySelectorAll('button[data-bs-toggle="tab"]').forEach(button => {
        button.addEventListener('shown.bs.tab', function(e) {
            window.location.hash = e.target.getAttribute('data-bs-target');
        });
    });
});
</script>