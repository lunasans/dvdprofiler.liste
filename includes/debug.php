<?php
/**
 * Debug & Wartungsmodus
 * 
 * Diese Datei aktiviert Debug-Features und Wartungsmodus wenn environment = 'development'
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 */

declare(strict_types=1);

// Nur im Development-Mode ausführen
$isDevelopment = getSetting('environment', 'production') === 'development';

/**
 * System-Informationen loggen (Development)
 */
if ($isDevelopment) {
    // Version-Informationen
    if (function_exists('getDVDProfilerVersionFull')) {
        error_log('DVD Profiler Liste ' . getDVDProfilerVersionFull() . ' loaded');
    }
    
    // Feature-Count
    if (defined('DVDPROFILER_FEATURES')) {
        error_log('Features enabled: ' . count(array_filter(DVDPROFILER_FEATURES)));
    }
    
    // Performance Monitoring registrieren
    register_shutdown_function(function() {
        $memory = memory_get_peak_usage(true);
        $time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        error_log(sprintf('Performance: %.3fs, %s memory', $time, formatBytes($memory)));
    });
    
    // Bootstrap-Abschluss wird am Ende geloggt
    register_shutdown_function(function() {
        if (function_exists('getDVDProfilerVersion')) {
            error_log('Bootstrap completed successfully - DVD Profiler Liste ' . getDVDProfilerVersion());
        }
    });
}

/**
 * Wartungsmodus prüfen
 * Zeigt Wartungsseite wenn environment = 'development' UND user ist nicht eingeloggt
 */
function checkMaintenanceMode(): void
{
    // Nur im Development-Mode aktiv
    if (getSetting('environment', 'production') !== 'development') {
        return;
    }
    
    // Admin ist eingeloggt? → Bypass
    if (isset($_SESSION['user_id'])) {
        return;
    }
    
    // Admin-Login-Seite? → Bypass (sonst kann man sich nicht einloggen!)
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, '/admin/login.php') !== false) {
        return;
    }
    
    // AJAX-Requests? → Bypass (sonst funktioniert Admin nicht)
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        return;
    }
    
    // Wartungsseite anzeigen
    showMaintenancePage();
}

/**
 * Wartungsseite anzeigen und Script beenden
 */
function showMaintenancePage(): void
{
    http_response_code(503);
    header('Retry-After: 3600'); // 1 Stunde
    
    $siteTitle = getSetting('site_title', 'DVD Profiler Liste');
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Wartungsmodus - <?= htmlspecialchars($siteTitle) ?></title>
        
        <!-- Fonts & Icons -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
        
        <!-- Login-Seite CSS -->
        <link href="admin/css/login.css" rel="stylesheet">
        
        <style>
            /* Zusätzliche Styles für Wartungsseite */
            .maintenance-icon {
                font-size: 4rem;
                margin-bottom: 1.5rem;
                animation: pulse 2s ease-in-out infinite;
                color: var(--clr-accent, #3498db);
            }
            
            @keyframes pulse {
                0%, 100% { 
                    transform: scale(1) rotate(0deg); 
                    opacity: 1; 
                }
                50% { 
                    transform: scale(1.1) rotate(5deg); 
                    opacity: 0.8; 
                }
            }
            
            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                background: rgba(52, 152, 219, 0.1);
                border: 1px solid rgba(52, 152, 219, 0.3);
                padding: 0.75rem 1.5rem;
                border-radius: 50px;
                margin-top: 1.5rem;
                font-size: 0.9rem;
                color: var(--clr-accent, #3498db);
            }
            
            .maintenance-text {
                text-align: center;
                margin: 1.5rem 0;
                color: var(--clr-text, #e4e4e7);
                line-height: 1.6;
            }
            
            .admin-hint {
                margin-top: 2rem;
                padding-top: 2rem;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                text-align: center;
            }
            
            .admin-hint p {
                color: var(--clr-text-muted, rgba(228, 228, 231, 0.7));
                font-size: 0.9rem;
                margin-bottom: 1rem;
            }
            
            .admin-link {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                padding: 0.75rem 1.5rem;
                background: var(--clr-accent, #3498db);
                color: white;
                text-decoration: none;
                border-radius: 8px;
                transition: all 0.3s ease;
                font-weight: 500;
            }
            
            .admin-link:hover {
                background: var(--clr-accent-hover, #2980b9);
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
            }
            
            .dev-info {
                margin-top: 2rem;
                padding: 1rem;
                background: rgba(255, 152, 0, 0.1);
                border: 1px solid rgba(255, 152, 0, 0.3);
                border-radius: 8px;
                font-size: 0.75rem;
                color: rgba(255, 152, 0, 0.9);
                text-align: center;
            }
            
            /* Override form-container padding für mehr Platz */
            .form-container {
                padding: 3rem 2.5rem;
            }
        </style>
    </head>
    <body>
        <section class="container">
            <div class="login-container">
                <div class="circle circle-one"></div>
                <div class="circle circle-two"></div>
                
                <div class="form-container">
                    <div class="maintenance-icon">
                        <i class="bi bi-tools"></i>
                    </div>
                    
                    <h1 style="text-align: center;">
                        <i class="bi bi-cone-striped" style="margin-right: 0.5rem; font-size: 0.8em;"></i>
                        Wartungsmodus
                    </h1>
                    
                    <div class="maintenance-text">
                        <p>Wir führen gerade wichtige Wartungsarbeiten durch,<br>um Ihnen das bestmögliche Erlebnis zu bieten.</p>
                        <p>Die Website ist in Kürze wieder verfügbar.</p>
                    </div>
                    
                    <div style="text-align: center;">
                        <div class="status-badge">
                            <i class="bi bi-arrow-repeat"></i>
                            In Bearbeitung...
                        </div>
                    </div>
                    
                    <div class="admin-hint">
                        <p>Sie sind Administrator?</p>
                        <a href="/admin/login.php" class="admin-link">
                            <i class="bi bi-shield-lock"></i>
                            Zum Admin-Login
                        </a>
                    </div>
                    
                    <div class="dev-info">
                        <i class="bi bi-info-circle"></i>
                        Wartungsmodus aktiv (Development Mode)<br>
                        Als Admin einloggen um die Website zu sehen
                    </div>
                </div>
            </div>
        </section>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Debug-Banner für eingeloggte Admins im Development-Mode (ERWEITERT)
 */
function showDebugBanner(): string
{
    global $pdo;
    
    // Nur im Development-Mode UND wenn Admin eingeloggt
    if (getSetting('environment', 'production') !== 'development') {
        return '';
    }
    
    if (!isset($_SESSION['user_id'])) {
        return '';
    }
    
    // Performance Daten sammeln
    $loadTime = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    $memoryUsage = memory_get_usage(true);
    $peakMemory = memory_get_peak_usage(true);
    
    // Database Stats
    $totalFilms = 0;
    $dbQueries = 0; // Wird von PDO nicht automatisch getrackt
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM dvds");
        $totalFilms = $stmt->fetchColumn();
    } catch (Exception $e) {
        $totalFilms = 'Error';
    }
    
    // System Info
    $includedFiles = count(get_included_files());
    $extensions = count(get_loaded_extensions());
    $opcacheEnabled = function_exists('opcache_get_status') && opcache_get_status() !== false;
    
    // Request Info
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
    $currentPage = basename($_SERVER['PHP_SELF'] ?? 'unknown');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    // Features
    $enabledFeatures = defined('DVDPROFILER_FEATURES') ? 
                       count(array_filter(DVDPROFILER_FEATURES)) : 0;
    
    ob_start();
    ?>
    <div id="debug-banner" style="position: fixed; bottom: 0; left: 0; right: 0; background: rgba(255, 87, 34, 0.98); color: white; font-family: 'Courier New', monospace; font-size: 11px; z-index: 99999; box-shadow: 0 -4px 20px rgba(0,0,0,0.5); backdrop-filter: blur(10px);">
        <!-- Hauptzeile (immer sichtbar) -->
        <div style="padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; cursor: pointer;" onclick="document.getElementById('debug-extended').style.display = document.getElementById('debug-extended').style.display === 'none' ? 'block' : 'none';">
            <div style="display: flex; gap: 20px; align-items: center;">
                <strong style="font-size: 13px;">🐛 DEBUG MODE</strong>
                <span>⏱️ <strong><?= number_format($loadTime * 1000, 2) ?>ms</strong></span>
                <span>💾 <strong><?= formatBytes($memoryUsage) ?></strong> / <?= formatBytes($peakMemory) ?></span>
                <span>🎬 <strong><?= $totalFilms ?></strong> Filme</span>
                <span>🐘 PHP <strong><?= PHP_VERSION ?></strong></span>
                <span>⚙️ <strong><?= $enabledFeatures ?></strong> Features</span>
            </div>
            <div style="display: flex; gap: 15px; align-items: center;">
                <span style="background: rgba(255,255,255,0.2); padding: 4px 12px; border-radius: 12px;">
                    Environment: <strong>DEVELOPMENT</strong>
                </span>
                <span style="background: rgba(255,193,7,0.3); padding: 4px 12px; border-radius: 12px;">
                    ⚠️ Wartungsmodus AKTIV
                </span>
                <span style="font-size: 14px;">▼</span>
            </div>
        </div>
        
        <!-- Erweiterte Infos (ausklappbar) -->
        <div id="debug-extended" style="display: none; border-top: 1px solid rgba(255,255,255,0.3); background: rgba(0,0,0,0.3);">
            <div style="padding: 15px 20px; display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                
                <!-- Performance -->
                <div>
                    <div style="font-weight: bold; margin-bottom: 8px; color: #ffeb3b;">⚡ Performance</div>
                    <div style="line-height: 1.8;">
                        <div>Page Load: <strong><?= number_format($loadTime * 1000, 2) ?>ms</strong></div>
                        <div>Memory: <strong><?= formatBytes($memoryUsage) ?></strong></div>
                        <div>Peak Memory: <strong><?= formatBytes($peakMemory) ?></strong></div>
                        <div>Included Files: <strong><?= $includedFiles ?></strong></div>
                        <div>OpCache: <strong><?= $opcacheEnabled ? '✅ Enabled' : '❌ Disabled' ?></strong></div>
                    </div>
                </div>
                
                <!-- Database -->
                <div>
                    <div style="font-weight: bold; margin-bottom: 8px; color: #4caf50;">🗄️ Database</div>
                    <div style="line-height: 1.8;">
                        <div>Total Films: <strong><?= $totalFilms ?></strong></div>
                        <div>Driver: <strong><?= $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) ?></strong></div>
                        <div>Server: <strong><?= $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) ?></strong></div>
                        <div>Connection: <strong><?= $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS) ?></strong></div>
                    </div>
                </div>
                
                <!-- Request Info -->
                <div>
                    <div style="font-weight: bold; margin-bottom: 8px; color: #2196f3;">🌐 Request</div>
                    <div style="line-height: 1.8;">
                        <div>Method: <strong><?= $requestMethod ?></strong></div>
                        <div>Page: <strong><?= $currentPage ?></strong></div>
                        <div>AJAX: <strong><?= $isAjax ? '✅ Yes' : '❌ No' ?></strong></div>
                        <div>Session ID: <strong><?= substr(session_id(), 0, 8) ?>...</strong></div>
                        <div>User: <strong><?= htmlspecialchars($_SESSION['username'] ?? 'Unknown') ?></strong></div>
                    </div>
                </div>
                
                <!-- System -->
                <div>
                    <div style="font-weight: bold; margin-bottom: 8px; color: #ff9800;">🖥️ System</div>
                    <div style="line-height: 1.8;">
                        <div>PHP Version: <strong><?= PHP_VERSION ?></strong></div>
                        <div>Extensions: <strong><?= $extensions ?></strong></div>
                        <div>OS: <strong><?= PHP_OS ?></strong></div>
                        <div>SAPI: <strong><?= PHP_SAPI ?></strong></div>
                        <?php if (function_exists('disk_free_space')): ?>
                        <div>Disk Free: <strong><?= formatBytes(disk_free_space('.')) ?></strong></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Features & Config -->
                <div>
                    <div style="font-weight: bold; margin-bottom: 8px; color: #e91e63;">⚙️ Configuration</div>
                    <div style="line-height: 1.8;">
                        <div>Version: <strong><?= DVDPROFILER_VERSION ?? '1.4.8' ?></strong></div>
                        <div>Codename: <strong><?= DVDPROFILER_CODENAME ?? 'Cinephile' ?></strong></div>
                        <div>Theme: <strong><?= getSetting('theme', 'default') ?></strong></div>
                        <div>Environment: <strong>development</strong></div>
                        <div>Features: <strong><?= $enabledFeatures ?></strong> enabled</div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div>
                    <div style="font-weight: bold; margin-bottom: 8px; color: #9c27b0;">🔧 Quick Actions</div>
                    <div style="line-height: 1.8;">
                        <a href="/admin/index.php?page=settings" style="color: #fff; text-decoration: underline;">⚙️ Settings</a><br>
                        <a href="/admin/index.php?page=dashboard" style="color: #fff; text-decoration: underline;">📊 Dashboard</a><br>
                        <a href="?clear_cache=1" style="color: #fff; text-decoration: underline;">🗑️ Clear Cache</a><br>
                        <button onclick="location.reload()" style="background: rgba(255,255,255,0.2); border: none; color: white; padding: 4px 12px; border-radius: 4px; cursor: pointer; margin-top: 5px;">🔄 Reload Page</button>
                    </div>
                </div>
                
            </div>
            
            <!-- Footer -->
            <div style="padding: 10px 20px; border-top: 1px solid rgba(255,255,255,0.2); text-align: center; background: rgba(0,0,0,0.2);">
                <span style="opacity: 0.7;">💡 Click anywhere on the banner to collapse</span>
                <span style="float: right; opacity: 0.7;">
                    Built: <?= DVDPROFILER_BUILD_DATE ?? date('Y-m-d') ?> | 
                    Author: <?= DVDPROFILER_AUTHOR ?? 'René Neuhaus' ?>
                </span>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Wartungsmodus aktivieren (stoppt Script wenn aktiviert)
checkMaintenanceMode();

// Debug-Banner für Admins (nur auf Haupt-Seite, nicht bei AJAX!)
if (getSetting('environment', 'production') === 'development' && isset($_SESSION['user_id'])) {
    // Prüfe ob es ein AJAX-Request ist
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
              strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    // Prüfe ob es ein Partial ist (film-list.php, film-detail.php, etc.)
    $isPartial = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/partials/') !== false;
    
    // Banner nur auf Haupt-Seiten anzeigen, nicht bei AJAX oder Partials
    if (!$isAjax && !$isPartial) {
        register_shutdown_function(function() {
            if (getSetting('environment', 'production') === 'development' && isset($_SESSION['user_id'])) {
                echo showDebugBanner();
            }
        });
    }
}