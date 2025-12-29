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
    
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Wartungsmodus - <?= getSetting('site_title', 'DVD Profiler Liste') ?></title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                color: #fff;
                padding: 20px;
            }
            
            .maintenance-container {
                text-align: center;
                max-width: 600px;
                background: rgba(255, 255, 255, 0.1);
                backdrop-filter: blur(10px);
                border-radius: 20px;
                padding: 60px 40px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }
            
            .icon {
                font-size: 80px;
                margin-bottom: 20px;
                animation: pulse 2s ease-in-out infinite;
            }
            
            @keyframes pulse {
                0%, 100% { transform: scale(1); opacity: 1; }
                50% { transform: scale(1.1); opacity: 0.8; }
            }
            
            h1 {
                font-size: 2.5rem;
                margin-bottom: 20px;
                font-weight: 700;
            }
            
            p {
                font-size: 1.2rem;
                line-height: 1.6;
                margin-bottom: 15px;
                opacity: 0.9;
            }
            
            .status {
                display: inline-block;
                background: rgba(255, 255, 255, 0.2);
                padding: 10px 20px;
                border-radius: 30px;
                margin-top: 20px;
                font-size: 0.9rem;
            }
            
            .admin-hint {
                margin-top: 40px;
                padding-top: 30px;
                border-top: 1px solid rgba(255, 255, 255, 0.2);
                font-size: 0.85rem;
                opacity: 0.7;
            }
            
            .admin-hint a {
                color: #fff;
                text-decoration: underline;
            }
            
            .dev-info {
                margin-top: 20px;
                font-size: 0.75rem;
                opacity: 0.5;
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            <div class="icon">🔧</div>
            <h1>Wartungsmodus</h1>
            <p>Wir führen gerade wichtige Wartungsarbeiten durch.</p>
            <p>Die Website ist bald wieder verfügbar.</p>
            
            <div class="status">
                🔄 In Bearbeitung...
            </div>
            
            <div class="admin-hint">
                Administrator? <a href="/admin/login.php">Hier einloggen</a>
            </div>
            
            <div class="dev-info">
                Wartungsmodus aktiv (Environment: Development)<br>
                Als Admin einloggen um die Website zu sehen
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/**
 * Debug-Banner für eingeloggte Admins im Development-Mode
 */
function showDebugBanner(): string
{
    // Nur im Development-Mode UND wenn Admin eingeloggt
    if (getSetting('environment', 'production') !== 'development') {
        return '';
    }
    
    if (!isset($_SESSION['user_id'])) {
        return '';
    }
    
    $stats = [
        'PHP Version' => PHP_VERSION,
        'Memory' => formatBytes(memory_get_usage(true)),
        'Peak Memory' => formatBytes(memory_get_peak_usage(true)),
        'Loaded Extensions' => count(get_loaded_extensions()),
    ];
    
    ob_start();
    ?>
    <div style="position: fixed; bottom: 0; left: 0; right: 0; background: rgba(255, 87, 34, 0.95); color: white; padding: 10px 20px; font-family: monospace; font-size: 12px; z-index: 99999; box-shadow: 0 -2px 10px rgba(0,0,0,0.3);">
        <strong>🐛 DEBUG MODE AKTIV</strong> | 
        <?php foreach ($stats as $label => $value): ?>
            <?= $label ?>: <strong><?= $value ?></strong> | 
        <?php endforeach; ?>
        <span style="float: right;">
            Environment: <strong>DEVELOPMENT</strong> | 
            Wartungsmodus: <strong>AKTIV für Besucher</strong>
        </span>
    </div>
    <?php
    return ob_get_clean();
}

// Wartungsmodus aktivieren (stoppt Script wenn aktiviert)
checkMaintenanceMode();

// Debug-Banner für Admins (gibt HTML zurück)
if (getSetting('environment', 'production') === 'development' && isset($_SESSION['user_id'])) {
    // Debug-Banner wird am Ende der Seite eingefügt (in index.php vor </body>)
    register_shutdown_function(function() {
        if (getSetting('environment', 'production') === 'development' && isset($_SESSION['user_id'])) {
            echo showDebugBanner();
        }
    });
}