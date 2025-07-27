<?php
/**
 * DVD Profiler Liste - Hauptseite
 * Beispiel für die Verwendung des neuen Core-Systems
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

// Core-System laden
require_once __DIR__ . '/includes/bootstrap.php';

// Application-Instance abrufen
$app = \DVDProfiler\Core\Application::getInstance();

try {
    // Sicherheitsvalidierung für Admin-Bereiche
    $page = $_GET['page'] ?? 'home';
    $allowedPages = ['home', 'stats', 'impressum', 'datenschutz'];
    
    if (!in_array($page, $allowedPages)) {
        $page = 'home';
    }
    
    // Search-Parameter verarbeiten
    $searchTerm = '';
    if (!empty($_GET['search'])) {
        // Neue Validation-Klasse nutzen
        $validator = \DVDProfiler\Core\Validation::make($_GET, [
            'search' => 'string|max:100|alpha_dash'
        ]);
        
        if (!$validator->hasErrors()) {
            $searchTerm = $validator->getValidatedData()['search'];
        }
    }
    
    // Rate-Limiting für Search-Anfragen
    $clientIP = \DVDProfiler\Core\Security::getClientIP();
    if (!$app->checkRateLimit("search_{$clientIP}", 30, 60)) {
        http_response_code(429);
        die('Zu viele Suchanfragen. Bitte warten Sie eine Minute.');
    }
    
    // Legacy-Variablen für bestehende Templates (Backward Compatibility)
    $siteTitle = $app->getSettings()->get('site_title', 'DVD Profiler Liste');
    $itemsPerPage = $app->getSettings()->getInt('items_per_page', 20);
    
    // Performance-Monitoring (Development)
    $startTime = microtime(true);
    
} catch (Exception $e) {
    error_log('[Index] Error: ' . $e->getMessage());
    http_response_code(500);
    
    if ($app->getSettings()->get('environment') === 'development') {
        die('<h1>Error</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
    } else {
        die('<h1>Wartung</h1><p>Die Seite ist vorübergehend nicht verfügbar.</p>');
    }
}

// ============================================
// Ab hier beginnt das normale HTML-Template
// ============================================
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle) ?></title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <!-- Meta Tags für SEO -->
    <meta name="description" content="<?= htmlspecialchars($app->getSettings()->get('site_description', 'Verwalten Sie Ihre DVD-Sammlung')) ?>">
    <meta name="robots" content="index, follow">
    
    <!-- Security Headers via Meta (zusätzlich zu PHP-Headern) -->
    <meta http-equiv="X-Content-Type-Options" content="nosniff">
    <meta http-equiv="X-Frame-Options" content="DENY">
</head>
<body>
    <!-- Skip Navigation für Accessibility -->
    <a href="#main-content" class="skip-nav">Zum Hauptinhalt springen</a>

    <!-- Header mit neuer Struktur -->
    <?php 
    try {
        include __DIR__ . '/partials/header.php'; 
    } catch (Exception $e) {
        error_log('Header include error: ' . $e->getMessage());
        echo '<header><h1>' . htmlspecialchars($siteTitle) . '</h1></header>';
    }
    ?>

    <!-- Hauptlayout -->
    <main class="layout" id="main-content" role="main">
        <!-- Film-Liste -->
        <section class="film-list-area" aria-label="Film-Liste">
            <?php 
            try {
                // Je nach Seite verschiedene Inhalte laden
                switch ($page) {
                    case 'stats':
                        include __DIR__ . '/partials/stats.php';
                        break;
                    case 'impressum':
                        include __DIR__ . '/partials/impressum.php';
                        break;
                    case 'datenschutz':
                        include __DIR__ . '/partials/datenschutz.php';
                        break;
                    default:
                        include __DIR__ . '/partials/film-list.php';
                }
            } catch (Exception $e) {
                error_log('Content include error: ' . $e->getMessage());
                echo '<div class="error-message">Inhalt konnte nicht geladen werden.</div>';
            }
            ?>
        </section>

        <!-- Detail-Panel (nur bei home) -->
        <?php if ($page === 'home'): ?>
        <aside class="detail-panel" id="detail-container" role="complementary" aria-label="Film-Details">
            <div class="detail-placeholder">
                <i class="bi bi-film"></i>
                <p>Wählen Sie einen Film aus der Liste, um Details anzuzeigen.</p>
            </div>
        </aside>
        <?php endif; ?>
    </main>

    <!-- Footer -->
    <?php
    try {
        include __DIR__ . '/includes/footer.php';
    } catch (Exception $e) {
        error_log('Footer include error: ' . $e->getMessage());
        // Fallback Footer mit neuen Core-Funktionen
        $version = \DVDProfiler\Core\Utils::env('DVDPROFILER_VERSION', '1.4.7');
        echo '<footer class="site-footer" role="contentinfo">
                <div class="footer-content">
                    <div class="version-info">
                        <div class="version-link">
                            Version ' . htmlspecialchars($version) . '
                        </div>
                        <div class="copyright">
                            &copy; ' . date('Y') . ' René Neuhaus
                        </div>
                    </div>
                </div>
              </footer>';
    }
    ?>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js"></script>
    <script src="js/main.js"></script>
    
    <!-- Performance-Info (Development only) -->
    <?php if ($app->getSettings()->get('environment') === 'development'): ?>
    <script>
        console.group('🚀 Performance Info');
        console.log('⏱️ Ladezeit:', <?= json_encode(number_format((microtime(true) - $startTime) * 1000, 2)) ?>ms);
        console.log('💾 Memory:', '<?= \DVDProfiler\Core\Utils::formatBytes(memory_get_peak_usage(true)) ?>');
        
        <?php 
        $dbStats = $app->getDatabase()->getStats();
        if ($dbStats['query_count'] > 0):
        ?>
        console.log('🗄️ DB Queries:', <?= $dbStats['query_count'] ?>);
        console.log('🗄️ DB Zeit:', <?= json_encode(number_format($dbStats['total_query_time'] * 1000, 2)) ?>ms);
        <?php endif; ?>
        
        console.groupEnd();
    </script>
    <?php endif; ?>

    <!-- CSRF-Token für AJAX-Requests -->
    <script>
        window.csrfToken = '<?= \DVDProfiler\Core\Security::generateCSRFToken() ?>';
    </script>
</body>
</html>

<?php
// Finale Performance-Logs (Development)
if ($app->getSettings()->get('environment') === 'development') {
    $loadTime = microtime(true) - $startTime;
    $memoryUsage = memory_get_peak_usage(true);
    $dbStats = $app->getDatabase()->getStats();
    
    error_log(sprintf(
        '[Performance] Page: %s | Time: %.3fs | Memory: %s | DB Queries: %d',
        $page,
        $loadTime,
        \DVDProfiler\Core\Utils::formatBytes($memoryUsage),
        $dbStats['query_count']
    ));
}
?>