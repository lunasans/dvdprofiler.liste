<?php
/**
 * API: User Rating speichern
 * PRODUCTION VERSION - Ohne Debug-Code
 */
declare(strict_types=1);

session_start();

try {
    require_once __DIR__ . '/../includes/bootstrap.php';
} catch (Exception $e) {
    error_log("Bootstrap error in save-rating.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'System error']);
    exit;
}

header('Content-Type: application/json');

// Authentifizierung prüfen
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

// Input validieren
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$filmId = (int)($input['film_id'] ?? 0);
$rating = (float)($input['rating'] ?? 0);

// Parameter validieren
if ($filmId <= 0 || $rating < 1 || $rating > 5) {
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Parameter']);
    exit;
}

try {
    // Datenbankverbindung testen
    $pdo->query("SELECT 1");
    
    // Prüfen ob user_ratings Tabelle existiert
    $tableExists = $pdo->query("SHOW TABLES LIKE 'user_ratings'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Tabelle erstellen falls nicht vorhanden
        $pdo->exec("
            CREATE TABLE user_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                film_id BIGINT NOT NULL,
                user_id INT NOT NULL,
                rating DECIMAL(2,1) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_film (user_id, film_id),
                FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
    
    // Film existiert prüfen
    $filmCheck = $pdo->prepare("SELECT id FROM dvds WHERE id = ?");
    $filmCheck->execute([$filmId]);
    if (!$filmCheck->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Film nicht gefunden']);
        exit;
    }
    
    // Rating einfügen/updaten
    $stmt = $pdo->prepare("
        INSERT INTO user_ratings (film_id, user_id, rating) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()
    ");
    
    $result = $stmt->execute([$filmId, $_SESSION['user_id'], $rating]);
    
    if (!$result) {
        throw new Exception('Rating konnte nicht gespeichert werden');
    }
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true, 
        'message' => 'Bewertung gespeichert',
        'data' => [
            'film_id' => $filmId,
            'rating' => $rating,
            'user_id' => $_SESSION['user_id']
        ]
    ]);
    
} catch (PDOException $e) {
    error_log("Database error in save-rating.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Datenbankfehler beim Speichern der Bewertung'
    ]);
} catch (Exception $e) {
    error_log("General error in save-rating.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Fehler beim Speichern der Bewertung'
    ]);
}
?>