<?php
declare(strict_types=1);

$lockFile = __DIR__ . '/install.lock';
$configDir = dirname(__DIR__) . '/config';
$coverDir = dirname(__DIR__) . '/cover';
$configFile = $configDir . '/config.php';

$requirements = [
    'PHP-Version ≥ 8.1'              => version_compare(PHP_VERSION, '8.1', '>='),
    'PDO-Erweiterung (pdo)'          => extension_loaded('pdo'),
    'PDO MySQL-Erweiterung'          => extension_loaded('pdo_mysql'),
    '/config-Verzeichnis beschreibbar' => is_dir($configDir) ? is_writable($configDir) : is_writable(dirname(__DIR__)),
];

$allRequirementsMet = !in_array(false, $requirements, true);
$errors = [];
$success = '';

if (file_exists($lockFile)) {
    exit('<h2>🔒 Die Installation wurde bereits durchgeführt. Entferne <code>install/install.lock</code>, um sie erneut zu starten.</h2>');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $allRequirementsMet) {
    $host     = trim($_POST['host']);
    $dbname   = trim($_POST['dbname']);
    $user     = trim($_POST['user']);
    $pass     = trim($_POST['pass']);
    $admin    = trim($_POST['admin_email']);
    $admin_pw = $_POST['admin_password'];

    $charset = 'utf8mb4';
    $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);

        $stmt = $pdo->query("SHOW TABLES");
        if ($stmt->rowCount() > 0) {
            $errors[] = 'Die Datenbank ist nicht leer. Bitte verwende eine leere Datenbank.';
        } else {
            // Tabellen einzeln erstellen
            try {
                $pdo->exec("
                    CREATE TABLE users (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        email VARCHAR(255) NOT NULL UNIQUE,
                        password VARCHAR(255) NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    );
                ");
            } catch (PDOException $e) {
                $errors[] = "Tabelle <code>users</code>: " . $e->getMessage();
            }

            try {
                $pdo->exec("
                        CREATE TABLE dvds (
                        id INT PRIMARY KEY,
                        title VARCHAR(255) NOT NULL,
                        year INT,
                        genre VARCHAR(100),
                        cover_id VARCHAR(50),
                        collection_type VARCHAR(100),
                        runtime INT,
                        rating_age VARCHAR(10),
                        overview TEXT,
                        trailer_url VARCHAR(255),
                        boxset_parent VARCHAR(50),
                        user_id INT,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                        );
                    ");

            } catch (PDOException $e) {
                $errors[] = "❌ Tabelle <code>dvds</code>: " . $e->getMessage();
            }

            try {
                $pdo->exec("
                    CREATE TABLE actors (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        dvd_id INT,
                        firstname VARCHAR(100),
                        lastname VARCHAR(100),
                        role VARCHAR(255),
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (dvd_id) REFERENCES dvds(id) ON DELETE CASCADE
                    );
                ");
            } catch (PDOException $e) {
                $errors[] = "❌ Tabelle <code>actors</code>: " . $e->getMessage();
            }

            if (empty($errors)) {
                try {
                    $hashedPw = password_hash($admin_pw, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
                    $stmt->execute([$admin, $hashedPw]);

                    // Admin-ID speichern
                    $adminId = (int)$pdo->lastInsertId();

                    // Admin automatisch einloggen
                    session_start();
                    $_SESSION['user_id'] = $adminId;
                    $_SESSION['email'] = $admin;

                    $success .= "<br>🆔 Admin-ID wurde gespeichert und automatisch eingeloggt.";

                } catch (PDOException $e) {
                    $errors[] = "❌ Admin-Benutzer: " . $e->getMessage();
                }

                $config = <<<PHP
<?php
declare(strict_types=1);

\$host = '$host';
\$db   = '$dbname';
\$user = '$user';
\$pass = '$pass';
\$charset = 'utf8mb4';

\$dsn = "mysql:host=\$host;dbname=\$db;charset=\$charset";

\$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    \$pdo = new PDO(\$dsn, \$user, \$pass, \$options);
} catch (PDOException \$e) {
    die("❌ Datenbankverbindung fehlgeschlagen: " . \$e->getMessage());
}
PHP;

                if (!file_put_contents($configFile, $config)) {
                    $errors[] = '❌ config.php konnte nicht geschrieben werden.';
                } else {
                    // install.lock setzen
                    file_put_contents($lockFile, "Installiert am " . date('Y-m-d H:i:s'));
                    $success = '✅ Installation erfolgreich abgeschlossen! Du kannst das <code>install/</code>-Verzeichnis jetzt löschen.';

                    // cover/ anlegen
                    if (!is_dir($coverDir)) {
                        if (mkdir($coverDir, 0755, true)) {
                            $success .= '<br>📁 Verzeichnis <code>cover/</code> wurde erfolgreich erstellt.';
                        } else {
                            $errors[] = '❌ Verzeichnis <code>cover/</code> konnte nicht erstellt werden.';
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $errors[] = "Verbindungsfehler: " . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <h1 class="mb-4">📦 Projekt-Installer</h1>

    <h4>Systemvoraussetzungen</h4>
    <ul class="list-group mb-4">
        <?php foreach ($requirements as $check => $ok): ?>
            <li class="list-group-item d-flex justify-content-between">
                <?= $check ?>
                <span class="badge <?= $ok ? 'bg-success' : 'bg-danger' ?>">
                    <?= $ok ? '✔️ OK' : '❌ Fehler' ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <ul><?php foreach ($errors as $e) echo "<li>$e</li>"; ?></ul>
        </div>
    <?php elseif ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if (!$success && $allRequirementsMet): ?>
    <form method="post" class="card p-4 bg-white shadow-sm">
        <h4 class="mb-3">Datenbankverbindung</h4>
        <div class="mb-2">
            <label class="form-label">Datenbank-Host</label>
            <input name="host" value="localhost" required class="form-control">
        </div>
        <div class="mb-2">
            <label class="form-label">Datenbankname</label>
            <input name="dbname" required class="form-control">
        </div>
        <div class="mb-2">
            <label class="form-label">Datenbank-Benutzer</label>
            <input name="user" required class="form-control">
        </div>
        <div class="mb-2">
            <label class="form-label">Datenbank-Passwort</label>
            <input name="pass" type="password" class="form-control">
        </div>

        <hr class="my-4">

        <h4 class="mb-3">Admin-Zugang</h4>
        <div class="mb-2">
            <label class="form-label">Admin-E-Mail</label>
            <input name="admin_email" required class="form-control" type="email">
        </div>
        <div class="mb-2">
            <label class="form-label">Admin-Passwort</label>
            <input name="admin_password" type="password" required class="form-control">
        </div>

        <button class="btn btn-success mt-4">🚀 Installation starten</button>
    </form>
    <?php elseif (!$allRequirementsMet): ?>
        <div class="alert alert-warning mt-3">
            ❗ Bitte erfülle zuerst alle Systemvoraussetzungen, bevor du mit der Installation fortfährst.
        </div>
    <?php endif; ?>
</div>
</body>
</html>
