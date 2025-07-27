<?php
/**
 * Footer für DVD Profiler Liste - Kompatibel mit neuem Core-System
 * Fixed: $baseUrl und andere Variablen für Core-System
 */

try {
    // Core-System verwenden falls verfügbar
    if (class_exists('DVDProfiler\\Core\\Application')) {
        $app = \DVDProfiler\Core\Application::getInstance();
        $settings = $app->getSettings();
        
        // Variablen aus Core-System
        $baseUrl = $settings->get('base_url', '');
        $siteTitle = $settings->get('site_title', 'DVD Profiler Liste');
        $environment = $settings->get('environment', 'production');
        
        // Legacy-Variablen falls nicht gesetzt
        if (!isset($version)) {
            $version = defined('DVDPROFILER_VERSION') ? DVDPROFILER_VERSION : '1.4.7';
        }
        if (!isset($codename)) {
            $codename = defined('DVDPROFILER_CODENAME') ? DVDPROFILER_CODENAME : 'Cinephile';
        }
        if (!isset($buildDate)) {
            $buildDate = defined('DVDPROFILER_BUILD_DATE') ? DVDPROFILER_BUILD_DATE : date('Y.m.d');
        }
        
    } else {
        // Fallback für Legacy-System
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        $siteTitle = $siteTitle ?? 'DVD Profiler Liste';
        $version = $version ?? '1.4.7';
        $codename = $codename ?? 'Cinephile';
        $buildDate = $buildDate ?? date('Y.m.d');
        $environment = 'production';
    }
    
    // Base URL normalisieren
    $baseUrl = rtrim($baseUrl, '/');
    if (!empty($baseUrl)) {
        $baseUrl .= '/';
    }
    
    // Statistiken abrufen (mit Fehlerbehandlung)
    $totalFilms = 0;
    $totalBoxsets = 0;
    $totalVisits = 0;
    $totalGenres = 0;
    
    try {
        if (isset($app)) {
            $database = $app->getDatabase();
            $totalFilms = (int) $database->fetchValue("SELECT COUNT(*) FROM dvds WHERE boxset_parent IS NULL OR boxset_parent = 0");
            $totalBoxsets = (int) $database->fetchValue("SELECT COUNT(*) FROM dvds WHERE (SELECT COUNT(*) FROM dvds d2 WHERE d2.boxset_parent = dvds.id) > 0");
            $totalGenres = (int) $database->fetchValue("SELECT COUNT(DISTINCT genre) FROM dvds WHERE genre IS NOT NULL AND genre != ''");
        }
        
        // Besucherzähler (falls vorhanden)
        if (isset($visits)) {
            $totalVisits = (int) $visits;
        } else {
            $counterFile = __DIR__ . '/../counter.txt';
            if (file_exists($counterFile)) {
                $totalVisits = (int) file_get_contents($counterFile);
            }
        }
        
    } catch (Exception $e) {
        error_log('Footer stats error: ' . $e->getMessage());
        // Bei Fehlern Standard-Werte verwenden
    }
    
    // Update-Check (simplified)
    $updateAvailable = false;
    $latestVersion = null;
    
    // Nur prüfen wenn Admin eingeloggt
    if (isset($_SESSION['user_id'])) {
        try {
            if (function_exists('isDVDProfilerUpdateAvailable')) {
                $updateAvailable = isDVDProfilerUpdateAvailable();
                if (function_exists('getDVDProfilerLatestVersion')) {
                    $latestVersion = getDVDProfilerLatestVersion();
                }
            }
        } catch (Exception $e) {
            // Update-Check fehlgeschlagen - ignorieren
        }
    }
    
} catch (Exception $e) {
    // Kompletter Fallback bei schweren Fehlern
    error_log('Footer initialization error: ' . $e->getMessage());
    $baseUrl = '';
    $siteTitle = 'DVD Profiler Liste';
    $version = '1.4.7';
    $codename = 'Cinephile';
    $buildDate = date('Y.m.d');
    $totalFilms = 0;
    $totalBoxsets = 0;
    $totalVisits = 0;
    $totalGenres = 0;
    $updateAvailable = false;
}
?>

<footer class="site-footer" role="contentinfo">
    <div class="footer-content">
        <div class="footer-left">
            <div class="footer-logo">
                <i class="bi bi-film"></i>
                <span><?= htmlspecialchars($siteTitle) ?></span>
            </div>
            <div class="footer-tagline">
                Moderne Filmsammlung verwalten
            </div>
            <?php if ($updateAvailable && isset($_SESSION['user_id'])): ?>
                <div class="update-notification">
                    <i class="bi bi-arrow-up-circle"></i>
                    <span>Update verfügbar: <?= htmlspecialchars($latestVersion['version'] ?? 'Unbekannt') ?></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer-center">
            <div class="footer-stats">
                <span class="stat-item" title="Filme in der Sammlung">
                    <i class="bi bi-collection"></i>
                    <?= number_format($totalFilms) ?> Filme
                </span>
                <?php if ($totalBoxsets > 0): ?>
                    <span class="stat-item" title="BoxSet Sammlungen">
                        <i class="bi bi-collection-play"></i>
                        <?= number_format($totalBoxsets) ?> BoxSets
                    </span>
                <?php endif; ?>
                <?php if ($totalVisits > 0): ?>
                    <span class="stat-item" title="Website Besucher">
                        <i class="bi bi-eye"></i>
                        <?= number_format($totalVisits) ?> Besucher
                    </span>
                <?php endif; ?>
                <?php if ($totalGenres > 0): ?>
                    <span class="stat-item" title="Verschiedene Genres">
                        <i class="bi bi-tags"></i>
                        <?= number_format($totalGenres) ?> Genres
                    </span>
                <?php endif; ?>
            </div>

            <div class="version-info">
                <div class="version-link">
                    <span class="version-badge" title="<?= htmlspecialchars($version . ' ' . $codename) ?>">
                        v<?= htmlspecialchars($version) ?>
                    </span>
                    <span class="codename"><?= htmlspecialchars($codename) ?></span>
                    <?php if (defined('DVDPROFILER_GITHUB_URL')): ?>
                        <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" rel="noopener noreferrer"
                            aria-label="GitHub Repository öffnen" title="Auf GitHub ansehen">
                            <i class="bi bi-github" aria-hidden="true"></i>
                        </a>
                    <?php endif; ?>
                </div>

                <div class="build-info">
                    Build <?= htmlspecialchars($buildDate) ?> | PHP <?= PHP_VERSION ?>
                </div>

                <div class="copyright">
                    &copy; <?= date('Y') ?> <?= defined('DVDPROFILER_AUTHOR') ? htmlspecialchars(DVDPROFILER_AUTHOR) : 'René Neuhaus' ?>
                </div>
            </div>
        </div>

        <nav class="footer-right" role="navigation" aria-label="Footer Navigation">
            <ul class="footer-nav">
                <li><a href="?page=impressum">Impressum</a></li>
                <li><a href="?page=datenschutz">Datenschutz</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="<?= $baseUrl ?>admin/" rel="nofollow">
                            Admin-Panel
                            <?php if ($updateAvailable): ?>
                                <span class="badge update-badge" title="Update verfügbar">!</span>
                            <?php endif; ?>
                        </a></li>
                    <li><a href="<?= $baseUrl ?>admin/logout.php" rel="nofollow">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?= $baseUrl ?>admin/login.php" rel="nofollow">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <!-- Erweiterte Info für Entwickler -->
    <?php if ($environment === 'development'): ?>
        <div class="footer-debug">
            <small>
                <strong>Debug:</strong> 
                Core: <?= class_exists('DVDProfiler\\Core\\Application') ? '✅' : '❌' ?> |
                Memory: <?= \DVDProfiler\Core\Utils::formatBytes(memory_get_usage(true)) ?> |
                Time: <?= number_format((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 1) ?>ms
            </small>
        </div>
    <?php endif; ?>

    <!-- Scroll Progress Indicator -->
    <div class="scroll-indicator" title="Scroll-Fortschritt"></div>
</footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Scroll Progress Indicator
    const scrollIndicator = document.querySelector('.scroll-indicator');
    if (scrollIndicator) {
        const updateProgress = () => {
            const scrollPercent = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
            scrollIndicator.style.width = Math.min(Math.max(scrollPercent, 0), 100) + '%';
        };
        
        window.addEventListener('scroll', updateProgress);
        updateProgress(); // Initial call
    }
    
    // Stats Animation
    const statItems = document.querySelectorAll('.stat-item');
    statItems.forEach((item, index) => {
        setTimeout(() => {
            item.classList.add('animate-in');
        }, index * 150);
    });
    
    // Tooltip for version badge
    const versionBadge = document.querySelector('.version-badge');
    if (versionBadge) {
        versionBadge.addEventListener('click', function() {
            if (navigator.clipboard) {
                const buildInfo = {
                    version: '<?= htmlspecialchars($version) ?>',
                    codename: '<?= htmlspecialchars($codename) ?>',
                    build: '<?= htmlspecialchars($buildDate) ?>',
                    php: '<?= PHP_VERSION ?>'
                };
                
                navigator.clipboard.writeText(JSON.stringify(buildInfo, null, 2))
                    .then(() => {
                        this.title = 'Build-Info kopiert!';
                        setTimeout(() => {
                            this.title = '<?= htmlspecialchars($version . ' ' . $codename) ?>';
                        }, 2000);
                    });
            }
        });
    }
});
</script>

<style>
/* Footer Styles für Core-System */
.footer-debug {
    text-align: center;
    padding: 0.5rem;
    background: rgba(255, 255, 255, 0.1);
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    font-family: 'Courier New', monospace;
}

.footer-stats {
    display: flex;
    gap: var(--space-md, 12px);
    margin-bottom: var(--space-md, 12px);
    flex-wrap: wrap;
    justify-content: center;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: var(--space-xs, 4px);
    font-size: 0.85rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    transition: all 0.3s ease;
    opacity: 0;
    transform: translateY(10px);
    cursor: help;
}

.stat-item.animate-in {
    opacity: 1;
    transform: translateY(0);
}

.stat-item:hover {
    color: var(--text-white, #ffffff);
    transform: translateY(-2px);
}

.version-badge {
    background: var(--gradient-accent, linear-gradient(135deg, #4facfe 0%, #00f2fe 100%));
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.version-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(79, 172, 254, 0.3);
}

.scroll-indicator {
    position: fixed;
    bottom: 0;
    left: 0;
    height: 3px;
    background: var(--gradient-accent, #4facfe);
    transition: width 0.1s ease;
    z-index: 100;
    width: 0;
}

.update-notification {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: var(--gradient-accent, #4facfe);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.7; }
}
</style>