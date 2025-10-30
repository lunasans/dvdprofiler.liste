<?php
// api/save-rating.php - DEBUG VERSION
declare(strict_types=1);

// Debug: Log alles
error_log("=== save-rating.php DEBUG START ===");
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Raw Input: " . file_get_contents('php://input'));

session_start();

// Debug: Session info
error_log("Session ID: " . session_id());
error_log("User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));

try {
    require_once __DIR__ . '/../includes/bootstrap.php';
    error_log("Bootstrap loaded successfully");
} catch (Exception $e) {
    error_log("Bootstrap error: " . $e->getMessage());
    die(json_encode(['error' => 'Bootstrap fehler: ' . $e->getMessage()]));
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    error_log("ERROR: User not logged in");
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON decode error: " . json_last_error_msg());
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$filmId = (int)($input['film_id'] ?? 0);
$rating = (float)($input['rating'] ?? 0);

error_log("Parsed data - Film ID: $filmId, Rating: $rating");

if ($filmId <= 0 || $rating < 1 || $rating > 5) {
    error_log("ERROR: Invalid parameters - filmId: $filmId, rating: $rating");
    http_response_code(400);
    echo json_encode(['error' => 'Ungültige Parameter']);
    exit;
}

try {
    // Test Datenbankverbindung
    $pdo->query("SELECT 1");
    error_log("Database connection OK");
    
    // Prüfen ob user_ratings Tabelle existiert
    $tableExists = $pdo->query("SHOW TABLES LIKE 'user_ratings'")->rowCount() > 0;
    error_log("user_ratings table exists: " . ($tableExists ? 'YES' : 'NO'));
    
    if (!$tableExists) {
        error_log("Creating user_ratings table...");
        $pdo->exec("
            CREATE TABLE user_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                film_id BIGINT NOT NULL,
                user_id INT NOT NULL,
                rating DECIMAL(2,1) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_film (user_id, film_id)
            )
        ");
        error_log("user_ratings table created successfully");
    }
    
    // Rating einfügen/updaten
    $stmt = $pdo->prepare("
        INSERT INTO user_ratings (film_id, user_id, rating) 
        VALUES (?, ?, ?) 
        ON DUPLICATE KEY UPDATE rating = VALUES(rating), updated_at = NOW()
    ");
    
    $result = $stmt->execute([$filmId, $_SESSION['user_id'], $rating]);
    error_log("SQL executed successfully. Result: " . ($result ? 'TRUE' : 'FALSE'));
    error_log("Affected rows: " . $stmt->rowCount());
    
    echo json_encode([
        'success' => true, 
        'message' => 'Bewertung gespeichert',
        'debug' => [
            'film_id' => $filmId,
            'user_id' => $_SESSION['user_id'],
            'rating' => $rating,
            'affected_rows' => $stmt->rowCount()
        ]
    ]);
    
    error_log("SUCCESS: Rating saved successfully");
    
} catch (PDOException $e) {
    error_log("DATABASE ERROR: " . $e->getMessage());
    error_log("Error Code: " . $e->getCode());
    error_log("SQL State: " . $e->errorInfo[0] ?? 'unknown');
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Datenbankfehler: ' . $e->getMessage(),
        'debug' => [
            'code' => $e->getCode(),
            'sqlstate' => $e->errorInfo[0] ?? 'unknown'
        ]
    ]);
} catch (Exception $e) {
    error_log("GENERAL ERROR: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Allgemeiner Fehler: ' . $e->getMessage()]);
}

error_log("=== save-rating.php DEBUG END ===");
?>