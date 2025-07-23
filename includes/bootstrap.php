<?php
declare(strict_types=1);

function setSecurityHeaders(): void {
    // Verhindert XSS Angriffe
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy - erweitert für bessere Sicherheit
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; img-src 'self' data: *; font-src 'self' cdn.jsdelivr.net; frame-src 'self' www.youtube.com");
    
    // Verhindert MIME-Type sniffing
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Zusätzliche Sicherheitsheader
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

// Security Headers setzen
setSecurityHeaders();

// Weiterleitung bei fehlender Installation
$lockFile = dirname(__DIR__) . '/install/install.lock';
$installScript = dirname($_SERVER['SCRIPT_NAME']) . '/install/index.php';

if (!file_exists($lockFile)) {
    header('Location: ' . $installScript);
    exit;
}

// Konfiguration einbinden
define('BASE_PATH', dirname(__DIR__));
$config = require BASE_PATH . '/config/config.php';

// Datenbankverbindung mit besserer Fehlerbehandlung
try {
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_STRINGIFY_FETCHES => false,
    ]);
} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    die('<h2 style="color:red;">❌ Fehler bei der Datenbankverbindung</h2><p>Die Anwendung kann nicht gestartet werden. Bitte prüfen Sie die Konfiguration.</p>');
}

// Settings-System initialisieren
$settings = [];
try {
    // Prüfen, ob Settings-Tabelle existiert
    $pdo->query("SELECT 1 FROM settings LIMIT 1");
    
    // Alle Settings laden und cachen
    $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // BASE_URL definieren
    define('BASE_URL', isset($settings['base_url']) ? rtrim($settings['base_url'], '/') : '');
    
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Base table or view not found')) {
        header('Location: ' . $installScript);
        exit;
    }
    error_log('Settings loading failed: ' . $e->getMessage());
    define('BASE_URL', '');
}

// Neue Versionsverwaltung laden (nach Datenbankverbindung)
try {
    require_once __DIR__ . '/version.php';
    
    // Legacy-Support: Alte Version-Variable für Kompatibilität setzen
    if (!isset($version)) {
        $version = DVDPROFILER_VERSION;
    }
    
    // System-Informationen für Debugging (nur im Development)
    if (getSetting('environment', 'production') === 'development') {
        error_log('DVD Profiler Liste ' . getDVDProfilerVersionFull() . ' loaded');
        error_log('Features enabled: ' . count(array_filter(DVDPROFILER_FEATURES)));
    }
    
} catch (Exception $e) {
    error_log('Version system loading failed: ' . $e->getMessage());
    // Fallback-Werte definieren
    if (!defined('DVDPROFILER_VERSION')) {
        define('DVDPROFILER_VERSION', '1.3.6');
        define('DVDPROFILER_CODENAME', 'Cinephile');
        define('DVDPROFILER_AUTHOR', 'René Neuhaus');
        define('DVDPROFILER_GITHUB_URL', 'https://github.com/lunasans/dvdprofiler.liste');
    }
    $version = DVDPROFILER_VERSION;
}

// Utility Functions

/**
 * Settings-Wert abrufen mit Fallback
 */
function getSetting(string $key, string $default = ''): string
{
    global $settings;
    
    // Key Validation: Nur erlaubte Zeichen
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key) || strlen($key) > 100) {
        error_log("Invalid setting key attempted: " . substr($key, 0, 50));
        return $default;
    }
    
    return $settings[$key] ?? $default;
}

/**
 * Settings-Wert setzen
 */
function setSetting(string $key, string $value): bool
{
    global $pdo, $settings;
    
    // Key Validation
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key) || strlen($key) > 100) {
        error_log("Invalid setting key attempted: " . substr($key, 0, 50));
        return false;
    }
    
    // Value Validation
    if (strlen($value) > 10000) {
        error_log("Setting value too long for key: " . $key);
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
        $result = $stmt->execute([$key, $value]);
        
        if ($result) {
            $settings[$key] = $value; // Cache aktualisieren
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Setting update failed for key '{$key}': " . $e->getMessage());
        return false;
    }
}

/**
 * Sichere Session-Initialisierung
 */
function initializeSecureSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        // Session-Sicherheit konfigurieren
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', !empty($_SERVER['HTTPS']));
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_strict_mode', '1');
        
        session_start();
        
        // Session-Hijacking Schutz
        if (!isset($_SESSION['initiated'])) {
            session_regenerate_id(true);
            $_SESSION['initiated'] = true;
            $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $_SESSION['remote_addr'] = $_SERVER['REMOTE_ADDR'] ?? '';
        }
        
        // Session-Validierung
        if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
            session_unset();
            session_destroy();
            session_start();
            error_log('Session invalidated due to user agent mismatch');
        }
    }
}

/**
 * CSRF-Token generieren und validieren
 */
function generateCSRFToken(): string
{
    if (!isset($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Input-Sanitization
 */
function sanitizeInput(string $input, int $maxLength = 255): string
{
    $input = trim($input);
    $input = strip_tags($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    
    if (strlen($input) > $maxLength) {
        $input = substr($input, 0, $maxLength);
    }
    
    return $input;
}

/**
 * SQL Injection Schutz für LIKE-Queries
 */
function escapeLikeValue(string $value): string
{
    return str_replace(['%', '_'], ['\\%', '\\_'], $value);
}

/**
 * Rate-Limiting für API-Aufrufe
 */
function checkRateLimit(string $identifier, int $maxRequests = 60, int $timeWindow = 3600): bool
{
    $key = 'rate_limit_' . md5($identifier);
    $current = getSetting($key, '0|0');
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
    setSetting($key, $count . '|' . $timestamp);
    
    return $count <= $maxRequests;
}

/**
 * System-Health Check
 */
function getSystemHealth(): array
{
    global $pdo;
    
    $health = [
        'database' => false,
        'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'extensions' => [],
        'permissions' => [],
        'disk_space' => 0,
        'memory_usage' => 0
    ];
    
    // Database Check
    try {
        $pdo->query('SELECT 1');
        $health['database'] = true;
    } catch (Exception $e) {
        error_log('Database health check failed: ' . $e->getMessage());
    }
    
    // PHP Extensions Check
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
    foreach ($requiredExtensions as $ext) {
        $health['extensions'][$ext] = extension_loaded($ext);
    }
    
    // File Permissions Check
    $paths = [
        'config' => BASE_PATH . '/config',
        'uploads' => BASE_PATH . '/uploads',
        'cache' => BASE_PATH . '/cache'
    ];
    
    foreach ($paths as $name => $path) {
        $health['permissions'][$name] = is_writable($path);
    }
    
    // System Resources
    $health['disk_space'] = disk_free_space(BASE_PATH);
    $health['memory_usage'] = memory_get_usage(true);
    
    return $health;
}

// Session initialisieren
initializeSecureSession();

// CSRF-Token für Forms verfügbar machen
$csrf_token = generateCSRFToken();

// System-Health Check (nur im Admin-Bereich)
if (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false) {
    $systemHealth = getSystemHealth();
    
    // Kritische Probleme loggen
    if (!$systemHealth['database']) {
        error_log('CRITICAL: Database connection failed in admin area');
    }
    
    foreach ($systemHealth['extensions'] as $ext => $loaded) {
        if (!$loaded) {
            error_log("WARNING: Required PHP extension '{$ext}' not loaded");
        }
    }
}

// Performance Monitoring (nur im Development)
if (getSetting('environment', 'production') === 'development') {
    register_shutdown_function(function() {
        $memory = memory_get_peak_usage(true);
        $time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        error_log(sprintf('Performance: %.3fs, %s memory', $time, formatBytes($memory)));
    });
}

/**
 * Helper-Funktion für Byte-Formatierung
 */
function formatBytes(int $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Version-Kompatibilität sicherstellen
if (!function_exists('getDVDProfilerVersion')) {
    function getDVDProfilerVersion(): string {
        return defined('DVDPROFILER_VERSION') ? DVDPROFILER_VERSION : '1.3.6';
    }
}

// Bootstrap-Abschluss-Log
if (getSetting('environment', 'production') === 'development') {
    error_log('Bootstrap completed successfully - DVD Profiler Liste ' . getDVDProfilerVersion());
}