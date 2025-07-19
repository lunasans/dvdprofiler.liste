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

// Utility Functions

/**
 * Settings-Wert abrufen mit Fallback
 */
function getSetting(string $key, string $default = ''): string
{
    global $settings;
    
    // Key Validation: Nur erlaubte Zeichen
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key) || strlen($key) > 100) {
        error_log("Invalid setting key attempted: " . $key);
        return $default;
    }
    
    $value = $settings[$key] ?? $default;
    
    // Zusätzliche Validierung für kritische Settings
    if (in_array($key, ['base_url']) && $value && !filter_var($value, FILTER_VALIDATE_URL)) {
        error_log("Invalid URL setting value for key: " . $key);
        return $default;
    }
    
    if (in_array($key, ['admin_email', 'smtp_sender']) && $value && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email setting value for key: " . $key);
        return $default;
    }
    
    return $value;
}

/**
 * Setting aktualisieren
 */
function updateSetting(string $key, string $value): bool {
    global $pdo, $settings;
    
    // Validation
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key) || strlen($key) > 100) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO settings (`key`, `value`) 
            VALUES (:key, :value) 
            ON DUPLICATE KEY UPDATE value = :value
        ");
        $result = $stmt->execute(['key' => $key, 'value' => $value]);
        
        // Cache aktualisieren
        if ($result) {
            $settings[$key] = $value;
        }
        
        return $result;
    } catch (PDOException $e) {
        error_log("Failed to update setting {$key}: " . $e->getMessage());
        return false;
    }
}

/**
 * Sicherer Cover-Image-Finder mit Caching
 */
function findCoverImage(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string
{
    static $cache = [];
    $cacheKey = "{$coverId}_{$suffix}_{$folder}";
    
    if (isset($cache[$cacheKey])) {
        return $cache[$cacheKey];
    }
    
    // Input Validation: Nur alphanumerische Zeichen + Bindestriche erlauben
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $coverId)) {
        return $cache[$cacheKey] = $fallback;
    }
    
    // Suffix validieren
    if (!preg_match('/^[a-z]$/', $suffix)) {
        $suffix = 'f'; // Default
    }
    
    // Folder validieren (keine Pfad-Traversal)
    $folder = basename($folder);
    if (!is_dir($folder)) {
        return $cache[$cacheKey] = $fallback;
    }
    
    $extensions = ['.jpg', '.jpeg', '.png', '.webp'];
    foreach ($extensions as $ext) {
        $file = "{$folder}/{$coverId}{$suffix}{$ext}";
        
        // Zusätzliche Sicherheit: realpath() prüfung
        $realFile = realpath($file);
        $realFolder = realpath($folder);
        
        if ($realFile && $realFolder && 
            str_starts_with($realFile, $realFolder) && 
            file_exists($realFile)) {
            return $cache[$cacheKey] = $file;
        }
    }
    
    return $cache[$cacheKey] = $fallback;
}

/**
 * Schauspieler für DVD abrufen
 */
function getActorsByDvdId(PDO $pdo, int $dvdId): array
{
    static $cache = [];
    
    if (isset($cache[$dvdId])) {
        return $cache[$dvdId];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT a.first_name, a.last_name, fa.role
            FROM actors a
            JOIN film_actor fa ON a.id = fa.actor_id
            WHERE fa.film_id = ?
            ORDER BY fa.role
        ");
        $stmt->execute([$dvdId]);
        return $cache[$dvdId] = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get actors for DVD {$dvdId}: " . $e->getMessage());
        return [];
    }
}

/**
 * Laufzeit formatieren
 */
function formatRuntime(?int $minutes): string
{
    if (!$minutes || $minutes <= 0) return '';
    
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    
    if ($h > 0 && $m > 0) {
        return "{$h}h {$m}min";
    } elseif ($h > 0) {
        return "{$h}h";
    } else {
        return "{$m}min";
    }
}

/**
 * Child-DVDs für BoxSets abrufen
 */
function getChildDvds(PDO $pdo, string $parentId): array
{
    static $cache = [];
    
    if (isset($cache[$parentId])) {
        return $cache[$parentId];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM dvds 
            WHERE boxset_parent = ? 
            ORDER BY year ASC, title ASC
        ");
        $stmt->execute([$parentId]);
        return $cache[$parentId] = $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get child DVDs for parent {$parentId}: " . $e->getMessage());
        return [];
    }
}

/**
 * Film-Card rendern mit verbesserter Sicherheit
 */
function renderFilmCard(array $dvd, bool $isChild = false): string
{
    // Alle Inputs validieren und escapen
    $coverId = preg_replace('/[^a-zA-Z0-9_.-]/', '', $dvd['cover_id'] ?? '');
    $cover = htmlspecialchars(findCoverImage($coverId, 'f'), ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($dvd['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $year = filter_var($dvd['year'] ?? 0, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1800, 'max_range' => 2100]
    ]) ?: 0;
    $genre = htmlspecialchars($dvd['genre'] ?? '', ENT_QUOTES, 'UTF-8');
    $id = filter_var($dvd['id'] ?? 0, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1]
    ]) ?: 0;
    $runtime = formatRuntime($dvd['runtime'] ?? null);

    if ($id === 0) {
        return '<!-- Invalid DVD ID -->';
    }

    $hasChildren = !$isChild && !empty(getChildDvds($GLOBALS['pdo'], (string)$id));
    
    // CSS-Klasse sicher zusammenbauen
    $cssClass = $isChild ? 'dvd child-dvd' : 'dvd';
    
    $runtimeHtml = $runtime ? "<p><strong>Laufzeit:</strong> {$runtime}</p>" : '';
    $boxsetButton = $hasChildren ? '<button class="boxset-toggle" type="button">► Box-Inhalte anzeigen</button>' : '';

    return sprintf('
    <div class="%s" data-dvd-id="%d">
      <div class="cover-area">
        <img src="%s" alt="Cover von %s" loading="lazy">
      </div>
      <div class="dvd-details">
        <h2><a href="#" class="toggle-detail" data-id="%d">%s (%d)</a></h2>
        <p><strong>Genre:</strong> %s</p>%s%s
      </div>
    </div>',
        htmlspecialchars($cssClass, ENT_QUOTES, 'UTF-8'),
        $id,
        $cover,
        $title,
        $id,
        $title,
        $year,
        $genre,
        $runtimeHtml,
        $boxsetButton
    );
}

/**
 * Sichere Eingabe-Bereinigung
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
 * CSRF-Token generieren
 */
function generateCSRFToken(): string
{
    if (!isset($_SESSION)) {
        session_start();
    }
    
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

/**
 * CSRF-Token validieren
 */
function validateCSRFToken(string $token): bool
{
    if (!isset($_SESSION)) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Session starten wenn noch nicht aktiv
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}