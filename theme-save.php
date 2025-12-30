<?php
/**
 * theme-save.php - Speichert Theme-Änderung
 * Für eingeloggte User: DB
 * Für Gäste: Cookie
 */

// Session und Bootstrap laden
session_start();
require_once __DIR__ . '/includes/bootstrap.php';

// POST Check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// CSRF Token prüfen
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    die('CSRF validation failed');
}

// Theme validieren
$allowedThemes = [
    'default', 'dark', 'blue', 'green', 'red', 'purple',
    'christmas', 'newyear', 'easter', 'summer', 'halloween', 'valentine'
];

$theme = $_POST['theme'] ?? '';

if (!in_array($theme, $allowedThemes)) {
    http_response_code(400);
    die('Invalid theme');
}

// Check ob User eingeloggt ist
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($isAdmin) {
    // ADMIN: In DB speichern
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, `value`) 
            VALUES ('theme', :theme)
            ON DUPLICATE KEY UPDATE `value` = :theme
        ");
        
        $stmt->execute(['theme' => $theme]);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'theme' => $theme,
            'saved_to' => 'database',
            'message' => 'Theme in Datenbank gespeichert'
        ]);
        
    } catch (Exception $e) {
        error_log('Theme save error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Database error'
        ]);
    }
} else {
    // GAST: Nur in Cookie speichern (30 Tage)
    setcookie('guest_theme', $theme, [
        'expires' => time() + (30 * 24 * 60 * 60),
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'theme' => $theme,
        'saved_to' => 'cookie',
        'message' => 'Theme temporär gespeichert (Cookie)'
    ]);
}