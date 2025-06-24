<?php
declare(strict_types=1);

// Weiterleitung bei fehlender Installation
$lockFile = dirname(__DIR__) . '/install/install.lock';
$installScript = dirname($_SERVER['SCRIPT_NAME']) . '/install/index.php';

if (!file_exists($lockFile)) {
    header('Location: ' . $installScript);
    exit;
}

// Optional: prüfen, ob Tabelle 'settings' existiert
try {
    $pdo->query("SELECT 1 FROM settings LIMIT 1");
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Base table or view not found')) {
        header('Location: ' . $installScript);
        exit;
    }
    throw $e;
}

// ZUERST definieren
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php'; // stellt $pdo bereit
//var_dump($pdo);

// config.php einbinden
//require_once __DIR__ . '/../includes/bootstrap.php';

// BASE_URL aus DB lesen und definieren
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'base_url' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    define('BASE_URL', isset($result['value']) ? rtrim($result['value'], '/') : '');
} catch (Exception $e) {
    define('BASE_URL', '');
}
