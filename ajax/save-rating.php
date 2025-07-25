<?php
// ajax/save-rating.php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$filmId = (int)($input['film_id'] ?? 0);
$rating = (float)($input['rating'] ?? 0);

if ($filmId <= 0 || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Parameter']);
    exit;
}

try {
    // Prüfen ob Tabelle existiert, falls nicht erstellen
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            film_id BIGINT NOT NULL,
            user_id INT NOT NULL,
            rating DECIMAL(2,1) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_film (user_id, film_id),
            FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )
    ");
    
    // Bewertung einfügen oder aktualisieren
    $stmt = $pdo->prepare("
        INSERT INTO user_ratings (film_id, user_id, rating) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()
    ");
    $stmt->execute([$filmId, $_SESSION['user_id'], $rating]);
    
    echo json_encode(['success' => true, 'message' => 'Bewertung gespeichert']);
} catch (PDOException $e) {
    error_log("Rating save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Speichern']);
}
?>