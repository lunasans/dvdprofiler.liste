<?php
declare(strict_types=1);

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
