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
} catch (Exception $e) {
    error_log('Version loading failed: ' . $e->getMessage());
    // Fallback-Version definieren
    if (!defined('DVDPROFILER_VERSION')) {
        define('DVDPROFILER_VERSION', '1.4.7');
        define('DVDPROFILER_CODENAME', 'Cinephile');
        define('DVDPROFILER_GITHUB_URL', 'https://update.neuhaus.or.at/dvdprofiler-liste');
        define('DVDPROFILER_AUTHOR', 'René Neuhaus');
    }
}

// Utility-Funktionen definieren
if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string {
        global $settings;
        return $settings[$key] ?? $default;
    }
}

if (!function_exists('setSetting')) {
    function setSetting(string $key, string $value): bool {
        global $pdo, $settings;
        try {
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $result = $stmt->execute([$key, $value]);
            if ($result) {
                $settings[$key] = $value; // Cache aktualisieren
            }
            return $result;
        } catch (PDOException $e) {
            error_log('Setting save failed: ' . $e->getMessage());
            return false;
        }
    }
}

// Session initialisieren
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// PHP Fehlerbehandlung konfigurieren
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Globale Funktionen für DVD-Sammlung
if (!function_exists('formatRuntime')) {
    function formatRuntime(?int $minutes): string {
        if (!$minutes) return '';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
    }
}

if (!function_exists('findCoverImage')) {
    function findCoverImage(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string {
        if (empty($coverId)) return $fallback;
        $extensions = ['.jpg', '.jpeg', '.png'];
        foreach ($extensions as $ext) {
            $file = "{$folder}/{$coverId}{$suffix}{$ext}";
            if (file_exists($file)) {
                return $file;
            }
        }
        return $fallback;
    }
}

if (!function_exists('getActorsByDvdId')) {
    function getActorsByDvdId(PDO $pdo, int $dvdId): array {
        try {
            $stmt = $pdo->prepare("
                SELECT a.first_name, a.last_name, fa.role 
                FROM film_actor fa 
                JOIN actors a ON fa.actor_id = a.id 
                WHERE fa.film_id = ? 
                ORDER BY fa.role ASC, a.last_name ASC
            ");
            $stmt->execute([$dvdId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Actor query error for DVD {$dvdId}: " . $e->getMessage());
            return [];
        }
    }
}