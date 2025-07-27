<?php
/**
 * DVD Profiler Liste - Bootstrap
 * VOLLSTÄNDIGE VERSION - repariert
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

// Fehlerbehandlung für kritische Situationen
error_reporting(E_ALL);
ini_set('display_errors', '0'); // In Production immer auf 0

// Basis-Pfad definieren
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// Installation prüfen
$lockFile = BASE_PATH . '/install/install.lock';
if (!file_exists($lockFile)) {
    $installUrl = dirname($_SERVER['SCRIPT_NAME']) . '/install/index.php';
    header('Location: ' . $installUrl);
    exit('Installation required. Redirecting...');
}

try {
    // 1. Autoloader laden
    require_once __DIR__ . '/autoloader.php';
    
    // 2. Prüfen ob Core-Klassen verfügbar sind
    if (!class_exists('DVDProfiler\\Core\\Application')) {
        throw new Exception('Core Application class not found. Please check if all core files are uploaded.');
    }
    
    // 3. Versionssystem laden (für Kompatibilität)
    require_once __DIR__ . '/version.php';
    
    // 4. Application initialisieren
    $app = \DVDProfiler\Core\Application::getInstance();
    $app->initialize();
    
    // 5. Legacy-Variablen für Kompatibilität setzen
    $pdo = $app->getDatabase()->getPDO();
    $settings = $app->getSettings()->getAll(true);
    $csrf_token = \DVDProfiler\Core\Security::generateCSRFToken();
    $version = DVDPROFILER_VERSION;
    $siteTitle = $app->getSettings()->get('site_title', 'DVD Profiler Liste');
    
    // 6. Debug-Modus aktivieren falls Development
    if ($app->getSettings()->get('environment', 'production') === 'development') {
        ini_set('display_errors', '1');
        define('DVDPROFILER_DEBUG', true);
        $app->getSettings()->setDebugMode(true);
    }
    
    // 7. Locale setzen für deutsche Sortierung
    if (function_exists('setlocale')) {
        setlocale(LC_COLLATE, 'de_DE.UTF-8', 'de_DE', 'German_Germany.1252', 'deu_deu');
    }
    
    // 8. Timezone setzen
    date_default_timezone_set($app->getSettings()->get('timezone', 'Europe/Berlin'));
    
} catch (Exception $e) {
    // Kritischer Fehler - System kann nicht starten
    error_log('[CRITICAL] Bootstrap failed: ' . $e->getMessage());
    
    // Einfache Fallback-Anzeige
    http_response_code(500);
    
    if (ini_get('display_errors')) {
        echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>System Error</title>
    <style>
        body { font-family: monospace; background: #000; color: #f00; padding: 20px; }
        .error { background: #222; border: 1px solid #f00; padding: 20px; border-radius: 5px; }
        pre { background: #111; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <div class="error">
        <h1>🚨 System Error</h1>
        <p>Das System konnte nicht initialisiert werden.</p>
        <p><strong>Fehler:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
        <p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
        
        if (defined('DVDPROFILER_DEBUG') && DVDPROFILER_DEBUG) {
            echo '<h2>Stack Trace:</h2>
            <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
        
        echo '<h2>🔧 Lösungsvorschläge:</h2>
        <ul>
            <li>Prüfen Sie, ob alle Core-Dateien hochgeladen wurden</li>
            <li>Überprüfen Sie die Datenbankverbindung in config/config.php</li>
            <li>Stellen Sie sicher, dass alle Verzeichnisse die korrekten Berechtigungen haben</li>
            <li>Führen Sie das Debug-Script aus: debug-500.php</li>
        </ul>
    </div>
</body>
</html>';
    } else {
        echo '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Wartungsmodus</title>
    <style>
        body { font-family: Arial, sans-serif; background: #1a1a2e; color: #fff; padding: 50px; text-align: center; }
        .container { max-width: 600px; margin: 0 auto; }
        .icon { font-size: 64px; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">🔧</div>
        <h1>Wartungsmodus</h1>
        <p>Das System ist vorübergehend nicht verfügbar.</p>
        <p>Wir arbeiten daran, den Service so schnell wie möglich wiederherzustellen.</p>
        <p>Bitte versuchen Sie es in wenigen Minuten erneut.</p>
    </div>
</body>
</html>';
    }
    
    exit;
}

// Erfolgreiche Initialisierung loggen (Development only)
if (defined('DVDPROFILER_DEBUG') && DVDPROFILER_DEBUG) {
    error_log('[Bootstrap] System successfully initialized in ' . 
        number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms');
}