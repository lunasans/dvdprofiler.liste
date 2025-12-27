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

// Version und Update-Informationen mit Fallback-Mechanismus
try {
    $currentVersion = defined('DVDPROFILER_VERSION') ? DVDPROFILER_VERSION : '1.4.8';
    $versionName = defined('DVDPROFILER_CODENAME') ? DVDPROFILER_CODENAME : 'Cinephile';
    $buildDate = defined('DVDPROFILER_BUILD_DATE') ? DVDPROFILER_BUILD_DATE : date('Y.m.d');
    
    // Build-Info mit Fallback
    if (function_exists('getDVDProfilerBuildInfo')) {
        $buildInfo = getDVDProfilerBuildInfo();
    } else {
        $buildInfo = [
            'version' => $currentVersion,
            'codename' => $versionName,
            'build_date' => $buildDate,
            'php_version' => PHP_VERSION
        ];
    }
    
    // Update-Check mit Fallback
    if (function_exists('isGitHubUpdateAvailable')) {
        $isUpdateAvailable = isGitHubUpdateAvailable();
    } elseif (function_exists('isGitHubUpdateAvailable')) {
        $isUpdateAvailable = isGitHubUpdateAvailable();
    } else {
        $isUpdateAvailable = false;
    }
    
    // System Health mit Fallback
    if (function_exists('getSystemHealth')) {
        $systemHealth = getSystemHealth();
    } else {
        // Basis-System-Health Check
        $systemHealth = [
            'database' => true, // Wird später geprüft
            'php_version' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'memory_usage' => memory_get_usage(true),
            'overall' => true
        ];
        
        // Test DB-Verbindung
        try {
            if (isset($pdo)) {
                $pdo->query('SELECT 1');
                $systemHealth['database'] = true;
            }
        } catch (Exception $e) {
            $systemHealth['database'] = false;
            $systemHealth['overall'] = false;
            error_log('Database health check failed: ' . $e->getMessage());
        }
    }
    
} catch (Exception $e) {
    error_log('Admin index error: ' . $e->getMessage());
    
    // Absolutes Fallback
    $currentVersion = '1.4.7';
    $versionName = 'Cinephile';
    $buildDate = date('Y.m.d');
    $buildInfo = ['version' => $currentVersion];
    $isUpdateAvailable = false;
    $systemHealth = ['database' => true, 'overall' => true];
}

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
    
    <!-- Additional Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Meta Tags -->
    <meta name="description" content="Admin-Panel für <?= htmlspecialchars($siteTitle) ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#1a1a2e">
    <meta name="author" content="<?= defined('DVDPROFILER_AUTHOR') ? DVDPROFILER_AUTHOR : 'René Neuhaus' ?>">
    
    <!-- Security Headers -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
    <meta http-equiv="X-XSS-Protection" content="1; mode=block">
    
    <!-- Admin-spezifische Styles -->
    <style>
        /* Performance-optimierte Critical CSS */
        .admin-layout {
            min-height: 100vh;
            background: var(--clr-bg, #1a1a2e);
        }
        
        .content-wrapper {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
        }
        
        .performance-info {
            position: fixed;
            bottom: 10px;
            right: 10px;
            background: rgba(0,0,0,0.8);
            color: #fff;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.7rem;
            z-index: 1000;
            display: none;
        }
        
        @media (max-width: 768px) {
            .content-wrapper {
                padding: 1rem;
            }
        }
    </style>
</head>
<body class="admin-body">
    <div class="admin-layout">
        <!-- Sidebar -->
        <?php 
        try {
            include __DIR__ . '/sidebar.php'; 
        } catch (Exception $e) {
            error_log('Sidebar include error: ' . $e->getMessage());
            // Fallback-Sidebar
            echo '<aside class="sidebar">
                    <div class="sidebar-header">
                        <h4><i class="bi bi-film"></i> Admin Center</h4>
                    </div>
                    <nav class="nav flex-column">
                        <a href="?page=dashboard" class="nav-link">Dashboard</a>
                        <a href="?page=import" class="nav-link">Import</a>
                        <a href="?page=settings" class="nav-link">Einstellungen</a>
                        <a href="logout.php" class="nav-link">Logout</a>
                    </nav>
                  </aside>';
        }
        ?>

        <!-- Main Content -->
        <main class="admin-content">
            <div class="admin-content-inner">
                <!-- Top Navigation -->
                <div class="top-nav">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="page-title">
                                <?php
                                $pageTitle = match($page) {
                                    'dashboard' => 'Dashboard',
                                    'import' => 'Film Import',
                                    'users' => 'Benutzer-Verwaltung', 
                                    'settings' => 'System-Einstellungen',
                                    'update' => 'System-Updates',
                                    default => 'Admin Center'
                                };
                                echo htmlspecialchars($pageTitle);
                                ?>
                            </h1>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="?page=dashboard">Admin</a></li>
                                    <?php if ($page !== 'dashboard'): ?>
                                    <li class="breadcrumb-item active"><?= htmlspecialchars($pageTitle) ?></li>
                                    <?php endif; ?>
                                </ol>
                            </nav>
                        </div>
                        
                        <div class="d-flex align-items-center gap-3">
                            <!-- Version Info -->
                            <span class="badge bg-secondary" title="Version <?= htmlspecialchars($currentVersion) ?>">
                                v<?= htmlspecialchars($currentVersion) ?>
                            </span>
                                                        
                            <!-- User Dropdown -->
                            <div class="dropdown">
                                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
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
                        echo '<div class="alert alert-warning">
                                <h5><i class="bi bi-exclamation-triangle"></i> Seite nicht gefunden</h5>
                                <p>Die angeforderte Admin-Seite existiert nicht.</p>
                                <a href="?page=dashboard" class="btn btn-primary">Zum Dashboard</a>
                              </div>';
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Performance Info (Development only) -->
    <?php if (getSetting('environment', 'production') === 'development'): ?>
    <div class="performance-info" id="performance-info">
        <!-- Wird via JavaScript gefüllt -->
    </div>
    <?php endif; ?>

    <!-- Core Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Admin-spezifische Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Performance Monitoring (Development)
        <?php if (getSetting('environment', 'production') === 'development'): ?>
        const perfInfo = document.getElementById('performance-info');
        if (perfInfo) {
            const loadTime = <?= round((microtime(true) - $pageStartTime) * 1000, 2) ?>;
            const memoryUsage = <?= round((memory_get_usage(true) - $memoryStart) / 1024 / 1024, 2) ?>;
            const totalMemory = <?= round(memory_get_usage(true) / 1024 / 1024, 2) ?>;
            
            perfInfo.innerHTML = `Load: ${loadTime}ms | Memory: +${memoryUsage}MB (${totalMemory}MB total)`;
            perfInfo.style.display = 'block';
            
            // Auto-hide nach 5 Sekunden
            setTimeout(() => {
                perfInfo.style.display = 'none';
            }, 5000);
        }
        <?php endif; ?>
        
        // Auto-Hide Alerts
        document.querySelectorAll('.alert').forEach(alert => {
            if (!alert.querySelector('.btn-close')) {
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.style.transition = 'opacity 0.3s';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 300);
                    }
                }, 8000);
            }
        });
        
        // Tooltip initialization
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"], [title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Confirm dialogs for destructive actions
        document.querySelectorAll('[data-confirm]').forEach(element => {
            element.addEventListener('click', function(e) {
                const message = this.dataset.confirm || 'Sind Sie sicher?';
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            });
        });
        
        console.log('📊 DVD Profiler Liste Admin Panel v<?= htmlspecialchars($currentVersion) ?> loaded');
        console.log('🔧 Build: <?= htmlspecialchars($buildDate) ?> | PHP: <?= PHP_VERSION ?>');
        <?php if ($isUpdateAvailable): ?>
        console.log('🔄 Update verfügbar! Gehen Sie zu Einstellungen → Updates');
        <?php endif; ?>
    });
    </script>
</body>
</html>