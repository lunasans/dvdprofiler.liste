<?php
declare(strict_types=1);

function setSecurityHeaders(): void {
    // Verhindert XSS Angriffe
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    
    // Content Security Policy
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' cdn.jsdelivr.net");
    
    // Verhindert MIME-Type sniffing
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// In bootstrap.php aufrufen:
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

try {
    // DSN inkl. Datenbank
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (PDOException $e) {
    die('<h2 style="color:red;">❌ Fehler bei der Datenbankverbindung:</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>');
}

// Projekt-Einstellungen aus der settings-Tabelle laden
$settings = [];


// Beispiel: Version ausgeben (optional)
$currentVersion = $settings['version'] ?? 'unbekannt';


// Prüfen, ob Tabelle 'settings' existiert
try {
    $pdo->query("SELECT 1 FROM settings LIMIT 1");
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Base table or view not found')) {
        header('Location: ' . $installScript);
        exit;
    }
    throw $e;
}

// BASE_URL aus DB lesen und definieren
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = 'base_url' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    define('BASE_URL', isset($result['value']) ? rtrim($result['value'], '/') : '');
} catch (Exception $e) {
    define('BASE_URL', '');
}

function findCoverImage(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string
{
    // Input Validation: Nur alphanumerische Zeichen + Bindestriche erlauben
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $coverId)) {
        return $fallback;
    }
    
    // Suffix validieren
    if (!preg_match('/^[a-z]$/', $suffix)) {
        $suffix = 'f'; // Default
    }
    
    // Folder validieren (keine Pfad-Traversal)
    $folder = basename($folder); // Entfernt ../ Angriffe
    if (!is_dir($folder)) {
        return $fallback;
    }
    
    $extensions = ['.jpg', '.jpeg', '.png'];
    foreach ($extensions as $ext) {
        $file = "{$folder}/{$coverId}{$suffix}{$ext}";
        
        // Zusätzliche Sicherheit: realpath() prüfung
        $realFile = realpath($file);
        $realFolder = realpath($folder);
        
        if ($realFile && $realFolder && 
            str_starts_with($realFile, $realFolder) && 
            file_exists($realFile)) {
            return $file;
        }
    }
    return $fallback;
}

function getActorsByDvdId(PDO $pdo, int $dvdId): array
{
    $stmt = $pdo->prepare("
        SELECT a.first_name AS firstname, a.last_name AS lastname, fa.role
          FROM actors a
          JOIN film_actor fa ON a.id = fa.actor_id
         WHERE fa.film_id = ?
    ");
    $stmt->execute([$dvdId]);
    return $stmt->fetchAll();
}

function formatRuntime(?int $minutes): string
{
    if (!$minutes) return '';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}

function getChildDvds(PDO $pdo, string $parentId): array
{
    $stmt = $pdo->prepare("SELECT * FROM dvds WHERE boxset_parent = ? ORDER BY title");
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

function renderFilmCard(array $dvd, bool $isChild = false): string
{
    // Alle Inputs validieren und escapen
    $coverId = preg_replace('/[^a-zA-Z0-9_-]/', '', $dvd['cover_id'] ?? '');
    $cover = htmlspecialchars(findCoverImage($coverId, 'f'), ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($dvd['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $year = filter_var($dvd['year'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1800, 'max_range' => 2100]]) ?: 0;
    $genre = htmlspecialchars($dvd['genre'] ?? '', ENT_QUOTES, 'UTF-8');
    $id = filter_var($dvd['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

    if ($id === 0) {
        return '<!-- Invalid DVD ID -->';
    }

    $hasChildren = !$isChild && !empty(getChildDvds($GLOBALS['pdo'], (string)$id));
    
    // CSS-Klasse sicher zusammenbauen
    $cssClass = $isChild ? 'dvd child-dvd' : 'dvd';

    return sprintf('
    <div class="%s" data-dvd-id="%d">
      <div class="cover-area">
        <img src="%s" alt="Cover von %s" loading="lazy">
      </div>
      <div class="dvd-details">
        <h2><a href="#" class="toggle-detail" data-id="%d">%s (%d)</a></h2>
        <p><strong>Genre:</strong> %s</p>%s
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
        $hasChildren ? '<button class="boxset-toggle" type="button">► Box-Inhalte anzeigen</button>' : ''
    );
}

function getSetting(string $key, string $default = ''): string
{
    global $pdo;
    
    // Key Validation: Nur erlaubte Zeichen
    if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $key) || strlen($key) > 100) {
        error_log("Invalid setting key attempted: " . $key);
        return $default;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();
        
        // Zusätzliche Validierung für kritische Settings
        if (in_array($key, ['base_url', 'admin_email']) && !filter_var($value, FILTER_VALIDATE_URL) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            error_log("Invalid setting value for key: " . $key);
            return $default;
        }
        
        return is_string($value) ? $value : $default;
    } catch (Throwable $e) {
        error_log("Database error in getSetting: " . $e->getMessage());
        return $default;
    }
}

function updateSetting(string $key, string $value): void {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE settings SET value = :value WHERE `key` = :key");
    $stmt->execute(['key' => $key, 'value' => $value]);
}

