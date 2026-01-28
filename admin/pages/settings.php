<?php
/**
 * Settings Page für Admin-Panel (AJAX Version)
 * CSS muss separat geladen werden!
 */

// CSRF-Token
$csrfToken = generateCSRFToken();

// System-Anforderungen prüfen
$systemRequirements = [
    'php' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'php_recommended' => version_compare(PHP_VERSION, '8.0.0', '>='),
    'extensions' => [
        'PDO' => extension_loaded('pdo'),
        'pdo_mysql' => extension_loaded('pdo_mysql'),
        'mbstring' => extension_loaded('mbstring'),
        'gd' => extension_loaded('gd'),
        'curl' => extension_loaded('curl'),
        'json' => extension_loaded('json'),
        'zip' => extension_loaded('zip')
    ]
];
?>

<!-- Save Status Notifications -->
<div id="saveStatus" class="save-status"></div>

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
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="signature-tab" data-bs-toggle="tab" data-bs-target="#signature" type="button" role="tab">
                <i class="bi bi-image"></i> Signatur-Banner
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
                        <form class="settings-form" data-section="general">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="section" value="general">
                            
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
                            
                            <button type="submit" class="btn-save" class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                Speichern
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
                        <form class="settings-form" data-section="security">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="section" value="security">
                            
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
                            
                            <button type="submit" class="btn-save" class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                Speichern
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
                        <form class="settings-form" data-section="tmdb">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="section" value="tmdb">
                            
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
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="tmdb_show_similar_movies" id="tmdb_show_similar_movies" value="1"
                                                   <?= getSetting('tmdb_show_similar_movies', '1') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="tmdb_show_similar_movies">
                                                <strong>"Das könnte dir auch gefallen"</strong>
                                                <br>
                                                <small class="text-muted">Zeigt ähnliche Filme auf der Detail-Seite</small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <div class="form-check">
                                            <input type="checkbox" class="form-check-input" name="tmdb_auto_download_covers" id="tmdb_auto_download_covers" value="1"
                                                   <?= getSetting('tmdb_auto_download_covers', '0') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="tmdb_auto_download_covers">
                                                <strong>Cover automatisch von TMDb laden</strong>
                                                <br>
                                                <small class="text-muted">Lädt fehlende Cover automatisch herunter</small>
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
                            
                            <button type="submit" class="btn-save" class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                TMDb-Speichern
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
                                    formData.append('csrf_token', '<?= $csrfToken ?>');
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

            <!-- Tab: Signatur-Banner -->
            <div class="tab-pane fade" id="signature" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-image"></i>
                            Signatur-Banner Einstellungen
                        </h5>
                        <small class="text-muted">
                            Erstelle dynamische Banner für Forum-Signaturen mit deinen neuesten Filmen
                        </small>
                    </div>
                    <div class="card-body">
                        <form class="settings-form" data-section="signature">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="section" value="signature">
                            
                            <!-- Banner aktivieren -->
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i>
                                <strong>Was sind Signatur-Banner?</strong><br>
                                Dynamische Bilder (600×100px) die deine neuesten Filme zeigen. Perfekt für Forum-Signaturen!
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <i class="bi bi-toggle-on"></i> Banner aktivieren
                                        </label>
                                        <div class="form-check form-switch">
                                            <input type="checkbox" name="signature_enabled" id="signature_enabled" 
                                                   class="form-check-input" role="switch"
                                                   <?= getSetting('signature_enabled', '1') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="signature_enabled">
                                                Banner-Generator aktivieren
                                            </label>
                                        </div>
                                        <small class="text-muted">Schaltet signature.php ein/aus</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="signature_film_count" class="form-label">
                                            <i class="bi bi-collection"></i> Anzahl Filme
                                        </label>
                                        <select name="signature_film_count" id="signature_film_count" class="form-select">
                                            <option value="5" <?= getSetting('signature_film_count', '10') == '5' ? 'selected' : '' ?>>5 Filme</option>
                                            <option value="10" <?= getSetting('signature_film_count', '10') == '10' ? 'selected' : '' ?>>10 Filme (Standard)</option>
                                            <option value="15" <?= getSetting('signature_film_count', '10') == '15' ? 'selected' : '' ?>>15 Filme</option>
                                            <option value="20" <?= getSetting('signature_film_count', '10') == '20' ? 'selected' : '' ?>>20 Filme</option>
                                        </select>
                                        <small class="text-muted">Wie viele Filme im Banner anzeigen</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Film-Quelle -->
                            <h6 class="mb-3">
                                <i class="bi bi-funnel"></i> Film-Auswahl
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="signature_film_source" class="form-label">Welche Filme anzeigen?</label>
                                        <select name="signature_film_source" id="signature_film_source" class="form-select">
                                            <option value="newest" <?= getSetting('signature_film_source', 'newest') == 'newest' ? 'selected' : '' ?>>
                                                📅 Neueste (zuletzt hinzugefügt)
                                            </option>
                                            <option value="newest_release" <?= getSetting('signature_film_source', 'newest') == 'newest_release' ? 'selected' : '' ?>>
                                                🎬 Neueste Veröffentlichungen (Jahr)
                                            </option>
                                            <option value="best_rated" <?= getSetting('signature_film_source', 'newest') == 'best_rated' ? 'selected' : '' ?>>
                                                ⭐ Bestbewertete (TMDb Rating)
                                            </option>
                                            <option value="random" <?= getSetting('signature_film_source', 'newest') == 'random' ? 'selected' : '' ?>>
                                                🎲 Zufällig
                                            </option>
                                        </select>
                                        <small class="text-muted">Kriterium für Film-Auswahl</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="signature_cache_time" class="form-label">
                                            <i class="bi bi-clock-history"></i> Cache-Zeit
                                        </label>
                                        <select name="signature_cache_time" id="signature_cache_time" class="form-select">
                                            <option value="1800" <?= getSetting('signature_cache_time', '3600') == '1800' ? 'selected' : '' ?>>30 Minuten</option>
                                            <option value="3600" <?= getSetting('signature_cache_time', '3600') == '3600' ? 'selected' : '' ?>>1 Stunde (Standard)</option>
                                            <option value="7200" <?= getSetting('signature_cache_time', '3600') == '7200' ? 'selected' : '' ?>>2 Stunden</option>
                                            <option value="14400" <?= getSetting('signature_cache_time', '3600') == '14400' ? 'selected' : '' ?>>4 Stunden</option>
                                            <option value="86400" <?= getSetting('signature_cache_time', '3600') == '86400' ? 'selected' : '' ?>>24 Stunden</option>
                                        </select>
                                        <small class="text-muted">Wie lange Banner gecacht werden</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Design-Optionen -->
                            <h6 class="mb-3">
                                <i class="bi bi-palette"></i> Design & Aussehen
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Banner-Typ Varianten</label>
                                        <div class="form-check">
                                            <input type="checkbox" name="signature_enable_type1" id="signature_enable_type1" 
                                                   class="form-check-input"
                                                   <?= getSetting('signature_enable_type1', '1') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="signature_enable_type1">
                                                Typ 1: Cover Grid
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="signature_enable_type2" id="signature_enable_type2" 
                                                   class="form-check-input"
                                                   <?= getSetting('signature_enable_type2', '1') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="signature_enable_type2">
                                                Typ 2: Cover + Stats
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="signature_enable_type3" id="signature_enable_type3" 
                                                   class="form-check-input"
                                                   <?= getSetting('signature_enable_type3', '1') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="signature_enable_type3">
                                                Typ 3: Compact Liste
                                            </label>
                                        </div>
                                        <small class="text-muted">Welche Varianten verfügbar sind</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Anzeige-Optionen</label>
                                        <div class="form-check">
                                            <input type="checkbox" name="signature_show_title" id="signature_show_title" 
                                                   class="form-check-input"
                                                   <?= getSetting('signature_show_title', '1') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="signature_show_title">
                                                Film-Titel anzeigen
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="signature_show_year" id="signature_show_year" 
                                                   class="form-check-input"
                                                   <?= getSetting('signature_show_year', '1') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="signature_show_year">
                                                Erscheinungsjahr anzeigen
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input type="checkbox" name="signature_show_rating" id="signature_show_rating" 
                                                   class="form-check-input"
                                                   <?= getSetting('signature_show_rating', '0') == '1' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="signature_show_rating">
                                                TMDb-Rating anzeigen
                                            </label>
                                        </div>
                                        <small class="text-muted">Was im Banner gezeigt wird</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="signature_quality" class="form-label">Bildqualität</label>
                                        <select name="signature_quality" id="signature_quality" class="form-select">
                                            <option value="6" <?= getSetting('signature_quality', '9') == '6' ? 'selected' : '' ?>>Niedrig (schnell, klein)</option>
                                            <option value="9" <?= getSetting('signature_quality', '9') == '9' ? 'selected' : '' ?>>Hoch (Standard)</option>
                                        </select>
                                        <small class="text-muted">PNG-Kompression (0-9)</small>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4">
                            
                            <!-- Vorschau & URLs -->
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="mb-3">
                                        <i class="bi bi-link-45deg"></i> Banner-URLs & Vorschau
                                    </h6>
                                    
                                    <div class="alert alert-info">
                                        <strong>Banner-URL:</strong><br>
                                        <code class="user-select-all"><?= htmlspecialchars(getSetting('base_url', 'https://deine-domain.de/')) ?>signature.php?type=1</code>
                                        <button type="button" class="btn btn-sm btn-outline-primary float-end" onclick="navigator.clipboard.writeText('<?= htmlspecialchars(getSetting('base_url', '')) ?>signature.php?type=1')">
                                            <i class="bi bi-clipboard"></i> Kopieren
                                        </button>
                                    </div>
                                    
                                    <div class="text-center mt-3">
                                        <a href="../signature.php?type=1" target="_blank" class="btn btn-primary">
                                            <i class="bi bi-eye"></i> Vorschau anzeigen
                                        </a>
                                        <a href="?page=signature-preview" class="btn btn-secondary">
                                            <i class="bi bi-grid-3x3"></i> Alle Varianten ansehen
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn-save" class="btn btn-primary">
                                    <i class="bi bi-save"></i>
                                    Signatur-Speichern
                                </button>
                                
                                <button type="button" class="btn btn-warning" onclick="if(confirm('Cache wirklich leeren?')) { fetch('../signature.php?clear_cache=1'); alert('Cache geleert!'); }">
                                    <i class="bi bi-trash"></i>
                                    Cache leeren
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
    </div>
</div>

<script>
// AJAX Form Handler - Complete Version
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.settings-form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const submitBtn = form.querySelector('.btn-save');
            if (!submitBtn) {
                console.error('Kein Save-Button gefunden');
                return;
            }
            
            const originalText = submitBtn.innerHTML;
            
            // Button Status
            submitBtn.classList.add('saving');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="bi bi-arrow-repeat"></i> Speichere...';
            
            // FormData mit AJAX Flag
            const formData = new FormData(form);
            formData.append('ajax', '1');
            
            // Checkboxen: Nicht gecheckte müssen als "0" gesendet werden
            form.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                if (!checkbox.checked && checkbox.name && checkbox.name !== 'csrf_token') {
                    formData.set(checkbox.name, '0');
                } else if (checkbox.checked && checkbox.name) {
                    formData.set(checkbox.name, '1');
                }
            });
            
            // POST zum separaten Handler
            fetch('actions/settings-ajax-handler.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                showNotification(data.message, data.success ? 'success' : 'danger');
                
                if (data.success) {
                    // Success Feedback
                    submitBtn.innerHTML = '<i class="bi bi-check"></i> Gespeichert!';
                    submitBtn.classList.remove('btn-primary');
                    submitBtn.classList.add('btn-success');
                    
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.classList.add('btn-primary');
                        submitBtn.classList.remove('btn-success');
                        submitBtn.classList.remove('saving');
                        submitBtn.disabled = false;
                    }, 2000);
                } else {
                    submitBtn.innerHTML = originalText;
                    submitBtn.classList.remove('saving');
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                showNotification('❌ Fehler beim Speichern: ' + error.message, 'danger');
                submitBtn.innerHTML = originalText;
                submitBtn.classList.remove('saving');
                submitBtn.disabled = false;
            });
        });
    });
    
    function showNotification(message, type) {
        const statusDiv = document.getElementById('saveStatus');
        if (!statusDiv) {
            console.error('saveStatus div nicht gefunden');
            return;
        }
        
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} fade-in alert-dismissible`;
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        statusDiv.appendChild(alert);
        
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 300);
        }, 4000);
    }
    
    console.log('✅ AJAX Settings System loaded - ' + forms.length + ' Forms found');
});
</script>