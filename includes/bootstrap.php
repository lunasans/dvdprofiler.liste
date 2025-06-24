<?php
declare(strict_types=1);

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

// Datenbankverbindung aufbauen
try {
    $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die('<h2 style="color:red;">❌ Fehler bei der Datenbankverbindung:</h2><p>' . htmlspecialchars($e->getMessage()) . '</p>');
}

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
    $extensions = ['.jpg', '.jpeg', '.png'];
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
    $stmt = $pdo->prepare("
        SELECT a.first_name, a.last_name, fa.role
        FROM film_actor fa
        JOIN actors a ON fa.actor_id = a.id
        WHERE fa.film_id = ?
        ORDER BY a.last_name, a.first_name
    ");
    $stmt->execute([$dvdId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    $cover = htmlspecialchars(findCoverImage($dvd['cover_id'], 'f'));
    $title = htmlspecialchars($dvd['title']);
    $year = (int)$dvd['year'];
    $genre = htmlspecialchars($dvd['genre'] ?? '');
    $id = (int)$dvd['id'];

    $hasChildren = !$isChild && !empty(getChildDvds($GLOBALS['pdo'], (string)$id));

    return '
    <div class="dvd' . ($isChild ? ' child-dvd' : '') . '" data-dvd-id="' . $id . '">
      <div class="cover-area">
        <img src="' . $cover . '" alt="Cover">
      </div>
      <div class="dvd-details">
        <h2><a href="#" class="toggle-detail" data-id="' . $id . '">' . $title . ' (' . $year . ')</a></h2>
        <p><strong>Genre:</strong> ' . $genre . '</p>'
        . ($hasChildren ? '<button class="boxset-toggle">► Box-Inhalte anzeigen</button>' : '') .
      '</div>
    </div>';
}

function getSetting(string $key, string $default = ''): string
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT value FROM settings WHERE `key` = :key LIMIT 1");
        $stmt->execute(['key' => $key]);
        $value = $stmt->fetchColumn();

        return is_string($value) ? $value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}