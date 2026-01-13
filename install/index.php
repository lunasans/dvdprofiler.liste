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
const DB_VERSION = '1.4.9'; // Aktuelle Version (mit Serien-Support)

// Pfade definieren
$lockFile = __DIR__ . '/install.lock';
$configDir = dirname(__DIR__) . '/config';
$configFile = $configDir . '/config.php';

// CSRF-Token generieren
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    'Random Bytes verfügbar' => function_exists('random_bytes'),
    'Password Hash verfügbar' => function_exists('password_hash'),
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
        <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
        <style>
            :root {
                --background: #1a1a2e;
                --color: #ffffff;
                --primary-color: #0f3460;
                --glass-color: rgba(145, 145, 145, 0.12);
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: "Roboto", sans-serif;
                background: var(--background);
                color: var(--color);
                letter-spacing: 1px;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .container {
                width: 22.2rem;
                padding: 2rem;
                border: 1px solid hsla(0, 0%, 65%, 0.158);
                box-shadow: 0 0 36px 1px rgba(0, 0, 0, 0.2);
                border-radius: 10px;
                backdrop-filter: blur(20px);
                background: var(--glass-color);
                text-align: center;
            }
            h4 { color: #f39c12; margin-bottom: 1rem; }
            p { margin-bottom: 1.5rem; opacity: 0.9; }
            .btn {
                display: inline-block;
                padding: 14.5px 20px;
                background: var(--primary-color);
                color: var(--color);
                text-decoration: none;
                border-radius: 5px;
                font-weight: 500;
                letter-spacing: 0.8px;
                transition: all 0.3s ease;
            }
            .btn:hover {
                background: #0a5d9f;
                transform: translateY(-2px);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h4>🔒 Installationssperre aktiv</h4>
            <p>Die Anwendung wurde bereits installiert. Entferne <code>install/install.lock</code>, um erneut zu installieren.</p>
            <a href="' . htmlspecialchars($baseUrl) . 'admin/login.php" class="btn">Zum Login</a>
        </div>
    </body>
    </html>
    ');
}

// CSRF-Token validieren
function validateCsrfToken(): bool {
    return isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token']);
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
    $email = validateInput($email, 191);
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Ungültige E-Mail-Adresse');
    }
    return $email;
}

function validatePassword(string $password): string {
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        throw new InvalidArgumentException('Passwort zu kurz (min. ' . MIN_PASSWORD_LENGTH . ' Zeichen)');
    }
    
    // Passwort-Stärke prüfen
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $password)) {
        throw new InvalidArgumentException('Passwort muss Groß-, Kleinbuchstaben und Zahlen enthalten');
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

// Erweiterte Datenbankschema-Installation
function createDatabaseSchema(PDO $pdo): void {
    // SQL-Statements für alle Tabellen - REIHENFOLGE WICHTIG wegen Foreign Keys!
    $sqlStatements = [
        // 1. Users-Tabelle ZUERST (wird von anderen referenziert)
        "CREATE TABLE IF NOT EXISTS users (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(191) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            twofa_secret VARCHAR(64) NULL,
            twofa_enabled TINYINT(1) DEFAULT 0,
            twofa_activated_at TIMESTAMP NULL,
            last_login TIMESTAMP NULL,
            failed_login_attempts INT DEFAULT 0,
            locked_until TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_twofa_enabled (twofa_enabled),
            INDEX idx_active (is_active),
            INDEX idx_locked (locked_until)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 2. DVDs-Tabelle (Haupttabelle für Filme)
        "CREATE TABLE IF NOT EXISTS dvds (
            id BIGINT NOT NULL PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            year INT,
            genre VARCHAR(255),
            cover_id VARCHAR(50),
            collection_type VARCHAR(100),
            runtime INT,
            rating_age INT,
            overview TEXT,
            trailer_url VARCHAR(500),
            boxset_parent BIGINT DEFAULT NULL,
            user_id BIGINT DEFAULT NULL,
            deleted TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_title (title),
            INDEX idx_year (year),
            INDEX idx_genre (genre),
            INDEX idx_collection_type (collection_type),
            INDEX idx_user (user_id),
            INDEX idx_deleted (deleted),
            INDEX idx_boxset_parent (boxset_parent),
            FULLTEXT idx_search (title, overview),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (boxset_parent) REFERENCES dvds(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 3. Actors-Tabelle
        "CREATE TABLE IF NOT EXISTS actors (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 4. Film-Actor Verbindungstabelle
        "CREATE TABLE IF NOT EXISTS film_actor (
            film_id BIGINT NOT NULL,
            actor_id BIGINT NOT NULL,
            role VARCHAR(255) DEFAULT NULL,
            is_main_role TINYINT(1) DEFAULT 0,
            sort_order INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (film_id, actor_id),
            INDEX idx_film (film_id),
            INDEX idx_actor (actor_id),
            INDEX idx_main_role (is_main_role),
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
            user_id BIGINT NOT NULL,
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
            user_id BIGINT NULL,
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

        // 9. Seasons Tabelle (Serien-Staffeln)
        "CREATE TABLE IF NOT EXISTS seasons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            series_id INT NOT NULL,
            season_number INT NOT NULL,
            name VARCHAR(255) DEFAULT NULL,
            overview TEXT DEFAULT NULL,
            episode_count INT DEFAULT 0,
            air_date DATE DEFAULT NULL,
            poster_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_season (series_id, season_number),
            INDEX idx_series (series_id),
            INDEX idx_season_number (season_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 10. Episodes Tabelle (Serien-Episoden)
        "CREATE TABLE IF NOT EXISTS episodes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            season_id INT NOT NULL,
            episode_number INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            overview TEXT DEFAULT NULL,
            air_date DATE DEFAULT NULL,
            runtime INT DEFAULT NULL,
            still_path VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_episode (season_id, episode_number),
            INDEX idx_season (season_id),
            INDEX idx_episode_number (episode_number)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        // 11. Audit-Log Tabelle
        "CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT NULL,
            action VARCHAR(100) NOT NULL,
            ip_address VARCHAR(45) NULL,
            user_agent TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user_id (user_id),
            INDEX idx_action (action),
            INDEX idx_created_at (created_at),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    ];

    foreach ($sqlStatements as $sql) {
        $pdo->exec($sql);
    }
}

function insertDefaultSettings(PDO $pdo, string $siteTitle, string $baseUrl): void {
    $defaultSettings = [
        // Basis-Einstellungen
        ['site_title', $siteTitle, 'Titel der Website', 1],
        ['base_url', $baseUrl, 'Basis-URL der Anwendung', 0],
        ['language', 'de', 'Standard-Sprache', 1],
        ['db_version', DB_VERSION, 'Datenbank-Schema Version', 0],
        
        // 2FA und Sicherheit
        ['enable_2fa', '0', '2-Faktor-Authentifizierung aktivieren', 0],
        ['2fa_required_for_new_users', '0', '2FA für neue Benutzer erforderlich', 0],
        ['2fa_backup_codes_count', '10', 'Anzahl der Backup-Codes', 0],
        
        // Login und Session
        ['login_attempts', '5', 'Maximale Login-Versuche', 0],
        ['login_attempt_limit', '5', 'Login-Versuch Limit', 0],
        ['lock_duration', '15', 'Sperrzeit in Minuten nach zu vielen Fehlversuchen', 0],
        ['login_lockout_duration', '900', 'Login-Sperrzeit in Sekunden', 0],
        ['session_lifetime', '7200', 'Session-Lebensdauer in Sekunden', 0],
        ['session_timeout', '7200', 'Session-Timeout in Sekunden', 0],
        
        // E-Mail/SMTP
        ['smtp_host', '', 'SMTP-Server Host', 0],
        ['smtp_port', '587', 'SMTP-Server Port', 0],
        ['smtp_sender', '', 'Absender E-Mail-Adresse', 0],
        ['smtp_username', '', 'SMTP Benutzername', 0],
        ['smtp_password', '', 'SMTP Passwort', 0],
        ['smtp_encryption', 'tls', 'SMTP Verschlüsselung (tls/ssl)', 0],
        
        // Dateien und Uploads
        ['max_file_size', '10485760', 'Maximale Dateigröße für Uploads (Bytes)', 0],
        ['allowed_extensions', 'jpg,jpeg,png,gif', 'Erlaubte Dateierweiterungen', 0],
        
        // UI und Theme
        ['theme', 'default', 'Standard-Theme', 1],
        ['items_per_page', '20', 'Einträge pro Seite', 1],
        
        // Benutzer und Registrierung
        ['enable_registration', '0', 'Registrierung neuer Benutzer erlauben', 0],
        
        // Backup
        ['backup_enabled', '0', 'Automatische Backups aktivieren', 0],
        ['backup_retention_days', '30', 'Backup-Aufbewahrung in Tagen', 0]
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`key`, `value`, description, is_public) VALUES (?, ?, ?, ?)");
    foreach ($defaultSettings as [$key, $value, $description, $isPublic]) {
        $stmt->execute([$key, $value, $description, $isPublic]);
    }
}

// Installationslogik
$errors = [];
$success = false;
$installationSteps = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    if (!validateCsrfToken()) {
        $errors[] = 'Ungültiges CSRF-Token. Bitte versuchen Sie es erneut.';
    } else {
        try {
            $installationSteps[] = '🔍 Eingaben validieren...';
            
            // Eingaben validieren
            $dbHost = validateInput($_POST['db_host'] ?? 'localhost', 100);
            $dbName = validateInput($_POST['db_name'] ?? '', 64);
            $dbUser = validateInput($_POST['db_user'] ?? '', 64);
            $dbPass = $_POST['db_pass'] ?? '';
            $siteTitle = validateInput($_POST['site_title'] ?? 'Meine DVD-Verwaltung');
            $baseUrl = validateUrl($_POST['base_url'] ?? $baseUrl);
            $adminEmail = validateEmail($_POST['admin_email'] ?? '');
            $adminPass = validatePassword($_POST['admin_password'] ?? '');

            $installationSteps[] = '✅ Eingaben validiert';
            $installationSteps[] = '🔌 Datenbankverbindung testen...';

            // Datenbankverbindung testen
            $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
            $pdo = new PDO($dsn, $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);

            $installationSteps[] = '✅ Datenbankverbindung erfolgreich';
            $installationSteps[] = '📝 Konfigurationsdatei erstellen...';

            // Config-Datei erstellen
            $configContent = "<?php\n// Generiert am " . date('Y-m-d H:i:s') . "\nreturn " . var_export([
                'db_host' => $dbHost,
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_pass' => $dbPass,
                'db_charset' => 'utf8mb4',
                'version' => DB_VERSION,
                'environment' => 'production'
            ], true) . ";\n";

            if (!file_put_contents($configFile, $configContent)) {
                throw new RuntimeException('Konnte Konfigurationsdatei nicht erstellen');
            }

            $installationSteps[] = '✅ Konfigurationsdatei erstellt';
            $installationSteps[] = '🗄️ Datenbankschema erstellen...';

            // Datenbankschema erstellen
            createDatabaseSchema($pdo);

            $installationSteps[] = '✅ Datenbankschema erstellt (9 Tabellen)';
            $installationSteps[] = '⚙️ Standardeinstellungen einfügen...';

            // Transaktion für Datenoperationen starten
            $pdo->beginTransaction();

            // Standardeinstellungen einfügen
            insertDefaultSettings($pdo, $siteTitle, $baseUrl);

            $installationSteps[] = '✅ Standardeinstellungen eingefügt';
            $installationSteps[] = '👤 Administrator-Account erstellen...';

            // Admin-Benutzer mit sicherem Passwort-Hash erstellen
            $hashedPassword = password_hash($adminPass, PASSWORD_DEFAULT, ['cost' => 12]);
            $stmt = $pdo->prepare("INSERT INTO users (email, password, is_active) VALUES (?, ?, 1)");
            $stmt->execute([$adminEmail, $hashedPassword]);

            $installationSteps[] = '✅ Administrator-Account erstellt';
            $installationSteps[] = '📊 Audit-Log Eintrag erstellen...';

            // Audit-Log Eintrag
            $userId = $pdo->lastInsertId();
            $auditStmt = $pdo->prepare("INSERT INTO audit_log (user_id, action, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $auditStmt->execute([
                $userId, 
                'SYSTEM_INSTALL',
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            // Activity-Log Eintrag
            $activityStmt = $pdo->prepare("INSERT INTO activity_log (user_id, action, details, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
            $activityStmt->execute([
                $userId,
                'SYSTEM_INSTALL',
                json_encode(['version' => DB_VERSION, 'admin_email' => $adminEmail, 'tables_created' => 9]),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);

            $installationSteps[] = '✅ Log-Einträge erstellt';

            // Transaktion abschließen
            $pdo->commit();

            $installationSteps[] = '🔒 Installation abschließen...';

            // Lock-Datei erstellen (nach erfolgreichem Commit)
            $lockContent = json_encode([
                'installed_at' => date('Y-m-d H:i:s'),
                'version' => DB_VERSION,
                'admin_email' => $adminEmail,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);

            if (!file_put_contents($lockFile, $lockContent)) {
                throw new RuntimeException('Konnte Lock-Datei nicht erstellen');
            }

            $installationSteps[] = '✅ Installation erfolgreich abgeschlossen!';
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
            transition: background 0.2s ease;
            min-height: 100vh;
            padding: 2rem 1rem;
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
            border-radius: 15px;
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
            border-radius: 10px;
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
            border-radius: 8px;
            color: var(--color);
            font-size: 15px;
            font-weight: 400;
            letter-spacing: 0.8px;
            backdrop-filter: blur(15px);
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(15, 52, 96, 0.2);
            background: rgba(145, 145, 145, 0.15);
        }

        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-text {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            opacity: 0.7;
        }

        .btn {
            display: inline-block;
            padding: 14.5px 20px;
            background: var(--primary-color);
            color: var(--color);
            text-decoration: none;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            letter-spacing: 0.8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 15px;
            width: 100%;
            text-align: center;
        }

        .btn:hover {
            background: #0a5d9f;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(15, 52, 96, 0.3);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-lg {
            padding: 18px 24px;
            font-size: 16px;
        }

        .row {
            display: flex;
            flex-wrap: wrap;
            margin: -0.75rem;
        }

        .col-md-6 {
            flex: 0 0 50%;
            padding: 0.75rem;
        }

        .col-12 {
            flex: 0 0 100%;
            padding: 0.75rem;
        }

        @media (max-width: 768px) {
            .col-md-6 {
                flex: 0 0 100%;
            }
        }

        .requirements-grid {
            display: grid;
            gap: 1rem;
        }

        .requirement-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            border: 1px solid var(--glass-border);
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
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.4;
        }

        .installation-step {
            padding: 0.25rem 0;
            opacity: 0;
            animation: fadeInStep 0.5s ease forwards;
        }

        .installation-step:nth-child(n) {
            animation-delay: calc(var(--step-index, 0) * 0.1s);
        }

        @keyframes fadeInStep {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: var(--color);
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        .password-strength {
            margin-top: 0.5rem;
        }

        .progress {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 0.5rem;
        }

        .progress-bar {
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 4px;
        }

        .progress-bar.bg-danger {
            background: var(--danger-color);
        }

        .progress-bar.bg-warning {
            background: var(--warning-color);
        }

        .progress-bar.bg-info {
            background: var(--info-color);
        }

        .progress-bar.bg-success {
            background: var(--success-color);
        }

        .theme-btn-container {
            position: fixed;
            left: 1rem;
            bottom: 2rem;
            z-index: 1000;
        }

        .theme-btn {
            width: 25px;
            height: 25px;
            border-radius: 50%;
            margin-bottom: 0.5rem;
            cursor: pointer;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .theme-btn:hover {
            width: 35px;
            height: 35px;
            border-color: rgba(255, 255, 255, 0.8);
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            border-bottom: 2px solid var(--primary-color);
            padding-bottom: 0.5rem;
        }

        .feature-list {
            list-style: none;
            padding: 0;
        }

        .feature-list li {
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .feature-list li:last-child {
            border-bottom: none;
        }

        .text-center {
            text-align: center;
        }

        .d-grid {
            display: grid;
        }

        .d-flex {
            display: flex;
        }

        .justify-content-between {
            justify-content: space-between;
        }

        .align-items-center {
            align-items: center;
        }

        .mb-0 {
            margin-bottom: 0;
        }

        .mb-3 {
            margin-bottom: 1.5rem;
        }

        .mb-4 {
            margin-bottom: 2rem;
        }

        .mb-5 {
            margin-bottom: 3rem;
        }

        .mt-2 {
            margin-top: 1rem;
        }

        .me-2 {
            margin-right: 1rem;
        }

        code {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        /* Floating particles animation */
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }

        .particle {
            position: absolute;
            width: 4px;
            height: 4px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s infinite linear;
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
                    <a href="<?= htmlspecialchars($baseUrl) ?>admin/login.php" class="btn btn-lg">
                        🚀 Zur Anmeldung
                    </a>
                </div>
                
                <div class="alert alert-warning">
                    <h5>🔐 Wichtige Sicherheitshinweise:</h5>
                    <ul class="feature-list mb-3">
                        <li>Lösche oder verschiebe den <code>install/</code> Ordner</li>
                        <li>Setze die Dateiberechtigungen für <code>config/config.php</code> auf 600</li>
                        <li>Aktiviere HTTPS für die Produktion</li>
                        <li>Konfiguriere regelmäßige Backups</li>
                        <li>Überprüfe die 2FA-Einstellungen in der Administration</li>
                    </ul>
                </div>

                <div class="alert alert-info">
                    <h5>🆕 Neue Features in v<?= DB_VERSION ?>:</h5>
                    <ul class="feature-list mb-0">
                        <li>Vollständige Datenbankstruktur (DVDs, Actors, Film-Actor Verknüpfungen)</li>
                        <li>2-Faktor-Authentifizierung (2FA) mit Backup-Codes</li>
                        <li>Erweiterte Activity-Logs und Audit-Trails</li>
                        <li>GitHub-Integration Cache für Updates</li>
                        <li>Verbesserte Sicherheitsfeatures und Login-Schutz</li>
                        <li>Optimierte Datenbankindexe für bessere Performance</li>
                        <li>FULLTEXT-Suche für Filme und Schauspieler</li>
                        <li>BoxSet-Unterstützung mit Parent-Child Beziehungen</li>
                        <li>Benutzer-Rollen und Rechteverwaltung</li>
                    </ul>
                </div>
            <?php else: ?>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">🔧 Systemanforderungen</h5>
                    </div>
                    <div class="card-body">
                        <div class="requirements-grid">
                            <?php foreach ($requirements as $label => $status): ?>
                                <div class="requirement-item">
                                    <span><?= htmlspecialchars($label) ?></span>
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
                                           value="<?= htmlspecialchars($_POST['base_url'] ?? $baseUrl) ?>"
                                           maxlength="255"
                                           required>
                                    <div class="form-text">Die URL unter der die Anwendung erreichbar ist.</div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="section-title">Administrator-Account</div>
                                
                                <div class="form-group">
                                    <label for="admin_email" class="form-label">Administrator E-Mail</label>
                                    <input type="email" 
                                           id="admin_email" 
                                           name="admin_email" 
                                           class="form-control" 
                                           value="<?= htmlspecialchars($_POST['admin_email'] ?? '') ?>"
                                           maxlength="191"
                                           required>
                                </div>
                                
                                <div class="form-group">
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
                                    <div class="password-strength" id="password-strength"></div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <div class="section-title">Datenbank-Verbindung</div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="db_host" class="form-label">Host</label>
                                            <input type="text" 
                                                   id="db_host" 
                                                   name="db_host" 
                                                   class="form-control" 
                                                   value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>"
                                                   maxlength="100"
                                                   required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="form-group">
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
                primaryColor: "#0F3460"
            },
            {
                background: "#461220",
                color: "#FFFFFF",
                primaryColor: "#E94560"
            },
            {
                background: "#192A51",
                color: "#FFFFFF",
                primaryColor: "#967AA1"
            },
            {
                background: "#F7B267",
                color: "#000000",
                primaryColor: "#F4845F"
            },
            {
                background: "#F25F5C",
                color: "#000000",
                primaryColor: "#642B36"
            },
            {
                background: "#231F20",
                color: "#FFF",
                primaryColor: "#BB4430"
            }
        ];

        const setTheme = (theme) => {
            const root = document.querySelector(":root");
            root.style.setProperty("--background", theme.background);
            root.style.setProperty("--color", theme.color);
            root.style.setProperty("--primary-color", theme.primaryColor);
        };

        const displayThemeButtons = () => {
            const btnContainer = document.getElementById("theme-btn-container");
            themes.forEach((theme) => {
                const div = document.createElement("div");
                div.className = "theme-btn";
                div.style.cssText = `background: ${theme.background}; border-color: ${theme.primaryColor}`;
                btnContainer.appendChild(div);
                div.addEventListener("click", () => setTheme(theme));
            });
        };

        // Floating particles
        const createParticles = () => {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 50;

            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 6 + 's';
                particle.style.animationDuration = (Math.random() * 3 + 3) + 's';
                particlesContainer.appendChild(particle);
            }
        };

        // Installation Progress Animation
        document.addEventListener('DOMContentLoaded', function() {
            displayThemeButtons();
            createParticles();

            const form = document.getElementById('installation-form');
            const submitBtn = document.getElementById('submit-btn');
            
            if (form && submitBtn) {
                form.addEventListener('submit', function() {
                    submitBtn.innerHTML = '<span class="spinner"></span>Installation läuft...';
                    submitBtn.disabled = true;
                });
            }
            
            // Passwort-Stärke-Anzeige
            const passwordInput = document.getElementById('admin_password');
            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    const strengthContainer = document.getElementById('password-strength');
                    
                    let strength = 0;
                    let feedback = [];
                    
                    if (password.length >= <?= MIN_PASSWORD_LENGTH ?>) strength++;
                    else feedback.push('Mindestens <?= MIN_PASSWORD_LENGTH ?> Zeichen');
                    
                    if (/[a-z]/.test(password)) strength++;
                    else feedback.push('Kleinbuchstaben');
                    
                    if (/[A-Z]/.test(password)) strength++;
                    else feedback.push('Großbuchstaben');
                    
                    if (/\d/.test(password)) strength++;
                    else feedback.push('Zahlen');
                    
                    if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) strength++;
                    
                    updateStrengthIndicator(strengthContainer, strength, feedback);
                });
            }
            
            function updateStrengthIndicator(container, strength, feedback) {
                let color, text, progressWidth;
                
                switch(strength) {
                    case 0:
                    case 1:
                        color = 'danger';
                        text = 'Sehr schwach';
                        progressWidth = '20%';
                        break;
                    case 2:
                        color = 'warning';
                        text = 'Schwach';
                        progressWidth = '40%';
                        break;
                    case 3:
                        color = 'info';
                        text = 'Mittel';
                        progressWidth = '60%';
                        break;
                    case 4:
                        color = 'success';
                        text = 'Stark';
                        progressWidth = '80%';
                        break;
                    case 5:
                        color = 'success';
                        text = 'Sehr stark';
                        progressWidth = '100%';
                        break;
                }
                
                container.innerHTML = `
                    <div class="progress">
                        <div class="progress-bar bg-${color}" style="width: ${progressWidth}"></div>
                    </div>
                    <small class="text-${color}">Passwort-Stärke: ${text}</small>
                    ${feedback.length > 0 ? `<br><small style="opacity: 0.7">Fehlt: ${feedback.join(', ')}</small>` : ''}
                `;
            }
            
            // Auto-scroll zu Fehlern
            const errorAlert = document.querySelector('.alert-danger');
            if (errorAlert) {
                errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Installationsfortschritt auto-scroll
            const stepContainer = document.getElementById('installation-steps');
            if (stepContainer) {
                stepContainer.scrollTop = stepContainer.scrollHeight;
            }
        });
    </script>

</body>
</html>