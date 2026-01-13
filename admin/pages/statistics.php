<?php
/**
 * DVD Profiler Liste - Statistiken Dashboard
 * Zeigt detaillierte Statistiken über die Film-Sammlung
 * 
 * @package    dvdprofiler.liste
 * @version    1.4.9
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Allgemeine Statistiken laden
try {
    // Gesamt-Zahlen
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_films,
            COUNT(CASE WHEN deleted = 0 THEN 1 END) as active_films,
            COUNT(CASE WHEN deleted = 1 THEN 1 END) as deleted_films,
            SUM(CASE WHEN deleted = 0 THEN runtime ELSE 0 END) as total_runtime,
            AVG(CASE WHEN deleted = 0 THEN runtime ELSE NULL END) as avg_runtime,
            MIN(CASE WHEN deleted = 0 THEN year ELSE NULL END) as oldest_year,
            MAX(CASE WHEN deleted = 0 THEN year ELSE NULL END) as newest_year
        FROM dvds
    ");
    $generalStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Filme pro Genre
    $stmt = $pdo->query("
        SELECT 
            genre,
            COUNT(*) as count
        FROM dvds
        WHERE deleted = 0 AND genre IS NOT NULL AND genre != ''
        GROUP BY genre
        ORDER BY count DESC
        LIMIT 10
    ");
    $genreStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filme pro Jahr
    $stmt = $pdo->query("
        SELECT 
            year,
            COUNT(*) as count
        FROM dvds
        WHERE deleted = 0 AND year IS NOT NULL
        GROUP BY year
        ORDER BY year ASC
    ");
    $yearStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Collection Type Verteilung
    $stmt = $pdo->query("
        SELECT 
            collection_type,
            COUNT(*) as count
        FROM dvds
        WHERE deleted = 0 AND collection_type IS NOT NULL
        GROUP BY collection_type
        ORDER BY count DESC
    ");
    $collectionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // FSK Verteilung
    $stmt = $pdo->query("
        SELECT 
            rating_age,
            COUNT(*) as count
        FROM dvds
        WHERE deleted = 0 AND rating_age IS NOT NULL
        GROUP BY rating_age
        ORDER BY rating_age ASC
    ");
    $fskStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top 10 Genres nach Gesamtlaufzeit
    $stmt = $pdo->query("
        SELECT 
            genre,
            SUM(runtime) as total_runtime,
            COUNT(*) as count
        FROM dvds
        WHERE deleted = 0 AND genre IS NOT NULL AND runtime IS NOT NULL
        GROUP BY genre
        ORDER BY total_runtime DESC
        LIMIT 10
    ");
    $genreRuntimeStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Neueste Filme
    $stmt = $pdo->query("
        SELECT title, year, genre, created_at
        FROM dvds
        WHERE deleted = 0
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $newestFilms = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filme ohne Cover
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM dvds
        WHERE deleted = 0 AND (cover_id IS NULL OR cover_id = '')
    ");
    $noCoverCount = $stmt->fetchColumn();
    
    // Serien-Statistiken (falls Tabelle existiert)
    $seriesStats = null;
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'seasons'");
        if ($tableCheck && $tableCheck->rowCount() > 0) {
            $stmt = $pdo->query("
                SELECT 
                    COUNT(DISTINCT d.id) as total_series,
                    COUNT(DISTINCT s.id) as total_seasons,
                    COUNT(e.id) as total_episodes
                FROM dvds d
                LEFT JOIN seasons s ON s.series_id = d.id
                LEFT JOIN episodes e ON e.season_id = s.id
                WHERE d.deleted = 0 AND d.collection_type = 'Serie'
            ");
            $seriesStats = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        // Tabelle existiert nicht, ignorieren
    }
    
} catch (PDOException $e) {
    $error = 'Fehler beim Laden der Statistiken: ' . $e->getMessage();
    error_log($error);
}

// Formatiere Laufzeit in Stunden und Minuten
function formatTotalRuntime($minutes) {
    if (!$minutes) return '0 Min';
    
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    $parts = [];
    if ($hours > 0) {
        $parts[] = number_format($hours, 0, ',', '.') . ' Std';
    }
    if ($mins > 0 || empty($parts)) {
        $parts[] = $mins . ' Min';
    }
    
    return implode(' ', $parts);
}
?>

<div class="container-fluid">
    <h2 class="mb-4">
        <i class="bi bi-graph-up"></i> Statistiken
        <small class="text-muted">Übersicht deiner Sammlung</small>
    </h2>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <!-- Gesamt-Übersicht Karten -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="stat-card bg-primary">
                <div class="stat-icon">
                    <i class="bi bi-film"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($generalStats['active_films'], 0, ',', '.') ?></div>
                    <div class="stat-label">Filme/Serien</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card bg-success">
                <div class="stat-icon">
                    <i class="bi bi-clock"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= formatTotalRuntime($generalStats['total_runtime']) ?></div>
                    <div class="stat-label">Gesamtlaufzeit</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card bg-info">
                <div class="stat-icon">
                    <i class="bi bi-tags"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= count($genreStats) ?></div>
                    <div class="stat-label">Genres</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="stat-card bg-warning">
                <div class="stat-icon">
                    <i class="bi bi-calendar-range"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= $generalStats['oldest_year'] ?? '?' ?> - <?= $generalStats['newest_year'] ?? '?' ?></div>
                    <div class="stat-label">Zeitraum</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Serien-Statistiken -->
    <?php if ($seriesStats && $seriesStats['total_series'] > 0): ?>
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="stat-card bg-purple">
                <div class="stat-icon">
                    <i class="bi bi-tv"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($seriesStats['total_series'], 0, ',', '.') ?></div>
                    <div class="stat-label">Serien</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card bg-purple">
                <div class="stat-icon">
                    <i class="bi bi-collection-play"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($seriesStats['total_seasons'], 0, ',', '.') ?></div>
                    <div class="stat-label">Staffeln</div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stat-card bg-purple">
                <div class="stat-icon">
                    <i class="bi bi-play-circle"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?= number_format($seriesStats['total_episodes'], 0, ',', '.') ?></div>
                    <div class="stat-label">Episoden</div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Charts Row 1 -->
    <div class="row g-3 mb-4">
        <!-- Genre Verteilung -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-pie-chart"></i> Top 10 Genres
                </div>
                <div class="card-body">
                    <canvas id="genreChart" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Collection Type -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-collection"></i> Collection Type
                </div>
                <div class="card-body">
                    <canvas id="collectionChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row 2 -->
    <div class="row g-3 mb-4">
        <!-- Filme pro Jahr -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-bar-chart"></i> Filme pro Jahr
                </div>
                <div class="card-body">
                    <canvas id="yearChart" height="250"></canvas>
                </div>
            </div>
        </div>
        
        <!-- FSK Verteilung -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-shield-check"></i> FSK Verteilung
                </div>
                <div class="card-body">
                    <canvas id="fskChart" height="250"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabellen Row -->
    <div class="row g-3 mb-4">
        <!-- Top Genres nach Laufzeit -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-trophy"></i> Top Genres nach Laufzeit
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Genre</th>
                                <th>Filme</th>
                                <th>Laufzeit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($genreRuntimeStats as $index => $stat): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><strong><?= htmlspecialchars($stat['genre']) ?></strong></td>
                                <td><?= $stat['count'] ?> Filme</td>
                                <td><?= formatTotalRuntime($stat['total_runtime']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Neueste Hinzufügungen -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-clock-history"></i> Neueste Hinzufügungen
                </div>
                <div class="card-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Titel</th>
                                <th>Jahr</th>
                                <th>Genre</th>
                                <th>Hinzugefügt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($newestFilms as $film): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($film['title']) ?></strong></td>
                                <td><?= $film['year'] ?></td>
                                <td><?= htmlspecialchars($film['genre']) ?></td>
                                <td><?= date('d.m.Y', strtotime($film['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Zusätzliche Infos -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-calculator display-4 text-muted mb-3"></i>
                    <h5>Durchschnittliche Laufzeit</h5>
                    <h3 class="text-primary"><?= round($generalStats['avg_runtime']) ?> Min</h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-image display-4 text-muted mb-3"></i>
                    <h5>Filme ohne Cover</h5>
                    <h3 class="text-warning"><?= $noCoverCount ?></h3>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-body text-center">
                    <i class="bi bi-trash display-4 text-muted mb-3"></i>
                    <h5>Gelöschte Filme</h5>
                    <h3 class="text-danger"><?= $generalStats['deleted_films'] ?></h3>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    padding: 1.5rem;
    border-radius: 12px;
    color: white;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 3rem;
    opacity: 0.8;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    line-height: 1;
    margin-bottom: 0.25rem;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.bg-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
.bg-success { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
.bg-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
.bg-warning { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
.bg-purple { background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%); }

.card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
}

.card-header {
    background: #f8f9fa;
    border-bottom: 2px solid #e9ecef;
    font-weight: 600;
    padding: 1rem 1.25rem;
}

.table th {
    font-weight: 600;
    color: #495057;
    border-bottom: 2px solid #dee2e6;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Chart.js Konfiguration
Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif';
Chart.defaults.color = '#495057';

// Genre Chart
const genreCtx = document.getElementById('genreChart').getContext('2d');
new Chart(genreCtx, {
    type: 'doughnut',
    data: {
        labels: <?= json_encode(array_column($genreStats, 'genre')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($genreStats, 'count')) ?>,
            backgroundColor: [
                '#667eea', '#764ba2', '#f093fb', '#f5576c',
                '#4facfe', '#00f2fe', '#fa709a', '#fee140',
                '#a8edea', '#fed6e3'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'right',
                labels: { padding: 15, font: { size: 12 } }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed + ' Filme';
                    }
                }
            }
        }
    }
});

// Collection Type Chart
const collectionCtx = document.getElementById('collectionChart').getContext('2d');
new Chart(collectionCtx, {
    type: 'pie',
    data: {
        labels: <?= json_encode(array_column($collectionStats, 'collection_type')) ?>,
        datasets: [{
            data: <?= json_encode(array_column($collectionStats, 'count')) ?>,
            backgroundColor: ['#667eea', '#f5576c', '#4facfe'],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { padding: 15, font: { size: 13 } }
            }
        }
    }
});

// Jahr Chart
const yearCtx = document.getElementById('yearChart').getContext('2d');
new Chart(yearCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($yearStats, 'year')) ?>,
        datasets: [{
            label: 'Anzahl Filme',
            data: <?= json_encode(array_column($yearStats, 'count')) ?>,
            backgroundColor: 'rgba(102, 126, 234, 0.8)',
            borderColor: '#667eea',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        },
        plugins: {
            legend: { display: false }
        }
    }
});

// FSK Chart
const fskCtx = document.getElementById('fskChart').getContext('2d');
new Chart(fskCtx, {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_map(function($v) { return 'FSK ' . $v; }, array_column($fskStats, 'rating_age'))) ?>,
        datasets: [{
            label: 'Anzahl',
            data: <?= json_encode(array_column($fskStats, 'count')) ?>,
            backgroundColor: [
                '#28a745', '#17a2b8', '#ffc107', '#fd7e14', '#dc3545'
            ],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: {
            x: {
                beginAtZero: true,
                ticks: { stepSize: 1 }
            }
        },
        plugins: {
            legend: { display: false }
        }
    }
});
</script>