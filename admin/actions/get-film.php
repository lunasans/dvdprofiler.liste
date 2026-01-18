<?php
/**
 * Get Film Data for Editing (AJAX Endpoint)
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$filmId = (int)($_GET['id'] ?? 0);

if ($filmId === 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid film ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id, title, year, genre, runtime, overview, 
               rating_age, collection_type, trailer_url, cover_id,
               created_at
        FROM dvds 
        WHERE id = ? AND deleted = 0
    ");
    
    $stmt->execute([$filmId]);
    $film = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$film) {
        http_response_code(404);
        echo json_encode(['error' => 'Film not found']);
        exit;
    }
    
    echo json_encode($film);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    error_log('Get Film Error: ' . $e->getMessage());
}