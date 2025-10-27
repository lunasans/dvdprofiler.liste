<?php
// inc/footer.php - Erweiterte Footer für DVD Profiler Liste


// Hole Statistiken für Footer
$dvdProfilerStats = getDVDProfilerStatistics();
$totalFilms = $dvdProfilerStats['total_films'] ?? 0;
$totalBoxsets = $dvdProfilerStats['total_boxsets'] ?? 0;
$totalVisits = $dvdProfilerStats['total_visits'] ?? $visits ?? 0;
$totalGenres = $dvdProfilerStats['total_genres'] ?? 0;
$storageSize = $dvdProfilerStats['storage_size'] ?? 0;

//  Update Check
$updateAvailable = isDVDProfilerUpdateAvailable();
$latestVersion = getDVDProfilerLatestVersion();
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
                <span class="stat-item" title="Website Besucher">
                    <i class="bi bi-eye"></i>
                    <?= number_format($totalVisits) ?> Besucher
                </span>
                <span class="stat-item" title="Verschiedene Genres">
                    <i class="bi bi-tags"></i>
                    <?= number_format($totalGenres) ?> Genres
                </span>
                <?php if ($storageSize > 0): ?>
                    <span class="stat-item" title="Geschätzte Speichergröße">
                        <i class="bi bi-hdd"></i>
                        <?= number_format($storageSize, 1) ?> GB
                    </span>
                <?php endif; ?>
            </div>
            
            <div class="version-info">
                <div class="version-link">
                    <span class="version-badge" 
                          title="<?= getDVDProfilerVersionFull() ?>" 
                          data-clipboard="<?= htmlspecialchars(json_encode(getDVDProfilerBuildInfo())) ?>">
                        v<?= $version ?>
                    </span>
                    <span class="codename"><?= $codename ?></span>
                    <a href="<?= DVDPROFILER_GITHUB_URL ?>" 
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
                    &copy; <?= date('Y') ?> <?= DVDPROFILER_AUTHOR ?>
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
                <strong>Features:</strong>
                <?php
                $enabledFeatures = array_keys(array_filter(DVDPROFILER_FEATURES));
                echo count($enabledFeatures) . ' aktiv (' . count(DVDPROFILER_FEATURES) . ' gesamt)';
                ?>
            </div>
            <div class="tech-item">
                <strong>Libraries:</strong> Bootstrap Icons, Fancybox, Chart.js
            </div>
            <div class="tech-item">
                <strong>Repository:</strong> 
                <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" rel="noopener">
                    <?= DVDPROFILER_REPOSITORY ?>
                </a>
            </div>
            <div class="tech-item">
                <strong>Letzter Commit:</strong> <?= DVDPROFILER_COMMIT ?>
            </div>
        </div>
        
        <?php if (isDVDProfilerFeatureEnabled('system_updates') && isset($_SESSION['user_id'])): ?>
            <div class="admin-shortcuts">
                <a href="<?= $baseUrl ?>admin/?page=settings" class="admin-link">
                    <i class="bi bi-gear"></i> System
                </a>
                <a href="<?= $baseUrl ?>admin/?page=import" class="admin-link">
                    <i class="bi bi-upload"></i> Import
                </a>
                <?php if ($updateAvailable): ?>
                    <a href="<?= $baseUrl ?>admin/?page=settings&action=update" class="admin-link update-link">
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
                    footerExtended.classList.remove('show');
                    extendedVisible = false;
                });
            } else {
                // Mobile: Click to toggle
                footer.addEventListener('click', function(e) {
                    if (!e.target.closest('a') && !e.target.closest('button')) {
                        footerExtended.classList.toggle('show');
                        extendedVisible = !extendedVisible;
                    }
                });
            }
        }

        // Konami Code Easter Egg für Film-Sammler
        let konamiCode = [];
        const konamiSequence = [
            'ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown',
            'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight',
            'KeyB', 'KeyA'
        ];

        document.addEventListener('keydown', function (e) {
            konamiCode.push(e.code);

            if (konamiCode.length > konamiSequence.length) {
                konamiCode.shift();
            }

            if (konamiCode.join(',') === konamiSequence.join(',')) {
                const footerLogo = document.querySelector('.footer-logo');
                if (footerLogo) {
                    footerLogo.classList.add('konami-active');
                    showDVDProfilerToast('🎬 DVD Profiler Konami Code aktiviert! Hail to the Cinephile!', 'success');

                    // Special effect: make all stat icons spin
                    document.querySelectorAll('.stat-item i').forEach(icon => {
                        icon.style.animation = 'spin360 1s ease-in-out';
                    });

                    setTimeout(() => {
                        footerLogo.classList.remove('konami-active');
                        document.querySelectorAll('.stat-item i').forEach(icon => {
                            icon.style.animation = '';
                        });
                    }, 2000);
                }

                konamiCode = [];
            }
        });

        // Version Badge Click Event - Erweiterte System-Infos
        const versionBadge = document.querySelector('.version-badge');
        if (versionBadge) {
            versionBadge.addEventListener('click', function () {
                const buildInfo = <?= json_encode(getDVDProfilerBuildInfo()) ?>;
                const versionInfo = `DVD Profiler Liste v${buildInfo.version} "${buildInfo.codename}"
Build: ${buildInfo.build_date} (${buildInfo.build_type})
Branch: ${buildInfo.branch} | Commit: ${buildInfo.commit}
Author: ${buildInfo.author}
Repository: ${buildInfo.repository}
PHP: ${buildInfo.php_version}
Features: ${Object.keys(buildInfo.features).filter(key => buildInfo.features[key]).length} aktiv
User Agent: ${navigator.userAgent.substring(0, 50)}...

GitHub: ${buildInfo.github_url}`;

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(versionInfo).then(() => {
                        showDVDProfilerToast('📋 System-Informationen kopiert!', 'success');
                    }).catch(() => {
                        showDVDProfilerToast('ℹ️ System-Info: ' + versionInfo.split('\n')[0], 'info');
                    });
                } else {
                    showDVDProfilerToast('ℹ️ ' + versionInfo.replace(/\n/g, ' | '), 'info');
                }
            });
        }

        // GitHub Link Click Tracking
        const githubLinks = document.querySelectorAll('a[href*="github.com"]');
        githubLinks.forEach(link => {
            link.addEventListener('click', function() {
                showDVDProfilerToast('🔗 GitHub Repository wird geöffnet...', 'info');
            });
        });

        // Update Notification Animation
        const updateNotification = document.querySelector('.update-notification');
        if (updateNotification) {
            // Pulse animation for update notification
            setInterval(() => {
                updateNotification.style.animation = 'none';
                setTimeout(() => {
                    updateNotification.style.animation = 'pulse 2s ease-in-out';
                }, 10);
            }, 5000);
        }

        // Stats Animation beim Scrollen ins Bild
        const statsItems = document.querySelectorAll('.stat-item');
        if (statsItems.length > 0) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.classList.add('animate-in');
                        }, index * 100); // Staggered animation
                    }
                });
            }, {
                threshold: 0.5
            });

            statsItems.forEach(item => {
                observer.observe(item);
            });
        }

        // Feature Count Animation
        const featuresText = document.querySelector('.tech-item:nth-child(3)');
        if (featuresText) {
            const enabledCount = <?= count(array_filter(DVDPROFILER_FEATURES)) ?>;
            const totalCount = <?= count(DVDPROFILER_FEATURES) ?>;
            
            featuresText.addEventListener('mouseenter', function() {
                this.innerHTML = `<strong>Features:</strong> ${enabledCount} aktiv, ${totalCount - enabledCount} geplant (${Math.round((enabledCount/totalCount)*100)}%)`;
            });
            
            featuresText.addEventListener('mouseleave', function() {
                this.innerHTML = `<strong>Features:</strong> ${enabledCount} aktiv (${totalCount} gesamt)`;
            });
        }
    });

    // Enhanced Toast Notification für DVD Profiler
    function showDVDProfilerToast(message, type = 'info', duration = 4000) {
        const toastContainer = document.getElementById('toast-container') || createToastContainer();
        
        const toast = document.createElement('div');
        toast.className = `dvd-toast toast-${type}`;
        
        const iconMap = {
            'success': 'bi-check-circle',
            'error': 'bi-x-circle',
            'warning': 'bi-exclamation-triangle',
            'info': 'bi-info-circle'
        };
        
        toast.innerHTML = `
            <div class="toast-content">
                <i class="bi ${iconMap[type] || iconMap.info}"></i>
                <span class="toast-message">${message}</span>
                <button class="toast-close" onclick="this.parentElement.parentElement.remove()">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
        
        // Toast Styling
        toast.style.cssText = `
            background: var(--glass-bg-strong, rgba(0, 0, 0, 0.8));
            backdrop-filter: blur(15px);
            border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
            border-radius: var(--radius-md, 12px);
            padding: 1rem;
            margin-bottom: 0.5rem;
            color: var(--text-white, #ffffff);
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
/* Erweiterte Footer Styles für DVD Profiler Liste */
.footer-logo {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 8px);
    font-weight: 700;
    font-size: 1.1rem;
    margin-bottom: var(--space-xs, 4px);
}

.footer-logo i {
    font-size: 1.3rem;
    background: var(--gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.footer-tagline {
    font-size: 0.85rem;
    opacity: 0.8;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    margin-bottom: var(--space-sm, 8px);
}

.update-notification {
    display: flex;
    align-items: center;
    gap: var(--space-xs, 4px);
    font-size: 0.8rem;
    color: #4ade80;
    background: rgba(74, 222, 128, 0.1);
    padding: 4px 8px;
    border-radius: var(--radius-sm, 6px);
    border: 1px solid rgba(74, 222, 128, 0.3);
    animation: pulse 2s ease-in-out infinite;
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
    transition: all var(--transition-fast, 0.3s);
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

.stat-item i {
    font-size: 1rem;
    color: var(--text-white, #ffffff);
    transition: transform 0.3s ease;
}

.version-badge {
    background: var(--gradient-accent, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    color: var(--text-white, #ffffff);
    padding: 3px 10px;
    border-radius: var(--radius-sm, 6px);
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    transition: all var(--transition-fast, 0.3s);
    display: inline-block;
    user-select: none;
}

.version-badge:hover {
    transform: scale(1.05);
    box-shadow: var(--shadow-md, 0 4px 12px rgba(0, 0, 0, 0.2));
}

.codename {
    font-style: italic;
    font-size: 0.8rem;
    opacity: 0.9;
    margin-left: var(--space-xs, 4px);
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
}

.build-info {
    font-size: 0.75rem;
    opacity: 0.7;
    margin: var(--space-xs, 4px) 0;
    font-family: 'Courier New', monospace;
}

.update-badge {
    background: #ef4444;
    color: white;
    font-size: 0.7rem;
    padding: 2px 6px;
    border-radius: 50%;
    margin-left: var(--space-xs, 4px);
    animation: bounce 1s ease-in-out infinite;
}

.footer-extended {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.1));
    backdrop-filter: blur(20px);
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    padding: var(--space-md, 12px) var(--space-xl, 24px);
    margin-top: var(--space-md, 12px);
    border-radius: 0 0 var(--radius-xl, 20px) var(--radius-xl, 20px);
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
    gap: var(--space-sm, 8px);
    font-size: 0.8rem;
    margin-bottom: var(--space-md, 12px);
}

.tech-item {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    transition: color 0.3s ease;
}

.tech-item:hover {
    color: var(--text-white, #ffffff);
}

.admin-shortcuts {
    display: flex;
    gap: var(--space-sm, 8px);
    padding-top: var(--space-sm, 8px);
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    flex-wrap: wrap;
}

.admin-link {
    display: flex;
    align-items: center;
    gap: var(--space-xs, 4px);
    padding: var(--space-xs, 4px) var(--space-sm, 8px);
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: var(--radius-sm, 6px);
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 0.75rem;
    transition: all 0.3s ease;
}

.admin-link:hover {
    background: var(--gradient-accent, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
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
    background: var(--gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    border-radius: 0 2px 0 0;
    transition: width 0.1s ease-out;
}

.konami-active {
    animation: dvdBounce 0.8s ease-in-out;
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

@keyframes dvdBounce {
    0%, 20%, 60%, 100% {
        transform: translateY(0) scale(1);
    }
    40% {
        transform: translateY(-15px) scale(1.1);
    }
    80% {
        transform: translateY(-5px) scale(1.05);
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

@keyframes bounce {
    0%, 20%, 60%, 100% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(-5px);
    }
    80% {
        transform: translateY(-2px);
    }
}

@keyframes spin360 {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Responsive Anpassungen */
@media (max-width: 768px) {
    .footer-stats {
        flex-direction: column;
        gap: var(--space-sm, 8px);
        align-items: center;
    }
    
    .stat-item {
        justify-content: center;
    }
    
    .footer-extended {
        cursor: pointer;
    }
    
    .footer-extended::before {
        content: "💡 Tippen für technische Details";
        display: block;
        text-align: center;
        font-size: 0.7rem;
        opacity: 0.6;
        padding-bottom: var(--space-xs, 4px);
        border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
        margin-bottom: var(--space-sm, 8px);
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
        gap: var(--space-xs, 4px);
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