<?php
/*
 * DVD Profiler Liste - PHP basierte DVD Verwaltung
 * 
 * Diese Datei ist Teil des DVD Profiler Liste Projekts.
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.8
 */
declare(strict_types=1);

// Zentrale Initialisierung ZUERST laden
try {
    require_once __DIR__ . '/includes/bootstrap.php';
    require_once __DIR__ . '/includes/counter.php';
    require_once __DIR__ . '/includes/version.php'; // Neue Versionsverwaltung laden
} catch (Exception $e) {
    error_log('Bootstrap error: ' . $e->getMessage());
    http_response_code(500);
    exit('Anwendungsfehler. Bitte versuchen Sie es später erneut.');
}

// Error Reporting für Development (nach Bootstrap-Loading)
if (getSetting('environment', 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Input Sanitization und Validierung
$search = isset($_GET['q']) ? trim(filter_var($_GET['q'], FILTER_SANITIZE_STRING)) : '';
$page = isset($_GET['page']) ? trim(filter_var($_GET['page'], FILTER_SANITIZE_STRING)) : 'home';
$filmId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Erlaubte Seiten definieren für Sicherheit
$allowedPages = ['home', 'impressum', 'datenschutz', 'kontakt', 'trailers', 'stats'];
if (!in_array($page, $allowedPages) && $page !== 'home') {
    $page = 'home';
}

// Site-Konfiguration laden (jetzt von der neuen Versionsverwaltung überschrieben)
$siteTitle = getSetting('site_title', 'DVD Profiler Liste');
$siteDescription = getSetting('site_description', 'Professionelle DVD-Sammlung verwalten und durchsuchen');
// $version wird jetzt von version.php bereitgestellt
// Theme laden: Gäste aus Cookie, Admin aus DB
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

if ($isAdmin) {
    // Admin: Theme aus DB
    $theme = getSetting('theme', 'default');
} else {
    // Gast: Theme aus Cookie (falls vorhanden), sonst DB-Default
    $theme = $_COOKIE['guest_theme'] ?? getSetting('theme', 'default');
    
    // Validierung (nur erlaubte Themes)
    $allowedThemes = ['default', 'dark', 'blue', 'green', 'red', 'purple'];
    if (!in_array($theme, $allowedThemes)) {
        $theme = 'default';
    }
}

// Sichere Base URL Generierung
function generateBaseUrl(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
    
    // Path sanitization
    $path = str_replace(['\\', '..'], ['/', ''], $path);
    $path = rtrim($path, '/');
    
    return $protocol . '://' . $host . $path . '/';
}

$baseUrl = generateBaseUrl();

// SEO und Meta-Daten
$pageTitle = $siteTitle;
$metaDescription = $siteDescription;

if (!empty($search)) {
    $pageTitle = "Suche: " . htmlspecialchars($search) . " - " . $siteTitle;
    $metaDescription = "Suchergebnisse für '" . htmlspecialchars($search) . "' in der DVD-Sammlung";
}

// CSP Header für zusätzliche Sicherheit
$cspPolicy = "default-src 'self'; " .
             "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
             "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; " .
             "frame-src 'self' https://www.youtube.com https://player.vimeo.com https://www.dailymotion.com; " . 
             "img-src 'self' data: https:; " .
             "font-src 'self' https://cdn.jsdelivr.net; " .
             "connect-src 'self';";
             
header("Content-Security-Policy: " . $cspPolicy);

// JSON-LD Schema für SEO
$schema = [
    '@context' => 'https://schema.org',
    '@type' => 'WebApplication',
    'name' => $siteTitle,
    'description' => $siteDescription,
    'url' => $baseUrl,
    'applicationCategory' => 'MultimediaApplication',
    'operatingSystem' => 'Web Browser',
    'author' => [
        '@type' => 'Person',
        'name' => DVDPROFILER_AUTHOR
    ],
    'version' => DVDPROFILER_VERSION
];
?>
<!DOCTYPE html>
<html lang="de" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta name="keywords" content="DVD Verwaltung, Filmsammlung, DVD Profiler, Medienverwaltung">
    <meta name="robots" content="index, follow">
    <meta name="author" content="<?= DVDPROFILER_AUTHOR ?>">
    
    <!-- Open Graph / Social Media -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($baseUrl) ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($siteTitle) ?>">
    
    <!-- Twitter Card -->
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">
    
    <title><?= htmlspecialchars($pageTitle) ?></title>
    
    <!-- Preload critical resources -->
    <link rel="preload" href="css/style.css" as="style">
    <link rel="preload" href="css/theme.css" as="style">
    <link rel="preload" href="css/film-view.css" rel="style">
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" as="style">
    
    <!-- CSS -->
    <link href="css/style.css" rel="stylesheet">
    <link href="css/theme.css" rel="stylesheet">
    <link href="css/film-view.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="libs/fancybox/dist/fancybox/fancybox.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎬</text></svg>">
    
    <!-- JSON-LD Schema -->
    <script type="application/ld+json">
    <?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
</head>
<body>
    

    <?php
    // Header laden
    try {
        include __DIR__ . '/includes/header.php'; 
    } catch (Exception $e) {
        error_log('Header include error: ' . $e->getMessage());
        echo '<header><h1>' . htmlspecialchars($siteTitle) . '</h1></header>';
    }
    ?>

    <!-- ───────────── Hauptlayout ───────────── -->
    <main class="layout" id="main-content" role="main">
        <!-- Linke Film-Liste + Tabs + Pagination -->
        <section class="film-list-area" aria-label="Film-Liste">
            <?php 
            try {
                // Lade immer film-list.php in der linken Seite
                include __DIR__ . '/partials/film-list.php';
            } catch (Exception $e) {
                error_log('Content include error: ' . $e->getMessage());
                echo '<div class="error-message">Inhalt konnte nicht geladen werden.</div>';
            }
            ?>
        </section>

        <!-- Rechte Detailansicht -->
        <aside class="detail-panel" id="detail-container" role="complementary" aria-label="Film-Details" style="padding-left: 24px;padding-right: 24px;">
            <div class="detail-placeholder">
                <i class="bi bi-film"></i>
                <p>Wählen Sie einen Film aus der Liste, um Details anzuzeigen.</p>
            </div>
        </aside>
    </main>

    <!-- ───────────── Footer ───────────── -->
    <?php
    // Neuen erweiterten Footer laden
    try {
        include __DIR__ . '/includes/footer.php';
    } catch (Exception $e) {
        error_log('Footer include error: ' . $e->getMessage());
        // Fallback Footer
        echo '<footer class="site-footer" role="contentinfo">
                <div class="footer-content">
                    <div class="footer-center">
                        <div class="version-info">
                            <div class="version-link">
                                Version <a href="' . DVDPROFILER_GITHUB_URL . '" target="_blank" rel="noopener noreferrer">
                                    ' . htmlspecialchars($version) . ' 
                                    <i class="bi bi-github"></i>
                                </a>
                            </div>
                            <div class="copyright">
                                &copy; ' . date('Y') . ' ' . DVDPROFILER_AUTHOR . '
                            </div>
                        </div>
                    </div>
                </div>
              </footer>';
    }
    ?>

    <!-- Cookie Consent Banner (DSGVO-konform) -->
    <div id="cookie-banner" class="cookie-banner" style="display: none;">
        <div class="cookie-content">
            <p>Diese Website verwendet Cookies für eine bessere Benutzererfahrung. 
               Durch die weitere Nutzung stimmen Sie dem zu.</p>
            <div class="cookie-buttons">
                <button onclick="acceptCookies()" class="btn btn-primary">
                    <i class="bi bi-check-lg"></i> Akzeptieren
                </button>
                <button onclick="declineCookies()" class="btn btn-secondary">
                    <i class="bi bi-x-lg"></i> Ablehnen
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="libs/fancybox/dist/index.umd.js"></script>
    <script src="js/main.js"></script>
    
    <!-- Cookie Consent Script -->
    <script>
        // Cookie Consent Management
        function showCookieBanner() {
            const banner = document.getElementById('cookie-banner');
            const consent = localStorage.getItem('cookieConsent');
            
            if (!consent) {
                banner.style.display = 'block';
                setTimeout(() => banner.classList.add('show'), 100);
            }
        }

        function acceptCookies() {
            localStorage.setItem('cookieConsent', 'accepted');
            hideCookieBanner();
            
            // Analytics oder andere Cookies hier aktivieren
            if (typeof gtag !== 'undefined') {
                gtag('consent', 'update', {
                    'analytics_storage': 'granted'
                });
            }
        }

        function declineCookies() {
            localStorage.setItem('cookieConsent', 'declined');
            hideCookieBanner();
        }

        function hideCookieBanner() {
            const banner = document.getElementById('cookie-banner');
            banner.classList.remove('show');
            setTimeout(() => banner.style.display = 'none', 300);
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Cookie-Banner nach kurzer Verzögerung anzeigen
            setTimeout(showCookieBanner, 2000);
            
            // Performance Monitoring
            if ('serviceWorker' in navigator && location.protocol === 'https:') {
                navigator.serviceWorker.register('/sw.js').catch(() => {
                    // Service Worker registration failed, but don't break the app
                });
            }
            
            // System Info für Debug (nur im Development)
            <?php if (getSetting('environment', 'production') === 'development'): ?>
            console.log('DVD Profiler Liste <?= getDVDProfilerVersionFull() ?>');
            console.log('Build: <?= DVDPROFILER_BUILD_DATE ?> | PHP: <?= PHP_VERSION ?>');
            console.log('Features: <?= count(array_filter(DVDPROFILER_FEATURES)) ?> aktiv');
            <?php endif; ?>
        });
    </script>
    
    <!-- Schema.org Rich Snippets für bessere SEO -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@type": "SoftwareApplication",
        "name": "<?= htmlspecialchars($siteTitle) ?>",
        "applicationCategory": "MultimediaApplication",
        "operatingSystem": "Web Browser",
        "url": "<?= htmlspecialchars($baseUrl) ?>",
        "author": {
            "@type": "Person",
            "name": "<?= DVDPROFILER_AUTHOR ?>"
        },
        "version": "<?= DVDPROFILER_VERSION ?>",
        "dateModified": "<?= DVDPROFILER_BUILD_DATE ?>",
        "description": "<?= htmlspecialchars($siteDescription) ?>"
    }
    </script>
</body>
</html>