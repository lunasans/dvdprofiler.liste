<?php
$counterFile = __DIR__ . '/../counter.txt';

// Datei anlegen, falls sie nicht existiert
if (!file_exists($counterFile)) {
    file_put_contents($counterFile, '0');
}

// Session starten – aber nur wenn noch nicht aktiv
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_COOKIE['counted'])) {
    setcookie('counted', '1', time() + 86400); // 24h gültig

    $count = (int)file_get_contents($counterFile);
    file_put_contents($counterFile, (string)($count + 1));
}

// Aktuellen Wert holen
$visits = (int)file_get_contents($counterFile);
?>