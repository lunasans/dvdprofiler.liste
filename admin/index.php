<?php
declare(strict_types=1);

// Bootstrap (startet bereits die Session)
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/version.php'; // Neue Versionsverwaltung laden
require_once __DIR__ . '/../includes/session-security-check.php'; // Session-Sicherheitscheck

// Zugriffsschutz
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Erlaubte Seiten - AKTUALISIERT: 'films' hinzugefügt
$allowedPages = ['dashboard', 'users', 'settings', 'import', 'update', 'films', 'impressum', 'tmdb-import', 'statistics','signature-preview'];
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
    <link href="css/settings.css" rel="stylesheet">
    
    <!-- Favicons -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
</head>
<body>
    
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
                                'update' => 'arrow-up-circle',
                                'films' => 'film',
                                'impressum' => 'info-circle',
                                'tmdb-import' => 'cloud-download',
                                'statistics' => 'bar-chart',
                                'signature-preview' => 'eye'
                            ];
                            $icon = $pageIcons[$page] ?? 'file-earmark';
                            ?>
                            <i class="bi bi-<?= $icon ?>"></i>
                            <?= ucfirst($page === 'films' ? 'Filme' : $page) ?>
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
            // Active navigation highlighting
            const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes(`page=${currentPage}`)) {
                    link.classList.add('active');
                }
            });

            // Auto-hide alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                if (!alert.classList.contains('alert-danger')) {
                    setTimeout(() => {
                        alert.style.transition = 'opacity 0.5s';
                        alert.style.opacity = '0';
                        setTimeout(() => alert.remove(), 500);
                    }, 5000);
                }
            });

            console.log('DVD Profiler Admin loaded - v<?= DVDPROFILER_VERSION ?>');
        });
    </script>
</body>
</html>