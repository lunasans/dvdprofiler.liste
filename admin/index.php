<?php
declare(strict_types=1);

// Performance-Monitoring für Admin
$pageStartTime = microtime(true);
$memoryStart = memory_get_usage(true);

// Bootstrap (startet bereits die Session) - LÄDT formatBytes()!
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/version.php'; // Neue Versionsverwaltung laden

// Konstanten-Fallbacks (nur falls nicht definiert)
if (!defined('DVDPROFILER_VERSION')) define('DVDPROFILER_VERSION', '1.4.6');
if (!defined('DVDPROFILER_CODENAME')) define('DVDPROFILER_CODENAME', 'Unknown');
if (!defined('DVDPROFILER_BUILD_DATE')) define('DVDPROFILER_BUILD_DATE', date('Y.m.d'));
if (!defined('DVDPROFILER_BUILD_TYPE')) define('DVDPROFILER_BUILD_TYPE', 'Release');
if (!defined('DVDPROFILER_GITHUB_URL')) define('DVDPROFILER_GITHUB_URL', '#');
if (!defined('DVDPROFILER_REPOSITORY')) define('DVDPROFILER_REPOSITORY', 'DVD Profiler Liste');
if (!defined('DVDPROFILER_AUTHOR')) define('DVDPROFILER_AUTHOR', 'René Neuhaus');

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

// Version und Update-Informationen von neuer Versionsverwaltung (sicher)
$currentVersion = DVDPROFILER_VERSION;
$versionName = DVDPROFILER_CODENAME;
$buildDate = DVDPROFILER_BUILD_DATE;
$buildInfo = function_exists('getDVDProfilerBuildInfo') ? getDVDProfilerBuildInfo() : [];
$isUpdateAvailable = function_exists('isDVDProfilerUpdateAvailable') ? isDVDProfilerUpdateAvailable() : false;
$systemHealth = function_exists('getSystemHealth') ? getSystemHealth() : [];

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
    
    <!-- Custom Admin CSS (ORIGINAL) -->
    <link href="css/admin.css" rel="stylesheet">
    
    <!-- Security & SEO Meta Tags -->
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="author" content="<?= DVDPROFILER_AUTHOR ?>">
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="SAMEORIGIN">
    
    <!-- Enhanced Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%233498db'%3E%3Cpath d='M18 4v1h-2V4c0-1.1-.9-2-2-2H8c-1.1 0-2 .9-2 2v1H4v11c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V4h-2zM8 4h6v1H8V4zm10 13H6V6h2v1h6V6h2v11z'/%3E%3C/svg%3E">
    
    <style>
        /* Enhanced Page Loader */
        #pageLoader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.5s ease-out, visibility 0.5s ease-out;
        }
        
        #pageLoader.hidden {
            opacity: 0;
            visibility: hidden;
        }
        
        .loader-content {
            text-align: center;
            color: white;
        }
        
        .loader-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid rgba(255,255,255,0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .system-status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.8rem;
            z-index: 1000;
            display: none;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body class="admin-body">
    <!-- Enhanced Page Loader -->
    <div id="pageLoader">
        <div class="loader-content">
            <div class="loader-spinner"></div>
            <h4><?= htmlspecialchars($siteTitle) ?></h4>
            <p>Admin Center wird geladen...</p>
            <small>Version <?= $currentVersion ?> "<?= $versionName ?>"</small>
        </div>
    </div>

    <!-- System Status -->
    <div id="systemStatus" class="system-status">
        <i class="bi bi-cpu"></i> System läuft | 
        <i class="bi bi-clock"></i> <?= date('H:i') ?> | 
        <i class="bi bi-person-check"></i> Admin
    </div>

    <div class="admin-layout">
        <!-- ORIGINAL Sidebar -->
        <aside class="sidebar">
            <?php include __DIR__ . '/sidebar.php'; ?>
        </aside>

        <!-- Main Content Area -->
        <main class="admin-content">
            <!-- ORIGINAL Header -->
            <div class="admin-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="admin-title">
                            <?php
                            $pagesTitles = [
                                'dashboard' => '<i class="bi bi-speedometer2"></i> Dashboard',
                                'users' => '<i class="bi bi-people"></i> Benutzer',
                                'settings' => '<i class="bi bi-gear"></i> Einstellungen', 
                                'import' => '<i class="bi bi-upload"></i> Film Import',
                                'update' => '<i class="bi bi-arrow-repeat"></i> System Updates'
                            ];
                            echo $pagesTitles[$page] ?? '<i class="bi bi-question"></i> Seite';
                            ?>
                        </h1>
                        <small class="text-muted">
                            <?= htmlspecialchars($siteTitle) ?> Admin Center
                        </small>
                    </div>
                    
                    <div class="admin-header-actions">
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                                <?= htmlspecialchars($_SESSION['user_email'] ?? 'Admin') ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="?page=settings"><i class="bi bi-gear"></i> Einstellungen</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </div>
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

            <!-- ORIGINAL Admin Footer -->
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
                            // SICHERE Performance-Berechnung (formatBytes ist aus bootstrap.php)
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
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- ORIGINAL Custom Admin JS (aber SICHER) -->
    <script>
        // Enhanced Admin JavaScript
        document.addEventListener('DOMContentLoaded', function() {
            'use strict';
            
            // SICHERE JavaScript-Konfiguration (alle Werte über json_encode)
            <?php
            $jsConfig = [
                'currentPage' => $page,
                'version' => DVDPROFILER_VERSION,
                'codename' => DVDPROFILER_CODENAME,
                'buildDate' => DVDPROFILER_BUILD_DATE,
                'buildType' => DVDPROFILER_BUILD_TYPE,
                'author' => DVDPROFILER_AUTHOR,
                'isUpdateAvailable' => $isUpdateAvailable,
                'siteTitle' => $siteTitle
            ];
            ?>
            
            // Globale Admin-Konfiguration
            window.adminConfig = <?= json_encode($jsConfig) ?>;
            
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
            const currentPage = window.adminConfig.currentPage;
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes('page=' + currentPage)) {
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
                
                // Ctrl/Cmd + U = Update
                if ((e.ctrlKey || e.metaKey) && e.key === 'u') {
                    e.preventDefault();
                    window.location.href = '?page=update';
                }
                
                // Ctrl/Cmd + I = Import
                if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
                    e.preventDefault();
                    window.location.href = '?page=import';
                }
                
                // Ctrl/Cmd + S = Settings  
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    window.location.href = '?page=settings';
                }
            });

            // Update notification
            if (window.adminConfig.isUpdateAvailable) {
                setTimeout(function() {
                    if (localStorage.getItem('update_notification_dismissed') !== 'true') {
                        var notification = document.createElement('div');
                        notification.className = 'alert alert-info alert-dismissible position-fixed';
                        notification.style.cssText = 'top: 20px; right: 20px; z-index: 1060; max-width: 400px;';
                        notification.innerHTML = `
                            <i class="bi bi-info-circle"></i>
                            <strong>Update verfügbar!</strong><br>
                            Eine neue Version ist jetzt verfügbar.
                            <br><br>
                            <button type="button" class="btn btn-sm btn-primary" onclick="window.location.href='?page=update'">
                                <i class="bi bi-download"></i>
                                Jetzt aktualisieren
                            </button>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" onclick="localStorage.setItem('update_notification_dismissed', 'true')"></button>
                        `;
                        document.body.appendChild(notification);
                    }
                }, 2000);
            }

            // Global admin functions
            window.dvdAdmin = window.dvdAdmin || {};
            Object.assign(window.dvdAdmin, {
                version: window.adminConfig.version,
                codename: window.adminConfig.codename,
                buildDate: window.adminConfig.buildDate,
                
                showToast: function(message, type = 'info') {
                    var toast = document.createElement('div');
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
            });
            
            console.log('DVD Profiler Liste Admin Center v' + window.adminConfig.version + ' "' + window.adminConfig.codename + '" ready');
            console.log('Build: ' + window.adminConfig.buildDate + ' | Features: aktiv');
        });
    </script>
</body>
</html>