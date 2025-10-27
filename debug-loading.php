<?php
// Fehler anzeigen
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Debug - version.php Loading Trace</h1>";
echo "<pre>";

// Tracker-Variable
global $versionLoadCount;
$versionLoadCount = 0;

// Override für require_once
$originalVersionPath = __DIR__ . '/includes/version.php';

echo "=== VERSION.PHP LOAD TRACKING ===\n\n";

// Lade bootstrap.php und tracke alle Includes
echo "1. Lade bootstrap.php...\n";
echo "   Pfad: " . __DIR__ . '/includes/bootstrap.php' . "\n\n";

// Setze einen include handler
set_include_path(get_include_path());

// Tracke alle requires via debug_backtrace
register_shutdown_function(function() {
    global $versionLoadCount;
    echo "\n=== FINALE STATISTIK ===\n";
    echo "version.php wurde {$versionLoadCount}x geladen\n";
});

// Monkey-patch version.php um Aufrufe zu tracken
$versionContent = file_get_contents($originalVersionPath);

// Füge Tracking-Code am Anfang ein
$trackingCode = <<<'PHP'
<?php
global $versionLoadCount;
$versionLoadCount++;
echo "[LOAD #{$versionLoadCount}] version.php geladen von:\n";
$trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
foreach ($trace as $idx => $frame) {
    if (isset($frame['file'])) {
        echo "  [{$idx}] " . $frame['file'] . ':' . ($frame['line'] ?? '?') . "\n";
    }
}
echo "\n";
PHP;

// Erstelle temporäre version.php mit Tracking
$tempVersionPath = sys_get_temp_dir() . '/version_tracked.php';
file_put_contents($tempVersionPath, $trackingCode . "\n" . substr($versionContent, 5)); // Remove opening <?php

echo "2. Temporäre tracked version.php erstellt\n";
echo "   Temp-Pfad: $tempVersionPath\n\n";

// Nun bootstrap laden, aber version.php ersetzen
echo "3. Lade bootstrap.php (mit Tracking)...\n\n";

// Wir müssen einen Trick anwenden - wir können require_once nicht wirklich überschreiben
// Also: Lade bootstrap.php direkt und schaue die Includes an

echo "=== ALTERNATIVE: Zeige alle require/include in bootstrap.php ===\n\n";

$bootstrapPath = __DIR__ . '/includes/bootstrap.php';
$bootstrapContent = file_get_contents($bootstrapPath);

// Finde alle require/include statements
preg_match_all('/\b(require_once|require|include_once|include)\s+([^;]+);/i', $bootstrapContent, $matches, PREG_SET_ORDER);

echo "Gefundene Includes in bootstrap.php:\n";
foreach ($matches as $idx => $match) {
    echo "  [{$idx}] {$match[1]} {$match[2]}\n";
}

echo "\n=== TESTE PFAD-AUFLÖSUNG ===\n\n";

// Teste verschiedene Pfade zu version.php
$testPaths = [
    '__DIR__ . "/version.php"' => __DIR__ . '/includes/version.php',
    'dirname(__FILE__) . "/version.php"' => dirname(__FILE__) . '/includes/version.php',
    'realpath(__DIR__ . "/version.php")' => realpath(__DIR__ . '/includes/version.php'),
    'aus bootstrap.php: __DIR__ . "/version.php"' => dirname(__DIR__ . '/includes/bootstrap.php') . '/version.php',
];

foreach ($testPaths as $label => $path) {
    echo "{$label}:\n";
    echo "  → {$path}\n";
    echo "  Existiert: " . (file_exists($path) ? 'JA' : 'NEIN') . "\n";
    if (file_exists($path)) {
        echo "  realpath: " . realpath($path) . "\n";
    }
    echo "\n";
}

echo "=== LADE JETZT TATSÄCHLICH index.php ===\n\n";

ob_start();
try {
    require __DIR__ . '/index.php';
} catch (Throwable $e) {
    $output = ob_get_clean();
    echo "Output vor Fehler:\n$output\n\n";
    echo "❌ FEHLER beim Laden von index.php:\n";
    echo "  Fehler: " . $e->getMessage() . "\n";
    echo "  Datei: " . $e->getFile() . "\n";
    echo "  Zeile: " . $e->getLine() . "\n\n";
    echo "  Stack Trace:\n";
    foreach ($e->getTrace() as $idx => $frame) {
        $file = $frame['file'] ?? 'unknown';
        $line = $frame['line'] ?? '?';
        $func = $frame['function'] ?? 'unknown';
        echo "    [$idx] $file:$line → $func()\n";
    }
}
ob_end_clean();

echo "</pre>";
