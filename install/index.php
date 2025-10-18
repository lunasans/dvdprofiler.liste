<?php
declare(strict_types=1);

// Installations-Konstanten
define('DB_VERSION', '1.4.7');
define('MIN_PHP_VERSION', '7.4.0');
define('RECOMMENDED_PHP_VERSION', '8.0.0');

// Session für CSRF-Schutz
session_start();
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = false;
$errors = [];
$installationSteps = [];

// System-Anforderungen prüfen
$requirements = [
    'PHP Version ≥ ' . MIN_PHP_VERSION => version_compare(PHP_VERSION, MIN_PHP_VERSION, '>='),
    'PDO Extension' => extension_loaded('pdo'),
    'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
    'Mbstring Extension' => extension_loaded('mbstring'),
    'JSON Extension' => extension_loaded('json'),
    'OpenSSL Extension' => extension_loaded('openssl'),
    'Session Support' => function_exists('session_start'),
    'Zlib Extension' => extension_loaded('zlib'),
];

$optional_requirements = [
    'ZIP Extension' => extension_loaded('zip'),
    'CURL Extension' => extension_loaded('curl'),
    'GD Extension' => extension_loaded('gd'),
    'EXIF Extension' => extension_loaded('exif'),
];

$ready = !in_array(false, $requirements);

// Installation verarbeiten
if ($_POST && $ready) {
    // CSRF-Schutz
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Sicherheitsfehler: Ungültiger CSRF-Token';
    } else {
        try {
            $installationSteps[] = '🚀 Installation gestartet...';

            // Eingaben validieren und sanitieren
            $siteTitle = trim(filter_var($_POST['site_title'] ?? '', FILTER_SANITIZE_STRING));
            $baseUrl = trim(filter_var($_POST['base_url'] ?? '', FILTER_SANITIZE_URL));
            $dbHost = trim(filter_var($_POST['db_host'] ?? '', FILTER_SANITIZE_STRING));
            $dbPort = (int)($_POST['db_port'] ?? 3306);
            $dbName = trim(filter_var($_POST['db_name'] ?? '', FILTER_SANITIZE_STRING));
            $dbUser = trim(filter_var($_POST['db_user'] ?? '', FILTER_SANITIZE_STRING));
            $dbPass = $_POST['db_pass'] ?? '';

            if (empty($siteTitle) || empty($baseUrl) || empty($dbHost) || empty($dbName) || empty($dbUser)) {
                throw new InvalidArgumentException('Alle Pflichtfelder müssen ausgefüllt werden');
            }

            $installationSteps[] = '✅ Eingaben validiert';

            // Datenbankverbindung testen
            $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
            
            try {
                $pdo = new PDO($dsn, $dbUser, $dbPass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]);
                $installationSteps[] = '✅ Datenbankverbindung hergestellt';
            } catch (PDOException $e) {
                throw new Exception('Datenbankverbindung fehlgeschlagen: ' . $e->getMessage());
            }

            // Datenbank erstellen falls nicht vorhanden
            try {
                $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` 
                           CHARACTER SET utf8mb4 
                           COLLATE utf8mb4_unicode_ci");
                $pdo->exec("USE `{$dbName}`");
                $installationSteps[] = '✅ Datenbank erstellt/ausgewählt';
            } catch (PDOException $e) {
                throw new Exception('Datenbank-Erstellung fehlgeschlagen: ' . $e->getMessage());
            }

            // Tabellen erstellen
            $tables = [
                // 1. Users-Tabelle
                "CREATE TABLE IF NOT EXISTS users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(255) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    totp_secret VARCHAR(32) NULL,
                    is_totp_enabled TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    last_login TIMESTAMP NULL,
                    login_attempts INT DEFAULT 0,
                    locked_until TIMESTAMP NULL,
                    INDEX idx_email (email),
                    INDEX idx_totp (is_totp_enabled)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 2. DVDs-Tabelle
                "CREATE TABLE IF NOT EXISTS dvds (
                    id BIGINT PRIMARY KEY,
                    title VARCHAR(255) NOT NULL,
                    year INT DEFAULT NULL,
                    genre TEXT DEFAULT NULL,
                    runtime INT DEFAULT NULL,
                    rating_age INT DEFAULT NULL,
                    overview TEXT DEFAULT NULL,
                    cover_id VARCHAR(100) DEFAULT NULL,
                    collection_type VARCHAR(50) DEFAULT NULL,
                    boxset_parent BIGINT DEFAULT NULL,
                    trailer_url VARCHAR(500) DEFAULT NULL,
                    user_id INT DEFAULT NULL,
                    view_count INT DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_title (title),
                    INDEX idx_year (year),
                    INDEX idx_genre (genre(100)),
                    INDEX idx_boxset_parent (boxset_parent),
                    INDEX idx_user_id (user_id),
                    INDEX idx_view_count (view_count),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 3. Actors-Tabelle
                "CREATE TABLE IF NOT EXISTS actors (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    first_name VARCHAR(100) NOT NULL,
                    last_name VARCHAR(100) NOT NULL,
                    birth_year INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_name (last_name, first_name),
                    INDEX idx_birth_year (birth_year)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 4. Film-Actor Junction-Tabelle
                "CREATE TABLE IF NOT EXISTS film_actor (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    film_id BIGINT NOT NULL,
                    actor_id INT NOT NULL,
                    role VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_film_actor (film_id, actor_id),
                    FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE,
                    FOREIGN KEY (actor_id) REFERENCES actors(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 5. Settings-Tabelle
                "CREATE TABLE IF NOT EXISTS settings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    `key` VARCHAR(100) UNIQUE NOT NULL,
                    `value` TEXT NOT NULL,
                    description TEXT NULL,
                    is_public TINYINT(1) DEFAULT 0,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_key (`key`),
                    INDEX idx_public (is_public)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 6. Backup-Codes Tabelle
                "CREATE TABLE IF NOT EXISTS user_backup_codes (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    code VARCHAR(255) NOT NULL,
                    used_at TIMESTAMP NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_code (code),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 7. GitHub-Cache Tabelle
                "CREATE TABLE IF NOT EXISTS github_cache (
                    cache_key VARCHAR(50) PRIMARY KEY,
                    data JSON NOT NULL,
                    timestamp INT NOT NULL,
                    INDEX idx_timestamp (timestamp)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 8. Activity-Log Tabelle
                "CREATE TABLE IF NOT EXISTS activity_log (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NULL,
                    action VARCHAR(100) NOT NULL,
                    details JSON NULL,
                    ip_address VARCHAR(45) NULL,
                    user_agent TEXT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_created_at (created_at),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 9. User Ratings Tabelle
                "CREATE TABLE IF NOT EXISTS user_ratings (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    film_id BIGINT NOT NULL,
                    user_id INT NOT NULL,
                    rating DECIMAL(2,1) NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_film (user_id, film_id),
                    FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 10. User Wishlist Tabelle
                "CREATE TABLE IF NOT EXISTS user_wishlist (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    film_id BIGINT NOT NULL,
                    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_film_wish (user_id, film_id),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

                // 11. User Watched Tabelle
                "CREATE TABLE IF NOT EXISTS user_watched (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    film_id BIGINT NOT NULL,
                    watched_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_user_film_watched (user_id, film_id),
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            ];

            $pdo->beginTransaction();

            foreach ($tables as $index => $sql) {
                try {
                    $pdo->exec($sql);
                    $installationSteps[] = '✅ Tabelle ' . ($index + 1) . ' erstellt';
                } catch (PDOException $e) {
                    throw new Exception('Tabellen-Erstellung fehlgeschlagen bei Tabelle ' . ($index + 1) . ': ' . $e->getMessage());
                }
            }

            // Standard-Einstellungen erstellen
            $defaultSettings = [
                ['site_title', $siteTitle, 'Website-Titel', 1],
                ['base_url', $baseUrl, 'Basis-URL der Website', 0],
                ['theme', 'default', 'Standard-Theme', 1],
                ['environment', 'production', 'Umgebung (development/production)', 0],
                ['max_upload_size', '50', 'Maximale Upload-Größe in MB', 0],
                ['session_timeout', '3600', 'Session-Timeout in Sekunden', 0],
                ['pagination_limit', '20', 'Elemente pro Seite', 1],
                ['enable_registration', '0', 'Registrierung aktiviert', 0],
                ['maintenance_mode', '0', 'Wartungsmodus aktiviert', 0],
                ['version', DB_VERSION, 'Installierte Version', 0]
            ];

            $settingsStmt = $pdo->prepare("
                INSERT INTO settings (`key`, `value`, description, is_public) 
                VALUES (?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
            ");

            foreach ($defaultSettings as $setting) {
                $settingsStmt->execute($setting);
            }

            $installationSteps[] = '✅ Standard-Einstellungen erstellt';

            // Admin-Benutzer erstellen
            $adminEmail = 'admin@localhost';
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);

            $userStmt = $pdo->prepare("
                INSERT IGNORE INTO users (email, password, created_at) 
                VALUES (?, ?, NOW())
            ");
            $userStmt->execute([$adminEmail, $adminPassword]);

            $installationSteps[] = '✅ Admin-Benutzer erstellt (admin@localhost / admin123)';

            // Cover-Verzeichnis erstellen
            $coverDir = __DIR__ . '/../cover';
            if (!is_dir($coverDir)) {
                if (!mkdir($coverDir, 0755, true)) {
                    $installationSteps[] = '⚠️ Cover-Verzeichnis konnte nicht erstellt werden';
                } else {
                    $installationSteps[] = '✅ Cover-Verzeichnis erstellt';
                }
            } else {
                $installationSteps[] = '✅ Cover-Verzeichnis bereits vorhanden';
            }

            // XML-Verzeichnis erstellen
            $xmlDir = __DIR__ . '/../admin/xml';
            if (!is_dir($xmlDir)) {
                if (!mkdir($xmlDir, 0755, true)) {
                    $installationSteps[] = '⚠️ XML-Verzeichnis konnte nicht erstellt werden';
                } else {
                    $installationSteps[] = '✅ XML-Verzeichnis erstellt';
                }
            } else {
                $installationSteps[] = '✅ XML-Verzeichnis bereits vorhanden';
            }

            $pdo->commit();
            $installationSteps[] = '🎉 Installation erfolgreich abgeschlossen!';
            
            $success = true;

        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollback();
            }
            $errors[] = 'Datenbankfehler: ' . $e->getMessage();
            $installationSteps[] = '❌ Datenbankfehler aufgetreten';
        } catch (InvalidArgumentException $e) {
            $errors[] = $e->getMessage();
            $installationSteps[] = '❌ Validierungsfehler: ' . $e->getMessage();
        } catch (Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollback();
            }
            $errors[] = 'Installationsfehler: ' . $e->getMessage();
            $installationSteps[] = '❌ Unerwarteter Fehler: ' . $e->getMessage();
            error_log('Installation error: ' . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DVD-Verwaltung Installation</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --background: #1a1a2e;
            --color: #ffffff;
            --primary-color: #0f3460;
            --secondary-color: #2c3e50;
            --glass-color: rgba(145, 145, 145, 0.12);
            --glass-border: hsla(0, 0%, 65%, 0.158);
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --info-color: #3498db;
            --radius-sm: 6px;
            --radius-md: 12px;
            --radius-lg: 18px;
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 24px;
            --space-xl: 32px;
            --transition-fast: 0.2s;
            --transition-slow: 0.4s;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            font-family: "Roboto", sans-serif;
            background: var(--background);
            color: var(--color);
            letter-spacing: 1px;
            transition: background var(--transition-fast) ease;
            min-height: 100vh;
            padding: 2rem 1rem;
            position: relative;
            overflow-x: hidden;
        }

        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 15s infinite linear;
        }

        .container {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            padding: 2rem 0;
        }

        .install-container {
            width: 100%;
            max-width: 700px;
        }

        .header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), #3498db);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header h2 {
            font-size: 1.2rem;
            opacity: 0.8;
            font-weight: 300;
        }

        .version-badge {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            margin-left: 0.5rem;
        }

        .card {
            border: 1px solid var(--glass-border);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            border-radius: var(--radius-lg);
            backdrop-filter: blur(20px);
            background: var(--glass-color);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .card-header {
            background: rgba(15, 52, 96, 0.3);
            padding: 1.5rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .card-header h5 {
            margin: 0;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .card-body {
            padding: 2rem;
        }

        .card-footer {
            background: rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            border-top: 1px solid var(--glass-border);
        }

        .alert {
            padding: 1.5rem;
            border-radius: var(--radius-md);
            margin-bottom: 2rem;
            backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .alert-success {
            background: rgba(39, 174, 96, 0.2);
            border-color: var(--success-color);
        }

        .alert-warning {
            background: rgba(243, 156, 18, 0.2);
            border-color: var(--warning-color);
        }

        .alert-danger {
            background: rgba(231, 76, 60, 0.2);
            border-color: var(--danger-color);
        }

        .alert-info {
            background: rgba(52, 152, 219, 0.2);
            border-color: var(--info-color);
        }

        .alert h4, .alert h5 {
            margin-top: 0;
            margin-bottom: 1rem;
        }

        .alert ul {
            margin-bottom: 0;
            padding-left: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--color);
        }

        .form-control {
            display: block;
            width: 100%;
            padding: 14.5px;
            background: rgba(145, 145, 145, 0.1);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            color: var(--color);
            font-size: 15px;
            font-weight: 400;
            letter-spacing: 0.8px;
            transition: all var(--transition-fast) ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            background: rgba(145, 145, 145, 0.15);
            box-shadow: 0 0 0 3px rgba(15, 52, 96, 0.1);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-text {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 0.5rem;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -0.75rem;
        }

        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 0.75rem;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius-sm);
            font-size: 16px;
            font-weight: 500;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-fast) ease;
            background: linear-gradient(135deg, var(--primary-color), #3498db);
            color: white;
            letter-spacing: 0.5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.3);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-lg {
            padding: 16px 32px;
            font-size: 18px;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #2ecc71);
        }

        .d-grid {
            display: grid;
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-4 {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--color);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--glass-border);
        }

        .requirement {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .requirement:last-child {
            border-bottom: none;
        }

        .requirement-ok {
            color: var(--success-color);
            font-weight: 500;
        }

        .requirement-fail {
            color: var(--danger-color);
            font-weight: 500;
        }

        .installation-steps {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-sm);
            padding: 1rem;
            background: rgba(0, 0, 0, 0.2);
        }

        .installation-step {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            animation: fadeInUp 0.3s ease forwards;
            opacity: 0;
            transform: translateY(10px);
            animation-delay: calc(var(--step-index) * 0.1s);
        }

        .installation-step:last-child {
            border-bottom: none;
        }

        .theme-btn-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .theme-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--glass-border);
            cursor: pointer;
            margin: 5px;
            transition: all var(--transition-fast) ease;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .theme-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }

        @keyframes float {
            0% {
                transform: translateY(100vh) rotate(0deg);
                opacity: 0;
            }
            10% {
                opacity: 1;
            }
            90% {
                opacity: 1;
            }
            100% {
                transform: translateY(-100px) rotate(360deg);
                opacity: 0;
            }
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }

            .header h1 {
                font-size: 2rem;
            }

            .install-container {
                padding: 1rem;
            }

            .theme-btn-container {
                top: 10px;
                right: 10px;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 1rem 0.5rem;
            }

            .card-body {
                padding: 1rem;
            }

            .header h1 {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>
    <!-- Floating particles -->
    <div class="particles" id="particles"></div>

    <!-- Theme selector -->
    <div class="theme-btn-container" id="theme-btn-container"></div>

    <div class="container">
        <div class="install-container">
            <div class="header">
                <h1>🎬 DVD-Verwaltung</h1>
                <h2>Installation <span class="version-badge">v<?= DB_VERSION ?></span></h2>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <h5>❌ Installationsfehler:</h5>
                    <ul class="mb-0">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (!empty($installationSteps)): ?>
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">📋 Installationsfortschritt</h5>
                    </div>
                    <div class="card-body">
                        <div class="installation-steps" id="installation-steps">
                            <?php foreach ($installationSteps as $index => $step): ?>
                                <div class="installation-step" style="--step-index: <?= $index ?>"><?= htmlspecialchars($step) ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success text-center">
                    <h4>✅ Installation erfolgreich abgeschlossen!</h4>
                    <p class="mb-3">Die DVD-Verwaltung v<?= DB_VERSION ?> ist nun einsatzbereit.</p>
                    <a href="../index.php" class="btn btn-success btn-lg">
                        🚀 Zur Anwendung
                    </a>
                    <div class="mt-3">
                        <small>
                            <strong>Standard-Login:</strong><br>
                            E-Mail: admin@localhost<br>
                            Passwort: admin123
                        </small>
                    </div>
                </div>
            <?php else: ?>
                <!-- System-Anforderungen anzeigen -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">🔧 System-Anforderungen</h5>
                    </div>
                    <div class="card-body">
                        <div class="section-title">Erforderlich</div>
                        <div class="requirements-list">
                            <?php foreach ($requirements as $requirement => $status): ?>
                                <div class="requirement">
                                    <span><?= htmlspecialchars($requirement) ?></span>
                                    <span class="<?= $status ? 'requirement-ok' : 'requirement-fail' ?>">
                                        <?= $status ? '✅ OK' : '❌ Fehlt' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="section-title mt-4">Optional (empfohlen)</div>
                        <div class="requirements-list">
                            <?php foreach ($optional_requirements as $requirement => $status): ?>
                                <div class="requirement">
                                    <span><?= htmlspecialchars($requirement) ?></span>
                                    <span class="<?= $status ? 'requirement-ok' : 'requirement-fail' ?>">
                                        <?= $status ? '✅ OK' : '❌ Fehlt' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <?php if ($ready): ?>
                    <form method="post" class="card" id="installation-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                        
                        <div class="card-header">
                            <h5 class="mb-0">⚙️ Installationskonfiguration</h5>
                        </div>
                        
                        <div class="card-body">
                            <div class="mb-4">
                                <div class="section-title">Allgemeine Einstellungen</div>
                                
                                <div class="form-group">
                                    <label for="site_title" class="form-label">Website-Titel</label>
                                    <input type="text" 
                                           id="site_title" 
                                           name="site_title" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($_POST['site_title'] ?? 'Meine DVD-Verwaltung') ?>"
                                           maxlength="100"
                                           required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="base_url" class="form-label">Basis-URL</label>
                                    <input type="url" 
                                           id="base_url" 
                                           name="base_url" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($_POST['base_url'] ?? 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'])) ?>"
                                           required>
                                    <div class="form-text">Die vollständige URL zu Ihrer Installation</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="section-title">Datenbank-Konfiguration</div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="db_host" class="form-label">Host</label>
                                            <input type="text" 
                                                   id="db_host" 
                                                   name="db_host" 
                                                   class="form-control" 
                                                   value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="db_port" class="form-label">Port</label>
                                            <input type="number" 
                                                   id="db_port" 
                                                   name="db_port" 
                                                   class="form-control" 
                                                   value="<?= htmlspecialchars($_POST['db_port'] ?? '3306') ?>"
                                                   min="1" 
                                                   max="65535"
                                                   required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="db_name" class="form-label">Datenbankname</label>
                                    <input type="text" 
                                           id="db_name" 
                                           name="db_name" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($_POST['db_name'] ?? 'dvd_verwaltung') ?>"
                                           pattern="[a-zA-Z0-9_]+"
                                           maxlength="64"
                                           required>
                                    <div class="form-text">Datenbank wird automatisch erstellt falls nicht vorhanden</div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="db_user" class="form-label">Benutzername</label>
                                            <input type="text" 
                                                   id="db_user" 
                                                   name="db_user" 
                                                   class="form-control" 
                                                   value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>"
                                                   maxlength="64"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="db_pass" class="form-label">Passwort</label>
                                            <input type="password" 
                                                   id="db_pass" 
                                                   name="db_pass" 
                                                   class="form-control"
                                                   autocomplete="new-password">
                                            <div class="form-text">Optional - leer lassen wenn kein Passwort gesetzt</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card-footer d-grid">
                            <button type="submit" class="btn btn-lg" id="submit-btn">
                                🚀 Installation starten
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-danger">
                        <h5>❌ Systemanforderungen nicht erfüllt</h5>
                        <p>Bitte beheben Sie die oben genannten Probleme, bevor Sie mit der Installation fortfahren.</p>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <script>
        // Theme system
        const themes = [
            {
                background: "#1A1A2E",
                color: "#FFFFFF",
                primary: "#0F3460",
                name: "Standard"
            },
            {
                background: "#2C3E50",
                color: "#ECF0F1",
                primary: "#3498DB",
                name: "Midnight"
            },
            {
                background: "#8E24AA",
                color: "#FFFFFF",
                primary: "#E91E63",
                name: "Purple"
            },
            {
                background: "#1565C0",
                color: "#FFFFFF",
                primary: "#FF9800",
                name: "Ocean"
            }
        ];

        // Create theme buttons
        const themeContainer = document.getElementById('theme-btn-container');
        themes.forEach((theme, index) => {
            const btn = document.createElement('div');
            btn.className = 'theme-btn';
            btn.style.background = `linear-gradient(135deg, ${theme.background}, ${theme.primary})`;
            btn.title = theme.name;
            btn.addEventListener('click', () => {
                document.documentElement.style.setProperty('--background', theme.background);
                document.documentElement.style.setProperty('--color', theme.color);
                document.documentElement.style.setProperty('--primary-color', theme.primary);
            });
            themeContainer.appendChild(btn);
        });

        // Floating particles
        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            
            const size = Math.random() * 6 + 2;
            particle.style.width = size + 'px';
            particle.style.height = size + 'px';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDuration = Math.random() * 10 + 10 + 's';
            particle.style.animationDelay = Math.random() * 15 + 's';
            
            document.getElementById('particles').appendChild(particle);
            
            setTimeout(() => {
                if (particle.parentNode) {
                    particle.parentNode.removeChild(particle);
                }
            }, 25000);
        }

        // Create particles periodically
        setInterval(createParticle, 3000);

        // Form submission enhancement
        const form = document.getElementById('installation-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = document.getElementById('submit-btn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span style="display: inline-block; width: 20px; height: 20px; border: 2px solid #ffffff; border-top: 2px solid transparent; border-radius: 50%; animation: spin 1s linear infinite; margin-right: 8px;"></span>Installation läuft...';
                }
            });
        }

        // Scroll enhancement for installation steps
        const stepsContainer = document.getElementById('installation-steps');
        if (stepsContainer) {
            const observer = new MutationObserver(() => {
                stepsContainer.scrollTop = stepsContainer.scrollHeight;
            });
            observer.observe(stepsContainer, { childList: true });
        }

        // Auto-focus first input
        const firstInput = document.querySelector('input[type="text"]');
        if (firstInput) {
            firstInput.focus();
        }

        // Add spin animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>