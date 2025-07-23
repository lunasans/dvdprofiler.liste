<?php
/**
 * DVD Profiler Liste - Erweiterte Statistik-Seite
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.5
 */

// Bootstrap laden für Datenbankverbindung
if (!isset($pdo)) {
    require_once __DIR__ . '/../includes/bootstrap.php';
}

// Versionsinformationen laden
require_once __DIR__ . '/../includes/version.php';

// Error Handling
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    // === GRUNDLEGENDE STATISTIKEN ===
    $totalFilms = (int)$pdo->query("SELECT COUNT(*) FROM dvds")->fetchColumn();
    
    // Laufzeit-Statistiken
    $runtimeStats = $pdo->query("
        SELECT 
            SUM(runtime) as total_runtime,
            AVG(runtime) as avg_runtime,
            MIN(runtime) as min_runtime,
            MAX(runtime) as max_runtime
        FROM dvds 
        WHERE runtime > 0
    ")->fetch();
    
    $totalRuntime = (int)($runtimeStats['total_runtime'] ?? 0);
    $avgRuntime = round((float)($runtimeStats['avg_runtime'] ?? 0));
    
    // Umrechnung der Gesamtlaufzeit
    $totalMinutes = $totalRuntime;
    $hours = floor($totalMinutes / 60);
    $days = floor($hours / 24);
    $years = floor($days / 365);
    $months = floor(($days % 365) / 30);
    $remainingDays = $days % 30;
    
    // Jahr-Statistiken
    $yearStats = $pdo->query("
        SELECT 
            AVG(year) as avg_year,
            MIN(year) as oldest_year,
            MAX(year) as newest_year
        FROM dvds 
        WHERE year > 0
    ")->fetch();
    
    $avgYear = round((float)($yearStats['avg_year'] ?? 0));
    
    // === DETAILLIERTE STATISTIKEN ===
    
    // Collection Types
    $collectionStmt = $pdo->query("
        SELECT 
            COALESCE(collection_type, 'Unbekannt') as collection_type, 
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds), 1) as percentage
        FROM dvds 
        GROUP BY collection_type 
        ORDER BY count DESC
    ");
    $collections = $collectionStmt->fetchAll();
    
    // Altersfreigaben
    $ratingStmt = $pdo->query("
        SELECT 
            CASE 
                WHEN rating_age = 0 THEN 'Ohne Altersfreigabe'
                WHEN rating_age IS NULL THEN 'Unbekannt'
                ELSE CONCAT('FSK ', rating_age)
            END as rating_label,
            rating_age,
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds), 1) as percentage
        FROM dvds 
        GROUP BY rating_age 
        ORDER BY rating_age ASC
    ");
    $ratings = $ratingStmt->fetchAll();
    
    // Top Genres (mit besserer Aufteilung)
    $genreStmt = $pdo->query("
        SELECT 
            TRIM(genre) as genre, 
            COUNT(*) as count,
            ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM dvds WHERE genre IS NOT NULL), 1) as percentage
        FROM dvds 
        WHERE genre IS NOT NULL AND genre != '' 
        GROUP BY TRIM(genre) 
        ORDER BY count DESC 
        LIMIT 8
    ");
    $topGenres = $genreStmt->fetchAll();
    
    // Filme pro Jahr (für Timeline)
    $yearDistribution = $pdo->query("
        SELECT 
            year, 
            COUNT(*) as count 
        FROM dvds 
        WHERE year > 0 AND year >= 1970
        GROUP BY year 
        ORDER BY year ASC
    ")->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Dekaden-Analyse
    $decadeStmt = $pdo->query("
        SELECT 
            CONCAT(FLOOR(year/10)*10, 's') as decade,
            COUNT(*) as count,
            ROUND(AVG(runtime)) as avg_runtime
        FROM dvds 
        WHERE year > 0 
        GROUP BY FLOOR(year/10)*10 
        ORDER BY decade ASC
    ");
    $decades = $decadeStmt->fetchAll();
    
    // Neueste Filme
    $newestFilms = $pdo->query("
        SELECT title, year, genre, created_at 
        FROM dvds 
        ORDER BY created_at DESC 
        LIMIT 8
    ")->fetchAll();
    
    // BoxSet Statistiken
    $boxsetStats = $pdo->query("
        SELECT 
            COUNT(DISTINCT boxset_parent) as total_boxsets,
            COUNT(*) as total_boxset_items
        FROM dvds 
        WHERE boxset_parent IS NOT NULL
    ")->fetch();
    
    // Top BoxSets
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
    
} catch (Exception $e) {
    error_log('Stats page error: ' . $e->getMessage());
    $error_message = 'Fehler beim Laden der Statistiken: ' . $e->getMessage();
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
            Stand: <?= date('d.m.Y H:i') ?> Uhr | Version <?= DVDPROFILER_VERSION ?>
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
                    <small>Spanne: <?= $yearStats['oldest_year'] ?? 'N/A' ?> - <?= $yearStats['newest_year'] ?? 'N/A' ?></small>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="bi bi-collection"></i>
                </div>
                <div class="stat-content">
                    <h3><?= number_format($boxsetStats['total_boxsets'] ?? 0) ?></h3>
                    <p>BoxSet-Sammlungen</p>
                    <small><?= number_format($boxsetStats['total_boxset_items'] ?? 0) ?> Einzelfilme</small>
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
            <!-- Dekaden-Analyse -->
            <div class="info-card">
                <h3><i class="bi bi-calendar2-range"></i> Dekaden-Analyse</h3>
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
            <?php if (!empty($topBoxsets)): ?>
            <div class="info-card">
                <h3><i class="bi bi-collection"></i> Größte BoxSets</h3>
                <div class="boxset-list">
                    <?php foreach ($topBoxsets as $boxset): ?>
                        <div class="boxset-item">
                            <span class="boxset-name"><?= htmlspecialchars($boxset['boxset_name']) ?></span>
                            <span class="boxset-count"><?= $boxset['child_count'] ?> Filme</span>
                            <span class="boxset-runtime"><?= round($boxset['total_runtime']/60) ?>h</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Neueste Hinzufügungen -->
            <div class="info-card">
                <h3><i class="bi bi-plus-circle"></i> Zuletzt hinzugefügt</h3>
                <div class="recent-list">
                    <?php foreach (array_slice($newestFilms, 0, 6) as $film): ?>
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

<!-- Chart.js Skripte -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Modern Chart.js Konfiguration mit besserer Responsivität
document.addEventListener('DOMContentLoaded', function() {
    
    // Gemeinsame Chart-Optionen
    const commonOptions = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'top',
                labels: {
                    usePointStyle: true,
                    padding: 20,
                    color: '#ffffff'
                }
            }
        }
    };

    // Color Schemes
    const colors = {
        primary: ['#3498db', '#2980b9', '#1abc9c', '#16a085', '#9b59b6', '#8e44ad', '#e74c3c', '#c0392b'],
        success: ['#2ecc71', '#27ae60', '#1abc9c', '#16a085'],
        warning: ['#f39c12', '#d68910', '#e67e22', '#d35400'],
        info: ['#3498db', '#2980b9', '#5dade2', '#85c1e9']
    };

    // Collection Types Chart (Doughnut)
    const collectionCtx = document.getElementById('collectionChart');
    if (collectionCtx) {
        new Chart(collectionCtx, {
            type: 'doughnut',
            data: {
                labels: <?= json_encode(array_column($collections, 'collection_type')) ?>,
                datasets: [{
                    data: <?= json_encode(array_column($collections, 'count')) ?>,
                    backgroundColor: colors.primary,
                    borderWidth: 2,
                    borderColor: 'rgba(255, 255, 255, 0.8)'
                }]
            },
            options: {
                ...commonOptions,
                cutout: '60%',
                plugins: {
                    ...commonOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const percentage = <?= json_encode(array_column($collections, 'percentage')) ?>[context.dataIndex];
                                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Ratings Chart (Bar)
    const ratingCtx = document.getElementById('ratingChart');
    if (ratingCtx) {
        new Chart(ratingCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($ratings, 'rating_label')) ?>,
                datasets: [{
                    label: 'Anzahl Filme',
                    data: <?= json_encode(array_column($ratings, 'count')) ?>,
                    backgroundColor: colors.warning[0],
                    borderColor: colors.warning[1],
                    borderWidth: 1
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#ffffff' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    x: {
                        ticks: { color: '#ffffff' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                }
            }
        });
    }

    // Genres Chart (Horizontal Bar)
    const genreCtx = document.getElementById('genreChart');
    if (genreCtx) {
        new Chart(genreCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($topGenres, 'genre')) ?>,
                datasets: [{
                    label: 'Anzahl Filme',
                    data: <?= json_encode(array_column($topGenres, 'count')) ?>,
                    backgroundColor: colors.success[0],
                    borderColor: colors.success[1],
                    borderWidth: 1
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { color: '#ffffff' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    y: {
                        ticks: { color: '#ffffff' },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                }
            }
        });
    }

    // Year Distribution Chart (Line with Area)
    const yearCtx = document.getElementById('yearChart');
    if (yearCtx) {
        new Chart(yearCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_keys($yearDistribution)) ?>,
                datasets: [{
                    label: 'Filme pro Jahr',
                    data: <?= json_encode(array_values($yearDistribution)) ?>,
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: colors.info[0],
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: colors.info[0],
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { 
                            color: '#ffffff',
                            callback: function(value) {
                                return Number.isInteger(value) ? value : '';
                            }
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    },
                    x: {
                        ticks: { 
                            color: '#ffffff',
                            maxTicksLimit: 15
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.1)' }
                    }
                },
                plugins: {
                    ...commonOptions.plugins,
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                }
            }
        });
    }
});
</script>

<style>
/* Stats-spezifische Styles */
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

.stat-card.success::before { background: var(--gradient-success, #2ecc71); }
.stat-card.info::before { background: var(--gradient-info, #3498db); }
.stat-card.warning::before { background: var(--gradient-warning, #f39c12); }

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-glass);
}

.stat-icon {
    font-size: 3rem;
    color: var(--accent-color, #3498db);
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
}

.chart-header p {
    color: var(--text-glass);
    font-size: 0.9rem;
}

.chart-container canvas {
    max-height: 400px;
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
}

.info-card h3 {
    color: var(--text-white);
    margin-bottom: var(--space-lg);
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    border-bottom: 1px solid var(--glass-border);
    padding-bottom: var(--space-sm);
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
        max-height: 300px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .chart-legend {
        flex-direction: column;
        align-items: center;
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
}
</style>