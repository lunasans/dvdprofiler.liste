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
    
    return $settings[$key] ?? $default;
}

/**
 * Prüft ob der aktuelle Benutzer eingeloggt ist
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Prüft Admin-Berechtigung
 */
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
    if (!preg_match('/^[0-9.]+$/', $coverId)) {
        return $cache[$cacheKey] = $fallback;
    }
    
    // Suffix validieren
    if (!preg_match('/^[f]$/', $suffix)) {
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
        // Prüfen welche Tabelle existiert (neue oder alte Struktur)
        $checkOld = $pdo->query("SHOW TABLES LIKE 'actors'");
        if ($checkOld->rowCount() > 0) {
            // Prüfen ob alte Struktur mit dvd_id
            $checkColumns = $pdo->query("SHOW COLUMNS FROM actors LIKE 'dvd_id'");
            if ($checkColumns->rowCount() > 0) {
                // Alte Struktur
                $stmt = $pdo->prepare("SELECT firstname as first_name, lastname as last_name, role FROM actors WHERE dvd_id = ?");
                $stmt->execute([$dvdId]);
                return $cache[$dvdId] = $stmt->fetchAll();
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
            return $cache[$dvdId] = $stmt->fetchAll();
        }
        
        return [];
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
function getChildDvds(PDO $pdo, $parentId): array
{
    static $cache = [];
    
    // Input validation und conversion
    $parentId = (string)$parentId;
    
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
 * Prüft ob ein Film ein BoxSet-Parent ist
 */
function isBoxsetParent(PDO $pdo, $filmId): bool
{
    static $cache = [];
    
    $filmId = (string)$filmId;
    
    if (isset($cache[$filmId])) {
        return $cache[$filmId];
    }
    
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE boxset_parent = ?");
        $stmt->execute([$filmId]);
        return $cache[$filmId] = $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        error_log("Failed to check if film is boxset parent: " . $e->getMessage());
        return false;
    }
}

/**
 * Lädt DVDs für die Hauptübersicht (nur Parents und Einzelfilme, keine Kinder)
 */
function getMainOverviewDvds(PDO $pdo, string $searchQuery = '', string $genre = '', string $year = '', int $limit = 50, int $offset = 0): array
{
    try {
        $whereConditions = ["boxset_parent IS NULL"]; // Nur Parent-Filme oder Einzelfilme
        $params = [];
        
        if (!empty($searchQuery)) {
            $whereConditions[] = "(title LIKE ? OR overview LIKE ?)";
            $searchTerm = "%{$searchQuery}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($genre)) {
            $whereConditions[] = "genre LIKE ?";
            $params[] = "%{$genre}%";
        }
        
        if (!empty($year)) {
            $whereConditions[] = "year = ?";
            $params[] = $year;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "SELECT * FROM dvds WHERE {$whereClause} ORDER BY title ASC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Failed to get main overview DVDs: " . $e->getMessage());
        return [];
    }
}

/**
 * Zählt DVDs für die Hauptübersicht (für Pagination)
 */
function countMainOverviewDvds(PDO $pdo, string $searchQuery = '', string $genre = '', string $year = ''): int
{
    try {
        $whereConditions = ["boxset_parent IS NULL"];
        $params = [];
        
        if (!empty($searchQuery)) {
            $whereConditions[] = "(title LIKE ? OR overview LIKE ?)";
            $searchTerm = "%{$searchQuery}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        if (!empty($genre)) {
            $whereConditions[] = "genre LIKE ?";
            $params[] = "%{$genre}%";
        }
        
        if (!empty($year)) {
            $whereConditions[] = "year = ?";
            $params[] = $year;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        $sql = "SELECT COUNT(*) FROM dvds WHERE {$whereClause}";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        return (int)$stmt->fetchColumn();
        
    } catch (PDOException $e) {
        error_log("Failed to count main overview DVDs: " . $e->getMessage());
        return 0;
    }
}



// NUR DIESE FUNKTION in Ihrer bootstrap.php ERSETZEN:

/**
 * KORRIGIERTE renderFilmCard Funktion - ohne onclick, mit data-Attributen
 */
function renderFilmCard(array $dvd, bool $isChild = false): string
{
    global $pdo;
    
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

    // Prüfen ob es ein BoxSet-Parent ist
    $isBoxsetParent = !$isChild && isBoxsetParent($pdo, $id);
    $childCount = 0;
    $totalRuntime = 0;
    
    if ($isBoxsetParent) {
        $children = getChildDvds($pdo, $id);
        $childCount = count($children);
        $totalRuntime = array_sum(array_column($children, 'runtime'));
    }
    
    // CSS-Klasse bestimmen
    $cssClass = $isChild ? 'dvd child-dvd' : 'dvd';
    if ($isBoxsetParent) {
        $cssClass .= ' film-card boxset-parent';
    }
    
    $runtimeHtml = $runtime ? "<p><strong>Laufzeit:</strong> {$runtime}</p>" : '';
    
    // BoxSet-spezifische HTML-Elemente
    $boxsetIndicator = $isBoxsetParent ? 
        '<span class="boxset-indicator"><i class="bi bi-collection"></i> BoxSet</span>' : '';
    
    $boxsetInfo = $isBoxsetParent ? 
        "<p class=\"boxset-stats\"><strong>{$childCount} Filme</strong>" . 
        ($totalRuntime > 0 ? " • Gesamtlaufzeit: " . formatRuntime($totalRuntime) : "") . "</p>" : 
        $runtimeHtml;
    
    // KORRIGIERT: Button mit data-Attributen (kein onclick)
    $boxsetButton = $isBoxsetParent ? 
        "<button class=\"btn boxset-btn btn-sm boxset-modal-trigger\" 
                 data-boxset-id=\"{$id}\"
                 data-boxset-title=\"{$title}\">
            <i class=\"bi bi-collection\"></i> BoxSet öffnen
         </button>" : '';

    // Für BoxSet-Parents: Moderne Card-Layout
    if ($isBoxsetParent) {
        return "
        <div class=\"{$cssClass}\" data-id=\"{$id}\">
            <div class=\"cover-area position-relative\">
                <img src=\"{$cover}\" alt=\"{$title}\" class=\"cover-image\" loading=\"lazy\">
                {$boxsetIndicator}
            </div>
            <div class=\"dvd-details\">
                <h2><a href=\"#\" class=\"toggle-detail\" data-id=\"{$id}\">{$title} ({$year})</a></h2>
                <p><strong>Genre:</strong> {$genre}</p>
                {$boxsetInfo}
                <div class=\"boxset-actions mt-2\">
                    <button class=\"btn btn-primary btn-sm toggle-detail\" data-id=\"{$id}\">
                        <i class=\"bi bi-info-circle\"></i> Details
                    </button>
                    {$boxsetButton}
                </div>
            </div>
        </div>";
    }
    
    // Für normale Filme und Kinder: Bestehende Layout
    $oldBoxsetButton = !$isChild && !empty(getChildDvds($pdo, (string)$id)) ? 
        '<button class="boxset-toggle" type="button">► Box-Inhalte anzeigen</button>' : '';

    return "
    <div class=\"{$cssClass}\" data-dvd-id=\"{$id}\">
      <div class=\"cover-area\">
        <img src=\"{$cover}\" alt=\"Cover von {$title}\" loading=\"lazy\">
      </div>
      <div class=\"dvd-details\">
        <h2><a href=\"#\" class=\"toggle-detail\" data-id=\"{$id}\">{$title} ({$year})</a></h2>
        <p><strong>Genre:</strong> {$genre}</p>{$runtimeHtml}{$oldBoxsetButton}
      </div>
    </div>";
}



/**
 * Hilfsfunktion für Query-Parameter
 */
function buildQuery(array $params): string
{
    return http_build_query(array_merge($_GET, $params));
}

// Session starten wenn noch nicht aktiv
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}