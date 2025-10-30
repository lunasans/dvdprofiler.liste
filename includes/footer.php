<?php
// includes/footer.php - Vollständiger Footer für DVD Profiler Liste
require_once __DIR__ . '/version.php';

// Verwende zentrale Versionsinformationen
$currentVersion = DVDPROFILER_VERSION;
$author = DVDPROFILER_AUTHOR;
$githubUrl = DVDPROFILER_GITHUB_URL;
$codename = DVDPROFILER_CODENAME;
$buildDate = DVDPROFILER_BUILD_DATE;
$commit = DVDPROFILER_COMMIT;
$repository = DVDPROFILER_REPOSITORY;

// Statistiken sammeln
$stats = [
    'total_films' => 0,
    'total_boxsets' => 0,
    'total_genres' => 0,
    'total_visits' => 0,
    'storage_size' => 0
];

try {
    if (isset($pdo)) {
        // Filme zählen
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM dvds");
        $result = $stmt->fetch();
        $stats['total_films'] = $result['count'] ?? 0;
        
        // BoxSets zählen
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM dvds WHERE boxset_parent IS NOT NULL");
        $result = $stmt->fetch();
        $stats['total_boxsets'] = $result['count'] ?? 0;
        
        // Genres zählen
        $stmt = $pdo->query("SELECT COUNT(DISTINCT genre) as count FROM dvds WHERE genre IS NOT NULL AND genre != ''");
        $result = $stmt->fetch();
        $stats['total_genres'] = $result['count'] ?? 0;
        
        // Geschätzte Speichergröße (basierend auf Laufzeit)
        $stmt = $pdo->query("SELECT AVG(runtime) as avg_runtime, COUNT(*) as total FROM dvds WHERE runtime > 0");
        $result = $stmt->fetch();
        if ($result && $result['avg_runtime']) {
            $avgSize = ($result['avg_runtime'] / 60) * 4.5; // ~4.5GB pro Stunde geschätzt
            $stats['storage_size'] = round($avgSize * $result['total'], 1);
        }
    }
    
    // Besucher aus Counter-Datei
    $counterFile = dirname(__DIR__) . '/counter.txt';
    if (file_exists($counterFile)) {
        $stats['total_visits'] = (int)file_get_contents($counterFile);
    }
    
} catch (Exception $e) {
    error_log('Footer stats error: ' . $e->getMessage());
}

// Update-Check
$updateAvailable = false;
try {
    if (function_exists('isGitHubUpdateAvailable')) {
        $updateAvailable = isGitHubUpdateAvailable();
    }
} catch (Exception $e) {
    // Ignoriere Update-Check Fehler
}

$baseUrl = defined('BASE_URL') ? BASE_URL : '';
?>

<footer class="site-footer" role="contentinfo">
    <div class="footer-content">
        <div class="footer-left">
            <div class="footer-logo">
                <i class="bi bi-film"></i>
                <span>DVD Profiler Liste</span>
            </div>
            <div class="footer-tagline">
                Moderne Filmsammlung verwalten
            </div>
            <?php if ($updateAvailable): ?>
                <div class="update-notification">
                    <i class="bi bi-arrow-up-circle"></i>
                    <span>Update verfügbar!</span>
                </div>
            <?php endif; ?>
        </div>

        <div class="footer-center">
            <div class="footer-stats">
                <span class="stat-item" title="Filme in der Sammlung">
                    <i class="bi bi-collection"></i>
                    <?= number_format($stats['total_films']) ?> Filme
                </span>
                <?php if ($stats['total_boxsets'] > 0): ?>
                    <span class="stat-item" title="BoxSet Sammlungen">
                        <i class="bi bi-collection-play"></i>
                        <?= number_format($stats['total_boxsets']) ?> BoxSets
                    </span>
                <?php endif; ?>
                <span class="stat-item" title="Website Besucher">
                    <i class="bi bi-eye"></i>
                    <?= number_format($stats['total_visits']) ?> Besucher
                </span>
                <span class="stat-item" title="Verschiedene Genres">
                    <i class="bi bi-tags"></i>
                    <?= number_format($stats['total_genres']) ?> Genres
                </span>
                <?php if ($stats['storage_size'] > 0): ?>
                    <span class="stat-item" title="Geschätzte Speichergröße">
                        <i class="bi bi-hdd"></i>
                        <?= number_format($stats['storage_size'], 1) ?> GB
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="version-info">
                <div class="version-link">
                    <span class="version-badge" title="Version <?= $currentVersion ?> &quot;<?= $codename ?>&quot;">
                        v<?= $currentVersion ?>
                    </span>
                    <span class="codename"><?= $codename ?></span>
                    <a href="<?= htmlspecialchars($githubUrl) ?>" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       aria-label="GitHub Repository öffnen"
                       title="Auf GitHub ansehen">
                        <i class="bi bi-github" aria-hidden="true"></i>
                    </a>
                </div>
                
                <div class="build-info">
                    Build <?= $buildDate ?> | PHP <?= PHP_VERSION ?>
                </div>
                
                <div class="copyright">
                    &copy; <?= date('Y') ?> <?= htmlspecialchars($author) ?>
                </div>
            </div>
        </div>

        <nav class="footer-right" role="navigation" aria-label="Footer Navigation">
            <ul class="footer-nav">
                <li><a href="?page=impressum">Impressum</a></li>
                <li><a href="?page=datenschutz">Datenschutz</a></li>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <li><a href="<?= $baseUrl ?>/admin/" rel="nofollow">
                        Admin-Panel 
                        <?php if ($updateAvailable): ?>
                            <span class="badge update-badge" title="Update verfügbar">!</span>
                        <?php endif; ?>
                    </a></li>
                    <li><a href="<?= $baseUrl ?>/admin/logout.php" rel="nofollow">Logout</a></li>
                <?php else: ?>
                    <li><a href="<?= $baseUrl ?>/admin/login.php" rel="nofollow">Login</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>

    <!-- Erweiterte Info beim Hover/Klick -->
    <div class="footer-extended" id="footerExtended">
        <div class="tech-info">
            <div class="tech-item">
                <strong>Frontend:</strong> HTML5, CSS3 (Glass-Morphism), Vanilla JavaScript
            </div>
            <div class="tech-item">
                <strong>Backend:</strong> PHP <?= PHP_VERSION ?> | MySQL/MariaDB
            </div>
            <div class="tech-item">
                <strong>Libraries:</strong> Bootstrap Icons, Fancybox, Chart.js
            </div>
            <div class="tech-item">
                <strong>Repository:</strong> 
                <a href="<?= htmlspecialchars($githubUrl) ?>" target="_blank" rel="noopener">
                    <?= htmlspecialchars($repository) ?>
                </a>
            </div>
            <div class="tech-item">
                <strong>Letzter Commit:</strong> <?= htmlspecialchars($commit) ?>
            </div>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="admin-shortcuts">
                <a href="<?= $baseUrl ?>/admin/?page=settings" class="admin-link">
                    <i class="bi bi-gear"></i> System
                </a>
                <a href="<?= $baseUrl ?>/admin/?page=import" class="admin-link">
                    <i class="bi bi-upload"></i> Import
                </a>
                <?php if ($updateAvailable): ?>
                    <a href="<?= $baseUrl ?>/admin/?page=settings" class="admin-link update-link">
                        <i class="bi bi-arrow-up-circle"></i> Update
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scroll Progress Indicator -->
    <div class="scroll-indicator" title="Scroll-Fortschritt"></div>
</footer>

<script>
// Footer JavaScript für DVD Profiler Liste
document.addEventListener('DOMContentLoaded', function () {
    const footer = document.querySelector('.site-footer');
    const footerExtended = document.getElementById('footerExtended');
    
    // Scroll Progress Indicator
    const scrollIndicator = document.querySelector('.scroll-indicator');
    
    if (scrollIndicator) {
        window.addEventListener('scroll', function () {
            const scrollPercent = (window.scrollY / (document.documentElement.scrollHeight - window.innerHeight)) * 100;
            scrollIndicator.style.width = Math.min(Math.max(scrollPercent, 0), 100) + '%';
        });
    }

    // Footer Extended Info Toggle
    if (footerExtended) {
        let extendedVisible = false;
        
        // Desktop: Hover effect
        if (window.innerWidth > 768) {
            footer.addEventListener('mouseenter', function() {
                footerExtended.classList.add('show');
                extendedVisible = true;
            });
            
            footer.addEventListener('mouseleave', function() {
                setTimeout(() => {
                    footerExtended.classList.remove('show');
                    extendedVisible = false;
                }, 200);
            });
        } else {
            // Mobile: Click to toggle
            footer.addEventListener('click', function() {
                extendedVisible = !extendedVisible;
                footerExtended.classList.toggle('show', extendedVisible);
            });
        }
    }

    // Clipboard functionality for version badge
    const versionBadge = document.querySelector('.version-badge');
    if (versionBadge) {
        versionBadge.addEventListener('click', function() {
            const buildInfo = `DVD Profiler Liste v<?= $currentVersion ?> "${<?= $codename ?>}"\nBuild: <?= $buildDate ?>\nCommit: <?= $commit ?>\nPHP: <?= PHP_VERSION ?>`;
            
            if (navigator.clipboard) {
                navigator.clipboard.writeText(buildInfo).then(() => {
                    showToast('Build-Info in Zwischenablage kopiert!', 'success');
                });
            }
        });
    }
});

// Toast notification function
function showToast(message, type = 'info', duration = 3000) {
    const toastContainer = document.getElementById('toast-container') || createToastContainer();
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    toast.style.cssText = `
        background: var(--glass-bg-strong, rgba(255, 255, 255, 0.2));
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.3));
        border-radius: var(--radius-md, 8px);
        padding: var(--space-md, 12px);
        margin-bottom: var(--space-sm, 8px);
        color: var(--text-white, #ffffff);
        display: flex;
        align-items: center;
        gap: var(--space-sm, 8px);
        font-size: 0.9rem;
        animation: slideInRight 0.3s ease-out;
        box-shadow: var(--shadow-lg, 0 10px 25px rgba(0, 0, 0, 0.3));
        min-width: 300px;
        max-width: 400px;
    `;
    
    toastContainer.appendChild(toast);
    
    // Auto-remove
    setTimeout(() => {
        if (toast.parentNode) {
            toast.style.animation = 'slideOutRight 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }
    }, duration);
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toast-container';
    container.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        display: flex;
        flex-direction: column;
    `;
    document.body.appendChild(container);
    return container;
}
</script>

<style>
/* Vollständige Footer Styles für DVD Profiler Liste */
.site-footer {
    margin-top: auto;
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    backdrop-filter: blur(20px);
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    position: relative;
    overflow: hidden;
    transition: all 0.4s ease;
}

.footer-content {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2rem;
    display: grid;
    grid-template-columns: 1fr 2fr 1fr;
    gap: 2rem;
    align-items: start;
}

.footer-logo {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.footer-logo i {
    font-size: 1.3rem;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.footer-tagline {
    font-size: 0.85rem;
    opacity: 0.8;
    margin-bottom: 0.5rem;
}

.update-notification {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-size: 0.8rem;
    color: #4ade80;
    background: rgba(74, 222, 128, 0.1);
    padding: 4px 8px;
    border-radius: 6px;
    border: 1px solid rgba(74, 222, 128, 0.3);
    animation: pulse 2s ease-in-out infinite;
}

.footer-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 1rem;
    justify-content: center;
    margin-bottom: 1rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    cursor: help;
}

.stat-item:hover {
    color: var(--text-white, #ffffff);
    transform: translateY(-1px);
}

.stat-item i {
    color: var(--accent-color, #3498db);
}

.version-info {
    text-align: center;
}

.version-link {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.version-badge {
    background: var(--accent-color, #3498db);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.version-badge:hover {
    background: var(--accent-color-hover, #2980b9);
    transform: scale(1.05);
}

.codename {
    font-size: 0.8rem;
    opacity: 0.7;
    font-style: italic;
}

.version-link a {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 1.1rem;
    transition: all 0.3s ease;
}

.version-link a:hover {
    color: var(--accent-color, #3498db);
    transform: scale(1.1);
}

.build-info {
    font-size: 0.75rem;
    opacity: 0.6;
    margin-bottom: 0.5rem;
}

.copyright {
    font-size: 0.8rem;
    opacity: 0.7;
}

.footer-nav {
    list-style: none;
    padding: 0;
    margin: 0;
}

.footer-nav li {
    margin-bottom: 0.5rem;
}

.footer-nav a {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    text-decoration: none;
    font-size: 0.9rem;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.footer-nav a:hover {
    color: var(--text-white, #ffffff);
    transform: translateX(2px);
}

.update-badge {
    background: #f39c12;
    color: #fff;
    font-size: 0.6rem;
    padding: 1px 4px;
    border-radius: 2px;
    margin-left: 0.25rem;
}

.footer-extended {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    margin: 0 -2rem -2rem -2rem;
    padding: 1.5rem 2rem;
    border-radius: 0 0 20px 20px;
    opacity: 0;
    max-height: 0;
    overflow: hidden;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.footer-extended.show {
    opacity: 1;
    max-height: 300px;
}

.tech-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.5rem;
    font-size: 0.8rem;
    margin-bottom: 1rem;
}

.tech-item {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    transition: color 0.3s ease;
}

.tech-item:hover {
    color: var(--text-white, #ffffff);
}

.tech-item a {
    color: var(--accent-color, #3498db);
    text-decoration: none;
}

.admin-shortcuts {
    display: flex;
    gap: 0.5rem;
    padding-top: 0.5rem;
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    flex-wrap: wrap;
}

.admin-link {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.25rem 0.5rem;
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 6px;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 0.75rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.admin-link:hover {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: var(--text-white, #ffffff);
    transform: translateY(-2px);
}

.update-link {
    background: rgba(74, 222, 128, 0.2) !important;
    border-color: rgba(74, 222, 128, 0.4) !important;
    color: #4ade80 !important;
}

.scroll-indicator {
    position: absolute;
    bottom: 0;
    left: 0;
    height: 3px;
    width: 0%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 0 2px 0 0;
    transition: width 0.1s ease-out;
}

/* Animations */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes slideOutRight {
    from {
        transform: translateX(0);
        opacity: 1;
    }
    to {
        transform: translateX(100%);
        opacity: 0;
    }
}

@keyframes pulse {
    0%, 100% {
        opacity: 1;
    }
    50% {
        opacity: 0.6;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .footer-content {
        grid-template-columns: 1fr;
        text-align: center;
        gap: 1.5rem;
        padding: 1.5rem;
    }
    
    .footer-stats {
        flex-direction: column;
        gap: 0.5rem;
        align-items: center;
    }
    
    .stat-item {
        justify-content: center;
    }
    
    .footer-extended {
        margin: 0 -1.5rem -1.5rem -1.5rem;
        padding: 1rem 1.5rem;
        cursor: pointer;
    }
    
    .footer-extended::before {
        content: "💡 Tippen für technische Details";
        display: block;
        text-align: center;
        font-size: 0.7rem;
        opacity: 0.6;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
        margin-bottom: 0.5rem;
    }
    
    .footer-extended.show::before {
        display: none;
    }
    
    .tech-info {
        grid-template-columns: 1fr;
    }
    
    .admin-shortcuts {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .footer-stats {
        grid-template-columns: 1fr 1fr;
        gap: 0.25rem;
    }
    
    .stat-item {
        font-size: 0.8rem;
    }
    
    .version-badge {
        font-size: 0.8rem;
        padding: 2px 8px;
    }
}
</style>