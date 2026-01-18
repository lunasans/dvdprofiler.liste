<?php
/**
 * admin/actions/tmdb-search.php
 * Sucht Filme auf TMDb - gibt ALLE Ergebnisse zurück
 */

// Output buffern
ob_start();

// Als AJAX markieren
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

// Error Handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "PHP Error: $errstr"
    ]);
    exit;
});

// Bootstrap laden
session_start();
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/tmdb-helper.php';

// Nur für eingeloggte User
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// CSRF Token prüfen
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'CSRF validation failed']));
}

// API Key Check
$apiKey = getSetting('tmdb_api_key', '');
if (empty($apiKey)) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Kein TMDb API Key gesetzt']));
}

// Parameter
$title = trim($_POST['title'] ?? '');
$year = !empty($_POST['year']) ? (int)$_POST['year'] : null;

if (empty($title)) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Titel darf nicht leer sein']));
}

try {
    // TMDb Helper
    $tmdb = new TMDbHelper($apiKey);
    
    // Filme suchen
    $results = $tmdb->searchMovies($title, $year, 20);
    
    if ($results === null) {
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'TMDb API Fehler']));
    }
    
    if (empty($results)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'count' => 0,
            'results' => [],
            'message' => 'Keine Filme gefunden'
        ]);
        exit;
    }
    
    // Response
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'count' => count($results),
        'results' => $results
    ]);
    exit;
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    error_log('TMDb Search Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fehler bei der Suche: ' . $e->getMessage()
    ]);
    exit;
}