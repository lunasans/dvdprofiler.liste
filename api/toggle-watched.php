<?php
// ajax/toggle-watched.php
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

if ($filmId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Film-ID']);
    exit;
}

try {
    // Prüfen ob Tabelle existiert, falls nicht erstellen
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS user_watched (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            film_id BIGINT NOT NULL,
            watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_film_watched (user_id, film_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE
        )
    ");
    
    // Prüfen ob bereits als gesehen markiert
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM user_watched WHERE user_id = ? AND film_id = ?");
    $checkStmt->execute([$_SESSION['user_id'], $filmId]);
    $exists = $checkStmt->fetchColumn() > 0;
    
    if ($exists) {
        // Markierung entfernen
        $deleteStmt = $pdo->prepare("DELETE FROM user_watched WHERE user_id = ? AND film_id = ?");
        $deleteStmt->execute([$_SESSION['user_id'], $filmId]);
        echo json_encode(['watched' => false, 'message' => 'Markierung entfernt']);
    } else {
        // Als gesehen markieren
        $insertStmt = $pdo->prepare("INSERT INTO user_watched (user_id, film_id) VALUES (?, ?)");
        $insertStmt->execute([$_SESSION['user_id'], $filmId]);
        echo json_encode(['watched' => true, 'message' => 'Als gesehen markiert']);
    }
} catch (PDOException $e) {
    error_log("Watched toggle error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Markieren']);
}
?>