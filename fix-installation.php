<?php
declare(strict_types=1);

/**
 * Fix-Skript für Installation-Redirect-Problem
 *
 * Problem: Installation erstellt Lock-Datei in /admin/.install.lock
 *          aber bootstrap.php sucht sie in /install/install.lock
 *
 * Lösung: Dieses Skript kopiert/erstellt die Lock-Datei am richtigen Ort
 */

header('Content-Type: text/html; charset=utf-8');

$baseDir = __DIR__;
$adminLockFile = $baseDir . '/admin/.install.lock';
$installLockFile = $baseDir . '/install/install.lock';
$configFile = $baseDir . '/config/config.php';

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Installation Fix - DVD Profiler Liste</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #1a1a2e;
            color: #fff;
            padding: 40px;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: rgba(255,255,255,0.1);
            padding: 30px;
            border-radius: 10px;
        }
        h1 { color: #3498db; }
        .ok { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .step {
            background: rgba(0,0,0,0.3);
            padding: 15px;
            margin: 15px 0;
            border-left: 4px solid #3498db;
            border-radius: 5px;
        }
        pre {
            background: rgba(0,0,0,0.5);
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .btn {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 Installation Fix</h1>
        <p>Dieses Skript behebt das Problem, dass Sie nach der Installation immer noch auf install/index.php weitergeleitet werden.</p>

        <hr>

        <?php
        echo '<h2>Diagnose:</h2>';

        echo '<div class="step">';
        echo '<strong>1. Lock-Datei in /admin/.install.lock:</strong><br>';
        if (file_exists($adminLockFile)) {
            echo '<span class="ok">✅ Existiert</span><br>';
            echo 'Inhalt: <pre>' . htmlspecialchars(file_get_contents($adminLockFile)) . '</pre>';
        } else {
            echo '<span class="warning">⚠️  Existiert nicht</span>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<strong>2. Lock-Datei in /install/install.lock (REQUIRED):</strong><br>';
        if (file_exists($installLockFile)) {
            echo '<span class="ok">✅ Existiert bereits</span><br>';
            echo 'Inhalt: <pre>' . htmlspecialchars(file_get_contents($installLockFile)) . '</pre>';
        } else {
            echo '<span class="error">❌ Fehlt - muss erstellt werden!</span>';
        }
        echo '</div>';

        echo '<div class="step">';
        echo '<strong>3. Config-Datei in /config/config.php:</strong><br>';
        if (file_exists($configFile)) {
            echo '<span class="ok">✅ Existiert</span>';
        } else {
            echo '<span class="error">❌ Fehlt - Installation unvollständig!</span>';
        }
        echo '</div>';

        // FIX AUSFÜHREN
        if (!file_exists($installLockFile)) {
            echo '<hr><h2>Fix wird angewendet...</h2>';

            $lockContent = null;

            // Versuche Lock-Datei von /admin zu kopieren
            if (file_exists($adminLockFile)) {
                $lockContent = file_get_contents($adminLockFile);
                echo '<div class="step"><span class="ok">✓</span> Lock-Datei von /admin/.install.lock gelesen</div>';
            } else {
                // Erstelle neue Lock-Datei
                $lockContent = json_encode([
                    'installed_at' => date('Y-m-d H:i:s'),
                    'version' => '1.4.7',
                    'admin_email' => 'admin@localhost',
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    'note' => 'Lock file created by fix-installation.php',
                    'fixed' => true
                ], JSON_PRETTY_PRINT);
                echo '<div class="step"><span class="warning">⚠</span> Neue Lock-Datei wird erstellt</div>';
            }

            // Schreibe Lock-Datei an den richtigen Ort
            if (file_put_contents($installLockFile, $lockContent)) {
                echo '<div class="step"><span class="ok">✅ Lock-Datei erfolgreich erstellt in /install/install.lock!</span></div>';
                echo '<p class="ok"><strong>Problem behoben!</strong></p>';
                echo '<p>Die Anwendung sollte jetzt funktionieren und nicht mehr zur Installation weiterleiten.</p>';
                echo '<a href="index.php" class="btn">🚀 Zur Anwendung</a>';
                echo '<a href="admin/login.php" class="btn">🔐 Zum Login</a>';
            } else {
                echo '<div class="step"><span class="error">❌ FEHLER: Konnte Lock-Datei nicht erstellen!</span></div>';
                echo '<p>Mögliche Ursachen:</p>';
                echo '<ul>';
                echo '<li>Keine Schreibrechte im /install Verzeichnis</li>';
                echo '<li>SELinux oder andere Security-Features blockieren das Schreiben</li>';
                echo '</ul>';
                echo '<p><strong>Manuelle Lösung:</strong></p>';
                echo '<pre>chmod 775 ' . dirname($installLockFile) . '
chown -R www-data:www-data ' . dirname($installLockFile) . '</pre>';
            }
        } else {
            echo '<hr><h2 class="ok">✅ Alles OK!</h2>';
            echo '<p>Die Lock-Datei existiert bereits am richtigen Ort. Das Redirect-Problem sollte behoben sein.</p>';
            echo '<a href="index.php" class="btn">🚀 Zur Anwendung</a>';
            echo '<a href="admin/login.php" class="btn">🔐 Zum Login</a>';
        }
        ?>

        <hr>
        <h2>Hintergrund:</h2>
        <p>Die Installation erstellt die Lock-Datei möglicherweise in <code>/admin/.install.lock</code>,
           aber <code>includes/bootstrap.php</code> sucht sie in <code>/install/install.lock</code>.</p>
        <p>Dieses Skript stellt sicher, dass die Lock-Datei am richtigen Ort existiert.</p>

        <hr>
        <p><small>Nach erfolgreicher Behebung können Sie dieses Skript löschen: <code>rm fix-installation.php</code></small></p>
    </div>
</body>
</html>
