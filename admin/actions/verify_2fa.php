<?php
declare(strict_types=1);

header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/bootstrap.php';

// Session starten falls nicht bereits gestartet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

// Simple2FA Klasse (mit korrektem int-Casting)
class Simple2FA {
    public static function base32Decode(string $secret): string {
        $secret = strtoupper($secret);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $charMap = array_flip(str_split($chars));
        
        $bits = '';
        for ($i = 0; $i < strlen($secret); $i++) {
            if (isset($charMap[$secret[$i]])) {
                $bits .= str_pad(decbin($charMap[$secret[$i]]), 5, '0', STR_PAD_LEFT);
            }
        }
        
        $result = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            if (strlen($bits) - $i >= 8) {
                $result .= chr(bindec(substr($bits, $i, 8)));
            }
        }
        
        return $result;
    }
    
    public static function hotp(string $secret, int $counter): string {
        $secretBinary = self::base32Decode($secret);
        $counterBinary = pack('N*', 0) . pack('N*', $counter);
        
        $hash = hash_hmac('sha1', $counterBinary, $secretBinary, true);
        $offset = ord($hash[19]) & 0xf;
        
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }
    
    public static function verifyTotp(string $secret, string $code, int $timeStep = 30, int $tolerance = 1): bool {
        $timeCounter = (int)floor(time() / $timeStep); // WICHTIG: Expliziter Cast zu int
        
        // Prüfe aktuellen Zeitschritt und ±1 für Zeitabweichung
        for ($i = -$tolerance; $i <= $tolerance; $i++) {
            if (self::hotp($secret, $timeCounter + $i) === $code) {
                return true;
            }
        }
        return false;
    }
}

try {
    $userId = $_SESSION['user_id'];
    $token = trim($_POST['token'] ?? '');
    $secret = $_SESSION['temp_2fa_secret'] ?? '';
    $backupCodes = $_SESSION['temp_backup_codes'] ?? [];
    
    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'Bestätigungscode erforderlich']);
        exit;
    }
    
    if (empty($secret)) {
        echo json_encode(['success' => false, 'message' => 'Kein 2FA-Setup in Bearbeitung', 'session_keys' => array_keys($_SESSION)]);
        exit;
    }
    
    if (!preg_match('/^[0-9]{6}$/', $token)) {
        echo json_encode(['success' => false, 'message' => 'Ungültiges Code-Format (6 Ziffern erwartet)']);
        exit;
    }
    
    // Token verifizieren mit korrektem int-Casting
    $currentTime = time();
    $timeCounter = (int)floor($currentTime / 30); // Expliziter Cast zu int
    $expectedCode = Simple2FA::hotp($secret, $timeCounter);
    
    if (Simple2FA::verifyTotp($secret, $token)) {
        // Token ist gültig - 2FA in Datenbank aktivieren
        $pdo->beginTransaction();
        
        try {
            // Prüfen ob user_backup_codes Tabelle existiert
            $stmt = $pdo->query("SHOW TABLES LIKE 'user_backup_codes'");
            $tableExists = $stmt->rowCount() > 0;
            
            if (!$tableExists) {
                // Tabelle erstellen falls nicht vorhanden
                $pdo->exec("
                    CREATE TABLE user_backup_codes (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        user_id INT NOT NULL,
                        code VARCHAR(255) NOT NULL,
                        used_at TIMESTAMP NULL DEFAULT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        INDEX idx_user_id (user_id)
                    )
                ");
            }
            
            // 2FA für Benutzer aktivieren
            $stmt = $pdo->prepare("
                UPDATE users 
                SET twofa_secret = ?, 
                    twofa_enabled = 1,
                    twofa_activated_at = NOW()
                WHERE id = ?
            ");
            $result = $stmt->execute([$secret, $userId]);
            
            if (!$result) {
                throw new Exception('Fehler beim Aktualisieren des Benutzers');
            }
            
            // Backup-Codes speichern
            $stmt = $pdo->prepare("
                INSERT INTO user_backup_codes (user_id, code, created_at) 
                VALUES (?, ?, NOW())
            ");
            
            foreach ($backupCodes as $code) {
                $hashedCode = password_hash($code, PASSWORD_DEFAULT);
                $stmt->execute([$userId, $hashedCode]);
            }
            
            // Activity log (optional)
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at)
                    VALUES (?, '2fa_activated', ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $userId,
                    json_encode(['method' => 'Simple2FA', 'backup_codes_generated' => count($backupCodes)]),
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                // Activity logging ist optional
                error_log('Activity logging failed: ' . $e->getMessage());
            }
            
            $pdo->commit();
            
            // Session-Daten löschen
            unset($_SESSION['temp_2fa_secret']);
            unset($_SESSION['temp_backup_codes']);
            
            echo json_encode([
                'success' => true,
                'message' => '2FA erfolgreich aktiviert!',
                'backup_codes' => $backupCodes,
                'implementation' => 'Simple2FA (PHP native)',
                'debug' => [
                    'user_id' => $userId,
                    'codes_saved' => count($backupCodes),
                    'timestamp' => $currentTime,
                    'time_counter' => $timeCounter,
                    'expected_code' => $expectedCode
                ]
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } else {
        // Token ungültig - Debug-Informationen hinzufügen
        echo json_encode([
            'success' => false,
            'message' => 'Ungültiger Bestätigungscode. Bitte versuchen Sie es erneut.',
            'debug' => [
                'submitted_code' => $token,
                'expected_code' => $expectedCode,
                'time_counter' => $timeCounter,
                'current_time' => $currentTime,
                'secret_length' => strlen($secret),
                'tolerance_check' => [
                    'minus_1' => Simple2FA::hotp($secret, $timeCounter - 1),
                    'current' => Simple2FA::hotp($secret, $timeCounter),
                    'plus_1' => Simple2FA::hotp($secret, $timeCounter + 1)
                ]
            ]
        ]);
    }
    
} catch (Exception $e) {
    error_log('Simple 2FA Verification Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Verifikation fehlgeschlagen: ' . $e->getMessage(),
        'debug' => [
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'error_message' => $e->getMessage()
        ]
    ]);
}
?>