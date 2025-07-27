<?php
/**
 * DVD Profiler Liste - Session Management
 * Sicheres Session-Management mit erweiterten Features
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.8
 */

declare(strict_types=1);

namespace DVDProfiler\Core;

use Exception;

/**
 * Session-Management-Klasse
 * Verwaltet sichere Sessions mit Anti-Hijacking und Timeout-Features
 */
class Session
{
    private Settings $settings;
    private bool $initialized = false;
    private int $lifetime;
    private string $sessionName;
    
    /**
     * Constructor
     */
    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->lifetime = $this->settings->getInt('session_timeout', 7200);
        $this->sessionName = 'DVDPROFILER_' . substr(hash('sha256', BASE_PATH), 0, 8);
    }
    
    /**
     * Session initialisieren
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }
        
        // Session-Konfiguration setzen
        $this->configureSession();
        
        // Session starten
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Session-Sicherheit prüfen
        $this->validateSession();
        
        $this->initialized = true;
    }
    
    /**
     * Session-Konfiguration
     */
    private function configureSession(): void
    {
        // Sicherheits-Konfiguration
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', $this->isHttps() ? '1' : '0');
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        
        // Session-Lifetime
        ini_set('session.gc_maxlifetime', (string) $this->lifetime);
        ini_set('session.cookie_lifetime', '0'); // Browser-Session
        
        // Garbage Collection
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');
        
        // Session-Name setzen
        session_name($this->sessionName);
        
        // Save-Path sicher setzen falls möglich
        $savePath = BASE_PATH . '/cache/sessions';
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }
    }
    
    /**
     * Session-Validierung
     */
    private function validateSession(): void
    {
        // Erstmalige Initialisierung
        if (!isset($_SESSION['_initialized'])) {
            $this->initializeNewSession();
            return;
        }
        
        // Timeout-Check
        if ($this->isExpired()) {
            $this->destroy();
            $this->initializeNewSession();
            return;
        }
        
        // Hijacking-Schutz
        if (!$this->validateFingerprint()) {
            Security::logSecurityEvent('session_hijacking_attempt', [
                'session_id' => session_id(),
                'expected_fingerprint' => $_SESSION['_fingerprint'] ?? 'none',
                'current_fingerprint' => $this->generateFingerprint()
            ]);
            
            $this->destroy();
            $this->initializeNewSession();
            return;
        }
        
        // Session-Regeneration alle 30 Minuten
        if ($this->shouldRegenerate()) {
            $this->regenerate();
        }
        
        // Letzten Zugriff aktualisieren
        $_SESSION['_last_activity'] = time();
    }
    
    /**
     * Neue Session initialisieren
     */
    private function initializeNewSession(): void
    {
        session_regenerate_id(true);
        
        $_SESSION['_initialized'] = true;
        $_SESSION['_created'] = time();
        $_SESSION['_last_activity'] = time();
        $_SESSION['_last_regeneration'] = time();
        $_SESSION['_fingerprint'] = $this->generateFingerprint();
        $_SESSION['_csrf_token_default'] = bin2hex(random_bytes(32));
    }
    
    /**
     * Session-Fingerprint generieren
     */
    private function generateFingerprint(): string
    {
        $data = [
            Security::getUserAgent(),
            Security::getClientIP(),
            // Sprache hinzufügen falls verfügbar
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '',
        ];
        
        return hash('sha256', implode('|', $data));
    }
    
    /**
     * Fingerprint validieren
     */
    private function validateFingerprint(): bool
    {
        $current = $this->generateFingerprint();
        $stored = $_SESSION['_fingerprint'] ?? '';
        
        return hash_equals($stored, $current);
    }
    
    /**
     * Session abgelaufen prüfen
     */
    private function isExpired(): bool
    {
        $lastActivity = $_SESSION['_last_activity'] ?? 0;
        return (time() - $lastActivity) > $this->lifetime;
    }
    
    /**
     * Session-Regeneration erforderlich
     */
    private function shouldRegenerate(): bool
    {
        $lastRegeneration = $_SESSION['_last_regeneration'] ?? 0;
        return (time() - $lastRegeneration) > 1800; // 30 Minuten
    }
    
    /**
     * Session-ID regenerieren
     */
    public function regenerate(): bool
    {
        if (!$this->initialized) {
            return false;
        }
        
        $oldSessionId = session_id();
        
        if (session_regenerate_id(true)) {
            $_SESSION['_last_regeneration'] = time();
            
            error_log('[Session] Regenerated session ID: ' . $oldSessionId . ' -> ' . session_id());
            return true;
        }
        
        return false;
    }
    
    /**
     * Wert in Session setzen
     */
    public function set(string $key, mixed $value): void
    {
        if (!$this->initialized) {
            throw new Exception('Session not initialized');
        }
        
        $_SESSION[$key] = $value;
    }
    
    /**
     * Wert aus Session abrufen
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (!$this->initialized) {
            return $default;
        }
        
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Prüfen ob Schlüssel existiert
     */
    public function has(string $key): bool
    {
        return $this->initialized && isset($_SESSION[$key]);
    }
    
    /**
     * Wert aus Session entfernen
     */
    public function remove(string $key): void
    {
        if ($this->initialized && isset($_SESSION[$key])) {
            unset($_SESSION[$key]);
        }
    }
    
    /**
     * Flash-Message setzen (einmaliger Abruf)
     */
    public function setFlash(string $type, string $message): void
    {
        if (!isset($_SESSION['_flash'])) {
            $_SESSION['_flash'] = [];
        }
        
        if (!isset($_SESSION['_flash'][$type])) {
            $_SESSION['_flash'][$type] = [];
        }
        
        $_SESSION['_flash'][$type][] = $message;
    }
    
    /**
     * Flash-Messages abrufen und löschen
     */
    public function getFlash(string $type = null): array
    {
        if (!$this->initialized || !isset($_SESSION['_flash'])) {
            return [];
        }
        
        if ($type !== null) {
            $messages = $_SESSION['_flash'][$type] ?? [];
            unset($_SESSION['_flash'][$type]);
            return $messages;
        }
        
        // Alle Flash-Messages
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }
    
    /**
     * User-Login verwalten
     */
    public function login(int $userId, array $userData = []): void
    {
        // Session regenerieren bei Login
        $this->regenerate();
        
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_logged_in'] = true;
        $_SESSION['user_login_time'] = time();
        
        // Zusätzliche User-Daten speichern
        foreach ($userData as $key => $value) {
            $_SESSION["user_{$key}"] = $value;
        }
        
        Security::logSecurityEvent('user_login', [
            'user_id' => $userId,
            'session_id' => session_id()
        ]);
    }
    
    /**
     * User-Logout
     */
    public function logout(): void
    {
        if ($this->isLoggedIn()) {
            $userId = $_SESSION['user_id'] ?? null;
            
            Security::logSecurityEvent('user_logout', [
                'user_id' => $userId,
                'session_id' => session_id()
            ]);
        }
        
        $this->destroy();
    }
    
    /**
     * Eingeloggt prüfen
     */
    public function isLoggedIn(): bool
    {
        return $this->initialized && 
               isset($_SESSION['user_logged_in']) && 
               $_SESSION['user_logged_in'] === true &&
               isset($_SESSION['user_id']);
    }
    
    /**
     * User-ID abrufen
     */
    public function getUserId(): ?int
    {
        return $this->isLoggedIn() ? (int) $_SESSION['user_id'] : null;
    }
    
    /**
     * User-Daten abrufen
     */
    public function getUserData(string $key = null): mixed
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        if ($key !== null) {
            return $_SESSION["user_{$key}"] ?? null;
        }
        
        // Alle User-Daten sammeln
        $userData = [];
        foreach ($_SESSION as $sessionKey => $value) {
            if (str_starts_with($sessionKey, 'user_')) {
                $userData[substr($sessionKey, 5)] = $value;
            }
        }
        
        return $userData;
    }
    
    /**
     * Session komplett zerstören
     */
    public function destroy(): void
    {
        if ($this->initialized) {
            $_SESSION = [];
            
            // Session-Cookie löschen
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }
            
            session_destroy();
            $this->initialized = false;
        }
    }
    
    /**
     * Session-Statistiken abrufen
     */
    public function getStats(): array
    {
        if (!$this->initialized) {
            return [];
        }
        
        return [
            'session_id' => session_id(),
            'created' => $_SESSION['_created'] ?? null,
            'last_activity' => $_SESSION['_last_activity'] ?? null,
            'last_regeneration' => $_SESSION['_last_regeneration'] ?? null,
            'lifetime' => $this->lifetime,
            'expires_at' => ($_SESSION['_last_activity'] ?? 0) + $this->lifetime,
            'logged_in' => $this->isLoggedIn(),
            'user_id' => $this->getUserId(),
            'size' => strlen(serialize($_SESSION)),
            'variables_count' => count($_SESSION)
        ];
    }
    
    /**
     * Session-Daten für Admin-Panel
     */
    public function getDebugInfo(): array
    {
        if (!$this->initialized) {
            return ['error' => 'Session not initialized'];
        }
        
        $debug = $this->getStats();
        
        // Sensitive Daten für Debug ausblenden
        $sessionData = $_SESSION;
        unset($sessionData['_csrf_token_default']);
        foreach ($sessionData as $key => $value) {
            if (str_contains($key, 'password') || str_contains($key, 'token')) {
                $sessionData[$key] = '[HIDDEN]';
            }
        }
        
        $debug['session_data'] = $sessionData;
        $debug['cookie_params'] = session_get_cookie_params();
        
        return $debug;
    }
    
    /**
     * HTTPS-Verbindung prüfen
     */
    private function isHttps(): bool
    {
        return (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ||
            (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        );
    }
}