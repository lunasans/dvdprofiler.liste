<?php
/**
 * tmdb-bulk-load.php
 * Lädt TMDb Ratings für ALLE Filme in der Datenbank
 * Wird per AJAX aufgerufen
 */

// Alle Outputs buffern (verhindert Header-Probleme)
ob_start();

// Error Handling - Fehler als JSON ausgeben
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "PHP Error: $errstr in $errfile:$errline"
    ]);
    exit;
});

// Session und Bootstrap laden (relativer Pfad aus admin/actions/)
session_start();
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/tmdb-helper.php';

// Nur für eingeloggte Admins
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized - Bitte einloggen']));
}

// CSRF Token prüfen
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(403);
    die(json_encode(['error' => 'CSRF validation failed']));
}

// API Key Check
$apiKey = getSetting('tmdb_api_key', '');
if (empty($apiKey)) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    die(json_encode(['error' => 'Kein TMDb API Key gesetzt']));
}

// Batch-Verarbeitung Parameter
$offset = (int)($_POST['offset'] ?? 0);
$limit = 10; // Pro Request 10 Filme (nicht zu viele wegen API Rate Limit)

try {
    // Filme aus DB holen (nur Titel + Jahr)
    $stmt = $pdo->prepare("
        SELECT id, title, year 
        FROM dvds 
        WHERE boxset_parent IS NULL 
        ORDER BY id 
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $films = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Gesamtzahl für Fortschritt
    $totalStmt = $pdo->query("SELECT COUNT(*) FROM dvds WHERE boxset_parent IS NULL");
    $total = (int)$totalStmt->fetchColumn();
    
    // Keine Filme mehr?
    if (empty($films)) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'completed' => true,
            'processed' => $offset,
            'total' => $total,
            'message' => 'Alle Ratings geladen!'
        ]);
        exit;
    }
    
    // Ratings für Batch laden
    $loaded = 0;
    $errors = 0;
    
    foreach ($films as $film) {
        $title = $film['title'];
        $year = $film['year'];
        
        // Rating laden (wird automatisch gecacht)
        $ratings = getFilmRatings($title, $year);
        
        if ($ratings !== null) {
            $loaded++;
        } else {
            $errors++;
        }
        
        // Kleine Pause zwischen Requests (API Rate Limit beachten)
        usleep(250000); // 0.25 Sekunden = 4 Requests/Sekunde
    }
    
    // Response
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'completed' => false,
        'processed' => $offset + count($films),
        'total' => $total,
        'loaded' => $loaded,
        'errors' => $errors,
        'next_offset' => $offset + $limit,
        'progress' => round((($offset + count($films)) / $total) * 100, 1)
    ]);
    exit; // Sofort beenden - nichts mehr ausgeben!
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    error_log('TMDb Bulk Load Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fehler beim Laden: ' . $e->getMessage()
    ]);
    exit;
}