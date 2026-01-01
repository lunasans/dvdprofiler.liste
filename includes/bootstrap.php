<?php
declare(strict_types=1);

require_once __DIR__ . '/soft-delete-helpers.php';

function setSecurityHeaders(): void {
    // Verhindert XSS Angriffe
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy - erweitert für bessere Sicherheit
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net https://fonts.googleapis.com; img-src 'self' data: *; font-src 'self' cdn.jsdelivr.net https://fonts.gstatic.com; frame-src 'self' www.youtube.com");
    
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
    
    // Debug-Logs werden in includes/debug.php behandelt
    
} catch (Exception $e) {
    error_log('Version system loading failed: ' . $e->getMessage());
    // Fallback-Werte definieren
    if (!defined('DVDPROFILER_VERSION')) {
        define('DVDPROFILER_VERSION', '1.4.7');
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
        // Named Parameters statt VALUES() (MySQL 8+ kompatibel)
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, `value`) 
            VALUES (:key, :value) 
            ON DUPLICATE KEY UPDATE `value` = :value_update
        ");
        
        error_log("DEBUG setSetting: key={$key}, value=" . substr($value, 0, 50));
        
        $result = $stmt->execute([
            'key' => $key,
            'value' => $value,
            'value_update' => $value
        ]);
        
        error_log("DEBUG execute result: " . ($result ? 'TRUE' : 'FALSE'));
        error_log("DEBUG rowCount: " . $stmt->rowCount());
        error_log("DEBUG affected rows check");
        
        if ($result) {
            $settings[$key] = $value; // Cache aktualisieren
            error_log("Setting saved: {$key}");
        } else {
            error_log("Setting save FAILED: {$key}");
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
    // Session bereits gestartet?
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    
    // Sichere Session-Konfiguration
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', '0'); // Auf 1 setzen wenn HTTPS verwendet wird
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.gc_maxlifetime', '7200'); // 2 Stunden
    
    // Session starten
    session_start();
    
    // Session-Hijacking Schutz
    if (!isset($_SESSION['initiated'])) {
        session_regenerate_id(true);
        $_SESSION['initiated'] = true;
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['ip_subnet'] = getIPSubnet($_SERVER['REMOTE_ADDR'] ?? '');
    } else {
        // Lockere Session-Validierung (nur im Admin-Bereich)
        $isAdmin = strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false;
        
        if ($isAdmin && isset($_SESSION['user_id'])) {
            $currentSubnet = getIPSubnet($_SERVER['REMOTE_ADDR'] ?? '');
            $currentUA = $_SERVER['HTTP_USER_AGENT'] ?? '';
            
            // Prüfe nur IP-Subnet (erlaubt IP-Wechsel im gleichen Netzwerk)
            // User-Agent wird NUR gewarnt, nicht ausgeloggt
            if (($_SESSION['ip_subnet'] ?? '') !== $currentSubnet) {
                // IP-Subnet hat sich geändert - kritisch!
                error_log("Security Warning: IP subnet changed for user {$_SESSION['user_id']}");
                session_destroy();
                header('Location: /admin/login.php?reason=ip_changed');
                exit;
            }
            
            // User-Agent Warnung (nur loggen, nicht ausloggen)
            if (($_SESSION['user_agent'] ?? '') !== $currentUA) {
                error_log("Security Notice: User-Agent changed for user {$_SESSION['user_id']}");
                // Update User-Agent, aber Session bleibt aktiv
                $_SESSION['user_agent'] = $currentUA;
            }
        }
    }
}

/**
 * IP-Subnet extrahieren (erste 3 Oktette für IPv4)
 */
function getIPSubnet(string $ip): string
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        return implode('.', array_slice($parts, 0, 3)) . '.0';
    }
    
    // IPv6: Verwende ersten Block
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        $parts = explode(':', $ip);
        return implode(':', array_slice($parts, 0, 4)) . '::';
    }
    
    return $ip;
}

/**
 * CSRF-Token generieren
 */
function generateCSRFToken(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * CSRF-Token validieren
 */
function validateCSRFToken(string $token): bool
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Rate-Limiting für Requests
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
        'memory_usage' => 0,
        'overall' => true
    ];
    
    // Database Check
    try {
        $pdo->query('SELECT 1');
        $health['database'] = true;
    } catch (Exception $e) {
        error_log('Database health check failed: ' . $e->getMessage());
        $health['database'] = false;
        $health['overall'] = false;
    }
    
    // PHP Extensions Check
    $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
    foreach ($requiredExtensions as $ext) {
        $health['extensions'][$ext] = extension_loaded($ext);
        if (!$health['extensions'][$ext]) {
            $health['overall'] = false;
        }
    }
    
    // File Permissions Check
    $paths = [
        'config' => BASE_PATH . '/config',
        'uploads' => BASE_PATH . '/uploads',
        'cache' => BASE_PATH . '/cache'
    ];
    
    foreach ($paths as $name => $path) {
        $health['permissions'][$name] = is_dir($path) && is_writable($path);
        if (!$health['permissions'][$name]) {
            $health['overall'] = false;
        }
    }
    
    // System Resources
    $health['disk_space'] = disk_free_space(BASE_PATH) ?: 0;
    $health['memory_usage'] = memory_get_usage(true);
    
    return $health;
}

/**
 * DVD Profiler Statistiken sammeln
 */
function getDVDProfilerStatistics(): array
{
    global $pdo;
    
    $stats = [
        'total_films' => 0,
        'total_boxsets' => 0,
        'total_genres' => 0,
        'total_visits' => 0,
        'storage_size' => 0
    ];
    
    try {
        // Filme zählen
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM dvds");
        $result = $stmt->fetch();
        $stats['total_films'] = $result['count'] ?? 0;
        
        // BoxSets zählen
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM dvds WHERE boxset_parent IS NOT NULL");
        $result = $stmt->fetch();
        $stats['total_boxsets'] = $result['count'] ?? 0;
        
        // Genres zählen
        $stmt = $pdo->query("SELECT COUNT(DISTINCT genre) as count FROM dvds WHERE genre IS NOT NULL AND genre != ''");
        $result = $stmt->fetch();
        $stats['total_genres'] = $result['count'] ?? 0;
        
        // Geschätzte Speichergröße
        $stmt = $pdo->query("SELECT AVG(runtime) as avg_runtime, COUNT(*) as total FROM dvds WHERE runtime > 0");
        $result = $stmt->fetch();
        if ($result && $result['avg_runtime']) {
            $avgSize = ($result['avg_runtime'] / 60) * 4.5; // ~4.5GB pro Stunde geschätzt
            $stats['storage_size'] = round($avgSize * $result['total'], 1);
        }
        
    } catch (Exception $e) {
        error_log('Statistics error: ' . $e->getMessage());
    }
    
    // Besucher aus Counter-Datei
    $counterFile = dirname(__DIR__) . '/counter.txt';
    if (file_exists($counterFile)) {
        $stats['total_visits'] = (int)file_get_contents($counterFile);
    }
    
    return $stats;
}

/**
 * Byte-Formatierung
 */
function formatBytes(int|float $bytes, int $precision = 2): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function getBoxsetStats($pdo): array {
    try {
        $boxsetStats = $pdo->query("
            SELECT 
                COUNT(DISTINCT boxset_parent) as total_boxsets,
                COUNT(*) as total_boxset_items
            FROM dvds 
            WHERE boxset_parent IS NOT NULL
        ")->fetch();
        
        $topBoxsets = $pdo->query("
            SELECT 
                p.title as boxset_name,
                COUNT(c.id) as child_count,
                SUM(c.runtime) as total_runtime
            FROM dvds p
            JOIN dvds c ON c.boxset_parent = p.id
            GROUP BY p.id, p.title
            ORDER BY child_count DESC
            LIMIT 5
        ")->fetchAll();
        
        return [
            'stats' => $boxsetStats ?: ['total_boxsets' => 0, 'total_boxset_items' => 0],
            'top' => $topBoxsets ?: []
        ];
    } catch (Exception $e) {
        error_log('Boxset stats error: ' . $e->getMessage());
        return [
            'stats' => ['total_boxsets' => 0, 'total_boxset_items' => 0],
            'top' => []
        ];
    }
}

// Session initialisieren
initializeSecureSession();

// CSRF-Token für Forms verfügbar machen
$csrf_token = generateCSRFToken();

// Debug & Wartungsmodus laden (VOR allem anderen!)
if (file_exists(__DIR__ . '/debug.php')) {
    require_once __DIR__ . '/debug.php';
}

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

// Performance Monitoring wird in includes/debug.php behandelt

// Version-Kompatibilität sicherstellen
if (!function_exists('getDVDProfilerVersion')) {
    function getDVDProfilerVersion(): string {
        return defined('DVDPROFILER_VERSION') ? DVDPROFILER_VERSION : '1.4.7';
    }
}

// TMDb Integration laden
if (file_exists(__DIR__ . '/tmdb-helper.php')) {
    require_once __DIR__ . '/tmdb-helper.php';
}

// Bootstrap-Abschluss-Log wird in includes/debug.php behandelt