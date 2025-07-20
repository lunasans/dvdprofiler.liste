<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';

// Security checks
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt']);
    exit;
}

try {
    $userId = (int)($_POST['user_id'] ?? 0);
    
    if ($userId <= 0) {
        throw new Exception('Ungültige Benutzer-ID');
    }
    
    // Prüfen ob Benutzer existiert und 2FA aktiviert hat
    $stmt = $pdo->prepare("SELECT email, twofa_enabled FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Benutzer nicht gefunden');
    }
    
    if (!$user['twofa_enabled']) {
        throw new Exception('2FA ist für diesen Benutzer nicht aktiviert');
    }
    
    // Transaktion starten
    $pdo->beginTransaction();
    
    try {
        // 2FA für Benutzer deaktivieren
        $stmt = $pdo->prepare("
            UPDATE users 
            SET twofa_secret = NULL, 
                twofa_enabled = 0,
                twofa_activated_at = NULL
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        
        // Alle Backup-Codes löschen
        $stmt = $pdo->prepare("DELETE FROM user_backup_codes WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Activity log
        try {
            $stmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at)
                VALUES (?, '2fa_disabled', ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                json_encode(['target_user_id' => $userId, 'email' => $user['email']]),
                $_SERVER['REMOTE_ADDR'] ?? '',
                $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            // Activity logging is optional
            error_log('Activity logging failed: ' . $e->getMessage());
        }
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => '2FA erfolgreich deaktiviert'
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    error_log('2FA Disable Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => '2FA-Deaktivierung fehlgeschlagen: ' . $e->getMessage()
    ]);
}
?>