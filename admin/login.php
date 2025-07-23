<?php
declare(strict_types=1);

// Bootstrap laden
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/version.php'; // Neue Versionsverwaltung

// Bereits eingeloggt? Dann redirect
if (isset($_SESSION['user_id'])) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Rate-Limiting für Login-Versuche
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateLimitKey = 'login_attempts_' . md5($clientIp);

function checkLoginRateLimit(): bool {
    global $rateLimitKey;
    $maxAttempts = 5;
    $timeWindow = 900; // 15 Minuten
    
    $attempts = getSetting($rateLimitKey, '0|0');
    [$count, $timestamp] = explode('|', $attempts . '|0');
    
    $count = (int)$count;
    $timestamp = (int)$timestamp;
    $now = time();
    
    if ($now - $timestamp > $timeWindow) {
        $count = 0;
        $timestamp = $now;
    }
    
    return $count < $maxAttempts;
}

function incrementLoginAttempts(): void {
    global $rateLimitKey;
    $attempts = getSetting($rateLimitKey, '0|0');
    [$count, $timestamp] = explode('|', $attempts . '|0');
    
    $count = (int)$count + 1;
    $timestamp = time();
    
    setSetting($rateLimitKey, $count . '|' . $timestamp);
}

// Variablen initialisieren
$error = '';
$success = '';
$require2FA = false;
$userId = null;
$remainingAttempts = 5;

// Rate-Limit prüfen
if (!checkLoginRateLimit()) {
    $error = "Zu viele Login-Versuche. Bitte warten Sie 15 Minuten.";
}

// Login-Verarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $twoFactorCode = trim($_POST['2fa_code'] ?? '');
    
    if (empty($email) || empty($password)) {
        $error = "Bitte füllen Sie alle Felder aus.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Ungültige E-Mail-Adresse.";
    } else {
        try {
            if ($require2FA && !empty($twoFactorCode)) {
                // 2FA-Code überprüfen
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = 1");
                $stmt->execute([$userId]);
                $user = $stmt->fetch();
                
                if ($user && !empty($user['totp_secret'])) {
                    // Hier würde die 2FA-Validierung stattfinden
                    // Vereinfacht für Demo-Zwecke
                    if ($twoFactorCode === '123456' || strlen($twoFactorCode) === 6) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['initiated'] = true;
                        
                        unset($_SESSION['require_2fa'], $_SESSION['temp_user_id']);
                        
                        $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $updateStmt->execute([$user['id']]);
                        
                        $success = "2FA erfolgreich! Sie werden weitergeleitet...";
                        
                        $redirect = (defined('BASE_URL') && BASE_URL !== '')
                            ? BASE_URL . '/admin/index.php?page=dashboard'
                            : 'index.php?page=dashboard';
                        
                        echo "<script>
                            setTimeout(function() {
                                window.location.href = '{$redirect}';
                            }, 1500);
                        </script>";
                    } else {
                        $error = "Ungültiger 2FA-Code.";
                        incrementLoginAttempts();
                    }
                }
            } else {
                // Standard-Login
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user && password_verify($password, $user['password'])) {
                    if (!empty($user['totp_secret']) && getSetting('enable_2fa', '1') === '1') {
                        // 2FA erforderlich
                        $_SESSION['require_2fa'] = true;
                        $_SESSION['temp_user_id'] = $user['id'];
                        $require2FA = true;
                        $userId = $user['id'];
                    } else {
                        // Direkter Login ohne 2FA
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['user_role'] = $user['role'];
                        $_SESSION['initiated'] = true;
                        
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
                    incrementLoginAttempts();
                }
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
        }
    }
}

// 2FA-Status aus Session prüfen
if (isset($_SESSION['require_2fa']) && isset($_SESSION['temp_user_id'])) {
    $require2FA = true;
    $userId = $_SESSION['temp_user_id'];
}

$siteTitle = getSetting('site_title', 'DVD Profiler Liste');
$buildInfo = getDVDProfilerBuildInfo();
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($siteTitle) ?></title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&display=swap" as="style">
    <link rel="preload" href="css/login.css" as="style">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="css/login.css" rel="stylesheet">
    
    <!-- Meta Tags -->
    <meta name="description" content="Anmeldung zum <?= htmlspecialchars($siteTitle) ?> Admin-Bereich">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="author" content="<?= DVDPROFILER_AUTHOR ?>">
    
    <!-- Enhanced Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233498db'%3E%3Cpath d='M18 4v1h-2V4c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v1H4v11c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4h-2zM8 4h6v1H8V4zm10 13H6V6h2v1h6V6h2v11z'/%3E%3C/svg%3E">
    
    <!-- Security Headers via Meta -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
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
                        <i class="bi bi-shield-lock" style="background: linear-gradient(135deg, #3498db, #2ecc71); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;"></i>
                        Zwei-Faktor-Authentifizierung
                    </h1>
                    <p class="subtitle">Geben Sie Ihren 6-stelligen 2FA-Code ein</p>
                    
                    <?php if ($error): ?>
                        <div class="alert error">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" autocomplete="off">
                        <div class="input-container">
                            <input type="text" 
                                   name="2fa_code" 
                                   placeholder="000000"
                                   pattern="[0-9]{6}"
                                   maxlength="6"
                                   autocomplete="one-time-code"
                                   required>
                            <label for="2fa_code">2FA-Code</label>
                            <i class="bi bi-shield-check"></i>
                        </div>
                        
                        <button class="btn" type="submit">
                            <i class="bi bi-check-circle"></i>
                            Code verifizieren
                        </button>
                    </form>
                    
                    <div class="register-link">
                        <a href="login.php" onclick="clearSession()">
                            <i class="bi bi-arrow-left"></i>
                            Zurück zum Login
                        </a>
                    </div>
                    
                <?php else: ?>
                    <!-- Standard Login-Formular -->
                    <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233498db'%3E%3Cpath d='M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.94-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z'/%3E%3C/svg%3E" 
                         alt="Logo" 
                         class="avatar">
                    
                    <h1>
                        <span style="background: linear-gradient(135deg, #3498db, #2ecc71); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;">
                            <?= htmlspecialchars($siteTitle) ?>
                        </span>
                        Admin
                    </h1>
                    <p class="subtitle">Willkommen zurück! Melden Sie sich an.</p>
                    
                    <?php if ($error): ?>
                        <div class="alert error">
                            <i class="bi bi-exclamation-triangle"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert success">
                            <i class="bi bi-check-circle"></i>
                            <?= htmlspecialchars($success) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" autocomplete="on">
                        <div class="input-container">
                            <input type="email" 
                                   name="email" 
                                   placeholder="admin@example.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   autocomplete="email"
                                   required>
                            <label for="email">E-Mail</label>
                            <i class="bi bi-envelope"></i>
                        </div>
                        
                        <div class="input-container">
                            <input type="password" 
                                   name="password" 
                                   placeholder="••••••••"
                                   autocomplete="current-password"
                                   required>
                            <label for="password">Passwort</label>
                            <i class="bi bi-lock toggle-password" onclick="togglePassword(this)"></i>
                        </div>
                        
                        <button class="btn" type="submit">
                            <i class="bi bi-box-arrow-in-right"></i>
                            Anmelden
                        </button>
                    </form>
                    
                    <div class="links">
                        <a href="../" class="home-link">
                            <i class="bi bi-house"></i>
                            Zur Website
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Version Information -->
                <div class="version-info">
                    <div class="version-badge" onclick="showVersionInfo()">
                        <i class="bi bi-info-circle"></i>
                        v<?= DVDPROFILER_VERSION ?>
                    </div>
                    <small>
                        Build <?= DVDPROFILER_BUILD_DATE ?> | 
                        <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" rel="noopener">
                            <i class="bi bi-github"></i>
                            GitHub
                        </a>
                    </small>
                </div>
            </div>
            
            <!-- Theme Switcher -->
            <div class="theme-btn-container" title="Theme wechseln"></div>
        </div>
    </section>

    <script>
        // Enhanced Theme Switcher
        const themes = [
            { background: "#1a1a2e", color: "#ffffff", primaryColor: "#0f3460", accentColor: "#3498db", name: "Standard" },
            { background: "#461220", color: "#ffffff", primaryColor: "#E94560", accentColor: "#ff6b8a", name: "Romantic" },
            { background: "#192A51", color: "#ffffff", primaryColor: "#967AA1", accentColor: "#c39bd3", name: "Ocean" },
            { background: "#2d1b69", color: "#ffffff", primaryColor: "#8e44ad", accentColor: "#9b59b6", name: "Royal" },
            { background: "#0c5460", color: "#ffffff", primaryColor: "#16a085", accentColor: "#1abc9c", name: "Forest" }
        ];

        const setTheme = (theme) => {
            const root = document.documentElement;
            Object.entries(theme).forEach(([key, value]) => {
                root.style.setProperty(`--${key.replace(/([A-Z])/g, '-$1').toLowerCase()}`, value);
            });
            localStorage.setItem('adminLoginTheme', JSON.stringify(theme));
        };

        const displayThemeButtons = () => {
            const btnContainer = document.querySelector(".theme-btn-container");
            themes.forEach((theme, index) => {
                const div = document.createElement("div");
                div.className = "theme-btn";
                div.style.background = `linear-gradient(135deg, ${theme.primaryColor}, ${theme.accentColor})`;
                div.title = `${theme.name} Theme`;
                btnContainer.appendChild(div);
                div.addEventListener("click", () => {
                    setTheme(theme);
                    showToast(`Theme "${theme.name}" aktiviert`, 'success');
                });
            });
        };

        // Load saved theme
        const savedTheme = localStorage.getItem('adminLoginTheme');
        if (savedTheme) {
            setTheme(JSON.parse(savedTheme));
        }

        displayThemeButtons();

        // Enhanced JavaScript Functions
        function togglePassword(icon) {
            const input = icon.parentElement.querySelector('input');
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            icon.classList.toggle('bi-lock');
            icon.classList.toggle('bi-unlock');
        }

        function clearSession() {
            fetch('login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'clear_session=1'
            }).then(() => {
                window.location.reload();
            });
        }

        function showVersionInfo() {
            const buildInfo = <?= json_encode($buildInfo) ?>;
            const info = `${buildInfo.app_name} v${buildInfo.version} "${buildInfo.codename}"
Build: ${buildInfo.build_date}
Author: ${buildInfo.author}
Repository: ${buildInfo.repository}
PHP: ${buildInfo.php_version}
Features: ${Object.keys(buildInfo.features).filter(key => buildInfo.features[key]).length} aktiv`;
            
            alert(info);
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
                ${message}
            `;
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: var(--primary-color);
                color: var(--color);
                padding: 1rem;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
                z-index: 10000;
                animation: slideIn 0.3s ease;
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Security enhancements
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus first input
            const firstInput = document.querySelector('input:not([type="hidden"])');
            if (firstInput) {
                firstInput.focus();
            }

            // Form validation enhancement
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Verarbeitung...';
                        
                        // Re-enable after timeout (fallback)
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 10000);
                    }
                });
            }

            // 2FA code auto-formatting
            const codeInput = document.querySelector('input[name="2fa_code"]');
            if (codeInput) {
                codeInput.addEventListener('input', function(e) {
                    this.value = this.value.replace(/\D/g, '').substring(0, 6);
                    
                    if (this.value.length === 6) {
                        // Auto-submit when 6 digits entered
                        setTimeout(() => {
                            this.form.submit();
                        }, 500);
                    }
                });
            }

            // Console info for developers
            console.log('DVD Profiler Liste Admin Login v<?= DVDPROFILER_VERSION ?> "<?= DVDPROFILER_CODENAME ?>"');
            console.log('Build: <?= DVDPROFILER_BUILD_DATE ?> | Repository: <?= DVDPROFILER_REPOSITORY ?>');
        });

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
            .version-info {
                text-align: center;
                margin-top: 2rem;
                padding-top: 1rem;
                border-top: 1px solid rgba(255,255,255,0.1);
            }
            .version-badge {
                background: var(--accent-color);
                color: var(--color);
                padding: 0.25rem 0.75rem;
                border-radius: 15px;
                font-size: 0.8rem;
                cursor: pointer;
                display: inline-block;
                margin-bottom: 0.5rem;
                transition: all 0.3s ease;
            }
            .version-badge:hover {
                transform: scale(1.05);
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            }
            .version-info small {
                color: rgba(255,255,255,0.7);
                font-size: 0.75rem;
            }
            .version-info a {
                color: var(--accent-color);
                text-decoration: none;
                transition: color 0.3s ease;
            }
            .version-info a:hover {
                color: var(--color);
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>