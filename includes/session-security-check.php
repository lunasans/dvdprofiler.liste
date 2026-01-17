<?php
/**
 * Session Security Check
 * Prüft Session-Timeout und loggt User automatisch aus
 * 
 * Include diese Datei in /admin/index.php NACH bootstrap.php:
 * require_once __DIR__ . '/includes/session-security-check.php';
 */

// Nur ausführen wenn Session existiert und User eingeloggt ist
if (isset($_SESSION['user_id']) && !isset($_SESSION['require_2fa'])) {
    
    // Session-Timeout aus Settings laden
    $sessionTimeout = (int)getSetting('session_timeout', '3600'); // Standard: 1 Stunde (3600 Sekunden)
    $loginTime = (int)($_SESSION['login_time'] ?? time());
    $currentTime = time();
    $sessionAge = $currentTime - $loginTime;
    
    // Debug-Logging (optional, kann später entfernt werden)
    // error_log("Session-Check: Age={$sessionAge}s, Timeout={$sessionTimeout}s, Remaining=" . ($sessionTimeout - $sessionAge) . "s");
    
    // Wenn Session zu alt ist, ausloggen
    if ($sessionAge > $sessionTimeout) {
        
        // Session-Daten sichern für Logging (optional)
        $userId = $_SESSION['user_id'] ?? 0;
        $userEmail = $_SESSION['user_email'] ?? 'unknown';
        
        // Session beenden
        session_unset();
        session_destroy();
        
        // Neue Session starten für Timeout-Meldung
        session_start();
        $_SESSION['timeout_message'] = 'Ihre Sitzung ist aus Sicherheitsgründen abgelaufen. Bitte melden Sie sich erneut an.';
        $_SESSION['timeout_duration'] = round($sessionTimeout / 60); // In Minuten
        
        // Optional: Activity-Log schreiben
        try {
            if (isset($pdo) && $userId > 0) {
                $stmt = $pdo->prepare("
                    INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at)
                    VALUES (?, 'session_timeout', ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $userId,
                    json_encode([
                        'email' => $userEmail,
                        'session_age' => $sessionAge,
                        'timeout_setting' => $sessionTimeout
                    ]),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            }
        } catch (Exception $e) {
            // Activity logging ist optional
            error_log('Session timeout activity logging failed: ' . $e->getMessage());
        }
        
        // Redirect zum Login mit timeout Parameter
        $loginUrl = (defined('BASE_URL') && BASE_URL !== '')
            ? BASE_URL . '/admin/login.php?timeout=1'
            : 'login.php?timeout=1';
        
        header("Location: $loginUrl");
        exit;
    }
    
    // Session ist noch gültig - keep-alive
    // Hinweis: Du kannst login_time NICHT bei jedem Request aktualisieren,
    // sonst läuft die Session NIE ab (sliding session)
    // Nur aktualisieren wenn du "sliding session" willst (Session verlängert sich bei Aktivität)
    
    // OPTION A: Fixed Session (Standard) - Session läuft nach X Sekunden IMMER ab
    // → Keine Aktualisierung von login_time
    
    // OPTION B: Sliding Session - Session verlängert sich bei Aktivität
    // → Kommentiere die nächste Zeile ein:
    // $_SESSION['login_time'] = $currentTime;
    
} else {
    // User ist nicht eingeloggt oder benötigt 2FA
    // Keine Session-Timeout-Prüfung nötig
}