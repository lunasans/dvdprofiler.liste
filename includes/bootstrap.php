<?php
declare(strict_types=1);

// ERSTMAL: Nur das Nötigste - zurück zur ursprünglichen Funktionalität
error_reporting(E_ALL);
ini_set('display_errors', '1');

function setSecurityHeaders(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; img-src 'self' data: *; font-src 'self' cdn.jsdelivr.net; frame-src 'self' www.youtube.com");
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
}

setSecurityHeaders();

$lockFile = dirname(__DIR__) . '/install/install.lock';
$installScript = dirname($_SERVER['SCRIPT_NAME']) . '/install/index.php';

if (!file_exists($lockFile)) {
    header('Location: ' . $installScript);
    exit;
}

define('BASE_PATH', dirname(__DIR__));
$config = require BASE_PATH . '/config/config.php';

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

$settings = [];
try {
    $pdo->query("SELECT 1 FROM settings LIMIT 1");
    $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    define('BASE_URL', isset($settings['base_url']) ? rtrim($settings['base_url'], '/') : '');
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Base table or view not found')) {
        header('Location: ' . $installScript);
        exit;
    }
    error_log('Settings loading failed: ' . $e->getMessage());
    define('BASE_URL', '');
}

/**
 * CSRF-Token generieren
 */
function generateCSRFToken(): string
{
    if (session_status() === PHP_SESSION_NONE) {
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
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF-Token für Formulare ausgeben
 */
function csrfTokenField(): string
{
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// MINIMALE FUNKTIONEN - NUR DAS NÖTIGSTE

function getSetting(string $key, string $default = ''): string
{
    global $settings;
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key) || strlen($key) > 100) {
        return $default;
    }
    return $settings[$key] ?? $default;
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        $loginUrl = (defined('BASE_URL') && BASE_URL !== '') 
            ? BASE_URL . '/admin/login.php'
            : 'login.php';
        header("Location: {$loginUrl}");
        exit;
    }
}

function findCoverImage(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string
{
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $coverId)) {
        return $fallback;
    }
    
    $extensions = ['.jpg', '.jpeg', '.png', '.webp'];
    foreach ($extensions as $ext) {
        $file = "{$folder}/{$coverId}{$suffix}{$ext}";
        if (file_exists($file)) {
            return $file;
        }
    }
    return $fallback;
}

function getActorsByDvdId(PDO $pdo, int $dvdId): array
{
    try {
        // FALLBACK: Prüfen welche Tabelle existiert
        $checkOld = $pdo->query("SHOW TABLES LIKE 'actors'");
        if ($checkOld->rowCount() > 0) {
            // Alte Struktur mit dvd_id
            $checkColumns = $pdo->query("SHOW COLUMNS FROM actors LIKE 'dvd_id'");
            if ($checkColumns->rowCount() > 0) {
                $stmt = $pdo->prepare("SELECT firstname as first_name, lastname as last_name, role FROM actors WHERE dvd_id = ?");
                $stmt->execute([$dvdId]);
                return $stmt->fetchAll();
            }
        }
        
        // Neue Struktur mit film_actor
        $checkNew = $pdo->query("SHOW TABLES LIKE 'film_actor'");
        if ($checkNew->rowCount() > 0) {
            $stmt = $pdo->prepare("
                SELECT a.first_name, a.last_name, fa.role
                FROM actors a
                JOIN film_actor fa ON a.id = fa.actor_id
                WHERE fa.film_id = ?
                ORDER BY fa.role
            ");
            $stmt->execute([$dvdId]);
            return $stmt->fetchAll();
        }
        
        return [];
    } catch (PDOException $e) {
        error_log("Failed to get actors for DVD {$dvdId}: " . $e->getMessage());
        return [];
    }
}

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

function getChildDvds(PDO $pdo, $parentId): array
{
    try {
        $stmt = $pdo->prepare("SELECT * FROM dvds WHERE boxset_parent = ? ORDER BY year ASC, title ASC");
        $stmt->execute([(string)$parentId]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get child DVDs for parent {$parentId}: " . $e->getMessage());
        return [];
    }
}

// EINFACHE renderFilmCard - EXAKT WIE ORIGINAL
function renderFilmCard(array $dvd, bool $isChild = false): string
{
    $cover = htmlspecialchars(findCoverImage($dvd['cover_id'] ?? '', 'f'));
    $title = htmlspecialchars($dvd['title'] ?? '');
    $year = (int)($dvd['year'] ?? 0);
    $genre = htmlspecialchars($dvd['genre'] ?? '');
    $id = (int)($dvd['id'] ?? 0);

    if ($id === 0) {
        return '<!-- Invalid DVD ID -->';
    }

    $hasChildren = false;
    if (!$isChild && isset($GLOBALS['pdo'])) {
        try {
            $stmt = $GLOBALS['pdo']->prepare("SELECT COUNT(*) FROM dvds WHERE boxset_parent = ?");
            $stmt->execute([$id]);
            $hasChildren = $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            $hasChildren = false;
        }
    }

    $cssClass = $isChild ? 'dvd child-dvd' : 'dvd';
    $boxsetButton = $hasChildren ? '<button class="boxset-toggle" type="button">► Box-Inhalte anzeigen</button>' : '';

    return '
    <div class="' . $cssClass . '" data-dvd-id="' . $id . '">
      <div class="cover-area">
        <img src="' . $cover . '" alt="Cover">
      </div>
      <div class="dvd-details">
        <h2><a href="#" class="toggle-detail" data-id="' . $id . '">' . $title . ' (' . $year . ')</a></h2>
        <p><strong>Genre:</strong> ' . $genre . '</p>' . $boxsetButton . '
      </div>
    </div>';
}

function buildQuery(array $params): string
{
    return http_build_query(array_merge($_GET, $params));
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

echo "<!-- DEBUG: Minimale Bootstrap geladen -->\n";