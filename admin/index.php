<?php
declare(strict_types=1);

// Bootstrap (startet bereits die Session)
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/version.php'; // Neue Versionsverwaltung laden

// Zugriffsschutz
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Erlaubte Seiten
$allowedPages = ['dashboard', 'users', 'settings', 'import', 'update'];
$page = $_GET['page'] ?? 'dashboard';
$siteTitle = getSetting('site_title', 'DVD Profiler Liste');

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard'; // Fallback
}

// Version und Update-Informationen von neuer Versionsverwaltung
$currentVersion = DVDPROFILER_VERSION;
$versionName = DVDPROFILER_CODENAME;
$buildDate = DVDPROFILER_BUILD_DATE;
$buildInfo = getDVDProfilerBuildInfo();
$isUpdateAvailable = isDVDProfilerUpdateAvailable();
$systemHealth = getSystemHealth(); // Aus bootstrap.php

// Performance-Monitoring für Admin
$pageStartTime = microtime(true);
$memoryStart = memory_get_usage(true);

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle) ?> - Admin Center</title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" as="style">
    <link rel="preload" href="css/admin.css" as="style">
    
    <!-- Bootstrap CSS (für Grid & Components) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    
    <!-- Custom Admin CSS (überschreibt Bootstrap) -->
    <link href="css/admin.css" rel="stylesheet">
    
    <!-- Meta Tags -->
    <meta name="description" content="Admin Center für <?= htmlspecialchars($siteTitle) ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="<?= DVDPROFILER_AUTHOR ?>">
    
    <!-- Admin-spezifische Meta-Informationen -->
    <meta name="application-name" content="DVD Profiler Liste Admin">
    <meta name="application-version" content="<?= DVDPROFILER_VERSION ?>">
    <meta name="application-build" content="<?= DVDPROFILER_BUILD_DATE ?>">
    
    <style>
        /* Enhanced loading animation */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--clr-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .page-loader.hidden {
            opacity: 0;
            pointer-events: none;
        }
        
        .loader-content {
            text-align: center;
            color: var(--clr-text);
        }
        
        .loader-version {
            margin-top: 1rem;
            font-size: 0.85rem;
            opacity: 0.7;
        }
        
        /* System status indicator */
        .system-status {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 1000;
            padding: 0.5rem;
            background: var(--clr-card);
            border-radius: var(--radius);
            border: 1px solid var(--clr-border);
            font-size: 0.75rem;
        }
        
        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }
        
        .status-ok { background: var(--clr-success); }
        .status-warning { background: var(--clr-warning); }
        .status-error { background: var(--clr-danger); }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="page-loader" id="pageLoader">
        <div class="loader-content">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Laden...</span>
            </div>
            <h5>Admin Center wird geladen...</h5>
            <div class="loader-version">
                <?= htmlspecialchars($siteTitle) ?> v<?= DVDPROFILER_VERSION ?> "<?= DVDPROFILER_CODENAME ?>"
            </div>
        </div>
    </div>

    <!-- System Status Indicator -->
    <div class="system-status" id="systemStatus" style="display: none;">
        <span class="status-indicator <?= $systemHealth['database'] ? 'status-ok' : 'status-error' ?>"></span>
        <span>System: <?= $systemHealth['database'] ? 'Online' : 'Offline' ?></span>
        <?php if ($isUpdateAvailable): ?>
            <br><small><i class="bi bi-arrow-up-circle text-warning"></i> Update verfügbar</small>
        <?php endif; ?>
    </div>

    <div class="admin-layout">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="container-fluid p-4">
                <!-- Admin Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">
                            <?php
                            $pageIcons = [
                                'dashboard' => 'speedometer2',
                                'users' => 'people',
                                'settings' => 'gear',
                                'import' => 'upload',
                                'update' => 'arrow-up-circle'
                            ];
                            $icon = $pageIcons[$page] ?? 'file-earmark';
                            ?>
                            <i class="bi bi-<?= $icon ?>"></i>
                            <?= ucfirst($page) ?>
                        </h1>
                        <small class="text-muted">
                            Admin Center - Version <?= DVDPROFILER_VERSION ?> "<?= DVDPROFILER_CODENAME ?>"
                        </small>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <!-- Quick Actions -->
                        <a href="../" class="btn btn-outline-light btn-sm" title="Zur Website">
                            <i class="bi bi-house"></i>
                        </a>
                        
                        <?php if (isDVDProfilerFeatureEnabled('system_updates') && $isUpdateAvailable): ?>
                        <a href="?page=settings&action=update" class="btn btn-warning btn-sm" title="Update verfügbar">
                            <i class="bi bi-arrow-up-circle"></i>
                            Update
                        </a>
                        <?php endif; ?>
                        
                        <div class="dropdown">
                            <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                                <?= htmlspecialchars($_SESSION['username'] ?? 'Admin') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="?page=settings"><i class="bi bi-gear"></i> Einstellungen</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="content-wrapper">
                    <?php
                    // Enhanced error handling with system information
                    if (file_exists(__DIR__ . "/pages/{$page}.php")) {
                        // Performance tracking
                        $pageLoadStart = microtime(true);
                        
                        try {
                            include __DIR__ . "/pages/{$page}.php";
                        } catch (Exception $e) {
                            error_log("Admin page error ({$page}): " . $e->getMessage());
                            echo '<div class="alert alert-danger">
                                    <h5><i class="bi bi-exclamation-triangle"></i> Fehler beim Laden der Seite</h5>
                                    <p>Die angeforderte Seite konnte nicht geladen werden.</p>';
                            
                            if (getSetting('environment', 'production') === 'development') {
                                echo '<details class="mt-3">
                                        <summary>Debug-Informationen</summary>
                                        <pre class="mt-2">' . htmlspecialchars($e->getMessage()) . '</pre>
                                        <pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>
                                      </details>';
                            }
                            
                            echo '</div>';
                        }
                        
                        $pageLoadTime = microtime(true) - $pageLoadStart;
                        
                        // Performance-Log für Entwicklung
                        if (getSetting('environment', 'production') === 'development') {
                            error_log("Admin page '{$page}' loaded in " . round($pageLoadTime * 1000, 2) . "ms");
                        }
                        
                    } else {
                        echo '<div class="alert alert-danger">
                                <h5><i class="bi bi-exclamation-triangle"></i> Seite nicht gefunden</h5>
                                <p>Die angeforderte Admin-Seite existiert nicht.</p>
                                <p class="mb-0">
                                    <a href="?page=dashboard" class="btn btn-primary">
                                        <i class="bi bi-house"></i> Zum Dashboard
                                    </a>
                                </p>
                              </div>';
                    }
                    ?>
                </div>

                <!-- Admin Footer -->
                <footer class="admin-footer mt-5 pt-4 border-top border-secondary">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <small class="text-muted">
                                <i class="bi bi-film"></i>
                                <?= htmlspecialchars($siteTitle) ?> Admin Center
                                <br>
                                Version <?= DVDPROFILER_VERSION ?> "<?= DVDPROFILER_CODENAME ?>" | Build <?= DVDPROFILER_BUILD_DATE ?>
                            </small>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <small class="text-muted">
                                <?php
                                $totalTime = microtime(true) - $pageStartTime;
                                $memoryUsage = memory_get_usage(true) - $memoryStart;
                                ?>
                                <i class="bi bi-speedometer2"></i>
                                <?= round($totalTime * 1000, 1) ?>ms | 
                                <i class="bi bi-memory"></i>
                                <?= formatBytes($memoryUsage) ?>
                                <br>
                                <i class="bi bi-github"></i>
                                <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" class="text-muted">
                                    <?= DVDPROFILER_REPOSITORY ?>
                                </a>
                            </small>
                        </div>
                    </div>
                </footer>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Admin JS -->
    <script>
        // Enhanced Admin JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            // Page Loader with enhanced timing
            const loader = document.getElementById('pageLoader');
            const systemStatus = document.getElementById('systemStatus');
            
            // Show system status after loader
            setTimeout(() => {
                loader.classList.add('hidden');
                setTimeout(() => {
                    loader.style.display = 'none';
                    systemStatus.style.display = 'block';
                }, 300);
            }, 800);

            // Active navigation highlighting
            const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes(`page=${currentPage}`)) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });

            // Enhanced form validation feedback
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.disabled = true;
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verarbeitung...';
                        
                        // Re-enable after timeout (fallback)
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 30000); // 30 seconds timeout
                    }
                });
            });

            // Auto-hide system status on scroll
            let scrollTimeout;
            window.addEventListener('scroll', function() {
                systemStatus.style.opacity = '0.5';
                
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    systemStatus.style.opacity = '1';
                }, 1000);
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Ctrl/Cmd + D = Dashboard
                if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                    e.preventDefault();
                    window.location.href = '?page=dashboard';
                }
                
                // Ctrl/Cmd + U = Users
                if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
                    e.preventDefault();
                    window.location.href = '?page=users';
                }
                
                // Ctrl/Cmd + S = Settings
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    window.location.href = '?page=settings';
                }
            });

            // Version info click handler
            const versionInfo = document.querySelector('.loader-version, small:contains("Version")');
            if (versionInfo) {
                versionInfo.addEventListener('click', function() {
                    const buildInfo = <?= json_encode($buildInfo) ?>;
                    console.log('DVD Profiler Liste Admin - Build Information:', buildInfo);
                });
            }

            // Update notification
            <?php if ($isUpdateAvailable): ?>
            setTimeout(() => {
                if (!localStorage.getItem('update_notification_dismissed')) {
                    const notification = document.createElement('div');
                    notification.className = 'alert alert-warning alert-dismissible position-fixed';
                    notification.style.cssText = 'top: 70px; right: 20px; z-index: 1050; max-width: 300px;';
                    notification.innerHTML = `
                        <i class="bi bi-arrow-up-circle"></i>
                        <strong>Update verfügbar!</strong>
                        <p class="mb-2">Eine neue Version ist verfügbar.</p>
                        <button type="button" class="btn btn-sm btn-warning" onclick="window.location.href='?page=settings&action=update'">
                            Jetzt aktualisieren
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="localStorage.setItem('update_notification_dismissed', 'true')"></button>
                    `;
                    document.body.appendChild(notification);
                }
            }, 2000);
            <?php endif; ?>

            console.log('DVD Profiler Liste Admin Center v<?= DVDPROFILER_VERSION ?> "<?= DVDPROFILER_CODENAME ?>" ready');
            console.log('Build: <?= DVDPROFILER_BUILD_DATE ?> | Features: <?= count(array_filter(DVDPROFILER_FEATURES)) ?> aktiv');
        });

        // Global admin functions
        window.dvdAdmin = {
            version: '<?= DVDPROFILER_VERSION ?>',
            codename: '<?= DVDPROFILER_CODENAME ?>',
            buildDate: '<?= DVDPROFILER_BUILD_DATE ?>',
            features: <?= json_encode(array_keys(array_filter(DVDPROFILER_FEATURES))) ?>,
            
            showToast: function(message, type = 'info') {
                // Simple toast notification system
                const toast = document.createElement('div');
                toast.className = `alert alert-${type} position-fixed`;
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 1060; min-width: 250px;';
                toast.innerHTML = `${message} <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>`;
                document.body.appendChild(toast);
                
                setTimeout(() => toast.remove(), 5000);
            },
            
            confirmAction: function(message, callback) {
                if (confirm(message)) {
                    callback();
                }
            }
        };
    </script>
</body>
</html>