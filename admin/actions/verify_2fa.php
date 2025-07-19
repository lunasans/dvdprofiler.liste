<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';

// Session & Auth prüfen
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
    $userId = $_SESSION['user_id'];
    $token = trim($_POST['token'] ?? '');
    $secret = $_SESSION['temp_2fa_secret'] ?? '';
    $backupCodes = $_SESSION['temp_backup_codes'] ?? [];
    
    if (empty($token)) {
        throw new Exception('Bestätigungscode erforderlich');
    }
    
    if (empty($secret)) {
        throw new Exception('Kein 2FA-Setup in Bearbeitung');
    }
    
    if (!preg_match('/^[0-9]{6}$/', $token)) {
        throw new Exception('Ungültiges Code-Format');
    }
    
    // 2FA-Library initialisieren
    $qrProvider = class_exists('\Endroid\QrCode\QrCode') 
        ? new \RobThree\Auth\Providers\Qr\EndroidQrCodeProvider()
        : new \RobThree\Auth\Providers\Qr\QRServerProvider();
        
    $tfa = new \RobThree\Auth\TwoFactorAuth(
        $qrProvider,
        getSetting('site_title', 'DVD-Verwaltung'),
        6, 30, \RobThree\Auth\Algorithm::SHA1
    );
    
    // Token verifizieren
    if ($tfa->verifyCode($secret, $token)) {
        // Token ist gültig - 2FA in Datenbank aktivieren
        $pdo->beginTransaction();
        
        try {
            // 2FA für Benutzer aktivieren
            $stmt = $pdo->prepare("
                UPDATE users 
                SET twofa_secret = ?, 
                    twofa_enabled = 1,
                    twofa_activated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$secret, $userId]);
            
            // Backup-Codes speichern
            $stmt = $pdo->prepare("
                INSERT INTO user_backup_codes (user_id, code, created_at) 
                VALUES (?, ?, NOW())
            ");
            
            foreach ($backupCodes as $code) {
                $hashedCode = password_hash($code, PASSWORD_DEFAULT);
                $stmt->execute([$userId, $hashedCode]);
            }
            
            $pdo->commit();
            
            // Session-Daten löschen
            unset($_SESSION['temp_2fa_secret']);
            unset($_SESSION['temp_backup_codes']);
            
            echo json_encode([
                'success' => true,
                'message' => '2FA erfolgreich aktiviert!',
                'backup_codes' => $backupCodes
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } else {
        // Token ungültig
        echo json_encode([
            'success' => false,
            'message' => 'Ungültiger Bestätigungscode. Bitte versuchen Sie es erneut.'
        ]);
    }
    
} catch (Exception $e) {
    error_log('2FA Verification Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Verifikation fehlgeschlagen: ' . $e->getMessage()
    ]);
}
?>