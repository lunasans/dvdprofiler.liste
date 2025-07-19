<?php
declare(strict_types=1);

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Konstanten definieren
const MIN_PHP_VERSION = '8.0.0';
const MIN_PASSWORD_LENGTH = 8;
const MAX_INPUT_LENGTH = 255;

// Pfade definieren
$lockFile = __DIR__ . '/install.lock';
$configDir = dirname(__DIR__) . '/config';
$configFile = $configDir . '/config.php';

// Sichere Base URL Generierung
function generateBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    
    // Path sanitization
    $path = str_replace(['\\', '..'], ['/', ''], $path);
    $path = rtrim($path, '/');
    
    return $protocol . '://' . $host . $path . '/';
}

$baseUrl = generateBaseUrl();

// Systemanforderungen prüfen
$requirements = [
    'PHP-Version ≥ ' . MIN_PHP_VERSION => version_compare(PHP_VERSION, MIN_PHP_VERSION, '>='),
    'PDO MySQL Extension' => extension_loaded('pdo_mysql'),
    'OpenSSL Extension' => extension_loaded('openssl'),
    'JSON Extension' => extension_loaded('json'),
    'mbstring Extension' => extension_loaded('mbstring'),
    'Config-Verzeichnis beschreibbar' => is_writable($configDir),
    'Session-Unterstützung' => function_exists('session_start'),
];

$ready = !in_array(false, $requirements, true);

// Installation sperren wenn bereits installiert
if (file_exists($lockFile)) {
    http_response_code(403);
    exit('
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <title>Installation gesperrt</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light py-5">
        <div class="container">
            <div class="alert alert-warning">
                <h4>🔒 Installationssperre aktiv</h4>
                <p>Die Anwendung wurde bereits installiert. Entferne <code>install/install.lock</code>, um erneut zu installieren.</p>
                <a href="' . htmlspecialchars($baseUrl) . 'admin/login.php" class="btn btn-primary">Zum Login</a>
            </div>
        </div>
    </body>
    </html>
    ');
}

// Eingabe-Validierung
function validateInput(string $input, int $maxLength = MAX_INPUT_LENGTH, bool $required = true): string {
    $input = trim($input);
    
    if ($required && empty($input)) {
        throw new InvalidArgumentException('Eingabe ist erforderlich');
    }
    
    if (strlen($input) > $maxLength) {
        throw new InvalidArgumentException("Eingabe zu lang (max. {$maxLength} Zeichen)");
    }
    
    return $input;
}

function validateEmail(string $email): string {
    $email = validateInput($email);
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Ungültige E-Mail-Adresse');
    }
    
    return $email;
}

function validatePassword(string $password): string {
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        throw new InvalidArgumentException('Passwort muss mindestens ' . MIN_PASSWORD_LENGTH . ' Zeichen lang sein');
    }
    
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        throw new InvalidArgumentException('Passwort muss Groß- und Kleinbuchstaben sowie Zahlen enthalten');
    }
    
    return $password;
}

function validateUrl(string $url): string {
    $url = validateInput($url);
    
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        throw new InvalidArgumentException('Ungültige URL');
    }
    
    return rtrim($url, '/') . '/';
}

// CSRF-Schutz
session_start();

function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken(string $token): bool {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

$csrfToken = generateCSRFToken();
$errors = [];
$success = false;

// POST-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    try {
        // CSRF-Token prüfen
        $submittedToken = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($submittedToken)) {
            throw new InvalidArgumentException('Ungültiger CSRF-Token');
        }
        
        // Eingaben validieren
        $dbHost = validateInput($_POST['db_host'] ?? '', 100);
        $dbName = validateInput($_POST['db_name'] ?? '', 64);
        $dbUser = validateInput($_POST['db_user'] ?? '', 64);
        $dbPass = $_POST['db_pass'] ?? ''; // Passwort kann leer sein
        $siteTitle = validateInput($_POST['site_title'] ?? 'Meine DVD-Verwaltung');
        $baseUrl = validateUrl($_POST['base_url'] ?? $baseUrl);
        $adminEmail = validateEmail($_POST['admin_email'] ?? '');
        $adminPass = validatePassword($_POST['admin_password'] ?? '');

        // Datenbankverbindung testen
        $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ]);

        // Config-Datei erstellen
        $configContent = "<?php\n// Generiert am " . date('Y-m-d H:i:s') . "\nreturn " . var_export([
            'db_host' => $dbHost,
            'db_name' => $dbName,
            'db_user' => $dbUser,
            'db_pass' => $dbPass,
            'db_charset' => 'utf8mb4',
            'version' => '1.0.0',
            'environment' => 'production'
        ], true) . ";\n";
        
        if (!file_put_contents($configFile, $configContent)) {
            throw new RuntimeException('Konnte Konfigurationsdatei nicht erstellen');
        }

        // Tabellen mit verbesserter Struktur erstellen (ohne Transaktion für DDL)
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(191) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                last_login TIMESTAMP NULL,
                failed_login_attempts INT DEFAULT 0,
                locked_until TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                twofa_secret VARCHAR(255) DEFAULT NULL,
                twofa_enabled TINYINT(1) DEFAULT 0,
                twofa_backup_codes TEXT DEFAULT NULL,
                INDEX idx_email (email),
                INDEX idx_active (is_active),
                INDEX idx_locked (locked_until)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

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
                trailer_url VARCHAR(500),
                boxset_parent BIGINT DEFAULT NULL,
                user_id BIGINT DEFAULT NULL,
                is_deleted TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (boxset_parent) REFERENCES dvds(id) ON DELETE SET NULL,
                INDEX idx_title (title),
                INDEX idx_year (year),
                INDEX idx_genre (genre),
                INDEX idx_user (user_id),
                INDEX idx_deleted (is_deleted),
                FULLTEXT idx_search (title, overview)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS actors (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                birth_year INT DEFAULT NULL,
                bio TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_name (last_name, first_name),
                INDEX idx_birth_year (birth_year),
                FULLTEXT idx_search (first_name, last_name, bio)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS film_actor (
                film_id BIGINT NOT NULL,
                actor_id BIGINT NOT NULL,
                role VARCHAR(255) DEFAULT NULL,
                is_main_role TINYINT(1) DEFAULT 0,
                sort_order INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (film_id, actor_id),
                FOREIGN KEY (film_id) REFERENCES dvds(id) ON DELETE CASCADE,
                FOREIGN KEY (actor_id) REFERENCES actors(id) ON DELETE CASCADE,
                INDEX idx_role (is_main_role),
                INDEX idx_sort (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                `key` VARCHAR(64) NOT NULL UNIQUE,
                `value` TEXT NOT NULL,
                description TEXT DEFAULT NULL,
                is_public TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_key (`key`),
                INDEX idx_public (is_public)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            // Audit-Log Tabelle für Sicherheit
            $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (
                id BIGINT AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT DEFAULT NULL,
                action VARCHAR(100) NOT NULL,
                table_name VARCHAR(64) DEFAULT NULL,
                record_id BIGINT DEFAULT NULL,
                old_values JSON DEFAULT NULL,
                new_values JSON DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                user_agent TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_user (user_id),
                INDEX idx_action (action),
                INDEX idx_table (table_name),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

        // Jetzt Transaktion für Datenoperationen starten
        $pdo->beginTransaction();

        // Standardeinstellungen
            $defaultSettings = [
                ['site_title', $siteTitle, 'Titel der Website', 1],
                ['base_url', $baseUrl, 'Basis-URL der Anwendung', 0],
                ['language', 'de', 'Standard-Sprache', 1],
                ['enable_2fa', '0', '2-Faktor-Authentifizierung aktivieren', 0],
                ['login_attempts', '5', 'Maximale Login-Versuche', 0],
                ['lock_duration', '15', 'Sperrzeit in Minuten nach zu vielen Fehlversuchen', 0],
                ['smtp_host', '', 'SMTP-Server Host', 0],
                ['smtp_port', '587', 'SMTP-Server Port', 0],
                ['smtp_sender', '', 'Absender E-Mail-Adresse', 0],
                ['smtp_username', '', 'SMTP Benutzername', 0],
                ['smtp_password', '', 'SMTP Passwort', 0],
                ['smtp_encryption', 'tls', 'SMTP Verschlüsselung (tls/ssl)', 0],
                ['session_lifetime', '7200', 'Session-Lebensdauer in Sekunden', 0],
                ['max_file_size', '10485760', 'Maximale Dateigröße für Uploads (Bytes)', 0],
                ['allowed_extensions', 'jpg,jpeg,png,gif', 'Erlaubte Dateierweiterungen', 0],
                ['theme', 'default', 'Standard-Theme', 1],
                ['items_per_page', '20', 'Einträge pro Seite', 1],
                ['enable_registration', '0', 'Registrierung neuer Benutzer erlauben', 0],
                ['backup_enabled', '0', 'Automatische Backups aktivieren', 0],
                ['backup_retention_days', '30', 'Backup-Aufbewahrung in Tagen', 0]
        ];

        $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`, description, is_public) VALUES (?, ?, ?, ?)");
        foreach ($defaultSettings as [$key, $value, $description, $isPublic]) {
            $stmt->execute([$key, $value, $description, $isPublic]);
        }

        // Admin-Benutzer mit sicherem Passwort-Hash erstellen
        $hashedPassword = password_hash($adminPass, PASSWORD_DEFAULT, ['cost' => 12]);
        $stmt = $pdo->prepare("INSERT INTO users (email, password) VALUES (?, ?)");
        $stmt->execute([$adminEmail, $hashedPassword]);

        // Audit-Log Eintrag
        $userId = $pdo->lastInsertId();
        $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
        $auditStmt->execute([
            $userId, 
            'SYSTEM_INSTALL',
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);

        // Transaktion abschließen
        $pdo->commit();

        // Lock-Datei erstellen (nach erfolgreichem Commit)
        if (!file_put_contents($lockFile, date('Y-m-d H:i:s'))) {
            throw new RuntimeException('Konnte Lock-Datei nicht erstellen');
        }

        $success = true;

    } catch (PDOException $e) {
        // Rollback nur wenn eine aktive Transaktion existiert
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollback();
        }
        $errors[] = 'Datenbankfehler: ' . $e->getMessage();
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    } catch (Exception $e) {
        // Rollback nur wenn eine aktive Transaktion existiert
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollback();
        }
        $errors[] = 'Installationsfehler: ' . $e->getMessage();
        error_log('Installation error: ' . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DVD-Verwaltung Installation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .requirement-ok { color: #198754; }
        .requirement-fail { color: #dc3545; }
        .install-container { max-width: 600px; margin: 0 auto; }
        .progress-indicator { margin: 2rem 0; }
    </style>
</head>
<body class="bg-light py-5">

<div class="container install-container">
    <div class="text-center mb-5">
        <h1 class="mb-3">🎬 DVD-Verwaltung</h1>
        <h2 class="text-muted">Installation</h2>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success text-center">
            <h4>✅ Installation erfolgreich abgeschlossen!</h4>
            <p class="mb-3">Die DVD-Verwaltung ist nun einsatzbereit.</p>
            <a href="<?= htmlspecialchars($baseUrl) ?>admin/login.php" class="btn btn-primary btn-lg">
                🚀 Zur Anmeldung
            </a>
        </div>
        
        <div class="alert alert-warning">
            <h5>🔐 Wichtige Sicherheitshinweise:</h5>
            <ul class="mb-0">
                <li>Lösche oder verschiebe den <code>install/</code> Ordner</li>
                <li>Setze die Dateiberechtigungen für <code>config/config.php</code> auf 600</li>
                <li>Aktiviere HTTPS für die Produktion</li>
                <li>Konfiguriere regelmäßige Backups</li>
            </ul>
        </div>
    <?php else: ?>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Systemanforderungen</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($requirements as $label => $status): ?>
                        <div class="col-12 d-flex justify-content-between align-items-center py-2 border-bottom">
                            <span><?= htmlspecialchars($label) ?></span>
                            <span class="<?= $status ? 'requirement-ok' : 'requirement-fail' ?>">
                                <?= $status ? '✓ OK' : '✗ Fehler' ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if (!$ready): ?>
            <div class="alert alert-danger">
                <h5>❌ Systemanforderungen nicht erfüllt</h5>
                <p>Bitte behebe die oben genannten Probleme und lade die Seite neu.</p>
            </div>
        <?php else: ?>

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

            <form method="post" class="card">
                <div class="card-body">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    
                    <div class="mb-4">
                        <h5 class="card-title">Website-Konfiguration</h5>
                        
                        <div class="mb-3">
                            <label for="site_title" class="form-label">Website-Titel</label>
                            <input type="text" 
                                   id="site_title" 
                                   name="site_title" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($_POST['site_title'] ?? 'Meine DVD-Verwaltung') ?>"
                                   maxlength="255"
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="base_url" class="form-label">Basis-URL</label>
                            <input type="url" 
                                   id="base_url" 
                                   name="base_url" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($_POST['base_url'] ?? $baseUrl) ?>"
                                   maxlength="255"
                                   required>
                            <div class="form-text">Die URL unter der die Anwendung erreichbar ist.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5 class="card-title">Administrator-Account</h5>
                        
                        <div class="mb-3">
                            <label for="admin_email" class="form-label">Administrator E-Mail</label>
                            <input type="email" 
                                   id="admin_email" 
                                   name="admin_email" 
                                   class="form-control" 
                                   value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"
                                   maxlength="191"
                                   required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_password" class="form-label">Administrator Passwort</label>
                            <input type="password" 
                                   id="admin_password" 
                                   name="admin_password" 
                                   class="form-control" 
                                   minlength="<?= MIN_PASSWORD_LENGTH ?>"
                                   required>
                            <div class="form-text">
                                Mindestens <?= MIN_PASSWORD_LENGTH ?> Zeichen mit Groß-, Kleinbuchstaben und Zahlen.
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <h5 class="card-title">Datenbank-Verbindung</h5>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="db_host" class="form-label">Host</label>
                                <input type="text" 
                                       id="db_host" 
                                       name="db_host" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"
                                       maxlength="100"
                                       required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="db_name" class="form-label">Datenbankname</label>
                                <input type="text" 
                                       id="db_name" 
                                       name="db_name" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['db_name'] ?? '') ?>"
                                       maxlength="64"
                                       required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="db_user" class="form-label">Benutzername</label>
                                <input type="text" 
                                       id="db_user" 
                                       name="db_user" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($_POST['db_user'] ?? '') ?>"
                                       maxlength="64"
                                       required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="db_pass" class="form-label">Passwort</label>
                                <input type="password" 
                                       id="db_pass" 
                                       name="db_pass" 
                                       class="form-control">
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        🚀 Installation starten
                    </button>
                </div>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '⏳ Installation läuft...';
            }
        });
    }
});
</script>

</body>
</html>