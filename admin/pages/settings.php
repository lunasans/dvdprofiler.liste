<?php
declare(strict_types=1);

$lockFile   = __DIR__ . '/install.lock';
$configDir  = dirname(__DIR__) . '/config';
$configFile = $configDir . '/config.php';

$requirements = [
    'PHP-Version'      => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO (MySQL)'      => extension_loaded('pdo_mysql'),
    'Schreibrechte /config' => is_writable($configDir),
];

$ready = !in_array(false, $requirements, true);

// Installation bereits gesperrt?
if (file_exists($lockFile)) {
    exit('<h2>Installationssperre aktiv</h2><p>Die Anwendung wurde bereits installiert. Entferne <code>install/install.lock</code>, um erneut zu installieren.</p>');
}

// Formular abgeschickt
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    $dbHost = $_POST['db_host'] ?? '';
    $dbName = $_POST['db_name'] ?? '';
    $dbUser = $_POST['db_user'] ?? '';
    $dbPass = $_POST['db_pass'] ?? '';

    try {
        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // config.php schreiben
        $config = <<<PHP
<?php
return [
    'db_host' => '$dbHost',
    'db_name' => '$dbName',
    'db_user' => '$dbUser',
    'db_pass' => '$dbPass'
];
PHP;
        file_put_contents($configFile, $config);

        // settings-Tabelle anlegen
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(100) PRIMARY KEY,
                `value` TEXT NOT NULL
            );
        ");

        // Basis-URL berechnen
        $baseUrl = rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' .
            $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';

        // Default-Einstellungen setzen
        $defaultSettings = [
            'site_title'     => 'Meine DVD-Verwaltung',
            'base_url'       => $baseUrl,
            'language'       => 'de',
            'enable_2fa'     => '0',
            'login_attempts' => '5',
            'smtp_host'      => '',
            'smtp_sender'    => ''
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (:key, :value)");
        foreach ($defaultSettings as $key => $value) {
            $stmt->execute(['key' => $key, 'value' => $value]);
        }

        // Lock-Datei setzen
        file_put_contents($lockFile, 'locked');

        echo '<h2>Installation abgeschlossen ✅</h2>';
        echo '<p>Du kannst das System jetzt verwenden. <a href="../index.php">Zur Startseite</a></p>';
        exit;

    } catch (PDOException $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Installation</title>
    <style>
        body { font-family: sans-serif; background: #f0f0f0; padding: 2em; }
        form { background: white; padding: 2em; border-radius: 8px; max-width: 500px; margin: auto; }
        input, button { padding: 0.5em; width: 100%; margin-bottom: 1em; }
        .fail { color: red; }
        .ok { color: green; }
    </style>
</head>
<body>

<h2>DVD Verwaltung – Installation</h2>

<ul>
<?php foreach ($requirements as $label => $status): ?>
    <li><?= $label ?>:
        <?= $status ? '<span class="ok">✔</span>' : '<span class="fail">✘</span>' ?>
    </li>
<?php endforeach; ?>
</ul>

<?php if (!$ready): ?>
    <p class="fail">Bitte erfülle alle Systemanforderungen.</p>
<?php else: ?>

    <?php if (!empty($error)): ?>
        <p class="fail"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="text" name="db_host" placeholder="Datenbank-Host (z. B. localhost)" required>
        <input type="text" name="db_name" placeholder="Datenbankname" required>
        <input type="text" name="db_user" placeholder="Datenbank-Benutzer" required>
        <input type="password" name="db_pass" placeholder="Datenbank-Passwort">
        <button type="submit">Installation starten</button>
    </form>

<?php endif; ?>

</body>
</html>
