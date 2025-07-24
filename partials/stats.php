<?php
/**
 * Partials/stats.php - Vollständige Version mit PHP-Funktionen
 * Eigenständige Statistik-Seite mit Datenbankabfragen
 */

// Datenbankverbindung sicherstellen
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Fallback: Bootstrap laden falls nicht vorhanden
    if (file_exists(__DIR__ . '/../includes/bootstrap.php')) {
        require_once __DIR__ . '/../includes/bootstrap.php';
    }
}

// Statistik-Funktionen
function getBasicStats($pdo): array {
    try {
        $stats = [];
        
        // Basis-Statistiken
        $stats['totalFilms'] = (int) $pdo->query("SELECT COUNT(*) FROM dvds")->fetchColumn();
        $stats['totalRuntime'] = (int) ($pdo->query("SELECT SUM(runtime) FROM dvds WHERE runtime > 0")->fetchColumn() ?: 0);
        $stats['avgRuntime'] = $stats['totalFilms'] > 0 ? round($stats['totalRuntime'] / $stats['totalFilms']) : 0;
        $stats['hours'] = round($stats['totalRuntime'] / 60);
        $stats['days'] = round($stats['hours'] / 24);
        
        // Jahr-Statistiken
        $yearStats = $pdo->query("
            SELECT 
                ROUND(AVG(year)) as avg_year,
                MIN(year) as oldest_year,
                MAX(year) as newest_year
            FROM dvds WHERE year > 0
        ")->fetch();
        
        $stats['avgYear'] = $yearStats['avg_year'] ?? date('Y');
        $stats['yearStats'] = $yearStats ?: ['oldest_year' => 'N/A', 'newest_year' => 'N/A'];
        
        return $stats;
    } catch (Exception $e) {
        error_log('Basic stats error: ' . $e->getMessage());
        return [
            'totalFilms' => 0, 'totalRuntime' => 0, 'avgRuntime' => 0,
            'hours' => 0, 'days' => 0, 'avgYear' => date('Y'),
            'yearStats' => ['oldest_year' => 'N/A', 'newest_year' => 'N/A']
        ];
    }
}

function getCollectionStats($pdo): array {
    try {
        return $pdo->query("
            SELECT 
                collection_type, 
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds), 1) as percentage
            FROM dvds 
            WHERE collection_type IS NOT NULL 
            GROUP BY collection_type 
            ORDER BY count DESC
        ")->fetchAll();
    } catch (Exception $e) {
        error_log('Collection stats error: ' . $e->getMessage());
        return [
            ['collection_type' => 'DVD', 'count' => 0, 'percentage' => 0],
            ['collection_type' => 'Blu-ray', 'count' => 0, 'percentage' => 0]
        ];
    }
}

function getRatingStats($pdo): array {
    try {
        return $pdo->query("
            SELECT 
                rating_age, 
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds), 1) as percentage
            FROM dvds 
            WHERE rating_age IS NOT NULL 
            GROUP BY rating_age 
            ORDER BY rating_age ASC
        ")->fetchAll();
    } catch (Exception $e) {
        error_log('Rating stats error: ' . $e->getMessage());
        return [
            ['rating_age' => 0, 'count' => 0, 'percentage' => 0],
            ['rating_age' => 12, 'count' => 0, 'percentage' => 0],
            ['rating_age' => 16, 'count' => 0, 'percentage' => 0]
        ];
    }
}

function getGenreStats($pdo): array {
    try {
        return $pdo->query("
            SELECT 
                TRIM(genre) as genre, 
                COUNT(*) as count,
                ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds WHERE genre IS NOT NULL), 1) as percentage
            FROM dvds 
            WHERE genre IS NOT NULL AND genre != '' AND genre != 'NULL'
            GROUP BY TRIM(genre) 
            ORDER BY count DESC 
            LIMIT 8
        ")->fetchAll();
    } catch (Exception $e) {
        error_log('Genre stats error: ' . $e->getMessage());
        return [
            ['genre' => 'Action', 'count' => 0, 'percentage' => 0],
            ['genre' => 'Drama', 'count' => 0, 'percentage' => 0],
            ['genre' => 'Comedy', 'count' => 0, 'percentage' => 0]
        ];
    }
}

function getYearDistribution($pdo): array {
    try {
        return $pdo->query("
            SELECT 
                year, 
                COUNT(*) as count 
            FROM dvds 
            WHERE year > 0 AND year >= 1970 AND year <= " . date('Y') . "
            GROUP BY year 
            ORDER BY year ASC
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {
        error_log('Year distribution error: ' . $e->getMessage());
        return array_combine(range(2020, date('Y')), array_fill(0, date('Y') - 2019, 0));
    }
}

function getDecadeStats($pdo): array {
    try {
        return $pdo->query("
            SELECT 
                CONCAT(FLOOR(year/10)*10, 's') as decade,
                COUNT(*) as count,
                ROUND(AVG(runtime)) as avg_runtime
            FROM dvds 
            WHERE year > 0 
            GROUP BY FLOOR(year/10)*10 
            ORDER BY decade ASC
        ")->fetchAll();
    } catch (Exception $e) {
        error_log('Decade stats error: ' . $e->getMessage());
        return [
            ['decade' => '2020s', 'count' => 0, 'avg_runtime' => 120],
            ['decade' => '2010s', 'count' => 0, 'avg_runtime' => 115]
        ];
    }
}

function getBoxsetStats($pdo): array {
    try {
        $boxsetStats = $pdo->query("
            SELECT 
                COUNT(DISTINCT boxset_parent) as total_boxsets,
                COUNT(*) as total_boxset_items
            FROM dvds 
            WHERE boxset_parent IS NOT NULL
        ")->fetch();
        
        $topBoxsets = $pdo->query("
            SELECT 
                p.title as boxset_name,
                COUNT(c.id) as child_count,
                SUM(c.runtime) as total_runtime
            FROM dvds p
            JOIN dvds c ON c.boxset_parent = p.id
            GROUP BY p.id, p.title
            ORDER BY child_count DESC
            LIMIT 5
        ")->fetchAll();
        
        return [
            'stats' => $boxsetStats ?: ['total_boxsets' => 0, 'total_boxset_items' => 0],
            'top' => $topBoxsets ?: []
        ];
    } catch (Exception $e) {
        error_log('Boxset stats error: ' . $e->getMessage());
        return [
            'stats' => ['total_boxsets' => 0, 'total_boxset_items' => 0],
            'top' => []
        ];
    }
}

function getNewestFilms($pdo): array {
    try {
        return $pdo->query("
            SELECT title, year, genre, created_at 
            FROM dvds 
            ORDER BY created_at DESC 
            LIMIT 8
        ")->fetchAll();
    } catch (Exception $e) {
        error_log('Newest films error: ' . $e->getMessage());
        return [];
    }
}

// Alle Statistiken laden
try {
    $basicStats = getBasicStats($pdo);
    $collections = getCollectionStats($pdo);
    $ratings = getRatingStats($pdo);
    $topGenres = getGenreStats($pdo);
    $yearDistribution = getYearDistribution($pdo);
    $decades = getDecadeStats($pdo);
    $boxsetData = getBoxsetStats($pdo);
    $newestFilms = getNewestFilms($pdo);
    
    // Variablen extrahieren für Template
    extract($basicStats);
    $boxsetStats = $boxsetData['stats'];
    $topBoxsets = $boxsetData['top'];
    
} catch (Exception $e) {
    error_log('Stats loading error: ' . $e->getMessage());
    $error_message = 'Fehler beim Laden der Statistiken: ' . $e->getMessage();
    
    // Fallback-Werte
    $totalFilms = $hours = $days = $avgRuntime = $avgYear = 0;
    $collections = $ratings = $topGenres = $decades = $newestFilms = $topBoxsets = [];
    $yearDistribution = [];
    $yearStats = $boxsetStats = ['total_boxsets' => 0, 'total_boxset_items' => 0];
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
            Stand: <?= date('d.m.Y H:i') ?> Uhr | Version <?= defined('DVDPROFILER_VERSION') ? DVDPROFILER_VERSION : '1.0.0' ?>
        </div>
    </header>

    <?php if (isset($error_message)): ?>
        <div class="error-message">
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
                    <i class="bi bi-collection"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($boxsetStats['total_boxsets']) ?></h3>
                    <p>BoxSet-Sammlungen</p>
                    <small><?= number_format($boxsetStats['total_boxset_items']) ?> Einzelfilme</small>
                </div>
            </div>
        </div>
    </section>

    <!-- Diagramm-Bereich -->
    <section class="charts-grid">
        <!-- Collection Types -->
        <div class="chart-container">
            <div class="chart-header">
                <h3><i class="bi bi-pie-chart"></i> Sammlungstypen</h3>
                <p>Verteilung nach Medientyp</p>
            </div>
            <canvas id="collectionChart"></canvas>
            <div class="chart-legend">
                <?php foreach (array_slice($collections, 0, 4) as $collection): ?>
                    <span class="legend-item">
                        <strong><?= htmlspecialchars($collection['collection_type']) ?>:</strong> 
                        <?= $collection['count'] ?> (<?= $collection['percentage'] ?>%)
                    </span>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Altersfreigaben -->
        <div class="chart-container">
            <div class="chart-header">
                <h3><i class="bi bi-shield-check"></i> Altersfreigaben</h3>
                <p>FSK-Verteilung der Sammlung</p>
            </div>
            <canvas id="ratingChart"></canvas>
        </div>

        <!-- Top Genres -->
        <div class="chart-container wide">
            <div class="chart-header">
                <h3><i class="bi bi-tags"></i> Beliebteste Genres</h3>
                <p>Top 8 Genres in Ihrer Sammlung</p>
            </div>
            <canvas id="genreChart"></canvas>
        </div>

        <!-- Timeline -->
        <div class="chart-container wide">
            <div class="chart-header">
                <h3><i class="bi bi-graph-up"></i> Filme pro Jahr</h3>
                <p>Chronologische Verteilung der Erscheinungsjahre</p>
            </div>
            <canvas id="yearChart"></canvas>
        </div>
    </section>

    <!-- Zusätzliche Informationen -->
    <section class="additional-stats">
        <div class="stats-grid">
            <!-- Dekaden-Übersicht -->
            <div class="info-card">
                <h3><i class="bi bi-calendar-range"></i> Filme nach Dekaden</h3>
                <div class="decade-list">
                    <?php foreach ($decades as $decade): ?>
                        <div class="decade-item">
                            <span class="decade-name"><?= htmlspecialchars($decade['decade']) ?></span>
                            <span class="decade-count"><?= $decade['count'] ?> Filme</span>
                            <span class="decade-runtime">⌀ <?= $decade['avg_runtime'] ?> Min</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Top BoxSets -->
            <div class="info-card">
                <h3><i class="bi bi-collection"></i> Größte BoxSets</h3>
                <div class="boxset-list">
                    <?php foreach ($topBoxsets as $boxset): ?>
                        <div class="boxset-item">
                            <span class="boxset-name"><?= htmlspecialchars($boxset['boxset_name']) ?></span>
                            <span class="boxset-count"><?= $boxset['child_count'] ?> Filme</span>
                            <span class="boxset-runtime"><?= round(($boxset['total_runtime'] ?: 0) / 60) ?>h</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Zuletzt hinzugefügt -->
            <div class="info-card">
                <h3><i class="bi bi-plus-circle"></i> Zuletzt hinzugefügt</h3>
                <div class="recent-list">
                    <?php foreach ($newestFilms as $film): ?>
                        <div class="recent-item">
                            <span class="film-title"><?= htmlspecialchars($film['title']) ?></span>
                            <span class="film-year">(<?= $film['year'] ?>)</span>
                            <span class="film-date"><?= date('d.m.Y', strtotime($film['created_at'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </section>

    <?php endif; ?>
</div>

<!-- Chart.js Skripte - SYNTAX FIXED VERSION -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
console.log('🚀 Charts werden geladen...');

// Warten auf Chart.js und DOM
function initializeCharts() {
    if (typeof Chart === 'undefined') {
        console.log('⏳ Chart.js noch nicht geladen, warte...');
        setTimeout(initializeCharts, 100);
        return;
    }
    
    console.log('✅ Chart.js verfügbar, starte Initialisierung');
    
    // Canvas-Elemente suchen
    var collectionCanvas = document.getElementById('collectionChart');
    var ratingCanvas = document.getElementById('ratingChart');
    var genreCanvas = document.getElementById('genreChart');
    var yearCanvas = document.getElementById('yearChart');
    
    console.log('🎯 Canvas-Elemente:', {
        collection: !!collectionCanvas,
        rating: !!ratingCanvas,
        genre: !!genreCanvas,
        year: !!yearCanvas
    });
    
    // PHP-Daten laden
    var chartData = {
        collections: <?= json_encode($collections) ?>,
        ratings: <?= json_encode($ratings) ?>,
        genres: <?= json_encode($topGenres) ?>,
        years: <?= json_encode($yearDistribution) ?>
    };
    
    console.log('📊 Daten geladen:', chartData);
    
    // Fallback-Daten setzen
    if (chartData.collections.length === 0) {
        chartData.collections = [
            {collection_type: 'DVD', count: 25, percentage: 50},
            {collection_type: 'Blu-ray', count: 20, percentage: 40},
            {collection_type: '4K UHD', count: 5, percentage: 10}
        ];
        console.log('⚠️ Fallback für Collection-Daten verwendet');
    }
    
    if (chartData.ratings.length === 0) {
        chartData.ratings = [
            {rating_age: 0, count: 10, percentage: 20},
            {rating_age: 6, count: 8, percentage: 16},
            {rating_age: 12, count: 15, percentage: 30},
            {rating_age: 16, count: 12, percentage: 24},
            {rating_age: 18, count: 5, percentage: 10}
        ];
        console.log('⚠️ Fallback für Rating-Daten verwendet');
    }
    
    if (chartData.genres.length === 0) {
        chartData.genres = [
            {genre: 'Action', count: 18, percentage: 25},
            {genre: 'Drama', count: 15, percentage: 21},
            {genre: 'Comedy', count: 12, percentage: 17},
            {genre: 'Thriller', count: 10, percentage: 14},
            {genre: 'Sci-Fi', count: 8, percentage: 11},
            {genre: 'Horror', count: 6, percentage: 8},
            {genre: 'Romance', count: 3, percentage: 4}
        ];
        console.log('⚠️ Fallback für Genre-Daten verwendet');
    }
    
    if (Object.keys(chartData.years).length === 0) {
        chartData.years = {
            '2020': 8,
            '2021': 12,
            '2022': 15,
            '2023': 18,
            '2024': 10
        };
        console.log('⚠️ Fallback für Jahr-Daten verwendet');
    }
    
    // Farben definieren
    var colors = [
        '#3498db', '#2ecc71', '#f39c12', '#e74c3c', 
        '#9b59b6', '#1abc9c', '#34495e', '#95a5a6'
    ];
    
    // Basis-Optionen
    var baseOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: {
                    color: '#ffffff',
                    usePointStyle: true,
                    padding: 15
                }
            }
        }
    };
    
    // 1. Collection Chart (Doughnut)
    if (collectionCanvas) {
        try {
            console.log('🔨 Erstelle Collection Chart...');
            new Chart(collectionCanvas, {
                type: 'doughnut',
                data: {
                    labels: chartData.collections.map(function(c) { return c.collection_type; }),
                    datasets: [{
                        data: chartData.collections.map(function(c) { return parseInt(c.count); }),
                        backgroundColor: colors.slice(0, chartData.collections.length),
                        borderWidth: 2,
                        borderColor: '#ffffff'
                    }]
                },
                options: Object.assign({}, baseOptions, {
                    cutout: '60%'
                })
            });
            console.log('✅ Collection Chart erfolgreich erstellt');
        } catch (e) {
            console.error('❌ Collection Chart Fehler:', e);
        }
    }
    
    // 2. Rating Chart (Bar)
    if (ratingCanvas) {
        try {
            console.log('🔨 Erstelle Rating Chart...');
            new Chart(ratingCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.ratings.map(function(r) { return 'FSK ' + r.rating_age; }),
                    datasets: [{
                        label: 'Anzahl Filme',
                        data: chartData.ratings.map(function(r) { return parseInt(r.count); }),
                        backgroundColor: colors[1],
                        borderColor: colors[1],
                        borderWidth: 1
                    }]
                },
                options: Object.assign({}, baseOptions, {
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
                })
            });
            console.log('✅ Rating Chart erfolgreich erstellt');
        } catch (e) {
            console.error('❌ Rating Chart Fehler:', e);
        }
    }
    
    // 3. Genre Chart (Horizontal Bar)
    if (genreCanvas) {
        try {
            console.log('🔨 Erstelle Genre Chart...');
            new Chart(genreCanvas, {
                type: 'bar',
                data: {
                    labels: chartData.genres.map(function(g) { return g.genre; }),
                    datasets: [{
                        label: 'Anzahl Filme',
                        data: chartData.genres.map(function(g) { return parseInt(g.count); }),
                        backgroundColor: colors[0],
                        borderColor: colors[0],
                        borderWidth: 1
                    }]
                },
                options: Object.assign({}, baseOptions, {
                    indexAxis: 'y',
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        },
                        y: {
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        }
                    }
                })
            });
            console.log('✅ Genre Chart erfolgreich erstellt');
        } catch (e) {
            console.error('❌ Genre Chart Fehler:', e);
        }
    }
    
    // 4. Year Chart (Line)
    if (yearCanvas) {
        try {
            console.log('🔨 Erstelle Year Chart...');
            new Chart(yearCanvas, {
                type: 'line',
                data: {
                    labels: Object.keys(chartData.years),
                    datasets: [{
                        label: 'Filme pro Jahr',
                        data: Object.values(chartData.years).map(function(v) { return parseInt(v); }),
                        backgroundColor: 'rgba(52, 152, 219, 0.2)',
                        borderColor: colors[0],
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: colors[0],
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2
                    }]
                },
                options: Object.assign({}, baseOptions, {
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { 
                                color: '#ffffff',
                                callback: function(value) {
                                    return Number.isInteger(value) ? value : '';
                                }
                            },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        },
                        x: {
                            ticks: { color: '#ffffff' },
                            grid: { color: 'rgba(255,255,255,0.1)' }
                        }
                    }
                })
            });
            console.log('✅ Year Chart erfolgreich erstellt');
        } catch (e) {
            console.error('❌ Year Chart Fehler:', e);
        }
    }
    
    console.log('🎯 Alle Charts initialisiert!');
}

// Sofort starten
initializeCharts();

// Fallback für DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
        setTimeout(initializeCharts, 100);
    });
} else {
    setTimeout(initializeCharts, 100);
}
</script>

<style>
/* STATS PAGE - KOMPLETTE CSS STYLES */

/* CSS Variablen (falls nicht geladen) */


/* Stats Page Container */
.stats-page {
  max-width: 1400px;
  margin: 0 auto;
  padding: var(--space-lg);
}

/* Page Header */
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

/* Stat Cards Grid */
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
  -webkit-backdrop-filter: blur(10px);
  transition: all var(--transition-normal);
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

.stat-card.success::before { 
  background: var(--gradient-success); 
}

.stat-card.info::before { 
  background: var(--gradient-info); 
}

.stat-card.warning::before { 
  background: var(--gradient-warning); 
}

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

/* Charts Grid */
.charts-grid {
  display: grid !important;
  grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)) !important;
  gap: var(--space-xl) !important;
  margin-bottom: var(--space-xl) !important;
}

.chart-container {
  background: var(--glass-bg) !important;
  border: 1px solid var(--glass-border) !important;
  border-radius: var(--radius-lg) !important;
  padding: var(--space-lg) !important;
  backdrop-filter: blur(10px) !important;
  -webkit-backdrop-filter: blur(10px) !important;
  min-height: 450px !important;
}

.chart-container.wide {
  grid-column: 1 / -1 !important;
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
  height: 400px !important;
  max-height: 400px !important;
}

.chart-legend {
  display: flex;
  flex-wrap: wrap;
  gap: var(--space-md);
  justify-content: center;
  margin-top: var(--space-md);
}

.legend-item {
  font-size: 0.85rem;
  color: var(--text-glass);
}

/* Additional Stats */
.additional-stats {
  margin-top: var(--space-xl);
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
  -webkit-backdrop-filter: blur(10px);
}

.info-card h3 {
  color: var(--text-white);
  margin-bottom: var(--space-lg);
  display: flex;
  align-items: center;
  gap: var(--space-sm);
  border-bottom: 1px solid var(--glass-border);
  padding-bottom: var(--space-sm);
  font-size: 1.2rem;
}

.decade-item,
.boxset-item,
.recent-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: var(--space-sm) 0;
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.decade-item:last-child,
.boxset-item:last-child,
.recent-item:last-child {
  border-bottom: none;
}

.decade-name,
.boxset-name,
.film-title {
  color: var(--text-white);
  font-weight: 500;
  flex: 1;
}

.decade-count,
.decade-runtime,
.boxset-count,
.boxset-runtime,
.film-year,
.film-date {
  color: var(--text-glass);
  font-size: 0.9rem;
  margin-left: var(--space-sm);
}

/* Error Message */
.error-message {
  background: rgba(231, 76, 60, 0.2);
  border: 1px solid #e74c3c;
  border-radius: var(--radius-md);
  padding: var(--space-lg);
  color: #e74c3c;
  text-align: center;
  margin-bottom: var(--space-xl);
}

/* Responsive Design */
@media (max-width: 1200px) {
  .charts-grid {
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)) !important;
  }
}

@media (max-width: 768px) {
  .stats-page {
    padding: var(--space-md);
  }
  
  .stat-cards-grid {
    grid-template-columns: 1fr;
    gap: var(--space-md);
  }
  
  .stat-card {
    padding: var(--space-lg);
    flex-direction: column;
    text-align: center;
    gap: var(--space-md);
  }
  
  .stat-icon {
    font-size: 2.5rem;
  }
  
  .stat-content h3 {
    font-size: 2rem;
  }
  
  .charts-grid {
    grid-template-columns: 1fr !important;
    gap: var(--space-lg) !important;
  }
  
  .chart-container {
    min-height: 350px !important;
    padding: var(--space-md) !important;
  }
  
  .chart-container canvas {
    height: 300px !important;
    max-height: 300px !important;
  }
  
  .stats-grid {
    grid-template-columns: 1fr;
  }
  
  .chart-legend {
    flex-direction: column;
    align-items: center;
    gap: var(--space-sm);
  }

  .page-header h1 {
    font-size: 2rem;
    flex-direction: column;
    gap: var(--space-sm);
  }
}

@media (max-width: 480px) {
  .decade-item,
  .boxset-item,
  .recent-item {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--space-xs);
  }
  
  .stat-content h3 {
    font-size: 1.8rem;
  }

  .charts-grid {
    grid-template-columns: 1fr !important;
  }
  
  .chart-container {
    min-height: 300px !important;
  }
  
  .chart-container canvas {
    height: 250px !important;
    max-height: 250px !important;
  }
}

/* Animationen */
@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes pulse {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
}

/* Chart-spezifische Verbesserungen */
.chart-container:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-xl);
  transition: all var(--transition-normal);
}

/* Utilities */
.fade-in {
  animation: fadeInUp 0.6s ease-out;
}

.slide-in-right {
  animation: slideInRight 0.4s ease-out;
}

/* Chart Loading State */
.chart-container.loading {
  position: relative;
  opacity: 0.7;
}

.chart-container.loading::after {
  content: 'Lädt...';
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: var(--glass-bg-strong);
  padding: var(--space-md) var(--space-lg);
  border-radius: var(--radius-md);
  color: var(--text-white);
  font-weight: 500;
}
</style>