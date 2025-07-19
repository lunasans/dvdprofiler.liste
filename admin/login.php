<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

// Session ist bereits in bootstrap.php gestartet - kein extra session_start() nötig!

$error = null;
$success = null;

// Redirect wenn bereits eingeloggt
if (isset($_SESSION['user_id'])) {
    $redirect = (defined('BASE_URL') && BASE_URL !== '')
        ? BASE_URL . '/admin/index.php?page=dashboard'
        : 'index.php?page=dashboard';
    header("Location: $redirect");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Basis-Validierung
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
                // Login erfolgreich
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['login_time'] = time();
                
                // Letzten Login aktualisieren
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$user['id']]);
                
                $success = "Login erfolgreich! Sie werden weitergeleitet...";
                
                // Redirect nach erfolgreicher Anmeldung
                $redirect = (defined('BASE_URL') && BASE_URL !== '')
                    ? BASE_URL . '/admin/index.php?page=dashboard'
                    : 'index.php?page=dashboard';
                
                // JavaScript redirect für bessere UX
                echo "<script>
                    setTimeout(function() {
                        window.location.href = '{$redirect}';
                    }, 1500);
                </script>";
            } else {
                $error = "E-Mail oder Passwort ist falsch.";
            }
        } catch (Exception $e) {
            error_log('Login error: ' . $e->getMessage());
            $error = "Ein Fehler ist aufgetreten. Bitte versuchen Sie es später erneut.";
        }
    }
}

$siteTitle = getSetting('site_title', 'DVD-Verwaltung');
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($siteTitle) ?></title>
    
    <!-- Preconnect für bessere Performance -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Segoe+UI:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    
    <!-- Custom CSS -->
    <link href="css/login.css" rel="stylesheet">
    
    <!-- Meta Tags -->
    <meta name="description" content="Anmeldung zum Admin-Bereich">
    <meta name="theme-color" content="#1a1a2e">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233498db'%3E%3Cpath d='M18 4v1h-2V4c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v1H4v11c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4h-2zM8 4h6v1H8V4zm10 13H6V6h2v1h6V6h2v11z'/%3E%3C/svg%3E">
</head>
<body>
    <section class="container">
        <div class="login-container">
            <!-- Decorative Circles -->
            <div class="circle circle-one"></div>
            <div class="circle circle-two"></div>
            
            <div class="form-container">
                <h1>
                    <i class="bi bi-film" style="margin-right: 0.5rem; font-size: 0.8em;"></i>
                    Admin Login
                </h1>
                
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
                    
                    <button 
                        type="submit" 
                        class="login-btn" 
                        id="loginBtn"
                        aria-label="Anmelden"
                    >
                        <span class="btn-text">Anmelden</span>
                    </button>
                </form>
                
                <div class="register-forget">
                    <a href="../" title="Zur Hauptseite">
                        <i class="bi bi-house"></i> Zur Website
                    </a>
                    <!--
                    <a href="forgot-password.php" title="Passwort vergessen">
                        <i class="bi bi-key"></i> Passwort vergessen?
                    </a>
                    -->
                </div>
            </div>
        </div>
        
        <!-- Theme Switcher -->
        <div class="theme-btn-container" title="Theme wechseln"></div>
    </section>

    <script>
        // Theme Switcher
        const themes = [
            {
                background: "#1a1a2e",
                color: "#ffffff",
                primaryColor: "#0f3460",
                accentColor: "#3498db"
            },
            {
                background: "#461220",
                color: "#ffffff", 
                primaryColor: "#E94560",
                accentColor: "#ff6b8a"
            },
            {
                background: "#192A51",
                color: "#ffffff",
                primaryColor: "#967AA1",
                accentColor: "#c39bd3"
            },
            {
                background: "#2d1b69",
                color: "#ffffff",
                primaryColor: "#8e44ad",
                accentColor: "#9b59b6"
            },
            {
                background: "#0c5460",
                color: "#ffffff",
                primaryColor: "#16a085",
                accentColor: "#1abc9c"
            }
        ];

        const setTheme = (theme) => {
            const root = document.documentElement;
            root.style.setProperty("--background", theme.background);
            root.style.setProperty("--color", theme.color);
            root.style.setProperty("--primary-color", theme.primaryColor);
            root.style.setProperty("--accent-color", theme.accentColor);
            
            // Save theme to localStorage
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

        // Load saved theme
        const loadSavedTheme = () => {
            const savedTheme = localStorage.getItem('adminTheme');
            if (savedTheme) {
                setTheme(JSON.parse(savedTheme));
            }
        };

        // Form Enhancement
        document.addEventListener('DOMContentLoaded', function() {
            displayThemeButtons();
            loadSavedTheme();
            
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('loginBtn');
            const btnText = submitBtn.querySelector('.btn-text');
            const originalText = btnText.textContent;
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const email = document.getElementById('email').value.trim();
                const password = document.getElementById('password').value;
                
                if (!email || !password) {
                    e.preventDefault();
                    showError('Bitte füllen Sie alle Felder aus.');
                    return;
                }
                
                if (!isValidEmail(email)) {
                    e.preventDefault();
                    showError('Bitte geben Sie eine gültige E-Mail-Adresse ein.');
                    return;
                }
                
                // Show loading state
                submitBtn.disabled = true;
                submitBtn.classList.add('loading');
                btnText.textContent = 'Anmeldung läuft...';
                
                // Re-enable after timeout (fallback)
                setTimeout(() => {
                    submitBtn.disabled = false;
                    submitBtn.classList.remove('loading');
                    btnText.textContent = originalText;
                }, 10000);
            });
            
            // Real-time validation
            const inputs = form.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('blur', validateInput);
                input.addEventListener('input', clearValidation);
            });
        });

        function isValidEmail(email) {
            return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
        }

        function validateInput(e) {
            const input = e.target;
            const value = input.value.trim();
            
            if (input.type === 'email' && value && !isValidEmail(value)) {
                input.style.borderColor = 'var(--error-color)';
                input.style.boxShadow = '0 0 10px rgba(231, 76, 60, 0.3)';
            } else if (input.required && !value) {
                input.style.borderColor = 'var(--error-color)';
                input.style.boxShadow = '0 0 10px rgba(231, 76, 60, 0.3)';
            }
        }

        function clearValidation(e) {
            const input = e.target;
            input.style.borderColor = '';
            input.style.boxShadow = '';
        }

        function showError(message) {
            // Remove existing alerts
            const existingAlert = document.querySelector('.alert');
            if (existingAlert) {
                existingAlert.remove();
            }
            
            // Create new alert
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger';
            alert.innerHTML = `<i class="bi bi-exclamation-triangle" style="margin-right: 0.5rem;"></i>${message}`;
            
            // Insert alert before form
            const form = document.getElementById('loginForm');
            form.parentNode.insertBefore(alert, form);
            
            // Auto-remove after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 5000);
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Enter key in any input submits form
            if (e.key === 'Enter' && e.target.tagName === 'INPUT') {
                document.getElementById('loginForm').requestSubmit();
            }
            
            // Escape key clears form
            if (e.key === 'Escape') {
                document.getElementById('loginForm').reset();
                clearAllValidation();
            }
        });

        function clearAllValidation() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.style.borderColor = '';
                input.style.boxShadow = '';
            });
        }

        // Auto-focus first empty input
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            if (!emailInput.value) {
                emailInput.focus();
            } else if (!passwordInput.value) {
                passwordInput.focus();
            }
        });

        // Show password toggle (optional enhancement)
        function addPasswordToggle() {
            const passwordInput = document.getElementById('password');
            const passwordGroup = passwordInput.parentElement;
            
            const toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'password-toggle';
            toggleBtn.innerHTML = '<i class="bi bi-eye"></i>';
            toggleBtn.title = 'Passwort anzeigen/verstecken';
            
            toggleBtn.style.cssText = `
                position: absolute;
                right: 1rem;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                color: rgba(255, 255, 255, 0.7);
                cursor: pointer;
                z-index: 10;
                padding: 0.5rem;
                border-radius: 4px;
                transition: all 0.3s ease;
            `;
            
            passwordGroup.style.position = 'relative';
            passwordGroup.appendChild(toggleBtn);
            
            toggleBtn.addEventListener('click', function() {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                this.innerHTML = isPassword ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>';
                this.title = isPassword ? 'Passwort verstecken' : 'Passwort anzeigen';
            });
            
            toggleBtn.addEventListener('mouseenter', function() {
                this.style.color = 'var(--accent-color)';
                this.style.background = 'rgba(255, 255, 255, 0.1)';
            });
            
            toggleBtn.addEventListener('mouseleave', function() {
                this.style.color = 'rgba(255, 255, 255, 0.7)';
                this.style.background = 'none';
            });
        }

        // Uncomment to enable password toggle
        // document.addEventListener('DOMContentLoaded', addPasswordToggle);

        // Connection status indicator
        function checkConnection() {
            const indicator = document.createElement('div');
            indicator.id = 'connection-indicator';
            indicator.style.cssText = `
                position: fixed;
                top: 1rem;
                right: 1rem;
                padding: 0.5rem 1rem;
                border-radius: 20px;
                font-size: 0.8rem;
                font-weight: 500;
                z-index: 1000;
                transition: all 0.3s ease;
                opacity: 0;
                pointer-events: none;
            `;
            document.body.appendChild(indicator);
            
            function updateStatus(online) {
                if (online) {
                    indicator.style.background = 'rgba(46, 204, 113, 0.2)';
                    indicator.style.color = 'var(--success-color)';
                    indicator.innerHTML = '<i class="bi bi-wifi"></i> Online';
                } else {
                    indicator.style.background = 'rgba(231, 76, 60, 0.2)';
                    indicator.style.color = 'var(--error-color)';
                    indicator.innerHTML = '<i class="bi bi-wifi-off"></i> Offline';
                }
                
                indicator.style.opacity = '1';
                setTimeout(() => {
                    indicator.style.opacity = '0';
                }, 3000);
            }
            
            window.addEventListener('online', () => updateStatus(true));
            window.addEventListener('offline', () => updateStatus(false));
        }

        document.addEventListener('DOMContentLoaded', checkConnection);

        // Prevent form resubmission on page reload
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>