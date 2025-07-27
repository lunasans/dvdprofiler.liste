<?php
/**
 * DVD Profiler Liste - Bootstrap
 * Schlanke Bootstrap-Datei für das neue Core-System
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
    
    // 4. Legacy-Variablen für Kompatibilität setzen
    $pdo = $app->getDatabase()->getPDO();
    $settings = $app->getSettings()->getAll(true);
    $csrf_token = \DVDProfiler\Core\Security::generateCSRFToken();
    $version = DVDPROFILER_VERSION;
    $siteTitle = $app->getSettings()->get('site_title', 'DVD Profiler Liste');
    
    // 5. Debug-Modus aktivieren falls Development
    if ($app->getSettings()->get('environment', 'production') === 'development') {
        ini_set('display_errors', '1');
        define('DVDPROFILER_DEBUG', true);
        $app->getSettings()->setDebugMode(true);
    }
    
    // 6. Locale setzen für deutsche Sortierung
    if (function_exists('setlocale')) {
        setlocale(LC_COLLATE, 'de_DE.UTF-8', 'de_DE', 'German_Germany.1252', 'deu_deu');
    }
    
    // 7. Timezone setzen
    date_default_timezone_set($app->getSettings()->get('timezone', 'Europe/Berlin'));
    
} catch (Exception $e) {
    // Kritischer Fehler - System kann nicht starten
    error_log('[CRITICAL] Bootstrap failed: ' . $e->getMessage());
    
    // Einfache Fallback-Anzeige
    http_response_code(500);
    
    if (ini_get('display_errors')) {
        echo '<h1>System Error</h1>';
        echo '<p>Das System konnte nicht initialisiert werden.</p>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
        
        if (defined('DVDPROFILER_DEBUG') && DVDPROFILER_DEBUG) {
            echo '<h2>Stack Trace:</h2>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        }
    } else {
        echo '<h1>Wartungsmodus</h1>';
        echo '<p>Das System ist vorübergehend nicht verfügbar. Bitte versuchen Sie es später erneut.</p>';
    }
    
    exit;
}

// Erfolgreiche Initialisierung loggen (Development only)
if (defined('DVDPROFILER_DEBUG') && DVDPROFILER_DEBUG) {
    error_log('[Bootstrap] System successfully initialized in ' . 
        number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2) . 'ms');
}