<?php
// Fehler anzeigen
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Debug - DVD Profiler Liste</h1>";
echo "<pre>";

// 1. PHP Version
echo "1. PHP Version: " . PHP_VERSION . "\n\n";

// 2. Lock-Datei prüfen
echo "2. Lock-Datei:\n";
$lockFile = __DIR__ . '/admin/.install.lock';
echo "   Pfad: $lockFile\n";
echo "   Existiert: " . (file_exists($lockFile) ? "JA" : "NEIN") . "\n";
if (file_exists($lockFile)) {
    echo "   Inhalt: " . file_get_contents($lockFile) . "\n";
}
echo "\n";

// 3. Config-Datei prüfen
echo "3. Config-Datei:\n";
$configFile = __DIR__ . '/config/config.php';
echo "   Pfad: $configFile\n";
echo "   Existiert: " . (file_exists($configFile) ? "JA" : "NEIN") . "\n";
if (file_exists($configFile)) {
    echo "   Lesbar: " . (is_readable($configFile) ? "JA" : "NEIN") . "\n";
    try {
        $config = require $configFile;
        echo "   Geladen: JA\n";
        echo "   DB-Host: " . ($config['db_host'] ?? 'FEHLT') . "\n";
        echo "   DB-Name: " . ($config['db_name'] ?? 'FEHLT') . "\n";
        echo "   DB-User: " . ($config['db_user'] ?? 'FEHLT') . "\n";
    } catch (Exception $e) {
        echo "   FEHLER: " . $e->getMessage() . "\n";
    }
} else {
    echo "   ❌ Config-Datei existiert nicht!\n";
}
echo "\n";

// 4. Bootstrap laden (mit Fehlerbehandlung)
echo "4. Bootstrap laden:\n";
try {
    require __DIR__ . '/includes/bootstrap.php';
    echo "   ✅ Bootstrap erfolgreich geladen\n";
} catch (Throwable $e) {
    echo "   ❌ FEHLER beim Laden von bootstrap.php:\n";
    echo "   Fehler: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . "\n";
    echo "   Zeile: " . $e->getLine() . "\n";
    echo "   Stack:\n" . $e->getTraceAsString() . "\n";
}

echo "\n";
echo "5. Wenn Sie bis hier gekommen sind, ist kein kritischer Fehler aufgetreten.\n";
echo "</pre>";
