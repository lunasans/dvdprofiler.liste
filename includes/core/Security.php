<?php
/**
 * DVD Profiler Liste - Security Management
 * Zentrales Security-Management für alle sicherheitsrelevanten Funktionen
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

namespace DVDProfiler\Core;

use Exception;

/**
 * Security-Management-Klasse
 * Verwaltet alle sicherheitsrelevanten Aspekte der Anwendung
 */
class Security
{
    /** @var array<string, string> CSRF-Token Cache */
    private static array $csrfTokens = [];
    
    /** @var array<string, array> Rate-Limiting Cache */
    private static array $rateLimits = [];
    
    /**
     * Security-Header setzen
     */
    public function setSecurityHeaders(): void
    {
        // Nur setzen wenn noch nicht gesendet
        if (headers_sent()) {
            return;
        }
        
        // XSS-Schutz
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        
        // Content Security Policy - Erweitert für DVD Profiler
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com",
            "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net fonts.googleapis.com",
            "img-src 'self' data: * blob:",
            "font-src 'self' cdn.jsdelivr.net fonts.gstatic.com",
            "frame-src 'self' www.youtube.com",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'"
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
        
        // Referrer-Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Permissions-Policy (Feature-Policy Nachfolger)
        $permissions = [
            'geolocation=()',
            'microphone=()',
            'camera=()',
            'payment=()',
            'usb=()',
            'magnetometer=()'
        ];
        header('Permissions-Policy: ' . implode(', ', $permissions));
        
        // HSTS für HTTPS-Verbindungen
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
        
        // Zusätzliche Security-Header
        header('X-Permitted-Cross-Domain-Policies: none');
        header('Cross-Origin-Embedder-Policy: require-corp');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
    }
    
    /**
     * CSRF-Token generieren
     */
    public static function generateCSRFToken(string $context = 'default'): string
    {
        if (!isset($_SESSION)) {
            throw new Exception('Session not started for CSRF token generation');
        }
        
        $sessionKey = "csrf_token_{$context}";
        
        if (!isset($_SESSION[$sessionKey]) || strlen($_SESSION[$sessionKey]) < 32) {
            $_SESSION[$sessionKey] = bin2hex(random_bytes(32));
        }
        
        // Cache für Performance
        self::$csrfTokens[$context] = $_SESSION[$sessionKey];
        
        return $_SESSION[$sessionKey];
    }
    
    /**
     * CSRF-Token validieren
     */
    public static function validateCSRFToken(string $token, string $context = 'default'): bool
    {
        if (!isset($_SESSION)) {
            return false;
        }
        
        $sessionKey = "csrf_token_{$context}";
        
        if (!isset($_SESSION[$sessionKey])) {
            return false;
        }
        
        $isValid = hash_equals($_SESSION[$sessionKey], $token);
        
        // Fehlgeschlagene Validierung loggen
        if (!$isValid) {
            error_log('[Security] CSRF token validation failed for context: ' . $context);
        }
        
        return $isValid;
    }
    
    /**
     * Input-Sanitization
     */
    public static function sanitizeInput(string $input, int $maxLength = 255, bool $allowHtml = false): string
    {
        // Whitespace trimmen
        $input = trim($input);
        
        // HTML entfernen falls nicht erlaubt
        if (!$allowHtml) {
            $input = strip_tags($input);
        }
        
        // HTML-Entities kodieren
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Länge begrenzen
        if (strlen($input) > $maxLength) {
            $input = substr($input, 0, $maxLength);
        }
        
        return $input;
    }
    
    /**
     * SQL-Injection Schutz für LIKE-Queries
     */
    public static function escapeLikeValue(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }
    
    /**
     * Passwort-Hash erstellen
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    
    /**
     * Passwort verifizieren
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
    
    /**
     * Passwort-Stärke prüfen
     */
    public static function validatePasswordStrength(string $password, int $minLength = 8): array
    {
        $result = [
            'valid' => true,
            'score' => 0,
            'errors' => [],
            'suggestions' => []
        ];
        
        // Länge prüfen
        if (strlen($password) < $minLength) {
            $result['valid'] = false;
            $result['errors'][] = "Passwort muss mindestens {$minLength} Zeichen lang sein";
        } else {
            $result['score'] += 1;
        }
        
        // Großbuchstaben
        if (!preg_match('/[A-Z]/', $password)) {
            $result['valid'] = false;
            $result['errors'][] = 'Passwort muss mindestens einen Großbuchstaben enthalten';
        } else {
            $result['score'] += 1;
        }
        
        // Kleinbuchstaben
        if (!preg_match('/[a-z]/', $password)) {
            $result['valid'] = false;
            $result['errors'][] = 'Passwort muss mindestens einen Kleinbuchstaben enthalten';
        } else {
            $result['score'] += 1;
        }
        
        // Zahlen
        if (!preg_match('/\d/', $password)) {
            $result['valid'] = false;
            $result['errors'][] = 'Passwort muss mindestens eine Zahl enthalten';
        } else {
            $result['score'] += 1;
        }
        
        // Sonderzeichen
        if (!preg_match('/[^a-zA-Z\d]/', $password)) {
            $result['suggestions'][] = 'Verwenden Sie Sonderzeichen für höhere Sicherheit';
        } else {
            $result['score'] += 1;
        }
        
        // Länge > 12
        if (strlen($password) >= 12) {
            $result['score'] += 1;
        }
        
        // Häufige Passwörter prüfen
        $commonPasswords = [
            'password', '123456', '123456789', 'qwerty', 'abc123',
            'password123', 'admin', 'letmein', 'welcome', 'monkey'
        ];
        
        if (in_array(strtolower($password), $commonPasswords)) {
            $result['valid'] = false;
            $result['errors'][] = 'Dieses Passwort ist zu häufig verwendet';
        }
        
        return $result;
    }
    
    /**
     * Rate-Limiting prüfen
     */
    public static function checkRateLimit(
        string $identifier, 
        int $maxRequests = 60, 
        int $timeWindow = 3600
    ): bool {
        $now = time();
        $key = hash('sha256', $identifier);
        
        // Cache-basiertes Rate-Limiting
        if (!isset(self::$rateLimits[$key])) {
            self::$rateLimits[$key] = ['count' => 0, 'reset_time' => $now + $timeWindow];
        }
        
        $limit = &self::$rateLimits[$key];
        
        // Zurücksetzen wenn Zeit abgelaufen
        if ($now >= $limit['reset_time']) {
            $limit['count'] = 0;
            $limit['reset_time'] = $now + $timeWindow;
        }
        
        $limit['count']++;
        
        if ($limit['count'] > $maxRequests) {
            error_log("[Security] Rate limit exceeded for: {$identifier}");
            return false;
        }
        
        return true;
    }
    
    /**
     * IP-Adresse sicher abrufen
     */
    public static function getClientIP(): string
    {
        // Prioritätsliste der Header
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load Balancer/Proxy
            'HTTP_X_REAL_IP',            // Nginx
            'HTTP_CLIENT_IP',            // Proxy
            'REMOTE_ADDR'                // Standard
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                
                // Falls mehrere IPs (Proxy-Chain), erste nehmen
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // IP validieren
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * User-Agent sicher abrufen
     */
    public static function getUserAgent(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Sanitize User-Agent (max 255 Zeichen)
        return self::sanitizeInput($userAgent, 255);
    }
    
    /**
     * Secure Random String generieren
     */
    public static function generateRandomString(int $length = 32, string $charset = null): string
    {
        if ($charset === null) {
            $charset = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        }
        
        $result = '';
        $charsetLength = strlen($charset);
        
        for ($i = 0; $i < $length; $i++) {
            $result .= $charset[random_int(0, $charsetLength - 1)];
        }
        
        return $result;
    }
    
    /**
     * Sichere Session-Konfiguration
     */
    public static function configureSecureSession(): void
    {
        // Session-Konfiguration
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', self::isHttps() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        
        // Session-Name randomisieren
        session_name('DVDPROFILER_' . substr(hash('sha256', __DIR__), 0, 8));
        
        // Garbage Collection
        ini_set('session.gc_maxlifetime', '7200'); // 2 Stunden
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
    }
    
    /**
     * File-Upload Validierung
     */
    public static function validateFileUpload(array $file, array $allowedTypes = [], int $maxSize = 0): array
    {
        $result = ['valid' => false, 'errors' => []];
        
        // Upload-Fehler prüfen
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['errors'][] = 'Upload-Fehler: ' . $file['error'];
            return $result;
        }
        
        // Dateigröße prüfen
        if ($maxSize > 0 && $file['size'] > $maxSize) {
            $result['errors'][] = 'Datei zu groß (max. ' . Utils::formatBytes($maxSize) . ')';
            return $result;
        }
        
        // MIME-Type prüfen
        if (!empty($allowedTypes)) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mimeType, $allowedTypes)) {
                $result['errors'][] = 'Dateityp nicht erlaubt: ' . $mimeType;
                return $result;
            }
        }
        
        // Dateiname sanitizen
        $file['safe_name'] = self::sanitizeFilename($file['name']);
        
        $result['valid'] = true;
        $result['file'] = $file;
        
        return $result;
    }
    
    /**
     * Dateiname sanitizen
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Gefährliche Zeichen entfernen
        $filename = preg_replace('/[^a-zA-Z0-9.-_]/', '_', $filename);
        
        // Mehrfache Punkte entfernen
        $filename = preg_replace('/\.+/', '.', $filename);
        
        // Führende/Abschließende Punkte/Unterstriche entfernen
        $filename = trim($filename, '._');
        
        // Länge begrenzen
        if (strlen($filename) > 100) {
            $pathinfo = pathinfo($filename);
            $extension = $pathinfo['extension'] ?? '';
            $basename = substr($pathinfo['filename'], 0, 100 - strlen($extension) - 1);
            $filename = $basename . '.' . $extension;
        }
        
        return $filename;
    }
    
    /**
     * HTTPS-Verbindung prüfen (statisch für flexiblen Aufruf)
     */
    private static function isHttps(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        );
    }
    
    /**
     * Security-Log erstellen
     */
    public static function logSecurityEvent(string $event, array $context = []): void
    {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => self::getClientIP(),
            'user_agent' => self::getUserAgent(),
            'context' => $context
        ];
        
        error_log('[SECURITY] ' . json_encode($logData, JSON_UNESCAPED_UNICODE));
    }
}