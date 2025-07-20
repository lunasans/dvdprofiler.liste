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

/**
 * Einfache 2FA-Implementation ohne externe Libraries
 * Basiert auf TOTP (Time-based One-Time Password) RFC 6238
 */
class Simple2FA {
    
    /**
     * Generiert einen zufälligen Secret (Base32)
     */
    public static function generateSecret(int $length = 32): string {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= $chars[random_int(0, 31)];
        }
        return $secret;
    }
    
    /**
     * Base32 Dekodierung
     */
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
    
    /**
     * HOTP Algorithmus (RFC 4226)
     */
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
    
    /**
     * TOTP - Time-based OTP (RFC 6238)
     */
    public static function totp(string $secret, int $timeStep = 30): string {
        $timeCounter = (int)floor(time() / $timeStep);
        return self::hotp($secret, $timeCounter);
    }
    
    /**
     * QR-Code URL mit mehreren Fallback-Providern generieren
     */
    public static function getQRCodeUrl(string $secret, string $issuer, string $accountName): string {
        $qrText = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            urlencode($issuer),
            urlencode($accountName),
            $secret,
            urlencode($issuer)
        );
        
        // Mehrere QR-Code-Provider als Fallback
        $providers = [
            // QR-Server.com (zuverlässiger als Google Charts)
            'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qrText),
            
            // Google Charts (Fallback)
            'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($qrText),
            
            // QuickChart.io (weiterer Fallback)
            'https://quickchart.io/qr?text=' . urlencode($qrText) . '&size=200'
        ];
        
        // Ersten verfügbaren Provider zurückgeben
        return $providers[0]; // QR-Server ist normalerweise am zuverlässigsten
    }
    
    /**
     * Manuelle Eingabe-Informationen
     */
    public static function getManualEntryInfo(string $secret, string $issuer, string $accountName): array {
        return [
            'issuer' => $issuer,
            'account' => $accountName,
            'secret' => $secret,
            'type' => 'TOTP',
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30
        ];
    }
}

try {
    // Benutzer-Info laden
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception('Benutzer nicht gefunden');
    }
    
    // Neuen Secret generieren
    $secret = Simple2FA::generateSecret();
    $issuer = getSetting('site_title', 'DVD-Verwaltung');
    $accountName = $user['email'];
    
    // QR-Code URL generieren (mit mehreren Fallbacks)
    $qrCodeUrl = Simple2FA::getQRCodeUrl($secret, $issuer, $accountName);
    
    // Fallback: Einfacher ASCII QR-Code für manuelle Eingabe
    $qrText = sprintf(
        'otpauth://totp/%s:%s?secret=%s&issuer=%s',
        urlencode($issuer),
        urlencode($accountName),
        $secret,
        urlencode($issuer)
    );
    
    // Test ob QR-Code-URL funktioniert
    $qrCodeWorking = true;
    $headers = @get_headers($qrCodeUrl);
    if (!$headers || strpos($headers[0], '200') === false) {
        $qrCodeWorking = false;
    }
    
    // Manuelle Eingabe-Infos
    $manualEntry = Simple2FA::getManualEntryInfo($secret, $issuer, $accountName);
    
    // Backup-Codes generieren
    $backupCodes = [];
    for ($i = 0; $i < 10; $i++) {
        $backupCodes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    
    // Temporär in Session speichern (noch nicht in DB)
    $_SESSION['temp_2fa_secret'] = $secret;
    $_SESSION['temp_backup_codes'] = $backupCodes;
    
    // Test-Code generieren (für Debugging)
    $testCode = Simple2FA::totp($secret);
    
    echo json_encode([
        'success' => true,
        'qrcode' => $qrCodeUrl,
        'qrcode_working' => $qrCodeWorking,
        'qr_text' => $qrText, // Für manuelle Eingabe falls QR-Code nicht lädt
        'secret' => $secret,
        'backup_codes' => $backupCodes,
        'manual_entry' => $manualEntry,
        'debug' => [
            'current_test_code' => $testCode,
            'time' => time(),
            'issuer' => $issuer,
            'account' => $accountName,
            'implementation' => 'Simple2FA (PHP native)',
            'qr_providers' => [
                'qr-server' => 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($qrText),
                'google_charts' => 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($qrText),
                'quickchart' => 'https://quickchart.io/qr?text=' . urlencode($qrText) . '&size=200'
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log('Simple 2FA Generation Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'debug' => [
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'implementation' => 'Simple2FA (PHP native)'
        ]
    ]);
}
?>