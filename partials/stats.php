<?php
/**
 * Partials/stats.php - Vollständig migriert auf neues Core-System
 * Umfassende Statistik-Seite mit optimierten Datenbankabfragen
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+ - Core Integration
 */

declare(strict_types=1);

try {
    // Core-System sollte bereits durch index.php geladen sein
    if (!class_exists('DVDProfiler\\Core\\Application')) {
        require_once __DIR__ . '/../includes/bootstrap.php';
    }
    
    // Application-Instance abrufen
    $app = \DVDProfiler\Core\Application::getInstance();
    $database = $app->getDatabase();
    $security = $app->getSecurity();
    
    // Rate-Limiting für Statistik-Anfragen
    $clientIP = $security::getClientIP();
    if (!$app->checkRateLimit("stats_{$clientIP}", 30, 300)) {
        http_response_code(429);
        throw new Exception('Zu viele Statistik-Anfragen. Bitte warten Sie 5 Minuten.');
    }
    
    // Performance-Start-Zeit
    $startTime = microtime(true);
    
} catch (Exception $e) {
    error_log("[DVDProfiler:ERROR] Stats-Page Initialization: " . $e->getMessage());
    $error_message = 'Fehler beim Laden der Statistiken: ' . $e->getMessage();
}

/**
 * Statistik-Funktionen mit Core-Database-Integration
 */

function getBasicStats(\DVDProfiler\Core\Database $database): array {
    try {
        // Optimierte Query: Alle Basic-Stats in einem Aufruf
        $basicData = $database->fetchRow("
            SELECT 
                COUNT(*) as total_films,
                COALESCE(SUM(runtime), 0) as total_runtime,
                COALESCE(AVG(runtime), 0) as avg_runtime,
                COALESCE(AVG(year), YEAR(NOW())) as avg_year,
                MIN(year) as oldest_year,
                MAX(year) as newest_year,
                COUNT(DISTINCT genre) as unique_genres,
                COALESCE(SUM(view_count), 0) as total_views
            FROM dvds 
            WHERE year > 0
        ");
        
        if (!$basicData) {
            throw new Exception('Keine Statistikdaten verfügbar');
        }
        
        return [
            'totalFilms' => (int)$basicData['total_films'],
            'totalRuntime' => (int)$basicData['total_runtime'],
            'avgRuntime' => round((float)$basicData['avg_runtime']),
            'hours' => round((int)$basicData['total_runtime'] / 60),
            'days' => round((int)$basicData['total_runtime'] / 1440, 1),
            'avgYear' => round((float)$basicData['avg_year']),
            'uniqueGenres' => (int)$basicData['unique_genres'],
            'totalViews' => (int)$basicData['total_views'],
            'yearStats' => [
                'oldest_year' => $basicData['oldest_year'] ?? 'N/A',
                'newest_year' => $basicData['newest_year'] ?? 'N/A'
            ]
        ];
    } catch (Exception $e) {
        error_log('[Stats] Basic stats error: ' . $e->getMessage());
        return [
            'totalFilms' => 0, 'totalRuntime' => 0, 'avgRuntime' => 0,
            'hours' => 0, 'days' => 0, 'avgYear' => (int)date('Y'),
            'uniqueGenres' => 0, 'totalViews' => 0,
            'yearStats' => ['oldest_year' => 'N/A', 'newest_year' => 'N/A']
        ];
    }
}

function getCollectionStats(\DVDProfiler\Core\Database $database): array {
    try {
        return $database->fetchAll("
            SELECT 
                COALESCE(collection_type, 'Unbekannt') as collection_type, 
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds), 1) as percentage
            FROM dvds 
            GROUP BY collection_type 
            ORDER BY count DESC
        ");
    } catch (Exception $e) {
        error_log('[Stats] Collection stats error: ' . $e->getMessage());
        return [
            ['collection_type' => 'DVD', 'count' => 0, 'percentage' => 0],
            ['collection_type' => 'Blu-ray', 'count' => 0, 'percentage' => 0]
        ];
    }
}

function getRatingStats(\DVDProfiler\Core\Database $database): array {
    try {
        return $database->fetchAll("
            SELECT 
                COALESCE(rating_age, 0) as rating_age, 
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds), 1) as percentage
            FROM dvds 
            GROUP BY rating_age 
            ORDER BY 
                CASE 
                    WHEN rating_age IS NULL THEN 999 
                    ELSE rating_age 
                END ASC
        ");
    } catch (Exception $e) {
        error_log('[Stats] Rating stats error: ' . $e->getMessage());
        return [
            ['rating_age' => 0, 'count' => 0, 'percentage' => 0],
            ['rating_age' => 12, 'count' => 0, 'percentage' => 0],
            ['rating_age' => 16, 'count' => 0, 'percentage' => 0]
        ];
    }
}

function getGenreStats(\DVDProfiler\Core\Database $database): array {
    try {
        return $database->fetchAll("
            SELECT 
                TRIM(COALESCE(genre, 'Unbekannt')) as genre, 
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds), 1) as percentage,
                ROUND(AVG(runtime), 0) as avg_runtime
            FROM dvds 
            WHERE genre IS NOT NULL AND genre != '' AND genre != 'NULL'
            GROUP BY TRIM(genre) 
            ORDER BY count DESC 
            LIMIT 10
        ");
    } catch (Exception $e) {
        error_log('[Stats] Genre stats error: ' . $e->getMessage());
        return [
            ['genre' => 'Action', 'count' => 0, 'percentage' => 0, 'avg_runtime' => 120],
            ['genre' => 'Drama', 'count' => 0, 'percentage' => 0, 'avg_runtime' => 115],
            ['genre' => 'Comedy', 'count' => 0, 'percentage' => 0, 'avg_runtime' => 100]
        ];
    }
}

function getYearDistribution(\DVDProfiler\Core\Database $database): array {
    try {
        $currentYear = (int)date('Y');
        $startYear = max(1970, $currentYear - 30); // Letzten 30 Jahre
        
        $data = $database->fetchAll("
            SELECT 
                year, 
                COUNT(*) as count 
            FROM dvds 
            WHERE year >= ? AND year <= ?
            GROUP BY year 
            ORDER BY year ASC
        ", [$startYear, $currentYear]);
        
        // In Key-Value Array umwandeln
        $result = [];
        foreach ($data as $row) {
            $result[(int)$row['year']] = (int)$row['count'];
        }
        
        return $result;
    } catch (Exception $e) {
        error_log('[Stats] Year distribution error: ' . $e->getMessage());
        return array_combine(range(2020, (int)date('Y')), array_fill(0, (int)date('Y') - 2019, 0));
    }
}

function getDecadeStats(\DVDProfiler\Core\Database $database): array {
    try {
        return $database->fetchAll("
            SELECT 
                CONCAT(FLOOR(year/10)*10, 's') as decade,
                COUNT(*) as count,
                ROUND(AVG(runtime), 0) as avg_runtime,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds WHERE year > 0), 1) as percentage
            FROM dvds 
            WHERE year > 0 
            GROUP BY FLOOR(year/10)*10 
            ORDER BY FLOOR(year/10)*10 DESC
        ");
    } catch (Exception $e) {
        error_log('[Stats] Decade stats error: ' . $e->getMessage());
        return [
            ['decade' => '2020s', 'count' => 0, 'avg_runtime' => 120, 'percentage' => 0],
            ['decade' => '2010s', 'count' => 0, 'avg_runtime' => 115, 'percentage' => 0]
        ];
    }
}

function getBoxsetStats(\DVDProfiler\Core\Database $database): array {
    try {
        // Boxset-Übersicht
        $boxsetOverview = $database->fetchRow("
            SELECT 
                COUNT(DISTINCT boxset_parent) as total_boxsets,
                COUNT(*) as total_boxset_items,
                ROUND(AVG(item_count)) as avg_items_per_boxset
            FROM (
                SELECT boxset_parent, COUNT(*) as item_count
                FROM dvds 
                WHERE boxset_parent IS NOT NULL
                GROUP BY boxset_parent
            ) boxset_counts
        ");
        
        // Top Boxsets
        $topBoxsets = $database->fetchAll("
            SELECT 
                p.title as boxset_name,
                p.year as boxset_year,
                COUNT(c.id) as child_count,
                ROUND(SUM(c.runtime) / 60, 1) as total_hours
            FROM dvds p
            JOIN dvds c ON c.boxset_parent = p.id
            GROUP BY p.id, p.title, p.year
            ORDER BY child_count DESC
            LIMIT 5
        ");
        
        return [
            'stats' => $boxsetOverview ?: ['total_boxsets' => 0, 'total_boxset_items' => 0, 'avg_items_per_boxset' => 0],
            'top' => $topBoxsets ?: []
        ];
    } catch (Exception $e) {
        error_log('[Stats] Boxset stats error: ' . $e->getMessage());
        return [
            'stats' => ['total_boxsets' => 0, 'total_boxset_items' => 0, 'avg_items_per_boxset' => 0],
            'top' => []
        ];
    }
}

function getNewestFilms(\DVDProfiler\Core\Database $database): array {
    try {
        return $database->fetchAll("
            SELECT 
                title, 
                year, 
                genre, 
                collection_type,
                created_at,
                ROUND(runtime / 60, 1) as hours
            FROM dvds 
            ORDER BY created_at DESC 
            LIMIT 8
        ");
    } catch (Exception $e) {
        error_log('[Stats] Newest films error: ' . $e->getMessage());
        return [];
    }
}

function getUserActivityStats(\DVDProfiler\Core\Database $database): array {
    try {
        $userStats = [];
        
        // User-Ratings (falls Tabelle existiert)
        if ($database->tableExists('user_ratings')) {
            $ratingStats = $database->fetchRow("
                SELECT 
                    COUNT(*) as total_ratings,
                    ROUND(AVG(rating), 1) as avg_rating,
                    COUNT(DISTINCT film_id) as rated_films,
                    COUNT(DISTINCT user_id) as active_users
                FROM user_ratings
            ");
            $userStats['ratings'] = $ratingStats ?: ['total_ratings' => 0, 'avg_rating' => 0, 'rated_films' => 0, 'active_users' => 0];
        }
        
        // Watched-Status (falls Tabelle existiert)
        if ($database->tableExists('user_watched')) {
            $watchedStats = $database->fetchRow("
                SELECT 
                    COUNT(*) as total_watched,
                    COUNT(DISTINCT film_id) as unique_watched_films,
                    COUNT(DISTINCT user_id) as active_watchers
                FROM user_watched
            ");
            $userStats['watched'] = $watchedStats ?: ['total_watched' => 0, 'unique_watched_films' => 0, 'active_watchers' => 0];
        }
        
        return $userStats;
    } catch (Exception $e) {
        error_log('[Stats] User activity stats error: ' . $e->getMessage());
        return ['ratings' => [], 'watched' => []];
    }
}

// Statistiken laden mit Error-Handling
try {
    $basicStats = getBasicStats($database);
    $collections = getCollectionStats($database);
    $ratings = getRatingStats($database);
    $topGenres = getGenreStats($database);
    $yearDistribution = getYearDistribution($database);
    $decades = getDecadeStats($database);
    $boxsetData = getBoxsetStats($database);
    $newestFilms = getNewestFilms($database);
    $userActivity = getUserActivityStats($database);
    
    // Variablen extrahieren für Template
    extract($basicStats);
    $boxsetStats = $boxsetData['stats'];
    $topBoxsets = $boxsetData['top'];
    
    // Performance-Logging (Development)
    if ($app->getSettings()->get('environment') === 'development') {
        $loadTime = microtime(true) - $startTime;
        error_log("[DVDProfiler:INFO] Stats-Page: Geladen in " . round($loadTime * 1000, 2) . "ms");
    }
    
} catch (Exception $e) {
    error_log('[DVDProfiler:ERROR] Stats loading error: ' . $e->getMessage());
    $error_message = 'Fehler beim Laden der Statistiken: ' . $e->getMessage();
    
    // Fallback-Werte
    $totalFilms = $hours = $days = $avgRuntime = $avgYear = $uniqueGenres = $totalViews = 0;
    $collections = $ratings = $topGenres = $decades = $newestFilms = $topBoxsets = [];
    $yearDistribution = [];
    $yearStats = ['oldest_year' => 'N/A', 'newest_year' => 'N/A'];
    $boxsetStats = ['total_boxsets' => 0, 'total_boxset_items' => 0, 'avg_items_per_boxset' => 0];
    $userActivity = ['ratings' => [], 'watched' => []];
}

// Besucher-Counter (Legacy-Support)
$totalVisits = 0;
$counterFile = dirname(__DIR__) . '/counter.txt';
if (file_exists($counterFile)) {
    $totalVisits = (int)file_get_contents($counterFile);
}
?>

<div class="stats-page">
    <header class="page-header">
        <h1>
            <i class="bi bi-bar-chart-line"></i>
            Sammlungs-Statistiken
        </h1>
        <p class="page-subtitle">
            Detaillierte Analyse Ihrer <?= number_format($totalFilms) ?> Filme umfassenden Sammlung
        </p>
        <div class="stats-summary">
            Stand: <?= date('d.m.Y H:i') ?> Uhr | 
            Version <?= \DVDProfiler\Core\Utils::env('DVDPROFILER_VERSION', '1.4.7') ?> | 
            <?= $uniqueGenres ?> Genres | 
            <?= number_format($totalViews) ?> Aufrufe
        </div>
    </header>

    <?php if (isset($error_message)): ?>
        <div class="error-message" style="
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #fff;
        ">
            <i class="bi bi-exclamation-triangle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php else: ?>

    <!-- Haupt-Statistik-Karten -->
    <section class="stats-overview">
        <div class="stat-cards-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="bi bi-collection-play"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($totalFilms) ?></h3>
                    <p>Filme insgesamt</p>
                    <small>in Ihrer Sammlung</small>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $days ?> Tage</h3>
                    <p>Gesamtlaufzeit</p>
                    <small><?= number_format($hours) ?> Stunden (⌀ <?= $avgRuntime ?> Min/Film)</small>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="bi bi-calendar3"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $avgYear ?></h3>
                    <p>Durchschnittsjahr</p>
                    <small>Spanne: <?= $yearStats['oldest_year'] ?> - <?= $yearStats['newest_year'] ?></small>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-boxes"></i>
                </div>
                <div class="stat-content">
                    <h3><?= $boxsetStats['total_boxsets'] ?></h3>
                    <p>BoxSets</p>
                    <small><?= $boxsetStats['total_boxset_items'] ?> Einzelfilme (⌀ <?= $boxsetStats['avg_items_per_boxset'] ?? 0 ?> pro Set)</small>
                </div>
            </div>
            
            <?php if ($totalVisits > 0): ?>
                <div class="stat-card secondary">
                    <div class="stat-icon">
                        <i class="bi bi-eye"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= number_format($totalVisits) ?></h3>
                        <p>Seitenaufrufe</p>
                        <small>seit Beginn der Aufzeichnung</small>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($userActivity['ratings'])): ?>
                <div class="stat-card accent">
                    <div class="stat-icon">
                        <i class="bi bi-star-fill"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $userActivity['ratings']['avg_rating'] ?? 0 ?>/5</h3>
                        <p>Durchschnittsbewertung</p>
                        <small><?= number_format($userActivity['ratings']['total_ratings'] ?? 0) ?> Bewertungen</small>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Charts -->
    <section class="charts-section">
        <div class="charts-grid">
            <!-- Genre-Verteilung -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3>
                        <i class="bi bi-pie-chart"></i>
                        Genre-Verteilung
                    </h3>
                    <p>Top <?= count($topGenres) ?> Genres nach Anzahl</p>
                </div>
                <canvas id="genreChart"></canvas>
            </div>
            
            <!-- Altersfreigaben -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3>
                        <i class="bi bi-shield-check"></i>
                        Altersfreigaben
                    </h3>
                    <p>Verteilung nach FSK-Einstufung</p>
                </div>
                <canvas id="ratingChart"></canvas>
            </div>
            
            <!-- Jahrzehnte-Verteilung -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3>
                        <i class="bi bi-calendar-range"></i>
                        Dekaden-Verteilung
                    </h3>
                    <p>Filme nach Jahrzehnten gruppiert</p>
                </div>
                <canvas id="decadeChart"></canvas>
            </div>
            
            <!-- Collection Types -->
            <div class="chart-container">
                <div class="chart-header">
                    <h3>
                        <i class="bi bi-disc"></i>
                        Medientypen
                    </h3>
                    <p>DVD, Blu-ray und andere Formate</p>
                </div>
                <canvas id="collectionChart"></canvas>
            </div>
            
            <!-- Jahr-Verteilung (breiter Chart) -->
            <div class="chart-container wide">
                <div class="chart-header">
                    <h3>
                        <i class="bi bi-graph-up"></i>
                        Jahresverteilung
                    </h3>
                    <p>Anzahl Filme pro Erscheinungsjahr</p>
                </div>
                <canvas id="yearChart"></canvas>
            </div>
        </div>
    </section>

    <!-- Zusätzliche Informationen -->
    <section class="additional-stats">
        <div class="stats-grid">
            <!-- Top BoxSets -->
            <?php if (!empty($topBoxsets)): ?>
                <div class="info-card">
                    <h3>
                        <i class="bi bi-collection"></i>
                        Größte BoxSets
                    </h3>
                    <div class="list-content">
                        <?php foreach ($topBoxsets as $boxset): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <span class="item-title"><?= htmlspecialchars($boxset['boxset_name']) ?></span>
                                    <span class="item-meta"><?= $boxset['boxset_year'] ?></span>
                                </div>
                                <div class="item-stats">
                                    <span class="item-count"><?= $boxset['child_count'] ?> Filme</span>
                                    <span class="item-duration"><?= $boxset['total_hours'] ?>h</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Neueste Filme -->
            <?php if (!empty($newestFilms)): ?>
                <div class="info-card">
                    <h3>
                        <i class="bi bi-plus-circle"></i>
                        Zuletzt hinzugefügt
                    </h3>
                    <div class="list-content">
                        <?php foreach ($newestFilms as $film): ?>
                            <div class="list-item">
                                <div class="item-info">
                                    <span class="item-title"><?= htmlspecialchars($film['title']) ?></span>
                                    <span class="item-meta">
                                        <?= $film['year'] ?> • <?= htmlspecialchars($film['genre']) ?>
                                        <?php if ($film['collection_type']): ?>
                                            • <?= htmlspecialchars($film['collection_type']) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <div class="item-stats">
                                    <span class="item-date"><?= date('d.m.Y', strtotime($film['created_at'])) ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php endif; ?>
</div>

<script>
// Chart.js Charts mit Core-System Integration
let chartsInitialized = false;

async function ensureChartJsLoaded() {
    if (typeof Chart === 'undefined') {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
            script.onload = () => {
                console.log('📊 Chart.js geladen');
                resolve();
            };
            script.onerror = () => {
                console.error('❌ Chart.js konnte nicht geladen werden');
                reject(new Error('Chart.js loading failed'));
            };
            document.head.appendChild(script);
        });
    }
    return Promise.resolve();
}

async function initializeCharts() {
    if (chartsInitialized) return;
    
    try {
        await ensureChartJsLoaded();
        
        // Chart.js Default-Konfiguration
        Chart.defaults.color = '#ffffff';
        Chart.defaults.font.family = 'Inter, sans-serif';
        Chart.defaults.plugins.legend.labels.usePointStyle = true;
        
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        color: '#ffffff'
                    }
                }
            }
        };

        // Genre Chart
        if (document.getElementById('genreChart')) {
            const genreCtx = document.getElementById('genreChart').getContext('2d');
            const genreData = <?= json_encode($topGenres) ?>;
            
            new Chart(genreCtx, {
                type: 'pie',
                data: {
                    labels: genreData.map(g => g.genre),
                    datasets: [{
                        data: genreData.map(g => g.count),
                        backgroundColor: [
                            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', 
                            '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F',
                            '#BB8FCE', '#85C1E9'
                        ],
                        borderWidth: 2,
                        borderColor: 'rgba(255,255,255,0.1)'
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const genre = genreData[context.dataIndex];
                                    return `${genre.genre}: ${genre.count} Filme (${genre.percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Rating Chart
        if (document.getElementById('ratingChart')) {
            const ratingCtx = document.getElementById('ratingChart').getContext('2d');
            const ratingData = <?= json_encode($ratings) ?>;
            
            new Chart(ratingCtx, {
                type: 'doughnut',
                data: {
                    labels: ratingData.map(r => r.rating_age == 0 ? 'Ohne Angabe' : `FSK ${r.rating_age}`),
                    datasets: [{
                        data: ratingData.map(r => r.count),
                        backgroundColor: ['#28a745', '#ffc107', '#fd7e14', '#dc3545', '#6c757d'],
                        borderWidth: 2,
                        borderColor: 'rgba(255,255,255,0.1)'
                    }]
                },
                options: {
                    ...commonOptions,
                    plugins: {
                        ...commonOptions.plugins,
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const rating = ratingData[context.dataIndex];
                                    return `${context.label}: ${rating.count} Filme (${rating.percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Decade Chart
        if (document.getElementById('decadeChart')) {
            const decadeCtx = document.getElementById('decadeChart').getContext('2d');
            const decadeData = <?= json_encode($decades) ?>;
            
            new Chart(decadeCtx, {
                type: 'bar',
                data: {
                    labels: decadeData.map(d => d.decade),
                    datasets: [{
                        label: 'Anzahl Filme',
                        data: decadeData.map(d => d.count),
                        backgroundColor: 'rgba(54, 162, 235, 0.6)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        },
                        x: {
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        }
                    }
                }
            });
        }

        // Collection Chart
        if (document.getElementById('collectionChart')) {
            const collectionCtx = document.getElementById('collectionChart').getContext('2d');
            const collectionData = <?= json_encode($collections) ?>;
            
            new Chart(collectionCtx, {
                type: 'polarArea',
                data: {
                    labels: collectionData.map(c => c.collection_type),
                    datasets: [{
                        data: collectionData.map(c => c.count),
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.6)',
                            'rgba(54, 162, 235, 0.6)',
                            'rgba(255, 205, 86, 0.6)',
                            'rgba(75, 192, 192, 0.6)',
                            'rgba(153, 102, 255, 0.6)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(255, 205, 86, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: commonOptions
            });
        }

        // Year Distribution Chart
        if (document.getElementById('yearChart')) {
            const yearCtx = document.getElementById('yearChart').getContext('2d');
            const yearData = <?= json_encode($yearDistribution) ?>;
            
            new Chart(yearCtx, {
                type: 'line',
                data: {
                    labels: Object.keys(yearData),
                    datasets: [{
                        label: 'Filme pro Jahr',
                        data: Object.values(yearData),
                        borderColor: 'rgba(75, 192, 192, 1)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    ...commonOptions,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        },
                        x: {
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        }
                    }
                }
            });
        }
        
        chartsInitialized = true;
        console.log('🎯 Alle Stats-Charts erfolgreich initialisiert!');
        
    } catch (error) {
        console.error('❌ Chart-Initialisierung fehlgeschlagen:', error);
    }
}

// Charts initialisieren
document.addEventListener('DOMContentLoaded', function() {
    console.log('📊 Stats-Page (Core-System) geladen');
    
    // Sofort versuchen
    setTimeout(initializeCharts, 100);
    
    // Fallback für langsame Verbindungen
    setTimeout(() => {
        if (!chartsInitialized) {
            console.log('🔄 Chart-Initialisierung Retry...');
            initializeCharts();
        }
    }, 1000);
});

// Performance-Info (Development)
<?php if ($app->getSettings()->get('environment') === 'development'): ?>
    console.log('📊 Stats-Performance:', {
        'loadTime': '<?= round((microtime(true) - $startTime) * 1000, 2) ?>ms',
        'totalFilms': <?= $totalFilms ?>,
        'genres': <?= count($topGenres) ?>,
        'decades': <?= count($decades) ?>,
        'memoryUsage': '<?= \DVDProfiler\Core\Utils::formatBytes(memory_get_peak_usage(true)) ?>'
    });
<?php endif; ?>
</script>

<style>
/* STATS PAGE - CORE-SYSTEM OPTIMIERTE STYLES */

:root {
    --glass-bg: rgba(255, 255, 255, 0.1);
    --glass-border: rgba(255, 255, 255, 0.2);
    --text-white: #ffffff;
    --text-glass: rgba(255, 255, 255, 0.8);
    --text-muted: rgba(255, 255, 255, 0.6);
    --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --gradient-success: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    --gradient-info: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    --gradient-warning: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    --gradient-secondary: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
    --gradient-accent: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
    --space-xs: 0.5rem;
    --space-sm: 0.75rem;
    --space-md: 1rem;
    --space-lg: 1.5rem;
    --space-xl: 2rem;
    --radius-lg: 12px;
    --transition-normal: all 0.3s ease;
    --shadow-glass: 0 8px 32px rgba(0, 0, 0, 0.1);
}

.stats-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: var(--space-lg);
}

.page-header {
    text-align: center;
    margin-bottom: var(--space-xl);
}

.page-header h1 {
    color: var(--text-white);
    margin-bottom: var(--space-sm);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-md);
    font-size: 2.5rem;
    font-weight: 700;
}

.page-subtitle {
    color: var(--text-glass);
    font-size: 1.1rem;
    margin-bottom: var(--space-sm);
}

.stats-summary {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.stat-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--space-lg);
    margin-bottom: var(--space-xl);
}

.stat-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-xl);
    backdrop-filter: blur(10px);
    transition: var(--transition-normal);
    display: flex;
    align-items: center;
    gap: var(--space-lg);
    position: relative;
    overflow: hidden;
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: var(--gradient-primary);
}

.stat-card.success::before { background: var(--gradient-success); }
.stat-card.info::before { background: var(--gradient-info); }
.stat-card.warning::before { background: var(--gradient-warning); }
.stat-card.secondary::before { background: var(--gradient-secondary); }
.stat-card.accent::before { background: var(--gradient-accent); }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-glass);
}

.stat-icon {
    font-size: 3rem;
    color: #3498db;
    flex-shrink: 0;
}

.stat-content h3 {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--text-white);
    margin: 0;
    line-height: 1;
}

.stat-content p {
    font-size: 1.1rem;
    color: var(--text-glass);
    margin: var(--space-xs) 0 0 0;
}

.stat-content small {
    color: var(--text-muted);
    font-size: 0.9rem;
}

.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: var(--space-xl);
    margin-bottom: var(--space-xl);
}

.chart-container {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    backdrop-filter: blur(10px);
    min-height: 450px;
}

.chart-container.wide {
    grid-column: 1 / -1;
}

.chart-header {
    margin-bottom: var(--space-lg);
    text-align: center;
}

.chart-header h3 {
    color: var(--text-white);
    margin-bottom: var(--space-xs);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
    font-size: 1.3rem;
}

.chart-header p {
    color: var(--text-glass);
    font-size: 0.9rem;
}

.chart-container canvas {
    width: 100% !important;
    height: 350px !important;
    max-height: 350px !important;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: var(--space-lg);
}

.info-card {
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-lg);
    padding: var(--space-lg);
    backdrop-filter: blur(10px);
}

.info-card h3 {
    color: var(--text-white);
    margin-bottom: var(--space-lg);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    font-size: 1.2rem;
}

.list-content {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--space-sm);
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    transition: var(--transition-normal);
}

.list-item:hover {
    background: rgba(255, 255, 255, 0.1);
}

.item-info {
    flex: 1;
}

.item-title {
    display: block;
    color: var(--text-white);
    font-weight: 600;
    margin-bottom: 2px;
}

.item-meta {
    color: var(--text-muted);
    font-size: 0.85rem;
}

.item-stats {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
}

.item-count, .item-duration, .item-date {
    color: var(--text-glass);
    font-size: 0.85rem;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .stats-page {
        padding: var(--space-md);
    }
    
    .page-header h1 {
        font-size: 2rem;
        flex-direction: column;
        gap: var(--space-sm);
    }
    
    .stat-cards-grid {
        grid-template-columns: 1fr;
        gap: var(--space-md);
    }
    
    .stat-card {
        padding: var(--space-lg);
    }
    
    .stat-icon {
        font-size: 2.5rem;
    }
    
    .stat-content h3 {
        font-size: 2rem;
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
        gap: var(--space-lg);
    }
    
    .chart-container canvas {
        height: 300px !important;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .chart-container {
        min-height: 350px;
        padding: var(--space-md);
    }
    
    .list-item {
        flex-direction: column;
        align-items: flex-start;
        gap: var(--space-xs);
    }
    
    .item-stats {
        align-items: flex-start;
        flex-direction: row;
        gap: var(--space-sm);
    }
}
</style>