<?php
/**
 * DVD Profiler Liste - Footer
 * Vereinfachte und aufgeräumte Version
 * 
 * @package    dvdprofiler.liste
 * @version    1.4.8
 * @author     René Neuhaus
 */

require_once __DIR__ . '/version.php';

// Versionsinformationen
$currentVersion = DVDPROFILER_VERSION;
$author = DVDPROFILER_AUTHOR;
$githubUrl = DVDPROFILER_GITHUB_URL;
$repository = DVDPROFILER_REPOSITORY;

// Statistiken sammeln
$stats = getDVDProfilerStatistics();

// Counter-Werte (falls neue counter.php verwendet wird)
$totalVisits = $GLOBALS['total_visits'] ?? ($stats['total_visits'] ?? 0);
$dailyVisits = $GLOBALS['daily_visits'] ?? 0;

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>

<footer class="site-footer" role="contentinfo">
    <div class="footer-content">
        <!-- Linke Seite: Logo & Branding -->
        <div class="footer-section">
            <div class="footer-logo">
                <i class="bi bi-film"></i>
                <span>DVD Profiler Liste</span>
            </div>
            <p class="footer-tagline">Moderne Filmverwaltung</p>
        </div>

        <!-- Mitte: Statistiken & Version -->
        <div class="footer-section footer-center">
            <div class="footer-stats">
                <div class="stat-item" title="Filme in der Sammlung">
                    <i class="bi bi-collection"></i>
                    <span><?= number_format($stats['total_films']) ?> Filme</span>
                </div>
                
                <?php if ($stats['total_boxsets'] > 0): ?>
                <div class="stat-item" title="BoxSet Sammlungen">
                    <i class="bi bi-collection-play"></i>
                    <span><?= number_format($stats['total_boxsets']) ?> Sets</span>
                </div>
                <?php endif; ?>
                
                <div class="stat-item" title="Website Besucher gesamt">
                    <i class="bi bi-eye"></i>
                    <span><?= number_format($totalVisits) ?> Besucher</span>
                </div>
                
                <?php if ($dailyVisits > 0): ?>
                <div class="stat-item" title="Besucher heute">
                    <i class="bi bi-calendar-day"></i>
                    <span><?= number_format($dailyVisits) ?> heute</span>
                </div>
                <?php endif; ?>
                
                <div class="stat-item" title="Verschiedene Genres">
                    <i class="bi bi-tags"></i>
                    <span><?= number_format($stats['total_genres']) ?> Genres</span>
                </div>
            </div>
            
            <!-- Version zentriert unter Statistik -->
            <div class="footer-meta">
                <div class="version-info">
                    <span class="version">v<?= $currentVersion ?></span>
                    <a href="<?= htmlspecialchars($githubUrl) ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       title="Auf GitHub ansehen"
                       aria-label="GitHub Repository">
                        <i class="bi bi-github"></i>
                    </a>
                </div>
                <div class="copyright">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($author) ?>
                </div>
            </div>
        </div>

        <!-- Rechts: Navigation horizontal -->
        <div class="footer-section footer-right">
            <nav class="footer-nav" aria-label="Footer Navigation">
                <a href="?page=impressum">Impressum</a>
                <a href="?page=datenschutz">Datenschutz</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="<?= $baseUrl ?>/admin/" rel="nofollow">Admin</a>
                    <a href="<?= $baseUrl ?>/admin/logout.php" rel="nofollow">Logout</a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>/admin/login.php" rel="nofollow">Login</a>
                <?php endif; ?>
            </nav>
        </div>
    </div>
    
    <!-- Scroll Progress Indicator -->
    <div class="scroll-progress" role="progressbar" aria-label="Scroll-Fortschritt"></div>
</footer>

<script>
// Footer JavaScript - Scroll Progress
(function() {
    const scrollProgress = document.querySelector('.scroll-progress');
    
    if (scrollProgress) {
        window.addEventListener('scroll', function() {
            const winHeight = window.innerHeight;
            const docHeight = document.documentElement.scrollHeight;
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollPercent = (scrollTop / (docHeight - winHeight)) * 100;
            
            scrollProgress.style.width = Math.min(Math.max(scrollPercent, 0), 100) + '%';
        }, { passive: true });
    }
})();
</script>

<style>
/* ============================================
   Footer Styles - Aufgeräumt & Modern
   ============================================ */

.site-footer {
    position: relative;
    background: var(--glass-bg-strong, rgba(20, 20, 30, 0.95));
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    margin-top: var(--space-2xl, 3rem);
    padding: var(--space-xl, 2rem) var(--space-lg, 1.5rem);
}

.footer-content {
    max-width: 1400px;
    margin: 0 auto;
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    gap: var(--space-xl, 2rem);
    align-items: start;
}

/* Logo & Branding */
.footer-logo {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 0.5rem);
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--text-white, #ffffff);
    margin-bottom: var(--space-sm, 0.5rem);
}

.footer-logo i {
    font-size: 1.5rem;
    color: var(--accent-color, #667eea);
}

.footer-tagline {
    font-size: 0.9rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.6));
    margin: 0;
}

/* Mitte: Statistiken + Version */
.footer-center {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: var(--space-md, 1rem);
}

.footer-stats {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-sm, 0.5rem);
    justify-content: center;
    align-items: center;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: var(--space-xs, 0.35rem);
    padding: var(--space-xs, 0.35rem) var(--space-sm, 0.5rem);
    background: var(--glass-bg, rgba(255, 255, 255, 0.05));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-md, 8px);
    font-size: 0.85rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    transition: all 0.3s ease;
}

.stat-item:hover {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.1));
    transform: translateY(-2px);
}

.stat-item i {
    font-size: 1rem;
    color: var(--accent-color, #667eea);
}

/* Rechts: Navigation horizontal */
.footer-right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
}

.footer-nav {
    display: flex;
    flex-direction: row;
    gap: var(--space-md, 1rem);
    align-items: center;
}

.footer-nav a {
    color: var(--text-glass, rgba(255, 255, 255, 0.7));
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    padding: var(--space-xs, 0.35rem);
    white-space: nowrap;
}

.footer-nav a:hover {
    color: var(--text-white, #ffffff);
    transform: translateY(-2px);
}

.footer-meta {
    text-align: center;
}

.version-info {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 0.5rem);
    justify-content: center;
    margin-bottom: var(--space-xs, 0.35rem);
}

.version {
    background: var(--accent-color, #667eea);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
}

.version-info a {
    color: var(--text-glass, rgba(255, 255, 255, 0.7));
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.version-info a:hover {
    color: var(--accent-color, #667eea);
    transform: scale(1.1);
}

.copyright {
    font-size: 0.8rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.5));
}

/* Scroll Progress Bar */
.scroll-progress {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    width: 0%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transition: width 0.1s ease;
}

/* ============================================
   Responsive Design
   ============================================ */

@media (max-width: 992px) {
    .footer-content {
        grid-template-columns: 1fr;
        gap: var(--space-lg, 1.5rem);
        text-align: center;
    }
    
    .footer-section {
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    
    .footer-center {
        order: -1; /* Statistiken + Version zuerst */
    }
    
    .footer-stats {
        justify-content: center;
    }
    
    .footer-nav {
        flex-wrap: wrap;
        justify-content: center;
    }
}

@media (max-width: 576px) {
    .site-footer {
        padding: var(--space-lg, 1.5rem) var(--space-md, 1rem);
    }
    
    .footer-stats {
        gap: var(--space-xs, 0.35rem);
    }
    
    .stat-item {
        font-size: 0.8rem;
        padding: 0.25rem 0.4rem;
    }
    
    .stat-item span {
        display: none; /* Nur Icons auf sehr kleinen Screens */
    }
    
    .footer-nav {
        gap: var(--space-sm, 0.5rem);
        font-size: 0.85rem;
    }
    
    .footer-nav a {
        padding: 0.25rem 0.5rem;
    }
}

/* ============================================
   Print Styles
   ============================================ */

@media print {
    .site-footer {
        background: none;
        border-top: 1px solid #000;
        padding: 1rem 0;
    }
    
    .footer-stats,
    .footer-nav,
    .scroll-progress {
        display: none;
    }
    
    .footer-content {
        display: block;
    }
}
</style>