<?php
declare(strict_types=1);

/**
 * Diagnose-Skript für DVD Profiler Liste
 * Prüft, warum die Installation fehlschlägt
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Diagnose - DVD Profiler Liste</title>
    <style>
        body {
            font-family: monospace;
            background: #1a1a2e;
            color: #fff;
            padding: 20px;
            line-height: 1.6;
        }
        .ok { color: #27ae60; }
        .error { color: #e74c3c; }
        .warning { color: #f39c12; }
        .section {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
        }
        h2 { color: #3498db; }
        pre { background: rgba(0,0,0,0.3); padding: 10px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🔍 Diagnose: DVD Profiler Liste</h1>

    <?php
    $errors = [];
    $warnings = [];

    // === 1. PHP & System ===
    echo '<div class="section">';
    echo '<h2>1. PHP & System</h2>';
    echo '<pre>';
    echo 'PHP Version: ' . PHP_VERSION . "\n";
    echo 'OS: ' . PHP_OS . "\n";
    echo 'User: ' . get_current_user() . " (UID: " . getmyuid() . ")\n";
    echo 'Server Software: ' . ($_SERVER['SERVER_SOFTWARE'] ?? 'CLI') . "\n";
    echo '</pre>';
    echo '</div>';

    // === 2. Verzeichnisse & Pfade ===
    echo '<div class="section">';
    echo '<h2>2. Verzeichnisse & Pfade</h2>';

    $baseDir = __DIR__;
    $configDir = $baseDir . '/config';
    $installDir = $baseDir . '/install';
    $configFile = $configDir . '/config.php';
    $lockFile = $installDir . '/install.lock';

    echo '<pre>';
    echo "Base Dir: $baseDir\n\n";

    // Config-Verzeichnis
    echo "Config-Verzeichnis: $configDir\n";
    if (!file_exists($configDir)) {
        echo '<span class="error">  ❌ Existiert NICHT!</span>' . "\n";
        $errors[] = 'Config-Verzeichnis existiert nicht';
    } else {
        echo '<span class="ok">  ✅ Existiert</span>' . "\n";
        echo '  Beschreibbar: ' . (is_writable($configDir) ? '<span class="ok">JA</span>' : '<span class="error">NEIN</span>') . "\n";
        echo '  Permissions: ' . substr(sprintf('%o', fileperms($configDir)), -4) . "\n";
        echo '  Owner: ' . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($configDir))['name'] : fileowner($configDir)) . "\n";

        if (!is_writable($configDir)) {
            $errors[] = 'Config-Verzeichnis ist nicht beschreibbar!';
        }
    }

    echo "\n";

    // Install-Verzeichnis
    echo "Install-Verzeichnis: $installDir\n";
    if (!file_exists($installDir)) {
        echo '<span class="error">  ❌ Existiert NICHT!</span>' . "\n";
        $errors[] = 'Install-Verzeichnis existiert nicht';
    } else {
        echo '<span class="ok">  ✅ Existiert</span>' . "\n";
        echo '  Beschreibbar: ' . (is_writable($installDir) ? '<span class="ok">JA</span>' : '<span class="error">NEIN</span>') . "\n";
        echo '  Permissions: ' . substr(sprintf('%o', fileperms($installDir)), -4) . "\n";
        echo '  Owner: ' . (function_exists('posix_getpwuid') ? posix_getpwuid(fileowner($installDir))['name'] : fileowner($installDir)) . "\n";

        if (!is_writable($installDir)) {
            $errors[] = 'Install-Verzeichnis ist nicht beschreibbar!';
        }
    }

    echo '</pre>';
    echo '</div>';

    // === 3. Dateien prüfen ===
    echo '<div class="section">';
    echo '<h2>3. Installations-Dateien</h2>';
    echo '<pre>';

    // Config-Datei
    echo "Config-Datei: $configFile\n";
    if (file_exists($configFile)) {
        echo '<span class="warning">  ⚠️  Existiert bereits</span>' . "\n";
        echo '  Größe: ' . filesize($configFile) . " Bytes\n";
        echo '  Geändert: ' . date('Y-m-d H:i:s', filemtime($configFile)) . "\n";
        $warnings[] = 'Config-Datei existiert bereits';
    } else {
        echo '<span class="ok">  ✅ Existiert noch nicht (OK für Neuinstallation)</span>' . "\n";
    }

    echo "\n";

    // Lock-Datei
    echo "Lock-Datei: $lockFile\n";
    if (file_exists($lockFile)) {
        echo '<span class="warning">  ⚠️  Existiert bereits</span>' . "\n";
        echo '  Größe: ' . filesize($lockFile) . " Bytes\n";
        echo '  Inhalt: ' . file_get_contents($lockFile) . "\n";
        echo '  Geändert: ' . date('Y-m-d H:i:s', filemtime($lockFile)) . "\n";
        $warnings[] = 'Lock-Datei existiert bereits - Installation wird blockiert!';
    } else {
        echo '<span class="ok">  ✅ Existiert noch nicht (OK für Neuinstallation)</span>' . "\n";
    }

    echo '</pre>';
    echo '</div>';

    // === 4. Schreibtest ===
    echo '<div class="section">';
    echo '<h2>4. Schreibtest</h2>';
    echo '<pre>';

    $testFile = $configDir . '/.writetest';
    $testContent = 'TEST-' . time();

    echo "Versuch Config-Datei zu schreiben...\n";
    $result = @file_put_contents($testFile, $testContent);

    if ($result === false) {
        $error = error_get_last();
        echo '<span class="error">❌ FEHLER beim Schreiben!</span>' . "\n";
        echo '   Fehler: ' . ($error['message'] ?? 'Unbekannt') . "\n";
        $errors[] = 'Kann nicht in config/ schreiben!';
    } else {
        echo '<span class="ok">✅ Schreiben erfolgreich (' . $result . ' Bytes)</span>' . "\n";

        // Datei wieder lesen
        $readBack = @file_get_contents($testFile);
        if ($readBack === $testContent) {
            echo '<span class="ok">✅ Lesen erfolgreich</span>' . "\n";
        } else {
            echo '<span class="error">❌ Lesefehler!</span>' . "\n";
            $errors[] = 'Kann geschriebene Datei nicht lesen!';
        }

        // Test-Datei löschen
        @unlink($testFile);
    }

    echo '</pre>';
    echo '</div>';

    // === 5. PHP Extensions ===
    echo '<div class="section">';
    echo '<h2>5. PHP Extensions</h2>';
    echo '<pre>';

    $required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
    foreach ($required as $ext) {
        $loaded = extension_loaded($ext);
        echo $ext . ': ' . ($loaded ? '<span class="ok">✅ Geladen</span>' : '<span class="error">❌ Fehlt</span>') . "\n";
        if (!$loaded) {
            $errors[] = "PHP Extension '$ext' fehlt!";
        }
    }

    echo '</pre>';
    echo '</div>';

    // === 6. Zusammenfassung ===
    echo '<div class="section">';
    echo '<h2>6. Zusammenfassung</h2>';

    if (empty($errors) && empty($warnings)) {
        echo '<p class="ok"><strong>✅ Alle Tests bestanden!</strong></p>';
        echo '<p>Die Installation sollte funktionieren.</p>';
    } else {
        if (!empty($errors)) {
            echo '<p class="error"><strong>❌ Kritische Fehler gefunden:</strong></p>';
            echo '<ul>';
            foreach ($errors as $error) {
                echo "<li class=\"error\">$error</li>";
            }
            echo '</ul>';
        }

        if (!empty($warnings)) {
            echo '<p class="warning"><strong>⚠️  Warnungen:</strong></p>';
            echo '<ul>';
            foreach ($warnings as $warning) {
                echo "<li class=\"warning\">$warning</li>";
            }
            echo '</ul>';
        }
    }

    echo '</div>';

    // === 7. Empfohlene Aktionen ===
    echo '<div class="section">';
    echo '<h2>7. Empfohlene Aktionen</h2>';

    if (file_exists($lockFile)) {
        echo '<p class="warning">⚠️  Die Lock-Datei existiert bereits!</p>';
        echo '<p>Um die Installation erneut durchzuführen, löschen Sie:</p>';
        echo '<pre>rm ' . $lockFile . "\nrm " . $configFile . '</pre>';
    }

    if (!empty($errors)) {
        echo '<p class="error">Beheben Sie die oben genannten Fehler vor der Installation.</p>';

        if (strpos(implode('', $errors), 'nicht beschreibbar') !== false) {
            echo '<p>Setzen Sie die Schreibrechte:</p>';
            echo '<pre>chmod -R 775 ' . $configDir . "\nchmod 775 " . $installDir . "\nchown -R www-data:www-data " . $configDir . '</pre>';
        }
    }

    echo '</div>';
    ?>

</body>
</html>
