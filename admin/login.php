<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$error = null;
$success = null;
$require2FA = false;
$userId = null;

// Simple2FA Klasse für Login (ohne externe Dependencies)
class Simple2FA {
    public static function base32Decode(string $secret): string {
        $secret = strtoupper($secret);
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $charMap = array_flip(str_split($chars));
        
        $bits = '';
        for ($i = 0; $i < strlen($secret); $i++) {
            if (isset($charMap[$secret[$i]])) {
                $bits .= str_pad(decbin($charMap[$secret[$i]]), 5, '0', STR_PAD_LEFT);
            }
        }
        
        $result = '';
        for ($i = 0; $i < strlen($bits); $i += 8) {
            if (strlen($bits) - $i >= 8) {
                $result .= chr(bindec(substr($bits, $i, 8)));
            }
        }
        
        return $result;
    }
    
    public static function hotp(string $secret, int $counter): string {
        $secretBinary = self::base32Decode($secret);
        $counterBinary = pack('N*', 0) . pack('N*', $counter);
        
        $hash = hash_hmac('sha1', $counterBinary, $secretBinary, true);
        $offset = ord($hash[19]) & 0xf;
        
        $code = (
            ((ord($hash[$offset + 0]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }
    
    public static function verifyTotp(string $secret, string $code, int $timeStep = 30, int $tolerance = 1): bool {
        $timeCounter = (int)floor(time() / $timeStep);
        
        for ($i = -$tolerance; $i <= $tolerance; $i++) {
            if (self::hotp($secret, $timeCounter + $i) === $code) {
                return true;
            }
        }
        return false;
    }
}

// Redirect wenn bereits eingeloggt
if (isset($_SESSION['user_id']) && !isset($_SESSION['require_2fa'])) {
    $redirect = (defined('BASE_URL') && BASE_URL !== '')
        ? BASE_URL . '/admin/index.php?page=dashboard'
        : 'index.php?page=dashboard';
    header("Location: $redirect");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 2FA-Verifikation
    if (isset($_POST['verify_2fa'])) {
        $token = trim($_POST['token'] ?? '');
        $userId = $_SESSION['temp_user_id'] ?? 0;
        
        if (empty($token) || !$userId) {
            $error = "Ungültiger Zugriff.";
        } else {
            try {
                // Benutzer und 2FA-Secret laden
                $stmt = $pdo->prepare("SELECT id, email, twofa_secret FROM users WHERE id = ? AND twofa_enabled = 1");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if (!$user) {
                    $error = "Benutzer nicht gefunden.";
                } else {
                    // 2FA-Token mit eigener Simple2FA-Klasse prüfen
                    $isValidToken = Simple2FA::verifyTotp($user['twofa_secret'], $token);
                    $isBackupCode = false;
                    
                    // Falls Token ungültig, prüfe Backup-Codes
                    if (!$isValidToken) {
                        $stmt = $pdo->prepare("
                            SELECT id, code FROM user_backup_codes 
                            WHERE user_id = ? AND used_at IS NULL
                        ");
                        $stmt->execute([$userId]);
                        $backupCodes = $stmt->fetchAll();
                        
                        foreach ($backupCodes as $backupCode) {
                            if (password_verify($token, $backupCode['code'])) {
                                $isBackupCode = true;
                                // Backup-Code als verwendet markieren
                                $updateStmt = $pdo->prepare("
                                    UPDATE user_backup_codes 
                                    SET used_at = NOW() 
                                    WHERE id = ?
                                ");
                                $updateStmt->execute([$backupCode['id']]);
                                break;
                            }
                        }
                    }
                    
                    if ($isValidToken || $isBackupCode) {
                        // 2FA erfolgreich - vollständig anmelden
                        $_SESSION['user_id'] = $userId;
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['login_time'] = time();
                        $_SESSION['2fa_verified'] = true;
                        
                        // Temporäre Session-Daten löschen
                        unset($_SESSION['temp_user_id']);
                        unset($_SESSION['require_2fa']);
                        
                        // Letzten Login aktualisieren
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$userId]);
                        
                        if ($isBackupCode) {
                            $_SESSION['backup_code_used'] = true;
                        }
                        
                        $success = "Anmeldung erfolgreich! Sie werden weitergeleitet...";
                        
                        $redirect = (defined('BASE_URL') && BASE_URL !== '')
                            ? BASE_URL . '/admin/index.php?page=dashboard'
                            : 'index.php?page=dashboard';
                        
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = '{$redirect}';
                            }, 1500);
                        </script>";
                    } else {
                        $error = "Ungültiger 2FA-Code oder Backup-Code.";
                        $require2FA = true;
                        $userId = $_SESSION['temp_user_id'];
                    }
                }
            } catch (Exception $e) {
                error_log('2FA Login error: ' . $e->getMessage());
                $error = "2FA-Verifikation fehlgeschlagen.";
                $require2FA = true;
                $userId = $_SESSION['temp_user_id'];
            }
        }
    }
    // Normale Anmeldung (E-Mail/Passwort)
    else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email) || empty($password)) {
            $error = "Bitte füllen Sie alle Felder aus.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Ungültige E-Mail-Adresse.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Passwort korrekt - prüfe 2FA
                    if ($user['twofa_enabled']) {
                        // 2FA erforderlich
                        $_SESSION['temp_user_id'] = $user['id'];
                        $_SESSION['require_2fa'] = true;
                        $require2FA = true;
                        $userId = $user['id'];
                    } else {
                        // Kein 2FA - direkt anmelden
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['login_time'] = time();
                        
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);
                        
                        $success = "Login erfolgreich! Sie werden weitergeleitet...";
                        
                        $redirect = (defined('BASE_URL') && BASE_URL !== '')
                            ? BASE_URL . '/admin/index.php?page=dashboard'
                            : 'index.php?page=dashboard';
                        
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = '{$redirect}';
                            }, 1500);
                        </script>";
                    }
                } else {
                    $error = "E-Mail oder Passwort ist falsch.";
                }
            } catch (Exception $e) {
                error_log('Login error: ' . $e->getMessage());
                $error = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
            }
        }
    }
}

// 2FA-Status aus Session prüfen
if (isset($_SESSION['require_2fa']) && isset($_SESSION['temp_user_id'])) {
    $require2FA = true;
    $userId = $_SESSION['temp_user_id'];
}

$siteTitle = getSetting('site_title', 'DVD-Verwaltung');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($siteTitle) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="css/login.css" rel="stylesheet">
    
    <meta name="description" content="Anmeldung zum Admin-Bereich">
    <meta name="theme-color" content="#1a1a2e">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233498db'%3E%3Cpath d='M18 4v1h-2V4c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v1H4v11c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4h-2zM8 4h6v1H8V4zm10 13H6V6h2v1h6V6h2v11z'/%3E%3C/svg%3E">
</head>
<body>
    <section class="container">
        <div class="login-container">
            <div class="circle circle-one"></div>
            <div class="circle circle-two"></div>
            
            <div class="form-container">
                <?php if ($require2FA): ?>
                    <!-- 2FA-Formular -->
                    <h1>
                        <i class="bi bi-shield-lock" style="margin-right: 0.5rem; font-size: 0.8em;"></i>
                        Zwei-Faktor-Authentifizierung
                    </h1>
                    
                    <p style="text-align: center; margin-bottom: 2rem; color: var(--clr-text-muted);">
                        Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein
                    </p>
                <?php else: ?>
                    <!-- Normales Login-Formular -->
                    <h1>
                        <i class="bi bi-film" style="margin-right: 0.5rem; font-size: 0.8em;"></i>
                        Admin Login
                    </h1>
                <?php endif; ?>
                
                <!-- Error/Success Messages -->
                <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-triangle" style="margin-right: 0.5rem;"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="bi bi-check-circle" style="margin-right: 0.5rem;"></i>
                        <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($require2FA): ?>
                    <!-- 2FA-Verifikation -->
                    <form method="post" action="" id="twoFAForm" novalidate>
                        <input type="hidden" name="verify_2fa" value="1">
                        
                        <div class="input-group password">
                            <input 
                                type="text" 
                                name="token" 
                                id="token"
                                placeholder="2FA-Code (6 Stellen)" 
                                required 
                                autocomplete="one-time-code"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                aria-label="2FA-Code"
                                style="text-align: center; letter-spacing: 0.5em; font-size: 1.2rem;"
                            />
                        </div>
                        
                        <button type="submit" class="login-btn" id="verify2FABtn">
                            <span class="btn-text">Bestätigen</span>
                        </button>
                        
                        <div style="text-align: center; margin-top: 1rem;">
                            <p style="color: var(--clr-text-muted); font-size: 0.9rem;">
                                Kein Zugriff auf Ihr Gerät?<br>
                                <button type="button" id="showBackupForm" style="background: none; border: none; color: var(--clr-accent); text-decoration: underline; cursor: pointer;">
                                    Backup-Code verwenden
                                </button>
                            </p>
                        </div>
                    </form>
                    
                    <!-- Backup-Code Formular (versteckt) -->
                    <form method="post" action="" id="backupCodeForm" style="display: none;" novalidate>
                        <input type="hidden" name="verify_2fa" value="1">
                        
                        <div class="input-group password">
                            <input 
                                type="text" 
                                name="token" 
                                placeholder="Backup-Code" 
                                required 
                                autocomplete="off"
                                aria-label="Backup-Code"
                                style="text-transform: uppercase;"
                            />
                        </div>
                        
                        <button type="submit" class="login-btn">
                            <span class="btn-text">Mit Backup-Code anmelden</span>
                        </button>
                        
                        <div style="text-align: center; margin-top: 1rem;">
                            <button type="button" id="showNormalForm" style="background: none; border: none; color: var(--clr-accent); text-decoration: underline; cursor: pointer;">
                                Zurück zu 2FA-Code
                            </button>
                        </div>
                    </form>
                    
                <?php else: ?>
                    <!-- Normale Anmeldung -->
                    <form method="post" action="" id="loginForm" novalidate>
                        <div class="input-group email">
                            <input 
                                type="email" 
                                name="email" 
                                id="email"
                                placeholder="E-Mail-Adresse" 
                                value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                required 
                                autocomplete="email"
                                aria-label="E-Mail-Adresse"
                            />
                        </div>
                        
                        <div class="input-group password">
                            <input 
                                type="password" 
                                name="password" 
                                id="password"
                                placeholder="Passwort" 
                                required 
                                autocomplete="current-password"
                                aria-label="Passwort"
                            />
                        </div>
                        
                        <button type="submit" class="login-btn" id="loginBtn">
                            <span class="btn-text">Anmelden</span>
                        </button>
                    </form>
                <?php endif; ?>
                
                <div class="register-forget">
                    <a href="../" title="Zur Hauptseite">
                        <i class="bi bi-house"></i> Zur Website
                    </a>
                    
                    <?php if ($require2FA): ?>
                        <a href="login.php" title="Neue Anmeldung">
                            <i class="bi bi-arrow-left"></i> Neue Anmeldung
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <div class="theme-btn-container" title="Theme wechseln"></div>
    </section>

    <script>
        // Theme Switcher (gleich wie vorher)
        const themes = [
            { background: "#1a1a2e", color: "#ffffff", primaryColor: "#0f3460", accentColor: "#3498db" },
            { background: "#461220", color: "#ffffff", primaryColor: "#E94560", accentColor: "#ff6b8a" },
            { background: "#192A51", color: "#ffffff", primaryColor: "#967AA1", accentColor: "#c39bd3" },
            { background: "#2d1b69", color: "#ffffff", primaryColor: "#8e44ad", accentColor: "#9b59b6" },
            { background: "#0c5460", color: "#ffffff", primaryColor: "#16a085", accentColor: "#1abc9c" }
        ];

        const setTheme = (theme) => {
            const root = document.documentElement;
            Object.entries(theme).forEach(([key, value]) => {
                root.style.setProperty(`--${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`, value);
            });
            localStorage.setItem('adminTheme', JSON.stringify(theme));
        };

        const displayThemeButtons = () => {
            const btnContainer = document.querySelector(".theme-btn-container");
            themes.forEach((theme, index) => {
                const div = document.createElement("div");
                div.className = "theme-btn";
                div.style.background = `linear-gradient(135deg, ${theme.primaryColor}, ${theme.accentColor})`;
                div.title = `Theme ${index + 1}`;
                btnContainer.appendChild(div);
                div.addEventListener("click", () => setTheme(theme));
            });
        };

        // 2FA-spezifische JavaScript-Funktionen
        document.addEventListener('DOMContentLoaded', function() {
            displayThemeButtons();
            
            // Gespeichertes Theme laden
            const savedTheme = localStorage.getItem('adminTheme');
            if (savedTheme) {
                setTheme(JSON.parse(savedTheme));
            }
            
            // 2FA-Code Auto-Format
            const tokenInput = document.getElementById('token');
            if (tokenInput) {
                tokenInput.addEventListener('input', function(e) {
                    // Nur Zahlen erlauben
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Auto-submit wenn 6 Zeichen
                    if (this.value.length === 6) {
                        setTimeout(() => {
                            this.form.submit();
                        }, 500);
                    }
                });
                
                // Auto-focus
                tokenInput.focus();
            }
            
            // Backup-Code Formular Toggle
            const showBackupBtn = document.getElementById('showBackupForm');
            const showNormalBtn = document.getElementById('showNormalForm');
            const twoFAForm = document.getElementById('twoFAForm');
            const backupForm = document.getElementById('backupCodeForm');
            
            if (showBackupBtn) {
                showBackupBtn.addEventListener('click', function() {
                    twoFAForm.style.display = 'none';
                    backupForm.style.display = 'block';
                    backupForm.querySelector('input').focus();
                });
            }
            
            if (showNormalBtn) {
                showNormalBtn.addEventListener('click', function() {
                    backupForm.style.display = 'none';
                    twoFAForm.style.display = 'block';
                    twoFAForm.querySelector('input').focus();
                });
            }
            
            // Form-Enhancement für alle Formulare
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        const btnText = submitBtn.querySelector('.btn-text');
                        const originalText = btnText.textContent;
                        btnText.textContent = 'Verarbeitung...';
                        submitBtn.classList.add('loading');
                        
                        // Re-enable nach Timeout (fallback)
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            btnText.textContent = originalText;
                            submitBtn.classList.remove('loading');
                        }, 10000);
                    }
                });
            });
        });
    </script>
</body>
</html>