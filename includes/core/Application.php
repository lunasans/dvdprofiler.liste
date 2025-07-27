<?php
/**
 * DVD Profiler Liste - Core Application Class
 * Zentrale Anwendungsklasse nach Singleton-Pattern
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

namespace DVDProfiler\Core;

use Exception;
use PDOException;

/**
 * Haupt-Application-Klasse
 * Verwaltet alle Core-Komponenten als Singleton
 */
class Application
{
    private static ?self $instance = null;
    
    private ?Database $database = null;
    private ?Settings $settings = null;
    private ?Security $security = null;
    private ?Session $session = null;
    
    private bool $initialized = false;
    private array $config = [];
    
    /**
     * Singleton Instance abrufen
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Private Constructor für Singleton-Pattern
     */
    private function __construct()
    {
        // Verhindere direkte Instanziierung
    }
    
    /**
     * Application initialisieren
     * 
     * @throws Exception Bei kritischen Fehlern
     */
    public function initialize(): void
    {
        if ($this->initialized) {
            return; // Bereits initialisiert
        }
        
        try {
            // 1. Konfiguration laden
            $this->loadConfiguration();
            
            // 2. Security-Layer initialisieren
            $this->initializeSecurity();
            
            // 3. Datenbank-Verbindung herstellen
            $this->initializeDatabase();
            
            // 4. Settings-System laden
            $this->initializeSettings();
            
            // 5. Session-Management starten
            $this->initializeSession();
            
            // 6. Error-Handler registrieren
            $this->registerErrorHandlers();
            
            $this->initialized = true;
            
            $this->logInfo('Application initialized successfully');
            
        } catch (Exception $e) {
            $this->logError('Application initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Konfiguration aus config.php laden
     */
    private function loadConfiguration(): void
    {
        $configFile = BASE_PATH . '/config/config.php';
        
        if (!file_exists($configFile)) {
            throw new Exception('Configuration file not found. Please run installation first.');
        }
        
        $this->config = require $configFile;
        
        if (!is_array($this->config)) {
            throw new Exception('Invalid configuration format');
        }
        
        // Validiere kritische Konfigurationswerte
        $required = ['db_host', 'db_name', 'db_user', 'db_pass'];
        foreach ($required as $key) {
            if (!isset($this->config[$key])) {
                throw new Exception("Missing required configuration: {$key}");
            }
        }
    }
    
    /**
     * Security-Komponente initialisieren
     */
    private function initializeSecurity(): void
    {
        $this->security = new Security();
        $this->security->setSecurityHeaders();
    }
    
    /**
     * Datenbank-Verbindung initialisieren
     */
    private function initializeDatabase(): void
    {
        try {
            $this->database = new Database($this->config);
            
            // Test-Query für Verbindungsvalidierung
            $this->database->query('SELECT 1')->fetchColumn();
            
        } catch (PDOException $e) {
            $this->logError('Database connection failed: ' . $e->getMessage());
            throw new Exception('Database connection failed. Please check configuration.');
        }
    }
    
    /**
     * Settings-System initialisieren
     */
    private function initializeSettings(): void
    {
        try {
            $this->settings = new Settings($this->database);
            
            // BASE_URL für Legacy-Support definieren
            if (!defined('BASE_URL')) {
                define('BASE_URL', $this->settings->get('base_url', ''));
            }
            
        } catch (Exception $e) {
            $this->logError('Settings initialization failed: ' . $e->getMessage());
            
            // Fallback für Installation oder Notfälle
            if (!defined('BASE_URL')) {
                define('BASE_URL', '');
            }
            throw $e;
        }
    }
    
    /**
     * Session-Management initialisieren
     */
    private function initializeSession(): void
    {
        $this->session = new Session($this->settings);
        $this->session->initialize();
    }
    
    /**
     * Error-Handler und Shutdown-Functions registrieren
     */
    private function registerErrorHandlers(): void
    {
        // Performance-Monitoring für Development-Umgebung
        if ($this->settings->get('environment', 'production') === 'development') {
            register_shutdown_function(function() {
                $memory = memory_get_peak_usage(true);
                $time = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
                $this->logInfo(sprintf('Performance: %.3fs, %s memory', $time, Utils::formatBytes($memory)));
            });
        }
        
        // Custom Error Handler für bessere Kontrolle
        set_error_handler([$this, 'handleError'], E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
    }
    
    /**
     * Custom Error Handler
     */
    public function handleError(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        // Nur in Development-Umgebung detaillierte Errors loggen
        if ($this->settings->get('environment', 'production') === 'development') {
            $this->logError("PHP Error [{$errno}]: {$errstr} in {$errfile}:{$errline}");
        }
        
        return false; // Lass PHP's internen Handler auch ausführen
    }
    
    /**
     * Database-Instance abrufen
     */
    public function getDatabase(): Database
    {
        if ($this->database === null) {
            throw new Exception('Database not initialized. Call initialize() first.');
        }
        return $this->database;
    }
    
    /**
     * Settings-Instance abrufen
     */
    public function getSettings(): Settings
    {
        if ($this->settings === null) {
            throw new Exception('Settings not initialized. Call initialize() first.');
        }
        return $this->settings;
    }
    
    /**
     * Security-Instance abrufen
     */
    public function getSecurity(): Security
    {
        if ($this->security === null) {
            throw new Exception('Security not initialized. Call initialize() first.');
        }
        return $this->security;
    }
    
    /**
     * Session-Instance abrufen
     */
    public function getSession(): Session
    {
        if ($this->session === null) {
            throw new Exception('Session not initialized. Call initialize() first.');
        }
        return $this->session;
    }
    
    /**
     * Konfigurationswert abrufen
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
    
    /**
     * Prüft ob Application initialisiert ist
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }
    
    /**
     * System-Health Check durchführen
     */
    public function getSystemHealth(): array
    {
        $health = [
            'overall' => true,
            'database' => false,
            'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
            'extensions' => [],
            'permissions' => [],
            'disk_space' => 0,
            'memory_usage' => 0,
            'last_check' => date('Y-m-d H:i:s')
        ];
        
        // Database Check
        try {
            $this->database?->query('SELECT 1');
            $health['database'] = true;
        } catch (Exception $e) {
            $health['overall'] = false;
            $this->logError('Health check - Database failed: ' . $e->getMessage());
        }
        
        // PHP Extensions Check
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'curl'];
        foreach ($requiredExtensions as $ext) {
            $loaded = extension_loaded($ext);
            $health['extensions'][$ext] = $loaded;
            if (!$loaded) {
                $health['overall'] = false;
            }
        }
        
        // File Permissions Check
        $paths = [
            'config' => BASE_PATH . '/config',
            'uploads' => BASE_PATH . '/uploads',
            'cache' => BASE_PATH . '/cache',
            'cover' => BASE_PATH . '/cover'
        ];
        
        foreach ($paths as $name => $path) {
            $writable = is_dir($path) && is_writable($path);
            $health['permissions'][$name] = $writable;
            if (!$writable && $name !== 'uploads') { // uploads optional
                $health['overall'] = false;
            }
        }
        
        // System Resources
        $health['disk_space'] = disk_free_space(BASE_PATH) ?: 0;
        $health['memory_usage'] = memory_get_usage(true);
        
        return $health;
    }
    
    /**
     * Rate-Limiting prüfen
     */
    public function checkRateLimit(string $identifier, int $maxRequests = 60, int $timeWindow = 3600): bool
    {
        $key = 'rate_limit_' . hash('sha256', $identifier);
        $current = $this->settings->get($key, '0|0');
        [$count, $timestamp] = explode('|', $current . '|0');
        
        $count = (int)$count;
        $timestamp = (int)$timestamp;
        $now = time();
        
        // Reset counter if time window expired
        if ($now - $timestamp > $timeWindow) {
            $count = 0;
            $timestamp = $now;
        }
        
        $count++;
        $this->settings->set($key, $count . '|' . $timestamp);
        
        return $count <= $maxRequests;
    }
    
    /**
     * Logging-Helper
     */
    private function logInfo(string $message): void
    {
        error_log("[DVDProfiler:INFO] {$message}");
    }
    
    /**
     * Error-Logging-Helper  
     */
    private function logError(string $message): void
    {
        error_log("[DVDProfiler:ERROR] {$message}");
    }
    
    /**
     * Verhindere Klonen
     */
    private function __clone() {}
    
    /**
     * Verhindere Unserialisierung
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}