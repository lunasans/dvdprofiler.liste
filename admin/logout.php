<?php
declare(strict_types=1);

/**
 * DVD Profiler Liste - Admin Logout
 * 
 * Sicheres Logout mit Session-Bereinigung und Weiterleitung
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.3.6
 */

// Bootstrap lädt $pdo, BASE_URL usw. und startet bereits die Session
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/version.php';

// Logout-Protokollierung (nur wenn eingeloggt)
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown';
    
    // Log the logout event
    error_log("User logout: {$username} (ID: {$userId}) from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    
    // Optional: Logout in Datenbank protokollieren
    try {
        if (isset($pdo)) {
            $stmt = $pdo->prepare("UPDATE users SET last_logout = NOW() WHERE id = ?");
            $stmt->execute([$userId]);
        }
    } catch (Exception $e) {
        error_log('Logout database update failed: ' . $e->getMessage());
    }
}

// Session komplett zerstören (Session ist bereits gestartet durch bootstrap.php)
$_SESSION = [];

// Session-Cookie löschen
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Session zerstören
session_destroy();

// Security: Cache-Control Headers setzen
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Redirect zur Login-Page
$loginUrl = (defined('BASE_URL') && BASE_URL !== '') 
    ? BASE_URL . '/admin/login.php'
    : 'login.php';

// Optional: Logout-Message als URL-Parameter anhängen
$loginUrl .= '?logout=1';

header("Location: {$loginUrl}");
exit;