<?php
/**
 * DVD Profiler Liste - Admin Dashboard (Clean Version)
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.8
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Statistiken sammeln
$stats = [
    'total_films' => 0,
    'total_boxsets' => 0,
    'total_genres' => 0,
    'total_visits' => 0,
    'newest_films' => [],
    'popular_genres' => []
];

try {
    // Filme
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM dvds");
    $stats['total_films'] = (int)($stmt->fetch()['total'] ?? 0);
    
    // BoxSets
    $stmt = $pdo->query("SELECT COUNT(DISTINCT boxset_parent) as total FROM dvds WHERE boxset_parent IS NOT NULL");
    $stats['total_boxsets'] = (int)($stmt->fetch()['total'] ?? 0);
    
    // Genres
    $stmt = $pdo->query("SELECT COUNT(DISTINCT genre) as total FROM dvds WHERE genre IS NOT NULL AND genre != ''");
    $stats['total_genres'] = (int)($stmt->fetch()['total'] ?? 0);
    
    // Besucher (aus DB wenn verfügbar)
    $stmt = $pdo->query("SHOW TABLES LIKE 'counter'");
    if ($stmt->fetch()) {
        $stmt = $pdo->query("SELECT visits FROM counter WHERE id = 1");
        $result = $stmt->fetch();
        $stats['total_visits'] = (int)($result['visits'] ?? 0);
    } else {
        // Fallback: counter.txt
        $counterFile = dirname(__DIR__, 2) . '/counter.txt';
        if (file_exists($counterFile)) {
            $stats['total_visits'] = (int)file_get_contents($counterFile);
        }
    }
    
    // Neueste Filme
    $stmt = $pdo->query("SELECT title, year, genre FROM dvds ORDER BY created_at DESC LIMIT 5");
    $stats['newest_films'] = $stmt->fetchAll();
    
    // Beliebte Genres
    $stmt = $pdo->query("SELECT genre, COUNT(*) as count FROM dvds WHERE genre IS NOT NULL AND genre != '' GROUP BY genre ORDER BY count DESC LIMIT 5");
    $stats['popular_genres'] = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Dashboard stats error: ' . $e->getMessage());
}

// System Health
$health = [
    'database' => true,
    'php_ok' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'memory' => memory_get_usage(true),
    'overall' => true
];

try {
    $pdo->query('SELECT 1');
} catch (Exception $e) {
    $health['database'] = false;
    $health['overall'] = false;
}
?>

<div class="dashboard-container">
    <!-- Header -->
    <div class="dashboard-header">
        <div>
            
            <p class="text-muted">Übersicht über Ihre DVD-Sammlung</p>
        </div>
        <div class="header-actions">
            <a href="../" class="btn btn-outline" target="_blank">
                <i class="bi bi-house"></i> Website
            </a>
            <a href="?page=import" class="btn btn-primary">
                <i class="bi bi-upload"></i> Import
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-film"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($stats['total_films']) ?></div>
                <div class="stat-label">Filme</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-collection"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($stats['total_boxsets']) ?></div>
                <div class="stat-label">BoxSets</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-tags"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($stats['total_genres']) ?></div>
                <div class="stat-label">Genres</div>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon">
                <i class="bi bi-eye"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= number_format($stats['total_visits']) ?></div>
                <div class="stat-label">Besucher</div>
            </div>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="content-grid">
        <!-- System Status -->
        <div class="card">
            <div class="card-header">
                <h5>
                    <i class="bi bi-heart-pulse"></i> System-Status
                    <span class="badge badge-<?= $health['overall'] ? 'success' : 'danger' ?>">
                        <?= $health['overall'] ? 'OK' : 'Fehler' ?>
                    </span>
                </h5>
            </div>
            <div class="card-body">
                <div class="health-grid">
                    <div class="health-item">
                        <i class="bi bi-database <?= $health['database'] ? 'text-success' : 'text-danger' ?>"></i>
                        <span>Datenbank: <strong><?= $health['database'] ? 'Verbunden' : 'Fehler' ?></strong></span>
                    </div>
                    <div class="health-item">
                        <i class="bi bi-code-slash <?= $health['php_ok'] ? 'text-success' : 'text-warning' ?>"></i>
                        <span>PHP: <strong><?= PHP_VERSION ?></strong></span>
                    </div>
                    <div class="health-item">
                        <i class="bi bi-memory text-info"></i>
                        <span>Memory: <strong><?= round($health['memory'] / 1024 / 1024, 1) ?> MB</strong></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Neueste Filme -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-clock-history"></i> Neueste Filme</h5>
            </div>
            <div class="card-body">
                <?php if (empty($stats['newest_films'])): ?>
                    <p class="text-muted">Noch keine Filme vorhanden.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($stats['newest_films'] as $film): ?>
                            <div class="list-item">
                                <div class="film-info">
                                    <strong><?= htmlspecialchars($film['title']) ?></strong>
                                    <?php if ($film['year']): ?>
                                        <span class="year">(<?= htmlspecialchars($film['year']) ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($film['genre']): ?>
                                    <span class="genre-badge"><?= htmlspecialchars($film['genre']) ?></span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Beliebte Genres -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-bar-chart"></i> Beliebte Genres</h5>
            </div>
            <div class="card-body">
                <?php if (empty($stats['popular_genres'])): ?>
                    <p class="text-muted">Keine Genre-Daten verfügbar.</p>
                <?php else: ?>
                    <div class="genre-list">
                        <?php 
                        $maxCount = $stats['popular_genres'][0]['count'];
                        foreach ($stats['popular_genres'] as $genre): 
                            $percentage = ($genre['count'] / $maxCount) * 100;
                        ?>
                            <div class="genre-item">
                                <div class="genre-header">
                                    <span class="genre-name"><?= htmlspecialchars($genre['genre']) ?></span>
                                    <span class="genre-count"><?= $genre['count'] ?></span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-lightning"></i> Schnellzugriff</h5>
            </div>
            <div class="card-body">
                <div class="quick-actions">
                    <a href="?page=import" class="action-btn">
                        <i class="bi bi-upload"></i>
                        <span>Filme importieren</span>
                    </a>
                    <a href="?page=films" class="action-btn">
                        <i class="bi bi-film"></i>
                        <span>Filme verwalten</span>
                    </a>
                    <a href="?page=settings" class="action-btn">
                        <i class="bi bi-gear"></i>
                        <span>Einstellungen</span>
                    </a>
                    <a href="../" class="action-btn" target="_blank">
                        <i class="bi bi-box-arrow-up-right"></i>
                        <span>Website öffnen</span>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* ============================================
   Dashboard Styles - Clean & Modern
   ============================================ */

.dashboard-container {
    padding: var(--space-lg, 1.5rem);
    max-width: 1400px;
    margin: 0 auto;
}

/* Header */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-xl, 2rem);
    padding-bottom: var(--space-lg, 1.5rem);
    border-bottom: 1px solid var(--border-color, #e0e0e0);
}

.dashboard-header h1 {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--text-dark, #1a1a1a);
    margin: 0 0 0.25rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.dashboard-header .text-muted {
    font-size: 0.9rem;
    color: var(--text-muted, #666);
    margin: 0;
}

.header-actions {
    display: flex;
    gap: var(--space-sm, 0.5rem);
}

/* Buttons */
.btn {
    padding: 0.5rem 1rem;
    border-radius: 6px;
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.2s ease;
    border: none;
    cursor: pointer;
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border-color, #e0e0e0);
    color: var(--text-dark, #1a1a1a);
}

.btn-outline:hover {
    background: var(--bg-light, #f5f5f5);
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5568d3;
    transform: translateY(-1px);
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-lg, 1.5rem);
    margin-bottom: var(--space-xl, 2rem);
}

.stat-card {
    background: white;
    border: 1px solid var(--border-color, #e0e0e0);
    border-radius: 12px;
    padding: var(--space-lg, 1.5rem);
    display: flex;
    align-items: center;
    gap: var(--space-md, 1rem);
    transition: all 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.8rem;
    color: white;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-dark, #1a1a1a);
    line-height: 1;
}

.stat-label {
    font-size: 0.9rem;
    color: var(--text-muted, #666);
    margin-top: 0.25rem;
}

/* Content Grid */
.content-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-lg, 1.5rem);
}

/* Cards */
.card {
    background: white;
    border: 1px solid var(--border-color, #e0e0e0);
    border-radius: 12px;
    overflow: hidden;
}

.card-header {
    padding: var(--space-md, 1rem) var(--space-lg, 1.5rem);
    border-bottom: 1px solid var(--border-color, #e0e0e0);
    background: var(--bg-light, #f8f9fa);
}

.card-header h5 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-dark, #1a1a1a);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-body {
    padding: var(--space-lg, 1.5rem);
}

/* Badges */
.badge {
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: auto;
}

.badge-success {
    background: #10b981;
    color: white;
}

.badge-danger {
    background: #ef4444;
    color: white;
}

/* Health Grid */
.health-grid {
    display: grid;
    gap: var(--space-md, 1rem);
}

.health-item {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 0.5rem);
    font-size: 0.9rem;
}

.health-item i {
    font-size: 1.2rem;
}

.text-success {
    color: #10b981;
}

.text-danger {
    color: #ef4444;
}

.text-warning {
    color: #f59e0b;
}

.text-info {
    color: #3b82f6;
}

/* List */
.list-group {
    display: flex;
    flex-direction: column;
    gap: var(--space-sm, 0.5rem);
}

.list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-sm, 0.5rem);
    border-radius: 6px;
    background: var(--bg-light, #f8f9fa);
    transition: background 0.2s ease;
}

.list-item:hover {
    background: #e9ecef;
}

.film-info {
    flex: 1;
}

.year {
    color: var(--text-muted, #666);
    font-size: 0.85rem;
}

.genre-badge {
    background: #667eea;
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

/* Genre List */
.genre-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-md, 1rem);
}

.genre-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.genre-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.genre-name {
    font-weight: 500;
    color: var(--text-dark, #1a1a1a);
}

.genre-count {
    font-size: 0.9rem;
    color: var(--text-muted, #666);
}

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transition: width 0.3s ease;
}

/* Quick Actions */
.quick-actions {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: var(--space-sm, 0.5rem);
}

.action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: var(--space-md, 1rem);
    background: var(--bg-light, #f8f9fa);
    border: 1px solid var(--border-color, #e0e0e0);
    border-radius: 8px;
    text-decoration: none;
    color: var(--text-dark, #1a1a1a);
    transition: all 0.2s ease;
    text-align: center;
}

.action-btn:hover {
    background: #667eea;
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.action-btn i {
    font-size: 1.5rem;
}

.action-btn span {
    font-size: 0.85rem;
    font-weight: 500;
}

/* Responsive */
@media (max-width: 992px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-md, 1rem);
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .content-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 576px) {
    .dashboard-container {
        padding: var(--space-md, 1rem);
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        padding: var(--space-md, 1rem);
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.5rem;
    }
    
    .stat-value {
        font-size: 1.5rem;
    }
    
    .quick-actions {
        grid-template-columns: 1fr;
    }
}
</style>