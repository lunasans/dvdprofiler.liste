<?php
/**
 * DVD Profiler Liste - Hauptseite
 * Fixed: Neueste Filme in Sidebar wieder hinzugefügt
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
    
    // FIXED: Neueste Filme für Sidebar laden (mit neuer Database-Klasse)
    $latestFilms = [];
    if ($page === 'home') {
        try {
            $database = $app->getDatabase();
            $latestFilms = $database->fetchAll(
                "SELECT id, title, year, genre, runtime, cover_id, created_at 
                 FROM dvds 
                 ORDER BY created_at DESC, id DESC 
                 LIMIT 10"
            );
        } catch (Exception $e) {
            error_log('Latest films query error: ' . $e->getMessage());
            $latestFilms = [];
        }
    }
    
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

// Helper-Funktionen für Sidebar
function formatRuntime(?int $minutes): string {
    if (!$minutes || $minutes <= 0) return '';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}

function findCoverImage(?string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string {
    if (empty($coverId)) return $fallback;
    $extensions = ['.jpg', '.jpeg', '.png'];
    foreach ($extensions as $ext) {
        $file = "{$folder}/{$coverId}{$suffix}{$ext}";
        if (file_exists($file)) {
            return $file;
        }
    }
    return $fallback;
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

        <!-- FIXED: Sidebar mit neuesten Filmen -->
        <?php if ($page === 'home'): ?>
        <aside class="detail-panel" id="detail-container" role="complementary" aria-label="Neueste Filme">
            <div class="sidebar-content">
                <header class="sidebar-header">
                    <h2>
                        <i class="bi bi-stars"></i>
                        Neu hinzugefügt
                        <span class="item-count">(<?= count($latestFilms) ?>)</span>
                    </h2>
                </header>

                <section class="latest-films">
                    <?php if (empty($latestFilms)): ?>
                        <div class="empty-state">
                            <i class="bi bi-film"></i>
                            <p>Noch keine Filme in der Sammlung vorhanden.</p>
                        </div>
                    <?php else: ?>
                        <div class="latest-list">
                            <?php foreach ($latestFilms as $film): 
                                // Sichere Werte extrahieren
                                $title = htmlspecialchars($film['title'] ?? 'Unbekannt');
                                $year = $film['year'] ? (int)$film['year'] : 0;
                                $id = (int)($film['id'] ?? 0);
                                $runtime = $film['runtime'] ? (int)$film['runtime'] : 0;
                                $genre = htmlspecialchars($film['genre'] ?? '');
                                $coverId = $film['cover_id'] ?? '';
                                $coverImage = findCoverImage($coverId, 'f', 'cover');
                                
                                // Datum formatieren
                                $createdAt = '';
                                if (!empty($film['created_at'])) {
                                    try {
                                        $date = new DateTime($film['created_at']);
                                        $createdAt = $date->format('d.m.Y');
                                    } catch (Exception $e) {
                                        $createdAt = '';
                                    }
                                }
                            ?>
                                <article class="latest-item" data-film-id="<?= $id ?>">
                                    <div class="latest-cover">
                                        <img src="<?= htmlspecialchars($coverImage) ?>" 
                                             alt="<?= $title ?> Cover" 
                                             loading="lazy"
                                             onerror="this.src='cover/placeholder.png'">
                                    </div>
                                    
                                    <div class="latest-info">
                                        <h3 class="latest-title">
                                            <a href="film-fragment.php?id=<?= $id ?>" 
                                               class="film-link"
                                               title="<?= $title ?> ansehen">
                                                <?= $title ?>
                                            </a>
                                        </h3>
                                        
                                        <div class="latest-meta">
                                            <?php if ($year > 0): ?>
                                                <span class="latest-year"><?= $year ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ($runtime > 0): ?>
                                                <span class="latest-runtime"><?= formatRuntime($runtime) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($genre): ?>
                                            <div class="latest-genre"><?= $genre ?></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($createdAt): ?>
                                            <div class="latest-date">
                                                <i class="bi bi-calendar-plus"></i>
                                                <?= $createdAt ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
                
                <!-- Footer für Sidebar -->
                <footer class="sidebar-footer">
                    <a href="?page=stats" class="stats-link">
                        <i class="bi bi-bar-chart"></i>
                        Alle Statistiken anzeigen
                    </a>
                </footer>
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
                            &copy; ' . date('Y') . ' DVD Profiler Liste
                        </div>
                    </div>
                </div>
              </footer>';
    }
    ?>

    <!-- JavaScript für Enhanced Features -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Film-Links in der Sidebar enhancen
        const filmLinks = document.querySelectorAll('.film-link');
        filmLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const url = this.href;
                
                // AJAX-Call für film-fragment.php
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        // Ersetze Detail-Panel Inhalt mit Film-Details
                        const detailContainer = document.getElementById('detail-container');
                        if (detailContainer) {
                            detailContainer.innerHTML = html;
                            detailContainer.scrollIntoView({ behavior: 'smooth' });
                        }
                    })
                    .catch(error => {
                        console.error('Error loading film details:', error);
                        // Fallback: Normale Navigation
                        window.location.href = url;
                    });
            });
        });
        
        // Performance-Logging (Development)
        <?php if ($app->getSettings()->get('environment') === 'development'): ?>
        const loadTime = performance.now();
        console.log(`🚀 Page loaded in ${loadTime.toFixed(2)}ms`);
        <?php endif; ?>
    });
    </script>
</body>
</html>