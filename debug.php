<?php
/**
 * Debug-Script für DVD Profiler Core-System
 * Temporär zur Fehlerdiagnose verwenden
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
define('DVDPROFILER_DEBUG', true);

echo "<h1>🔍 DVD Profiler Core-System Diagnose</h1>";

// 1. Basis-Pfade prüfen
echo "<h2>📁 Verzeichnisstruktur:</h2>";
$basePath = __DIR__;
echo "Base Path: <code>{$basePath}</code><br>";

$paths = [
    'includes/' => $basePath . '/includes',
    'includes/core/' => $basePath . '/includes/core',
    'config/' => $basePath . '/config',
    'install/install.lock' => $basePath . '/install/install.lock'
];

foreach ($paths as $name => $path) {
    $exists = is_dir($path) || is_file($path);
    $status = $exists ? '✅' : '❌';
    echo "{$status} {$name}: <code>{$path}</code><br>";
}

// 2. Core-Dateien prüfen
echo "<h2>📄 Core-Dateien:</h2>";
$coreFiles = [
    'includes/autoloader.php',
    'includes/bootstrap.php', 
    'includes/version.php',
    'includes/core/Application.php',
    'includes/core/Database.php',
    'includes/core/Settings.php',
    'includes/core/Security.php',
    'includes/core/Session.php',
    'includes/core/Utils.php',
    'includes/core/Validation.php'
];

foreach ($coreFiles as $file) {
    $fullPath = $basePath . '/' . $file;
    $exists = file_exists($fullPath);
    $status = $exists ? '✅' : '❌';
    $size = $exists ? ' (' . number_format(filesize($fullPath)) . ' bytes)' : '';
    echo "{$status} {$file}{$size}<br>";
}

// 3. Autoloader testen (falls vorhanden)
echo "<h2>🔄 Autoloader-Test:</h2>";
$autoloaderPath = $basePath . '/includes/autoloader.php';

if (file_exists($autoloaderPath)) {
    echo "✅ Autoloader gefunden, teste...<br>";
    
    try {
        require_once $autoloaderPath;
        echo "✅ Autoloader geladen<br>";
        
        // Klassen-Existenz prüfen
        $testClasses = [
            'DVDProfiler\\Core\\Application',
            'DVDProfiler\\Core\\Database', 
            'DVDProfiler\\Core\\Settings'
        ];
        
        foreach ($testClasses as $class) {
            $exists = class_exists($class);
            $status = $exists ? '✅' : '❌';
            echo "{$status} Klasse: <code>{$class}</code><br>";
        }
        
    } catch (Exception $e) {
        echo "❌ Autoloader-Fehler: " . htmlspecialchars($e->getMessage()) . "<br>";
    }
} else {
    echo "❌ Autoloader nicht gefunden!<br>";
}

// 4. Bootstrap testen (falls Autoloader funktioniert)
echo "<h2>🚀 Bootstrap-Test:</h2>";
$bootstrapPath = $basePath . '/includes/bootstrap.php';

if (file_exists($bootstrapPath)) {
    echo "✅ Bootstrap gefunden<br>";
    
    try {
        if (class_exists('DVDProfiler\\Core\\Application')) {
            require_once $bootstrapPath;
            echo "✅ Bootstrap erfolgreich geladen!<br>";
            echo "✅ System ist einsatzbereit!<br>";
        } else {
            echo "❌ Core-Klassen nicht verfügbar - Bootstrap übersprungen<br>";
        }
    } catch (Exception $e) {
        echo "❌ Bootstrap-Fehler: " . htmlspecialchars($e->getMessage()) . "<br>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }
} else {
    echo "❌ Bootstrap nicht gefunden!<br>";
}

// 5. Rollback-Option
echo "<h2>🔄 Rollback-Option:</h2>";
$backupPath = $basePath . '/includes/bootstrap.php.backup';
if (file_exists($backupPath)) {
    echo "✅ Backup gefunden: <code>bootstrap.php.backup</code><br>";
    echo "💡 <strong>Rollback-Befehl:</strong><br>";
    echo "<code>mv includes/bootstrap.php.backup includes/bootstrap.php</code><br>";
} else {
    echo "⚠️ Kein Backup gefunden<br>";
}

echo "<h2>📋 Nächste Schritte:</h2>";
echo "<ol>";
echo "<li>❌ markierte Dateien hochladen/erstellen</li>";
echo "<li>Autoloader-Test wiederholen</li>";
echo "<li>Bei Erfolg: <code>debug.php</code> löschen</li>";
echo "<li>Bei Problemen: Rollback durchführen</li>";
echo "</ol>";

echo "<p><strong>⚠️ Wichtig:</strong> Diese Datei nach dem Debug löschen!</p>";
?>