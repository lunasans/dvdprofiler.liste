<?php
declare(strict_types=1);

// Bootstrap (startet bereits die Session)
require_once __DIR__ . '/../includes/bootstrap.php';

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

// Version und Update-Informationen
$currentVersion = DVDPROFILER_VERSION;
$versionName = DVDPROFILER_CODENAME;
$buildDate = DVDPROFILER_BUILD_DATE;
$buildInfo = getDVDProfilerBuildInfo();

// Update-System: ROBUSTE Implementierung mit Fehlerbehandlung
$isUpdateAvailable = false;
$latestVersion = null;
$updateError = null;

try {
    // Debug: Update-API testen
    if (getSetting('environment', 'production') === 'development') {
        error_log('Testing update API...');
    }
    
    $isUpdateAvailable = isDVDProfilerUpdateAvailable();
    if ($isUpdateAvailable) {
        $latestVersionData = getDVDProfilerLatestVersion();
        
        if ($latestVersionData && is_array($latestVersionData)) {
            $latestVersion = $latestVersionData['tag_name'] ?? $latestVersionData['version'] ?? null;
            
            // Debug: API-Antwort loggen
            if (getSetting('environment', 'production') === 'development') {
                error_log('Update API Response: ' . print_r($latestVersionData, true));
            }
        } else {
            $updateError = 'Invalid API response format';
            $isUpdateAvailable = false;
        }
    }
} catch (Exception $e) {
    $updateError = $e->getMessage();
    error_log('Update check failed: ' . $e->getMessage());
    $isUpdateAvailable = false;
    $latestVersion = null;
}

$systemHealth = getSystemHealth();

// Performance-Monitoring für Admin
$pageStartTime = microtime(true);
$memoryStart = memory_get_usage(true);

// SICHERE JavaScript-Variablen mit vollständiger Validierung
function safeJsonEncode($data): string {
    $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_HEX_QUOT | JSON_HEX_APOS);
    return $encoded !== false ? $encoded : 'null';
}

function safeJsString($value): string {
    if ($value === null) return 'null';
    // Doppelte Sicherheit: htmlspecialchars + json_encode
    $safe = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '"' . str_replace(['"', "'", "\n", "\r", "\t"], ['\"', "\'", "\\n", "\\r", "\\t"], $safe) . '"';
}

// Sichere JavaScript-Variablen zusammenstellen
$jsVars = [
    'version' => $currentVersion,
    'codename' => $versionName,
    'buildDate' => $buildDate,
    'siteTitle' => $siteTitle,
    'featuresCount' => count(array_filter(DVDPROFILER_FEATURES)),
    'environment' => getSetting('environment', 'production'),
    'updateAvailable' => $isUpdateAvailable,
    'latestVersion' => $latestVersion,
    'updateError' => $updateError
];

// Features und BuildInfo sicher enkodieren
$safeFeaturesJson = safeJsonEncode(array_keys(array_filter(DVDPROFILER_FEATURES)));
$safeBuildInfoJson = safeJsonEncode($buildInfo);

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
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="css/admin.css" rel="stylesheet">
    
    <!-- Meta Tags -->
    <meta name="description" content="Admin Center für <?= htmlspecialchars($siteTitle) ?>">
    <meta name="robots" content="noindex, nofollow">
    <meta name="author" content="<?= DVDPROFILER_AUTHOR ?>">
    <meta name="application-name" content="DVD Profiler Liste Admin">
    <meta name="application-version" content="<?= DVDPROFILER_VERSION ?>">
    <meta name="application-build" content="<?= DVDPROFILER_BUILD_DATE ?>">
    
    <!-- SICHERE JavaScript-Konfiguration mit Fehlerbehandlung -->
    <script>
        // Sichere Admin-Konfiguration (alle Werte validiert und escaped)
        window.DVDAdmin = {
            version: <?= safeJsString($jsVars['version']) ?>,
            codename: <?= safeJsString($jsVars['codename']) ?>,
            buildDate: <?= safeJsString($jsVars['buildDate']) ?>,
            siteTitle: <?= safeJsString($jsVars['siteTitle']) ?>,
            featuresCount: <?= (int)$jsVars['featuresCount'] ?>,
            environment: <?= safeJsString($jsVars['environment']) ?>,
            updateAvailable: <?= $jsVars['updateAvailable'] ? 'true' : 'false' ?>,
            latestVersion: <?= $jsVars['latestVersion'] ? safeJsString($jsVars['latestVersion']) : 'null' ?>,
            updateError: <?= $jsVars['updateError'] ? safeJsString($jsVars['updateError']) : 'null' ?>,
            features: <?= $safeFeaturesJson ?>,
            buildInfo: <?= $safeBuildInfoJson ?>
        };
        
        // Debug-Ausgabe für Entwicklung
        <?php if (getSetting('environment', 'production') === 'development'): ?>
        console.log('DVDAdmin Config loaded:', window.DVDAdmin);
        if (window.DVDAdmin.updateError) {
            console.warn('Update API Error:', window.DVDAdmin.updateError);
        }
        <?php endif; ?>
    </script>
    
    <style>
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
        
        .update-notification {
            background: linear-gradient(135deg, #f39c12 0%, #e74c3c 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            animation: pulse 2s ease-in-out infinite;
        }
        
        .update-error {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.8; }
        }
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
        <?php if ($isUpdateAvailable && $latestVersion): ?>
            <br><small><i class="bi bi-arrow-up-circle text-warning"></i> Update: v<?= htmlspecialchars($latestVersion) ?></small>
        <?php elseif ($updateError): ?>
            <br><small><i class="bi bi-exclamation-triangle text-warning"></i> Update-Check: Fehler</small>
        <?php endif; ?>
    </div>

    <div class="admin-layout">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Content -->
        <main class="admin-content">
            <div class="container-fluid p-4">
                <!-- Update Notification -->
                <?php if ($isUpdateAvailable && $latestVersion): ?>
                <div class="update-notification">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-arrow-up-circle me-2"></i>
                            <strong>Update verfügbar!</strong>
                            <span class="ms-2">Eine neue Version (v<?= htmlspecialchars($latestVersion) ?>) ist verfügbar.</span>
                        </div>
                        <div>
                            <a href="?page=settings&tab=updates" class="btn btn-light btn-sm me-2">
                                Details ansehen
                            </a>
                            <button class="btn-close btn-close-white" onclick="this.parentElement.parentElement.parentElement.style.display='none'"></button>
                        </div>
                    </div>
                </div>
                <?php elseif ($updateError && getSetting('environment', 'production') === 'development'): ?>
                <div class="update-error">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Update-API Fehler:</strong>
                            <span class="ms-2"><?= htmlspecialchars($updateError) ?></span>
                        </div>
                        <button class="btn-close btn-close-white" onclick="this.parentElement.parentElement.style.display='none'"></button>
                    </div>
                </div>
                <?php endif; ?>

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
                        
                        <?php if ($isUpdateAvailable && $latestVersion): ?>
                        <a href="?page=settings&tab=updates" class="btn btn-warning btn-sm" title="Update verfügbar: v<?= htmlspecialchars($latestVersion) ?>">
                            <i class="bi bi-arrow-up-circle"></i>
                            Update v<?= htmlspecialchars($latestVersion) ?>
                        </a>
                        <?php endif; ?>
                        
                        <div class="dropdown">
                            <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle"></i>
                                <?= htmlspecialchars($_SESSION['username'] ?? $_SESSION['user_email'] ?? 'Admin') ?>
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
                    // Enhanced error handling
                    if (file_exists(__DIR__ . "/pages/{$page}.php")) {
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
                                <i class="bi bi-cloud"></i>
                                Update-API: 
                                <?php if ($isUpdateAvailable): ?>
                                    <span class="text-success">Verfügbar</span>
                                <?php elseif ($updateError): ?>
                                    <span class="text-warning">Fehler</span>
                                <?php else: ?>
                                    <span class="text-info">Kein Update</span>
                                <?php endif; ?>
                                <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" class="text-muted ms-2">
                                    <i class="bi bi-box-arrow-up-right"></i>
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
    
    <!-- SICHERE Admin JavaScript mit Fehlerbehandlung -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Überprüfe ob DVDAdmin korrekt geladen wurde
            if (typeof window.DVDAdmin === 'undefined') {
                console.error('DVDAdmin configuration failed to load!');
                return;
            }
            
            // Page Loader
            const loader = document.getElementById('pageLoader');
            const systemStatus = document.getElementById('systemStatus');
            
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

            // Enhanced form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        submitBtn.disabled = true;
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verarbeitung...';
                        
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 30000);
                    }
                });
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'd') {
                    e.preventDefault();
                    window.location.href = '?page=dashboard';
                }
            });

            // Debug-Ausgabe für Development (mit Fehlerbehandlung)
            if (window.DVDAdmin.environment === 'development') {
                console.log('DVD Profiler Liste Admin Center v' + window.DVDAdmin.version + ' "' + window.DVDAdmin.codename + '" ready');
                console.log('Build: ' + window.DVDAdmin.buildDate + ' | Features: ' + window.DVDAdmin.featuresCount + ' aktiv');
                
                if (window.DVDAdmin.updateError) {
                    console.warn('Update-Fehler:', window.DVDAdmin.updateError);
                } else {
                    console.log('Update verfügbar: ' + (window.DVDAdmin.updateAvailable ? 'Ja (v' + window.DVDAdmin.latestVersion + ')' : 'Nein'));
                }
            }
        });

        // Global admin functions (mit Fehlerbehandlung)
        window.dvdAdmin = {
            version: window.DVDAdmin?.version || 'Unknown',
            updateAvailable: window.DVDAdmin?.updateAvailable || false,
            
            showToast: function(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `alert alert-${type} position-fixed`;
                toast.style.cssText = 'top: 20px; right: 20px; z-index: 1060; min-width: 250px;';
                toast.innerHTML = message + ' <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>';
                document.body.appendChild(toast);
                setTimeout(() => toast.remove(), 5000);
            },
            
            testUpdateAPI: function() {
                fetch('https://update.neuhaus.or.at/update-api.php')
                    .then(response => response.json())
                    .then(data => {
                        console.log('Update API Test:', data);
                        this.showToast('Update-API funktioniert: ' + (data.tag_name || 'OK'), 'success');
                    })
                    .catch(error => {
                        console.error('Update API Test failed:', error);
                        this.showToast('Update-API Fehler: ' + error.message, 'danger');
                    });
            }
        };
    </script>
</body>
</html>