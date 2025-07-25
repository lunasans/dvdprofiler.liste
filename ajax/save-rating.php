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

<?php
// ajax/toggle-wishlist.php
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
        CREATE TABLE IF NOT EXISTS user_wishlist (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            film_id BIGINT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_film_wish (user_id, film_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE
        )
    ");
    
    // Prüfen ob bereits auf Wunschliste
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM user_wishlist WHERE user_id = ? AND film_id = ?");
    $checkStmt->execute([$_SESSION['user_id'], $filmId]);
    $exists = $checkStmt->fetchColumn() > 0;
    
    if ($exists) {
        // Von Wunschliste entfernen
        $deleteStmt = $pdo->prepare("DELETE FROM user_wishlist WHERE user_id = ? AND film_id = ?");
        $deleteStmt->execute([$_SESSION['user_id'], $filmId]);
        echo json_encode(['added' => false, 'message' => 'Von Wunschliste entfernt']);
    } else {
        // Zu Wunschliste hinzufügen
        $insertStmt = $pdo->prepare("INSERT INTO user_wishlist (user_id, film_id) VALUES (?, ?)");
        $insertStmt->execute([$_SESSION['user_id'], $filmId]);
        echo json_encode(['added' => true, 'message' => 'Zur Wunschliste hinzugefügt']);
    }
} catch (PDOException $e) {
    error_log("Wishlist toggle error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Fehler beim Aktualisieren der Wunschliste']);
}
?>

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