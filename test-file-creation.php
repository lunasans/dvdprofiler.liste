<?php
declare(strict_types=1);

echo "=== Test: Datei-Erstellung ===\n\n";

$configDir = __DIR__ . '/config';
$configFile = $configDir . '/config.php';
$lockFile = __DIR__ . '/install/install.lock';

// Test 1: Config-Verzeichnis
echo "1. Config-Verzeichnis prüfen:\n";
echo "   Pfad: $configDir\n";
echo "   Existiert: " . (file_exists($configDir) ? 'JA' : 'NEIN') . "\n";
echo "   Beschreibbar: " . (is_writable($configDir) ? 'JA' : 'NEIN') . "\n\n";

// Test 2: Config-Datei erstellen
echo "2. Config-Datei erstellen:\n";
$configContent = "<?php\n// Test-Konfiguration\nreturn " . var_export([
    'db_host' => 'localhost',
    'db_name' => 'test',
    'db_user' => 'test',
    'db_pass' => 'test',
    'db_charset' => 'utf8mb4',
    'version' => '1.4.6',
    'environment' => 'development'
], true) . ";\n";

$result = file_put_contents($configFile, $configContent);
if ($result === false) {
    echo "   ❌ FEHLER: Konnte Config-Datei nicht erstellen!\n";
    echo "   Fehler: " . error_get_last()['message'] . "\n";
} else {
    echo "   ✅ Config-Datei erstellt ($result Bytes)\n";
    echo "   Existiert: " . (file_exists($configFile) ? 'JA' : 'NEIN') . "\n";
}
echo "\n";

// Test 3: Install-Verzeichnis
echo "3. Install-Verzeichnis prüfen:\n";
$installDir = __DIR__ . '/install';
echo "   Pfad: $installDir\n";
echo "   Existiert: " . (file_exists($installDir) ? 'JA' : 'NEIN') . "\n";
echo "   Beschreibbar: " . (is_writable($installDir) ? 'JA' : 'NEIN') . "\n\n";

// Test 4: Lock-Datei erstellen
echo "4. Lock-Datei erstellen:\n";
$lockContent = json_encode([
    'installed_at' => date('Y-m-d H:i:s'),
    'version' => '1.4.6',
    'test' => true
]);

$result = file_put_contents($lockFile, $lockContent);
if ($result === false) {
    echo "   ❌ FEHLER: Konnte Lock-Datei nicht erstellen!\n";
    echo "   Fehler: " . error_get_last()['message'] . "\n";
} else {
    echo "   ✅ Lock-Datei erstellt ($result Bytes)\n";
    echo "   Existiert: " . (file_exists($lockFile) ? 'JA' : 'NEIN') . "\n";
}
echo "\n";

// Test 5: Dateien prüfen
echo "5. Finale Prüfung:\n";
echo "   Config-Datei: " . (file_exists($configFile) ? '✅ EXISTIERT' : '❌ FEHLT') . "\n";
echo "   Lock-Datei: " . (file_exists($lockFile) ? '✅ EXISTIERT' : '❌ FEHLT') . "\n";
echo "\n";

echo "=== Test abgeschlossen ===\n";
