<?php
/**
 * api/save-rating.php - KORRIGIERTE VERSION
 * Behebt Fehler in Version 1.4.7
 */
declare(strict_types=1);

// Debug-Logging aktivieren
error_reporting(E_ALL);
ini_set('log_errors', '1');

session_start();

try {
    require_once __DIR__ . '/../includes/bootstrap.php';
} catch (Exception $e) {
    error_log("Bootstrap error in save-rating.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'System nicht verfügbar']);
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
    error_log("JSON decode error in save-rating.php: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige JSON-Daten']);
    exit;
}

$filmId = (int)($input['film_id'] ?? 0);
$rating = (float)($input['rating'] ?? 0);

// Parameter validieren
if ($filmId <= 0 || $rating < 1 || $rating > 5) {
    error_log("Invalid parameters in save-rating.php - filmId: $filmId, rating: $rating");
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Parameter (Film-ID oder Bewertung)']);
    exit;
}

try {
    // Datenbankverbindung prüfen
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Datenbankverbindung nicht verfügbar');
    }
    
    // Test der Verbindung
    $pdo->query("SELECT 1");
    
    // Prüfen ob Film existiert
    $filmCheck = $pdo->prepare("SELECT id FROM dvds WHERE id = ?");
    $filmCheck->execute([$filmId]);
    if (!$filmCheck->fetch()) {
        http_response_code(404);
        echo json_encode(['error' => 'Film nicht gefunden']);
        exit;
    }
    
    // Prüfen ob user_ratings Tabelle existiert, falls nicht erstellen
    $tableExists = $pdo->query("SHOW TABLES LIKE 'user_ratings'")->rowCount() > 0;
    
    if (!$tableExists) {
        error_log("Creating user_ratings table in save-rating.php");
        $pdo->exec("
            CREATE TABLE user_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                film_id BIGINT NOT NULL,
                user_id INT NOT NULL,
                rating DECIMAL(2,1) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_film (user_id, film_id),
                FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    // Rating einfügen/updaten
    $stmt = $pdo->prepare("
        INSERT INTO user_ratings (film_id, user_id, rating) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE 
            rating = VALUES(rating), 
            updated_at = CURRENT_TIMESTAMP
    ");
    
    $result = $stmt->execute([$filmId, $_SESSION['user_id'], $rating]);
    
    if (!$result) {
        throw new Exception('Bewertung konnte nicht gespeichert werden');
    }
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true, 
        'message' => 'Bewertung erfolgreich gespeichert',
        'data' => [
            'film_id' => $filmId,
            'user_id' => $_SESSION['user_id'],
            'rating' => $rating,
            'affected_rows' => $stmt->rowCount()
        ]
    ]);
    
    error_log("Rating saved successfully - Film: $filmId, User: {$_SESSION['user_id']}, Rating: $rating");
    
} catch (PDOException $e) {
    error_log("Database error in save-rating.php: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Datenbankfehler beim Speichern der Bewertung',
        'debug' => [
            'code' => $e->getCode(),
            'sqlstate' => $e->errorInfo[0] ?? 'unknown'
        ]
    ]);
    
} catch (Exception $e) {
    error_log("General error in save-rating.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Fehler beim Speichern der Bewertung: ' . $e->getMessage()
    ]);
}
?>