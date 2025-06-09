<?php
declare(strict_types=1);

// Absoluter Pfad zum Projektverzeichnis (z. B. /var/www/html/dvd)
define('BASE_PATH', dirname(__DIR__));

// config.php einbinden
require_once __DIR__ . '/../config/config.php';

// BASE_URL aus DB lesen und definieren
try {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE name = 'base_url' LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    define('BASE_URL', isset($result['value']) ? rtrim($result['value'], '/') : '');
} catch (Exception $e) {
    define('BASE_URL', '');
}
