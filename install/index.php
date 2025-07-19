<?php
declare(strict_types=1);

$lockFile   = __DIR__ . '/install.lock';
$configDir  = dirname(__DIR__) . '/config';
$configFile = $configDir . '/config.php';
$baseUrl = rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME'])), '/') . '/';

$requirements = [
    'PHP-Version'      => version_compare(PHP_VERSION, '8.0.0', '>='),
    'PDO (MySQL)'      => extension_loaded('pdo_mysql'),
    'Schreibrechte /config' => is_writable($configDir),
];

$ready = !in_array(false, $requirements, true);

if (file_exists($lockFile)) {
    exit('<h2>Installationssperre aktiv</h2><p>Die Anwendung wurde bereits installiert. Entferne <code>install/install.lock</code>, um erneut zu installieren.</p>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    $dbHost     = $_POST['db_host'] ?? '';
    $dbName     = $_POST['db_name'] ?? '';
    $dbUser     = $_POST['db_user'] ?? '';
    $dbPass     = $_POST['db_pass'] ?? '';
    $siteTitle  = trim($_POST['site_title'] ?? 'Meine DVD-Verwaltung');
    $baseUrl    = rtrim(trim($_POST['base_url'] ?? '', "/\\"), '/') . '/';
    $adminEmail = trim($_POST['admin_email'] ?? '');
    $adminPass  = $_POST['admin_password'] ?? '';

    try {
        $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        // config.php speichern
        $configContent = "<?php\nreturn " . var_export([
            'db_host' => $dbHost,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
            'db_charset' => 'uft8mb4',
        ], true) . ";\n";
        file_put_contents($configFile, $configContent);

        // Tabellen anlegen
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(190) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            twofa_secret VARCHAR(255) DEFAULT NULL,
            twofa_enabled TINYINT(1) DEFAULT 0,
            twofa_backup_codes TEXT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS dvds (
            id BIGINT NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            year INT,
            genre VARCHAR(100),
            cover_id VARCHAR(50),
            collection_type VARCHAR(100),
            runtime INT,
            rating_age INT,
            overview TEXT,
            trailer_url VARCHAR(255),
            boxset_parent BIGINT DEFAULT NULL,
            user_id BIGINT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS actors (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            birth_year INT DEFAULT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS film_actor (
            film_id BIGINT NOT NULL,
            actor_id BIGINT NOT NULL,
            role VARCHAR(255),
            PRIMARY KEY (film_id, actor_id),
            FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE,
            FOREIGN KEY (actor_id) REFERENCES actors(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(64) NOT NULL UNIQUE,
            `value` TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

// Standardwerte setzen
$defaultSettings = [
    'site_title'     => $siteTitle, // kommt aus deinem Install-Formular
    'base_url'       => $baseUrl,   // kommt aus deinem Install-Formular
    'language'       => 'de',
    'enable_2fa'     => '0',
    'login_attempts' => '5',
    'smtp_host'      => '',
    'smtp_sender'    => '',
    'version'        => '1.4.1'
];

$stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`) VALUES (:key, :value)");
foreach ($defaultSettings as $key => $value) {
    $stmt->execute(['key' => $key, 'value' => $value]);
}

        // Admin-Benutzer anlegen
        $hashed = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->execute([$adminEmail, $hashed]);

        file_put_contents($lockFile, 'locked');

        echo '<h2>✅ Installation erfolgreich!</h2>';
        echo '<p><a href="' . $baseUrl . 'admin/login.php">Zum Login</a></p>';
        echo '<p style="color:darkred;">🔐 <strong>Wichtig:</strong> Bitte lösche oder verschiebe den Ordner <code>install/</code>.</p>';
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light py-5">

<div class="container">
    <h2 class="mb-4">🎬 DVD-Verwaltung – Installation</h2>

    <ul class="list-group mb-4">
        <?php foreach ($requirements as $label => $status): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <?= $label ?>
                <?= $status ? '<span class="text-success">✔</span>' : '<span class="text-danger">✘</span>' ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (!$ready): ?>
        <div class="alert alert-danger">Bitte erfülle alle Systemanforderungen.</div>
    <?php else: ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="post" class="bg-white border rounded p-4">
            <h4 class="mb-3">Seiteneinstellung</h4>
            <div class="mb-2">
                <label class="form-label">Seitentitel</label>
                <input type="text" name="site_title" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Basis-URL</label>
                <input type="url" name="base_url" class="form-control" required
                       value="<?= htmlspecialchars((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname(dirname($_SERVER['SCRIPT_NAME']))) . '/' ?>">
            </div>

            <h4 class="mb-3">Admin-Zugang</h4>
            <div class="mb-2">
                <label class="form-label">Admin-E-Mail</label>
                <input name="admin_email" type="email" required class="form-control">
            </div>
            <div class="mb-2">
                <label class="form-label">Admin-Passwort</label>
                <input name="admin_password" type="password" required class="form-control">
            </div>

            <h4 class="mb-3">Datenbank</h4>
            <div class="mb-2">
                <label class="form-label">Datenbank-Host</label>
                <input type="text" name="db_host" class="form-control" placeholder="localhost" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Datenbankname</label>
                <input type="text" name="db_name" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Datenbank-Benutzer</label>
                <input type="text" name="db_user" class="form-control" required>
            </div>
            <div class="mb-2">
                <label class="form-label">Datenbank-Passwort</label>
                <input type="password" name="db_pass" class="form-control">
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3">🚀 Installation starten</button>
        </form>

    <?php endif; ?>
</div>

</body>
</html>