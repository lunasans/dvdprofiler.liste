<?php
/**
 * tmdb-download-cover.php
 * Lädt fehlende Cover von TMDb herunter
 */

// Als AJAX markieren
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

ob_start();

session_start();
require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__, 2) . '/tmdb-helper.php';

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
$filmId = (int)($_POST['film_id'] ?? 0);
$type = $_POST['type'] ?? 'poster'; // 'poster' oder 'backdrop'

if ($filmId <= 0) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Ungültige Film-ID']));
}

try {
    // Film laden
    $stmt = $pdo->prepare("SELECT id, title, year, cover_id FROM dvds WHERE id = ?");
    $stmt->execute([$filmId]);
    $film = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$film) {
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(404);
        die(json_encode(['success' => false, 'error' => 'Film nicht gefunden']));
    }
    
    // Cover-ID generieren falls nicht vorhanden
    $coverId = $film['cover_id'];
    if (empty($coverId)) {
        $coverId = strtolower(preg_replace('/[^a-z0-9]+/i', '', $film['title']));
        
        // In DB speichern
        $updateStmt = $pdo->prepare("UPDATE dvds SET cover_id = ? WHERE id = ?");
        $updateStmt->execute([$coverId, $filmId]);
    }
    
    // TMDb Helper
    $tmdb = new TMDbHelper($apiKey);
    
    // Cover herunterladen
    if ($type === 'poster') {
        $success = $tmdb->downloadPoster($film['title'], $film['year'], $coverId);
    } elseif ($type === 'backdrop') {
        $success = $tmdb->downloadBackdrop($film['title'], $film['year'], $coverId);
    } else {
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(400);
        die(json_encode(['success' => false, 'error' => 'Ungültiger Type']));
    }
    
    if ($success) {
        ob_clean();
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => ucfirst($type) . ' erfolgreich heruntergeladen',
            'cover_id' => $coverId,
            'type' => $type
        ]);
        die();
    } else {
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(500);
        die(json_encode(['success' => false, 'error' => 'Download fehlgeschlagen - Film nicht auf TMDb gefunden']));
    }
    
} catch (Exception $e) {
    ob_clean();
    header('Content-Type: application/json');
    error_log('TMDb Cover Download Error: ' . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'error' => $e->getMessage()]));
}