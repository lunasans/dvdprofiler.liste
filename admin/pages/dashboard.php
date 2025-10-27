<?php
/**
 * DVD Profiler Liste - Admin Dashboard
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.5
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// version.php wird bereits in bootstrap.php geladen (von admin/index.php)

// Dashboard-Statistiken sammeln
$dashboardStats = [
    'total_films' => 0,
    'total_boxsets' => 0,
    'total_genres' => 0,
    'total_actors' => 0,
    'newest_films' => [],
    'popular_genres' => [],
    'recent_activity' => [],
    'system_health' => []
];

try {
    // Film-Statistiken
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dvds");
    $dashboardStats['total_films'] = (int) ($stmt->fetch()['total'] ?? 0);

    // BoxSet-Statistiken
    $stmt = $pdo->query("SELECT COUNT(DISTINCT boxset_name) as total FROM dvds WHERE boxset_name IS NOT NULL AND boxset_name != ''");
    $dashboardStats['total_boxsets'] = (int) ($stmt->fetch()['total'] ?? 0);

    // Genre-Statistiken
    $stmt = $pdo->query("SELECT COUNT(DISTINCT genre) as total FROM dvds WHERE genre IS NOT NULL AND genre != ''");
    $dashboardStats['total_genres'] = (int) ($stmt->fetch()['total'] ?? 0);

    // Actor-Statistiken (vereinfacht)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dvds WHERE actors IS NOT NULL AND actors != ''");
    $dashboardStats['total_actors'] = (int) ($stmt->fetch()['total'] ?? 0);

    // Neueste Filme (letzte 5)
    $stmt = $pdo->query("SELECT title, year, genre, created_at FROM dvds ORDER BY created_at DESC LIMIT 5");
    $dashboardStats['newest_films'] = $stmt->fetchAll();

    // Beliebte Genres
    $stmt = $pdo->query("SELECT genre, COUNT(*) as count FROM dvds WHERE genre IS NOT NULL AND genre != '' GROUP BY genre ORDER BY count DESC LIMIT 5");
    $dashboardStats['popular_genres'] = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
    $statsError = 'Statistiken konnten nicht geladen werden.';
}

// System-Health von Bootstrap abrufen
$systemHealth = getSystemHealth();
$updateAvailable = isDVDProfilerUpdateAvailable();
$latestRelease = getDVDProfilerLatestGitHubVersion();

// Build-Informationen
$buildInfo = getDVDProfilerBuildInfo();
$dvdStats = getDVDProfilerStatistics();
?>

<div class="dashboard-container">
    <!-- Welcome Header -->
    <div class="welcome-section mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="dashboard-title">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </h1>
                <p class="dashboard-subtitle">
                    Willkommen im Admin Center von
                    <?= htmlspecialchars(getSetting('site_title', 'DVD Profiler Liste')) ?>
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <div class="version-info-card">
                    <div class="version-badge-large">
                        v<?= DVDPROFILER_VERSION ?>
                    </div>
                    <div class="version-details">
                        <small>
                            "<?= DVDPROFILER_CODENAME ?>"<br>
                            Build <?= DVDPROFILER_BUILD_DATE ?>
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-collection-play"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($dashboardStats['total_films']) ?></h3>
                    <p>Filme in der Sammlung</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-collection"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($dashboardStats['total_boxsets']) ?></h3>
                    <p>BoxSet-Sammlungen</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-tags"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($dashboardStats['total_genres']) ?></h3>
                    <p>Verschiedene Genres</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 col-sm-6 mb-3">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="bi bi-eye"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($dvdStats['total_visits'] ?? 0) ?></h3>
                    <p>Website-Besucher</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            <!-- System Status Card -->
            <div class="card dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-heart-pulse"></i>
                        System-Status
                        <span class="badge bg-<?= ($systemHealth['overall'] ?? false) ? 'success' : 'danger' ?> ms-2">
                            <?= ($systemHealth['overall'] ?? false) ? 'Gesund' : 'Probleme' ?>
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="health-item">
                                <i
                                    class="bi bi-database <?= $systemHealth['database'] ? 'text-success' : 'text-danger' ?>"></i>
                                <span>Datenbank:
                                    <strong><?= $systemHealth['database'] ? 'Verbunden' : 'Fehler' ?></strong></span>
                            </div>
                            <div class="health-item">
                                <i
                                    class="bi bi-code-slash <?= $systemHealth['php_version'] ? 'text-success' : 'text-warning' ?>"></i>
                                <span>PHP: <strong><?= PHP_VERSION ?></strong></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="health-item">
                                <i class="bi bi-memory text-info"></i>
                                <span>Memory:
                                    <strong><?= formatBytes($systemHealth['memory_usage'] ?? 0) ?></strong></span>
                            </div>
                            <div class="health-item">
                                <i class="bi bi-hdd text-info"></i>
                                <span>Disk: <strong><?= formatBytes($systemHealth['disk_space'] ?? 0) ?>
                                        frei</strong></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($systemHealth['overall'] ?? false): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Achtung:</strong> Es wurden Systemprobleme erkannt. Bitte überprüfen Sie die
                            Konfiguration.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Updates Card -->
            <?php if ($updateAvailable): ?>
                <div class="card dashboard-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-arrow-up-circle text-warning"></i>
                            Update verfügbar
                            <span
                                class="badge bg-warning text-dark ms-2"><?= htmlspecialchars($latestRelease['version'] ?? 'Unbekannt') ?></span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">
                            Eine neue Version von DVD Profiler Liste ist verfügbar.
                            Aktuelle Version: <strong><?= DVDPROFILER_VERSION ?></strong> →
                            Neue Version: <strong><?= htmlspecialchars($latestRelease['version'] ?? 'Unbekannt') ?></strong>
                        </p>

                        <?php if (!empty($latestRelease['description'])): ?>
                            <div class="update-changelog">
                                <h6>Changelog:</h6>
                                <div class="changelog-content">
                                    <?= nl2br(htmlspecialchars(substr($latestRelease['description'] ?? '', 0, 300))) ?>
                                    <?php if (strlen($latestRelease['description'] ?? '') > 300): ?>...<?php endif; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 mt-3">
                            <a href="?page=settings&action=update" class="btn btn-warning">
                                <i class="bi bi-download"></i>
                                Jetzt aktualisieren
                            </a>
                            <a href="<?= DVDPROFILER_GITHUB_URL ?>/releases/latest" target="_blank"
                                class="btn btn-outline-secondary">
                                <i class="bi bi-github"></i>
                                Details ansehen
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recent Films Card -->
            <div class="card dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-clock-history"></i>
                        Neueste Filme
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($dashboardStats['newest_films'])): ?>
                        <div class="recent-films-list">
                            <?php foreach ($dashboardStats['newest_films'] as $film): ?>
                                <div class="recent-film-item">
                                    <div class="film-info">
                                        <h6 class="film-title">
                                            <?= htmlspecialchars($film['title']) ?>
                                            <?php if ($film['year']): ?>
                                                <span class="year">(<?= htmlspecialchars($film['year']) ?>)</span>
                                            <?php endif; ?>
                                        </h6>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($film['genre'] ?? 'Unbekannt') ?> •
                                            <?= date('d.m.Y H:i', strtotime($film['created_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Noch keine Filme in der Sammlung.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-lg-4">
            <!-- Quick Actions Card -->
            <div class="card dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning"></i>
                        Schnellaktionen
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="?page=import" class="btn btn-primary">
                            <i class="bi bi-upload"></i>
                            Filme importieren
                        </a>
                        <a href="../" class="btn btn-outline-primary" target="_blank">
                            <i class="bi bi-eye"></i>
                            Website ansehen
                        </a>
                        <a href="?page=settings" class="btn btn-outline-secondary">
                            <i class="bi bi-gear"></i>
                            Einstellungen
                        </a>
                        <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" class="btn btn-outline-info">
                            <i class="bi bi-github"></i>
                            GitHub Repository
                        </a>
                    </div>
                </div>
            </div>

            <!-- Popular Genres Card -->
            <div class="card dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-bar-chart"></i>
                        Beliebte Genres
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($dashboardStats['popular_genres'])): ?>
                        <div class="genre-list">
                            <?php foreach ($dashboardStats['popular_genres'] as $genre): ?>
                                <div class="genre-item">
                                    <div class="genre-name"><?= htmlspecialchars($genre['genre']) ?></div>
                                    <div class="genre-stats">
                                        <span class="badge bg-secondary"><?= $genre['count'] ?></span>
                                        <div class="genre-bar">
                                            <div class="genre-progress"
                                                style="width: <?= ($genre['count'] / max(1, $dashboardStats['popular_genres'][0]['count'])) * 100 ?>%">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Noch keine Genre-Statistiken verfügbar.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- System Info Card -->
            <div class="card dashboard-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-info-circle"></i>
                        System-Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="system-info">
                        <div class="info-item">
                            <strong>Version:</strong> <?= getDVDProfilerVersionFull() ?>
                        </div>
                        <div class="info-item">
                            <strong>Build:</strong> <?= DVDPROFILER_BUILD_DATE ?>
                        </div>
                        <div class="info-item">
                            <strong>Branch:</strong> <?= DVDPROFILER_BRANCH ?>
                        </div>
                        <div class="info-item">
                            <strong>Commit:</strong> <?= DVDPROFILER_COMMIT ?>
                        </div>
                        <div class="info-item">
                            <strong>PHP:</strong> <?= PHP_VERSION ?>
                        </div>
                        <div class="info-item">
                            <strong>Features:</strong>
                            <?= count(array_filter(DVDPROFILER_FEATURES)) ?>/<?= count(DVDPROFILER_FEATURES) ?> aktiv
                        </div>
                        <div class="info-item">
                            <strong>Repository:</strong>
                            <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" class="text-decoration-none">
                                <?= DVDPROFILER_REPOSITORY ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    /* Dashboard-spezifische Styles */
    .dashboard-container {
        padding: 0;
    }

    .welcome-section {
        background: var(--glass-bg, rgba(255, 255, 255, 0.1));
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
        border-radius: var(--radius-lg, 16px);
        padding: var(--space-xl, 24px);
        margin-bottom: var(--space-xl, 24px);
    }

    .dashboard-title {
        font-size: 2rem;
        margin-bottom: var(--space-sm, 8px);
        color: var(--text-white, #ffffff);
    }

    .dashboard-subtitle {
        color: var(--text-glass, rgba(255, 255, 255, 0.8));
        margin-bottom: 0;
    }

    .version-info-card {
        text-align: center;
        background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
        border-radius: var(--radius-md, 12px);
        padding: var(--space-md, 16px);
    }

    .version-badge-large {
        background: var(--gradient-accent, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
        color: var(--text-white, #ffffff);
        padding: var(--space-sm, 8px) var(--space-md, 16px);
        border-radius: var(--radius-md, 12px);
        font-size: 1.1rem;
        font-weight: 700;
        display: inline-block;
        margin-bottom: var(--space-sm, 8px);
    }

    .version-details {
        color: var(--text-glass, rgba(255, 255, 255, 0.8));
        font-size: 0.85rem;
    }

    .stat-card {
        background: var(--glass-bg, rgba(255, 255, 255, 0.1));
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
        border-radius: var(--radius-lg, 16px);
        padding: var(--space-lg, 20px);
        height: 100%;
        transition: all var(--transition-fast, 0.3s);
        display: flex;
        align-items: center;
        gap: var(--space-md, 16px);
    }

    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg, 0 10px 25px rgba(0, 0, 0, 0.2));
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        background: var(--gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
        border-radius: var(--radius-lg, 16px);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: var(--text-white, #ffffff);
        flex-shrink: 0;
    }

    .stat-content h3 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
        color: var(--text-white, #ffffff);
    }

    .stat-content p {
        margin: 0;
        color: var(--text-glass, rgba(255, 255, 255, 0.8));
        font-size: 0.9rem;
    }

    .dashboard-card {
        background: var(--glass-bg, rgba(255, 255, 255, 0.1));
        backdrop-filter: blur(10px);
        border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
        border-radius: var(--radius-lg, 16px);
    }

    .dashboard-card .card-header {
        background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
        border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
        border-radius: var(--radius-lg, 16px) var(--radius-lg, 16px) 0 0;
        padding: var(--space-md, 16px) var(--space-lg, 20px);
    }

    .dashboard-card .card-body {
        padding: var(--space-lg, 20px);
    }

    .health-item {
        display: flex;
        align-items: center;
        gap: var(--space-sm, 8px);
        margin-bottom: var(--space-sm, 8px);
        font-size: 0.9rem;
    }

    .recent-film-item {
        padding: var(--space-md, 16px);
        border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
        transition: all var(--transition-fast, 0.3s);
    }

    .recent-film-item:last-child {
        border-bottom: none;
    }

    .recent-film-item:hover {
        background: var(--glass-bg-strong, rgba(255, 255, 255, 0.1));
        border-radius: var(--radius-sm, 6px);
    }

    .film-title {
        margin: 0 0 var(--space-xs, 4px) 0;
        color: var(--text-white, #ffffff);
    }

    .year {
        color: var(--text-glass, rgba(255, 255, 255, 0.6));
        font-weight: normal;
    }

    .genre-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: var(--space-sm, 8px) 0;
        border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    }

    .genre-item:last-child {
        border-bottom: none;
    }

    .genre-name {
        flex: 1;
        color: var(--text-white, #ffffff);
    }

    .genre-stats {
        display: flex;
        align-items: center;
        gap: var(--space-sm, 8px);
    }

    .genre-bar {
        width: 60px;
        height: 4px;
        background: var(--glass-border, rgba(255, 255, 255, 0.2));
        border-radius: 2px;
        overflow: hidden;
    }

    .genre-progress {
        height: 100%;
        background: var(--gradient-accent, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
        transition: width 0.3s ease;
    }

    .system-info .info-item {
        display: flex;
        justify-content: space-between;
        padding: var(--space-xs, 4px) 0;
        border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
        font-size: 0.85rem;
    }

    .system-info .info-item:last-child {
        border-bottom: none;
    }

    .update-changelog {
        background: var(--glass-bg-strong, rgba(255, 255, 255, 0.1));
        border-radius: var(--radius-sm, 6px);
        padding: var(--space-md, 16px);
        margin: var(--space-md, 16px) 0;
    }

    .changelog-content {
        font-size: 0.9rem;
        line-height: 1.4;
        color: var(--text-glass, rgba(255, 255, 255, 0.8));
    }

    /* Responsive */
    @media (max-width: 768px) {
        .dashboard-title {
            font-size: 1.5rem;
        }

        .stat-card {
            text-align: center;
            flex-direction: column;
            gap: var(--space-sm, 8px);
        }

        .genre-stats {
            flex-direction: column;
            align-items: flex-end;
            gap: var(--space-xs, 4px);
        }

        .system-info .info-item {
            flex-direction: column;
            align-items: flex-start;
            gap: var(--space-xs, 4px);
        }
    }

    /* Animation beim Laden */
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .dashboard-card {
        animation: fadeInUp 0.5s ease-out;
    }

    .stat-card {
        animation: fadeInUp 0.5s ease-out;
    }

    /* Staggered Animation */
    .stat-card:nth-child(1) {
        animation-delay: 0.1s;
    }

    .stat-card:nth-child(2) {
        animation-delay: 0.2s;
    }

    .stat-card:nth-child(3) {
        animation-delay: 0.3s;
    }

    .stat-card:nth-child(4) {
        animation-delay: 0.4s;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Counter Animation für Statistiken
        function animateCounters() {
            const counters = document.querySelectorAll('.stat-content h3');

            counters.forEach(counter => {
                const target = parseInt(counter.textContent.replace(/[,\.]/g, ''));
                const increment = target / 50;
                let current = 0;

                const timer = setInterval(() => {
                    current += increment;
                    if (current >= target) {
                        counter.textContent = target.toLocaleString();
                        clearInterval(timer);
                    } else {
                        counter.textContent = Math.floor(current).toLocaleString();
                    }
                }, 30);
            });
        }

        // Animation starten nach kurzer Verzögerung
        setTimeout(animateCounters, 500);

        // Progress bars für Genres animieren
        const progressBars = document.querySelectorAll('.genre-progress');
        progressBars.forEach((bar, index) => {
            setTimeout(() => {
                bar.style.width = bar.style.width;
            }, 800 + (index * 100));
        });

        // System Health Status Tooltip
        const healthItems = document.querySelectorAll('.health-item');
        healthItems.forEach(item => {
            item.addEventListener('mouseenter', function () {
                this.style.transform = 'translateX(5px)';
            });

            item.addEventListener('mouseleave', function () {
                this.style.transform = 'translateX(0)';
            });
        });

        console.log('DVD Profiler Liste Dashboard v<?= DVDPROFILER_VERSION ?> loaded');
    });
</script>