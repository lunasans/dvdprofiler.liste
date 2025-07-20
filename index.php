<?php
/*
 * DVD Profiler Liste - PHP basierte DVD Verwaltung
 * 
 * Diese Datei ist Teil des DVD Profiler Liste Projekts.
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 */
declare(strict_types=1);

// Zentrale Initialisierung ZUERST laden
try {
    require_once __DIR__ . '/includes/bootstrap.php';
    require_once __DIR__ . '/includes/counter.php';
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
$allowedPages = ['home', 'impressum', 'datenschutz', 'kontakt'];
if (!in_array($page, $allowedPages) && $page !== 'home') {
    $page = 'home';
}

// Site-Konfiguration laden
$siteTitle = getSetting('site_title', 'Meine DVD-Verwaltung');
$siteDescription = getSetting('site_description', 'Professionelle DVD-Sammlung verwalten und durchsuchen');
$version = getSetting('version', '1.4.5');
$theme = getSetting('theme', 'default');

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
             "style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://fonts.googleapis.com; " .
             "font-src 'self' https://cdn.jsdelivr.net https://fonts.gstatic.com; " .
             "img-src 'self' data: https:; " .
             "connect-src 'self';";
             
header("Content-Security-Policy: " . $cspPolicy);
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($metaDescription) ?>">
  <meta name="keywords" content="DVD, Sammlung, Filme, Verwaltung, Katalog">
  <meta name="author" content="René Neuhaus">
  <meta name="robots" content="index, follow">
  
  <!-- Open Graph Meta Tags für Social Media -->
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($metaDescription) ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?= htmlspecialchars($baseUrl) ?>">

  
  <!-- Twitter Card -->
  <meta name="twitter:card" content="summary">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($metaDescription) ?>">
  
  <title><?= htmlspecialchars($pageTitle) ?></title>
  
  <!-- Preload kritische Ressourcen --> 
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
  
  <!-- Stylesheets -->
  <link rel="stylesheet" href="css/style.css?v=<?= htmlspecialchars($version) ?>">
  <link href="libs/fancybox/dist/fancybox/fancybox.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  
  <!-- Conditional Theme Loading -->
  <?php if ($theme !== 'default'): ?>
    <link rel="stylesheet" href="css/themes/<?= htmlspecialchars($theme) ?>.css?v=<?= htmlspecialchars($version) ?>">
  <?php endif; ?>
  
  <!-- Favicon -->
  <link rel="icon" type="image/x-icon" href="<?= $baseUrl ?>favicon.ico">
  
  <!-- Structured Data für Suchmaschinen -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebApplication",
    "name": "<?= htmlspecialchars($siteTitle) ?>",
    "description": "<?= htmlspecialchars($siteDescription) ?>",
    "url": "<?= htmlspecialchars($baseUrl) ?>",
    "applicationCategory": "MultimediaApplication",
    "operatingSystem": "Web"
  }
  </script>
</head>
<body class="theme-<?= htmlspecialchars($theme) ?>" data-base-url="<?= htmlspecialchars($baseUrl) ?>">

<!-- Loading Indicator -->
<div id="loading-indicator" class="loading-indicator" style="display: none;">
  <div class="spinner"></div>
  <span>Lädt...</span>
</div>

<!-- ───────────── Header ───────────── -->
<?php 
try {
    include __DIR__ . '/partials/header.php'; 
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
        include __DIR__ . '/partials/film-list.php'; 
    } catch (Exception $e) {
        error_log('Film-list include error: ' . $e->getMessage());
        echo '<div class="error-message">Filme konnten nicht geladen werden.</div>';
    }
    ?>
  </section>

  <!-- Rechte Detailansicht -->
  <aside class="detail-panel" id="detail-container" role="complementary" aria-label="Film-Details">
    <div class="detail-placeholder">
      <i class="bi bi-film"></i>
      <p>Wählen Sie einen Film aus der Liste, um Details anzuzeigen.</p>
    </div>
  </aside>
</main>

<!-- ───────────── Footer ───────────── -->
<footer class="site-footer" role="contentinfo">
  <div class="footer-content">
    <div class="footer-left">
      <!-- Optional: Logo oder zusätzliche Informationen -->
    </div>

    <div class="footer-center">
      <div class="version-info">
        <div class="version-link">
          Version <a href="https://github.com/lunasans/dvdprofiler.liste" 
                    target="_blank" 
                    rel="noopener noreferrer"
                    aria-label="GitHub Repository öffnen">
            <?= htmlspecialchars($version) ?> 
            <i class="bi bi-github" aria-hidden="true"></i>
          </a>
        </div>
        <div class="visitor-counter">
          Besucher: <span id="visitor-count"><?= (int)($visits ?? 0) ?></span>
        </div>
        <div class="copyright">
          &copy; <?= date('Y') ?> René Neuhaus
        </div>
      </div>
    </div>

    <nav class="footer-right" role="navigation" aria-label="Footer Navigation">
      <ul class="footer-nav">
        <li><a href="?page=impressum">Impressum</a></li>
        <li><a href="?page=datenschutz">Datenschutz</a></li>
        <?php if (isset($_SESSION['user_id'])): ?>
          <li><a href="<?= $baseUrl ?>admin/" rel="nofollow">Admin-Panel</a></li>
          <li><a href="<?= $baseUrl ?>admin/logout.php" rel="nofollow">Logout</a></li>
        <?php else: ?>
          <li><a href="<?= $baseUrl ?>admin/login.php" rel="nofollow">Login</a></li>
        <?php endif; ?>
      </ul>
    </nav>
  </div>
</footer>

<!-- Cookie Consent Banner (DSGVO-konform) -->
<div id="cookie-banner" class="cookie-banner" style="display: none;">
  <div class="cookie-content">
    <p>Diese Website verwendet Cookies für eine bessere Benutzererfahrung. 
       <a href="?page=datenschutz">Mehr erfahren</a></p>
    <div class="cookie-buttons">
      <button id="accept-cookies" class="btn btn-primary">Akzeptieren</button>
      <button id="decline-cookies" class="btn btn-secondary">Ablehnen</button>
    </div>
  </div>
</div>

<!-- JavaScript -->
<script>
  // Globale Konfiguration
  window.AppConfig = {
    baseUrl: <?= json_encode($baseUrl) ?>,
    version: <?= json_encode($version) ?>,
    theme: <?= json_encode($theme) ?>,
    isLoggedIn: <?= json_encode(isset($_SESSION['user_id'])) ?>
  };
</script>

<script src="js/main.js?v=<?= htmlspecialchars($version) ?>"></script>
<script src="libs/fancybox/dist/fancybox/fancybox.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" 
        crossorigin="anonymous"></script>

<!-- Lazy Loading und Performance Optimierungen -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Loading Indicator Management
    const loadingIndicator = document.getElementById('loading-indicator');
    
    function showLoading() {
        if (loadingIndicator) loadingIndicator.style.display = 'flex';
    }
    
    function hideLoading() {
        if (loadingIndicator) loadingIndicator.style.display = 'none';
    }
    
    // Cookie Banner Management
    const cookieBanner = document.getElementById('cookie-banner');
    const acceptBtn = document.getElementById('accept-cookies');
    const declineBtn = document.getElementById('decline-cookies');
    
    // Zeige Cookie Banner wenn noch keine Entscheidung getroffen wurde
    if (!localStorage.getItem('cookieConsent') && cookieBanner) {
        cookieBanner.style.display = 'block';
    }
    
    if (acceptBtn) {
        acceptBtn.addEventListener('click', function() {
            localStorage.setItem('cookieConsent', 'accepted');
            cookieBanner.style.display = 'none';
        });
    }
    
    if (declineBtn) {
        declineBtn.addEventListener('click', function() {
            localStorage.setItem('cookieConsent', 'declined');
            cookieBanner.style.display = 'none';
        });
    }
    
    // Error Handling für AJAX-Requests
    window.addEventListener('unhandledrejection', function(event) {
        console.error('Unhandled promise rejection:', event.reason);
        hideLoading();
    });
    
    // Performance Monitoring
    if ('performance' in window && 'measure' in window.performance) {
        window.addEventListener('load', function() {
            setTimeout(function() {
                const loadTime = performance.timing.loadEventEnd - performance.timing.navigationStart;
                console.log('Page load time:', loadTime + 'ms');
            }, 0);
        });
    }
    
    // Expose global functions
    window.showLoading = showLoading;
    window.hideLoading = hideLoading;
});
</script>
<script src="js/main.js?v=<?= htmlspecialchars($version) ?>"></script>
</body>
</html>