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

    // BoxSet-Statistiken - KORRIGIERT für aktuelle DB-Struktur
    try {
        // Versuche zuerst boxset_parent (neue Struktur)
        $stmt = $pdo->query("SELECT COUNT(DISTINCT boxset_parent) as total FROM dvds WHERE boxset_parent IS NOT NULL");
        $dashboardStats['total_boxsets'] = (int) ($stmt->fetch()['total'] ?? 0);
    } catch (Exception $e) {
        // Fallback für alte Struktur mit boxset_name
        try {
            $stmt = $pdo->query("SELECT COUNT(DISTINCT boxset_name) as total FROM dvds WHERE boxset_name IS NOT NULL AND boxset_name != ''");
            $dashboardStats['total_boxsets'] = (int) ($stmt->fetch()['total'] ?? 0);
        } catch (Exception $e2) {
            // Wenn beide fehlschlagen, setze auf 0
            $dashboardStats['total_boxsets'] = 0;
            error_log('BoxSet count error: ' . $e2->getMessage());
        }
    }

    // Genre-Statistiken
    $stmt = $pdo->query("SELECT COUNT(DISTINCT genre) as total FROM dvds WHERE genre IS NOT NULL AND genre != ''");
    $dashboardStats['total_genres'] = (int) ($stmt->fetch()['total'] ?? 0);

    // Actor-Statistiken (vereinfacht)
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dvds WHERE actors IS NOT NULL AND actors != ''");
    $dashboardStats['total_actors'] = (int) ($stmt->fetch()['total'] ?? 0);

    // Neueste Filme (letzte 5) - robuster
    try {
        $stmt = $pdo->query("SELECT title, year, genre, created_at FROM dvds ORDER BY created_at DESC LIMIT 5");
        $dashboardStats['newest_films'] = $stmt->fetchAll();
    } catch (Exception $e) {
        // Fallback ohne created_at
        try {
            $stmt = $pdo->query("SELECT title, year, genre, id FROM dvds ORDER BY id DESC LIMIT 5");
            $dashboardStats['newest_films'] = $stmt->fetchAll();
        } catch (Exception $e2) {
            $dashboardStats['newest_films'] = [];
            error_log('Newest films error: ' . $e2->getMessage());
        }
    }

    // Beliebte Genres
    $stmt = $pdo->query("SELECT genre, COUNT(*) as count FROM dvds WHERE genre IS NOT NULL AND genre != '' GROUP BY genre ORDER BY count DESC LIMIT 5");
    $dashboardStats['popular_genres'] = $stmt->fetchAll();

} catch (Exception $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
    $statsError = 'Statistiken konnten nicht geladen werden.';
}

// System Health Check mit Fallback
if (function_exists('getSystemHealth')) {
    $systemHealth = getSystemHealth();
} else {
    $systemHealth = [
        'database' => true,
        'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
        'memory_usage' => memory_get_usage(true),
        'disk_space' => disk_free_space(__DIR__) ?: 0,
        'overall' => true
    ];
    
    // Test DB-Verbindung
    try {
        if (isset($pdo)) {
            $pdo->query('SELECT 1');
            $systemHealth['database'] = true;
        }
    } catch (Exception $e) {
        $systemHealth['database'] = false;
        $systemHealth['overall'] = false;
    }
}

// Update-Check mit Fallback
$updateAvailable = false;
$latestRelease = ['version' => 'Unbekannt'];

try {
    if (function_exists('isGitHubUpdateAvailable')) {
        $updateAvailable = isGitHubUpdateAvailable();
    }
    
    if (function_exists('getLatestGitHubRelease')) {
        $latestRelease = getLatestGitHubRelease();
    }
} catch (Exception $e) {
    error_log('Update check error: ' . $e->getMessage());
}

// DVD-Statistiken für Besucher-Count
$dvdStats = ['total_visits' => 0];
try {
    $counterFile = dirname(__DIR__, 2) . '/counter.txt';
    if (file_exists($counterFile)) {
        $dvdStats['total_visits'] = (int)file_get_contents($counterFile);
    }
} catch (Exception $e) {
    // Ignoriere Counter-Fehler
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Dashboard Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">📊 Dashboard</h1>
                    <p class="text-muted">Übersicht über Ihre DVD-Sammlung und System-Status</p>
                </div>
                <div class="btn-group">
                    <a href="../" class="btn btn-outline-primary" target="_blank">
                        <i class="bi bi-house"></i> Website
                    </a>
                    <a href="?page=import" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Import
                    </a>
                </div>
            </div>

            <!-- Error Message -->
            <?php if (isset($statsError)): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warnung:</strong> <?= htmlspecialchars($statsError) ?>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 mb-3">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="bi bi-film"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?= number_format($dashboardStats['total_films']) ?></h3>
                            <p>Filme total</p>
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
                            <h3><?= number_format($dvdStats['total_visits']) ?></h3>
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
                                <span class="badge bg-<?= ($systemHealth['overall'] ?? true) ? 'success' : 'danger' ?> ms-2">
                                    <?= ($systemHealth['overall'] ?? true) ? 'Gesund' : 'Probleme' ?>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="health-item">
                                        <i class="bi bi-database <?= ($systemHealth['database'] ?? true) ? 'text-success' : 'text-danger' ?>"></i>
                                        <span>Datenbank:
                                            <strong><?= ($systemHealth['database'] ?? true) ? 'Verbunden' : 'Fehler' ?></strong>
                                        </span>
                                    </div>
                                    <div class="health-item">
                                        <i class="bi bi-code-slash <?= ($systemHealth['php_version'] ?? true) ? 'text-success' : 'text-warning' ?>"></i>
                                        <span>PHP: <strong><?= PHP_VERSION ?></strong></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="health-item">
                                        <i class="bi bi-memory text-info"></i>
                                        <span>Memory:
                                            <strong><?= function_exists('formatBytes') ? formatBytes($systemHealth['memory_usage'] ?? memory_get_usage(true)) : round((memory_get_usage(true) / 1024 / 1024), 1) . 'MB' ?></strong>
                                        </span>
                                    </div>
                                    <div class="health-item">
                                        <i class="bi bi-hdd text-info"></i>
                                        <span>Disk: 
                                            <strong><?= function_exists('formatBytes') ? formatBytes($systemHealth['disk_space'] ?? disk_free_space(__DIR__)) : 'OK' ?> frei</strong>
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <?php if (!($systemHealth['overall'] ?? true)): ?>
                                <div class="alert alert-warning mt-3 mb-0">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Achtung:</strong> Es wurden Systemprobleme erkannt. Bitte überprüfen Sie die Konfiguration.
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
                                    <span class="badge bg-warning text-dark ms-2"><?= htmlspecialchars($latestRelease['version'] ?? 'Unbekannt') ?></span>
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">
                                    Eine neue Version von DVD Profiler Liste ist verfügbar.
                                    Aktuelle Version: <strong><?= defined('DVDPROFILER_VERSION') ? DVDPROFILER_VERSION : '1.4.7' ?></strong> →
                                    Neue Version: <strong><?= htmlspecialchars($latestRelease['version'] ?? 'Unbekannt') ?></strong>
                                </p>

                                <div class="d-flex gap-2 mt-3">
                                    <a href="?page=settings" class="btn btn-warning">
                                        <i class="bi bi-download"></i>
                                        Zur Update-Seite
                                    </a>
                                    <a href="<?= defined('DVDPROFILER_GITHUB_URL') ? DVDPROFILER_GITHUB_URL : 'https://github.com/lunasans/dvdprofiler.liste' ?>/releases/latest" target="_blank" class="btn btn-outline-secondary">
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
                                                    <?php if (!empty($film['year'])): ?>
                                                        <span class="year">(<?= htmlspecialchars($film['year']) ?>)</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars($film['genre'] ?? 'Unbekannt') ?>
                                                    <?php if (!empty($film['created_at'])): ?>
                                                        • Hinzugefügt: <?= date('d.m.Y', strtotime($film['created_at'])) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-film display-4"></i>
                                    <p class="mt-2">Noch keine Filme importiert</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Popular Genres Card -->
                    <div class="card dashboard-card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-tags"></i>
                                Beliebte Genres
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($dashboardStats['popular_genres'])): ?>
                                <div class="genre-list">
                                    <?php foreach ($dashboardStats['popular_genres'] as $genre): ?>
                                        <div class="genre-item">
                                            <span class="genre-name"><?= htmlspecialchars($genre['genre']) ?></span>
                                            <span class="genre-count"><?= $genre['count'] ?> Filme</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-tags display-4"></i>
                                    <p class="mt-2">Noch keine Genre-Daten vorhanden</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions Card -->
                    <div class="card dashboard-card">
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
                                <a href="?page=settings" class="btn btn-outline-secondary">
                                    <i class="bi bi-gear"></i>
                                    Einstellungen
                                </a>
                                <a href="../" class="btn btn-outline-info" target="_blank">
                                    <i class="bi bi-eye"></i>
                                    Website ansehen
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Dashboard Styles */
.stat-card {
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, rgba(255, 255, 255, 0.05) 100%);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    padding: 1.5rem;
    color: white;
    transition: all 0.3s ease;
    height: 100%;
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.stat-icon {
    font-size: 3rem;
    opacity: 0.8;
    color: #3498db;
}

.stat-content h3 {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    color: white;
}

.stat-content p {
    margin: 0;
    opacity: 0.8;
    font-size: 0.9rem;
}

.dashboard-card {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
}

.dashboard-card .card-header {
    background: rgba(255, 255, 255, 0.05);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.health-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.recent-film-item, .genre-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.75rem 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.recent-film-item:last-child, .genre-item:last-child {
    border-bottom: none;
}

.film-title, .genre-name {
    font-weight: 500;
}

.year, .genre-count {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

@media (max-width: 768px) {
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .stat-icon {
        font-size: 2.5rem;
    }
    
    .stat-content h3 {
        font-size: 2rem;
    }
}
</style>