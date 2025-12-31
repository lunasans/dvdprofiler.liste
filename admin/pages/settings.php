<?php
/**
 * DVD Profiler Liste - Admin Settings
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.8
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


// POST-Handler mit CSRF-Schutz
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token prüfen
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $error = '❌ Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.';
    } else {
        // Einstellungen speichern (POST mit csrf_token = Settings-Form)
        if (isset($_POST['site_title']) || isset($_POST['environment']) || isset($_POST['tmdb_api_key'])) {
            $allowedSettings = [
                'site_title' => ['maxlength' => 255, 'required' => true],
                'site_description' => ['maxlength' => 500],
                'base_url' => ['filter' => FILTER_VALIDATE_URL],
                'environment' => ['maxlength' => 20],
                'theme' => ['maxlength' => 50],
                'items_per_page' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 5, 'max_range' => 100]],
                'latest_films_count' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 5, 'max_range' => 50]],
                'enable_2fa' => ['filter' => FILTER_VALIDATE_BOOLEAN],
                'login_attempts_max' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1, 'max_range' => 10]],
                'login_lockout_time' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 60, 'max_range' => 3600]],
                'session_timeout' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 300, 'max_range' => 86400]],
                'backup_retention_days' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1, 'max_range' => 365]],
                // TMDb Integration
                'tmdb_api_key' => ['maxlength' => 255],
                'tmdb_show_ratings_on_cards' => ['filter' => FILTER_VALIDATE_BOOLEAN],
                'tmdb_show_ratings_details' => ['filter' => FILTER_VALIDATE_BOOLEAN],
                'tmdb_cache_hours' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1, 'max_range' => 168]]
            ];
            
            $savedCount = 0;
            foreach ($allowedSettings as $key => $validation) {
                // Special handling für Checkboxen
                $isCheckbox = isset($validation['filter']) && $validation['filter'] === FILTER_VALIDATE_BOOLEAN;
                
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
                } elseif ($isCheckbox) {
                    // Checkbox nicht gecheckt = speichere "0"
                    if (setSetting($key, '0')) {
                        $savedCount++;
                    }
                }
            }
            
            if (!$error) {
                $success = "✅ {$savedCount} Einstellung(en) erfolgreich gespeichert.";
                // KEIN Redirect bei AJAX! Success-Message wird im Response angezeigt
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
                <!-- Platz für Quick Actions -->
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
                <button class="nav-link" id="tmdb-tab" data-bs-toggle="tab" data-bs-target="#tmdb" type="button" role="tab">
                    <i class="bi bi-star-fill"></i> TMDb
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                    <i class="bi bi-cpu"></i> System
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
                                        <label for="theme" class="form-label">
                                            <i class="bi bi-palette"></i> Theme / Farbschema
                                        </label>
                                        <select name="theme" id="theme" class="form-select">
                                            <option value="default" <?= getSetting('theme', 'default') === 'default' ? 'selected' : '' ?>>
                                                🎨 Standard (Lila/Blau)
                                            </option>
                                            <option value="dark" <?= getSetting('theme', 'default') === 'dark' ? 'selected' : '' ?>>
                                                🌙 Dark Mode (Pure Black)
                                            </option>
                                            <option value="blue" <?= getSetting('theme', 'default') === 'blue' ? 'selected' : '' ?>>
                                                💙 Blue (Ocean)
                                            </option>
                                            <option value="green" <?= getSetting('theme', 'default') === 'green' ? 'selected' : '' ?>>
                                                💚 Green (Matrix)
                                            </option>
                                            <option value="red" <?= getSetting('theme', 'default') === 'red' ? 'selected' : '' ?>>
                                                ❤️ Red (Warm)
                                            </option>
                                            <option value="purple" <?= getSetting('theme', 'default') === 'purple' ? 'selected' : '' ?>>
                                                💜 Purple (Royal)
                                            </option>
                                        </select>
                                        <small class="text-muted">Ändert Farben der gesamten Website</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="items_per_page" class="form-label">
                                            <i class="bi bi-grid"></i> Filme pro Seite (Pagination)
                                        </label>
                                        <input type="number" name="items_per_page" id="items_per_page" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting('items_per_page', '20')) ?>" 
                                               min="5" max="100">
                                        <small class="text-muted">Anzahl Filme in der linken Liste</small>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="latest_films_count" class="form-label">
                                            <i class="bi bi-stars"></i> Neueste Filme anzeigen
                                        </label>
                                        <input type="number" name="latest_films_count" id="latest_films_count" class="form-control" 
                                               value="<?= htmlspecialchars(getSetting('latest_films_count', '10')) ?>" 
                                               min="5" max="50">
                                        <small class="text-muted">Anzahl neuester Filme rechts (5-50)</small>
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

            <!-- Tab: TMDb Integration -->
            <div class="tab-pane fade" id="tmdb" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-star-fill"></i>
                            TMDb Integration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            
                            <div class="mb-4">
                                <label for="tmdb_api_key" class="form-label">
                                    TMDb API Key
                                    <span class="text-muted small">Kostenlos bei themoviedb.org</span>
                                </label>
                                <input type="text" name="tmdb_api_key" id="tmdb_api_key" class="form-control" 
                                       value="<?= htmlspecialchars(getSetting('tmdb_api_key', '')) ?>"
                                       placeholder="z.B. 1a2b3c4d5e6f7g8h9i0j..." 
                                       maxlength="255">
                                
                                <?php if (empty(getSetting('tmdb_api_key', ''))): ?>
                                <!-- Anleitung nur anzeigen wenn KEIN Key gesetzt -->
                                <div class="alert alert-info mt-3 tmdb-instructions" role="alert">
                                    <h6 class="alert-heading">
                                        <i class="bi bi-info-circle"></i> API Key beantragen
                                    </h6>
                                    <ol class="mb-0">
                                        <li>Account erstellen auf <a href="https://www.themoviedb.org/signup" target="_blank" rel="noopener">themoviedb.org</a></li>
                                        <li>Gehe zu <a href="https://www.themoviedb.org/settings/api" target="_blank" rel="noopener">Settings → API</a></li>
                                        <li>Klicke "Create" und wähle "Developer"</li>
                                        <li>Fülle das Formular aus (Zweck: "Personal Use")</li>
                                        <li>Kopiere den "API Key (v3 auth)" hier rein</li>
                                    </ol>
                                </div>
                                <?php else: ?>
                                <!-- Info bei gesetztem Key -->
                                <small class="form-text text-muted d-block mt-2">
                                    <i class="bi bi-check-circle text-success"></i>
                                    API Key ist gesetzt. Du kannst ihn jederzeit ändern.
                                </small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="tmdb_show_ratings_on_cards" id="tmdb_show_ratings_on_cards" value="1"
                                                   <?= getSetting('tmdb_show_ratings_on_cards', '1') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="tmdb_show_ratings_on_cards">
                                                <strong>Ratings auf Film-Kacheln anzeigen</strong>
                                                <br>
                                                <small class="text-muted">Zeigt Rating-Badges (⭐ 8.5) auf den Film-Covern im Grid</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="tmdb_show_ratings_details" id="tmdb_show_ratings_details" value="1"
                                                   <?= getSetting('tmdb_show_ratings_details', '1') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="tmdb_show_ratings_details">
                                                <strong>Ausführliche Ratings auf Detail-Seite</strong>
                                                <br>
                                                <small class="text-muted">Zeigt TMDb + IMDb Ratings mit Details auf der Film-Seite</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="tmdb_cache_hours" class="form-label">
                                    Cache-Dauer (Stunden)
                                    <span class="text-muted small">Wie lange Ratings gecacht werden</span>
                                </label>
                                <input type="number" name="tmdb_cache_hours" id="tmdb_cache_hours" class="form-control" 
                                       value="<?= htmlspecialchars(getSetting('tmdb_cache_hours', '24')) ?>"
                                       min="1" max="168" step="1">
                                <small class="form-text text-muted">
                                    Standard: 24 Stunden (1 Tag) | Maximal: 168 Stunden (7 Tage)
                                    <br>
                                    💡 <strong>Tipp:</strong> Kürzere Zeit = Aktuellere Ratings, aber mehr API-Calls
                                </small>
                            </div>
                            
                            <?php if (!empty(getSetting('tmdb_api_key', ''))): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle-fill"></i>
                                <strong>TMDb Integration aktiv!</strong> Ratings werden automatisch geladen und gecacht (<?= getSetting('tmdb_cache_hours', '24') ?>h).
                            </div>
                            
                            <!-- Bulk Load Section -->
                            <div class="card mb-4" style="background: var(--bg-secondary); border: 1px solid var(--border-color);">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="bi bi-download"></i> Ratings vorladen
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-3">
                                        Lädt TMDb Ratings für <strong>alle Filme</strong> in Ihrer Sammlung und speichert sie im Cache.
                                        Dies beschleunigt die Anzeige später.
                                    </p>
                                    
                                    <button type="button" id="bulkLoadBtn" class="btn btn-outline-primary">
                                        <i class="bi bi-cloud-download"></i>
                                        Alle Ratings jetzt laden
                                    </button>
                                    
                                    <!-- Progress -->
                                    <div id="bulkLoadProgress" style="display: none; margin-top: 1rem;">
                                        <div class="progress" style="height: 25px;">
                                            <div id="bulkProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                                                 role="progressbar" style="width: 0%">
                                                0%
                                            </div>
                                        </div>
                                        <div id="bulkLoadStatus" class="mt-2 text-muted small"></div>
                                    </div>
                                    
                                    <!-- Results -->
                                    <div id="bulkLoadResults" style="display: none; margin-top: 1rem;"></div>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <strong>Kein API Key gesetzt.</strong> Ratings werden nicht angezeigt.
                            </div>
                            <?php endif; ?>
                            
                            <button type="submit" name="save_settings" class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                TMDb-Einstellungen speichern
                            </button>
                            
                            <script>
                            // Bulk Load Script
                            document.getElementById('bulkLoadBtn')?.addEventListener('click', function() {
                                const btn = this;
                                const progress = document.getElementById('bulkLoadProgress');
                                const progressBar = document.getElementById('bulkProgressBar');
                                const status = document.getElementById('bulkLoadStatus');
                                const results = document.getElementById('bulkLoadResults');
                                
                                // UI vorbereiten
                                btn.disabled = true;
                                progress.style.display = 'block';
                                results.style.display = 'none';
                                
                                let totalLoaded = 0;
                                let totalErrors = 0;
                                
                                function loadBatch(offset = 0) {
                                    const formData = new FormData();
                                    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
                                    formData.append('offset', offset);
                                    
                                    fetch('../actions/tmdb-bulk-load.php', {
                                        method: 'POST',
                                        body: formData
                                    })
                                    .then(response => {
                                        return response.text().then(text => {
                                            <?php if (getSetting('environment', 'production') === 'development'): ?>
                                            // Debug-Logging nur im Development-Mode
                                            console.log('RAW Response:', text);
                                            <?php endif; ?>
                                            
                                            try {
                                                return JSON.parse(text);
                                            } catch (e) {
                                                <?php if (getSetting('environment', 'production') === 'development'): ?>
                                                console.error('JSON Parse Error:', e);
                                                console.error('Response Text:', text);
                                                <?php endif; ?>
                                                throw new Error('Server returned invalid JSON. ' + 
                                                    <?php if (getSetting('environment', 'production') === 'development'): ?>
                                                    text.substring(0, 200)
                                                    <?php else: ?>
                                                    'Bitte Development-Mode aktivieren für Details.'
                                                    <?php endif; ?>
                                                );
                                            }
                                        });
                                    })
                                    .then(data => {
                                        if (!data.success) {
                                            throw new Error(data.error || 'Unbekannter Fehler');
                                        }
                                        
                                        // Progress aktualisieren
                                        const percent = data.progress || 0;
                                        progressBar.style.width = percent + '%';
                                        progressBar.textContent = percent + '%';
                                        
                                        status.innerHTML = `
                                            Verarbeitet: ${data.processed} / ${data.total} Filme<br>
                                            Geladen: ${data.loaded || 0} | Fehler: ${data.errors || 0}
                                        `;
                                        
                                        totalLoaded += data.loaded || 0;
                                        totalErrors += data.errors || 0;
                                        
                                        // Fertig?
                                        if (data.completed) {
                                            progressBar.classList.remove('progress-bar-animated');
                                            progressBar.classList.add('bg-success');
                                            
                                            results.style.display = 'block';
                                            results.innerHTML = `
                                                <div class="alert alert-success">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                    <strong>Fertig!</strong><br>
                                                    ${totalLoaded} Ratings erfolgreich geladen<br>
                                                    ${totalErrors > 0 ? totalErrors + ' Fehler (Filme nicht gefunden)' : ''}
                                                </div>
                                            `;
                                            
                                            btn.disabled = false;
                                            btn.innerHTML = '<i class="bi bi-check"></i> Abgeschlossen';
                                        } else {
                                            // Nächster Batch
                                            setTimeout(() => loadBatch(data.next_offset), 500);
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Bulk Load Error:', error);
                                        results.style.display = 'block';
                                        results.innerHTML = `
                                            <div class="alert alert-danger">
                                                <i class="bi bi-x-circle-fill"></i>
                                                <strong>Fehler:</strong> ${error.message}
                                            </div>
                                        `;
                                        btn.disabled = false;
                                    });
                                }
                                
                                // Start
                                loadBatch(0);
                            });
                            </script>
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
                        <p class="text-muted">Leeren Sie den Cache bei Problemen.</p>
                        
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
        </div>
    </div>
</div>

<style>
/* Settings Page Styling */
.settings-container {
    max-width: 1400px;
    margin: 0 auto;
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

.settings-tabs .nav-tabs {
    border-bottom: 2px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: wrap !important;
}

.settings-tabs .nav-tabs .nav-item {
    margin-bottom: 0 !important;
}

.settings-tabs .nav-link {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    margin-right: var(--space-sm, 8px);
    border-radius: var(--radius-md, 12px) var(--radius-md, 12px) 0 0;
    transition: all var(--transition-fast, 0.3s);
    display: inline-block !important;
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
// IIFE für AJAX-Kompatibilität (sofortige Ausführung)
(function() {
    // Bootstrap Tabs manuell initialisieren
    const triggerTabList = document.querySelectorAll('#settingsTabs button[data-bs-toggle="tab"]');
    if (triggerTabList.length > 0 && typeof bootstrap !== 'undefined') {
        triggerTabList.forEach(triggerEl => {
            new bootstrap.Tab(triggerEl);
        });
        console.log('Bootstrap Tabs initialisiert:', triggerTabList.length);
    }
    
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
})();

// Settings Form AJAX Handler
// Füge dieses Script am Ende von admin/pages/settings.php ein

(function() {
    'use strict';
    
    // Warte bis DOM geladen ist
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAjaxForms);
    } else {
        initAjaxForms();
    }
    
    function initAjaxForms() {
        const forms = document.querySelectorAll('form[method="post"]');
        
        forms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                
                // WICHTIG: Button-Name wird bei FormData nicht automatisch mitgeschickt
                // Füge ihn manuell hinzu, damit PHP weiß dass es ein Settings-Save ist
                if (submitBtn && submitBtn.name) {
                    formData.append(submitBtn.name, '1');
                }
                
                // Button disablen
                if (submitBtn) {
                    submitBtn.disabled = true;
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Wird gespeichert...';
                    
                    // AJAX Submit
                    fetch('?page=settings', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.text())
                    .then(html => {
                        // Parse Response für Success/Error Messages
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Finde Alert-Messages
                        const alerts = doc.querySelectorAll('.alert');
                        
                        // Zeige Success/Error
                        if (alerts.length > 0) {
                            // Entferne alte Alerts
                            document.querySelectorAll('.alert').forEach(a => a.remove());
                            
                            // Füge neue Alerts ein (vor dem Form)
                            alerts.forEach(alert => {
                                form.parentNode.insertBefore(alert, form);
                            });
                            
                            // Scroll to alert
                            alerts[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                            
                            // Success: Reload nach 2 Sekunden
                            const hasSuccess = Array.from(alerts).some(a => a.classList.contains('alert-success'));
                            if (hasSuccess) {
                                setTimeout(() => {
                                    location.reload();
                                }, 2000);
                            }
                        } else {
                            // Keine Alert gefunden - zeige generische Success
                            const successDiv = document.createElement('div');
                            successDiv.className = 'alert alert-success';
                            successDiv.innerHTML = '✅ Einstellungen erfolgreich gespeichert!';
                            form.parentNode.insertBefore(successDiv, form);
                            
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                        }
                    })
                    .catch(error => {
                        console.error('Save error:', error);
                        
                        // Zeige Error
                        const errorDiv = document.createElement('div');
                        errorDiv.className = 'alert alert-danger';
                        errorDiv.innerHTML = '❌ Fehler beim Speichern. Bitte versuchen Sie es erneut.';
                        form.parentNode.insertBefore(errorDiv, form);
                    })
                    .finally(() => {
                        // Button wieder enablen
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }
                    });
                }
            });
        });
        
        console.log('✅ AJAX Form Handler für Settings aktiviert');
    }
})();
</script>