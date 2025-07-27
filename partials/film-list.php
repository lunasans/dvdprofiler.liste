<?php
/**
 * Partials/film-list.php - Core-System kompatibel
 * FIXED: Funktioniert mit dem neuen Core-System
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+ - WORKING VERSION
 */

declare(strict_types=1);

// Core-System sollte bereits geladen sein
try {
    $app = \DVDProfiler\Core\Application::getInstance();
    $database = $app->getDatabase();
    
    // Legacy $pdo für Kompatibilität
    if (!isset($pdo)) {
        $pdo = $database->getPDO();
    }
} catch (Exception $e) {
    error_log('Film-list error: ' . $e->getMessage());
    // Fallback falls Core-System nicht verfügbar
    if (!isset($pdo)) {
        die('Database connection failed');
    }
}

// ===== HELPER FUNCTIONS =====

function buildQuery(array $updates = []): string {
    $current = $_GET;
    foreach ($updates as $key => $value) {
        if ($value === '' || $value === null) {
            unset($current[$key]);
        } else {
            $current[$key] = $value;
        }
    }
    return http_build_query($current);
}

function findCoverImage(?string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string {
    if (empty($coverId)) return $fallback;
    $extensions = ['.jpg', '.jpeg', '.png'];
    foreach ($extensions as $ext) {
        $file = "{$folder}/{$coverId}{$suffix}{$ext}";
        if (file_exists($file)) {
            return $file;
        }
    }
    return $fallback;
}

function formatRuntime(?int $minutes): string {
    if (!$minutes || $minutes <= 0) return '';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}

function renderFilmCard(array $film): string {
    // Sichere Werte extrahieren
    $title = htmlspecialchars($film['title'] ?? 'Unbekannt');
    $year = $film['year'] ? (int)$film['year'] : 0;
    $genre = htmlspecialchars($film['genre'] ?? '');
    $id = (int)($film['id'] ?? 0);
    $runtime = $film['runtime'] ? (int)$film['runtime'] : 0;
    $coverId = $film['cover_id'] ?? '';
    
    // Cover-Bild finden
    $coverImage = findCoverImage($coverId);
    
    // HTML generieren
    return '<article class="film-card" data-film-id="' . $id . '">
        <div class="film-cover">
            <img src="' . htmlspecialchars($coverImage) . '" 
                 alt="' . $title . ' Cover" 
                 loading="lazy"
                 onerror="this.src=\'cover/placeholder.png\'">
        </div>
        <div class="film-info">
            <h3 class="film-title">
                <a href="film-fragment.php?id=' . $id . '" class="film-link">
                    ' . $title . '
                </a>
            </h3>
            <div class="film-meta">
                ' . ($year > 0 ? '<span class="film-year">' . $year . '</span>' : '') . '
                ' . ($runtime > 0 ? '<span class="film-runtime">' . formatRuntime($runtime) . '</span>' : '') . '
            </div>
            ' . ($genre ? '<div class="film-genre">' . $genre . '</div>' : '') . '
        </div>
    </article>';
}

// ===== PARAMETER PROCESSING =====

// Pagination
$page = max(1, (int)($_GET['seite'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Search & Filter
$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');

// ===== DATABASE QUERIES =====

try {
    // WHERE-Bedingungen aufbauen
    $where = ['1=1'];
    $params = [];
    
    if (!empty($search)) {
        $where[] = "title LIKE ?";
        $params[] = "%{$search}%";
    }
    
    if (!empty($type)) {
        $where[] = "collection_type = ?";
        $params[] = $type;
    }
    
    $whereSql = 'WHERE ' . implode(' AND ', $where);
    
    // Gesamtanzahl
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dvds $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($total / $perPage);
    
    // Filme laden
    $sql = "SELECT * FROM dvds $whereSql ORDER BY title LIMIT ? OFFSET ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [$perPage, $offset]));
    $films = $stmt->fetchAll();
    
    // Collection Types für Filter
    $typesStmt = $pdo->query("SELECT DISTINCT collection_type FROM dvds WHERE collection_type IS NOT NULL AND collection_type != '' ORDER BY collection_type");
    $types = $typesStmt ? $typesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    
} catch (Exception $e) {
    error_log('Film query error: ' . $e->getMessage());
    $films = [];
    $total = 0;
    $totalPages = 0;
    $types = [];
}
?>

<!-- Search & Filter Section -->
<section class="search-section">
    <form method="GET" class="search-form">
        <div class="search-row">
            <div class="search-field">
                <input type="text" 
                       name="q" 
                       value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Film suchen..." 
                       class="search-input">
            </div>
            <button type="submit" class="search-btn">
                <i class="bi bi-search"></i>
                Suchen
            </button>
        </div>
        
        <?php if (!empty($types)): ?>
        <div class="filter-row">
            <select name="type" class="filter-select" onchange="this.form.submit()">
                <option value="">Alle Typen</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= $type === $t ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
    </form>
</section>

<!-- Results Header -->
<section class="results-header">
    <div class="results-info">
        <h2>
            <i class="bi bi-film"></i>
            <?= number_format($total) ?> <?= $total === 1 ? 'Film' : 'Filme' ?>
            <?php if (!empty($search)): ?>
                für "<?= htmlspecialchars($search) ?>"
            <?php endif; ?>
            <?php if (!empty($type)): ?>
                (<?= htmlspecialchars($type) ?>)
            <?php endif; ?>
        </h2>
        
        <?php if ($total > 0): ?>
            <span class="page-info">
                Seite <?= $page ?> von <?= $totalPages ?>
            </span>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($search) || !empty($type)): ?>
        <div class="active-filters">
            <span class="filter-label">Aktive Filter:</span>
            <?php if (!empty($search)): ?>
                <span class="filter-tag">
                    Suche: "<?= htmlspecialchars($search) ?>"
                    <a href="?<?= buildQuery(['q' => '']) ?>" class="remove-filter">×</a>
                </span>
            <?php endif; ?>
            <?php if (!empty($type)): ?>
                <span class="filter-tag">
                    Typ: <?= htmlspecialchars($type) ?>
                    <a href="?<?= buildQuery(['type' => '']) ?>" class="remove-filter">×</a>
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<!-- Film Grid -->
<section class="film-list-section">
    <div class="film-grid">
        <?php if (empty($films)): ?>
            <div class="empty-state">
                <i class="bi bi-film"></i>
                <h3>Keine Filme gefunden</h3>
                <p>
                    <?php if (!empty($search)): ?>
                        Keine Filme gefunden für "<strong><?= htmlspecialchars($search) ?></strong>".
                    <?php elseif (!empty($type)): ?>
                        Keine Filme vom Typ "<strong><?= htmlspecialchars($type) ?></strong>" gefunden.
                    <?php else: ?>
                        Noch keine Filme in der Sammlung vorhanden.
                    <?php endif; ?>
                </p>
                
                <?php if (!empty($search) || !empty($type)): ?>
                    <a href="?" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i>
                        Alle Filme anzeigen
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($films as $film): ?>
                <?= renderFilmCard($film) ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<nav class="pagination-nav">
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?<?= buildQuery(['seite' => $page - 1]) ?>" class="page-link">
                <i class="bi bi-chevron-left"></i> Zurück
            </a>
        <?php endif; ?>
        
        <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        
        for ($i = $start; $i <= $end; $i++):
        ?>
            <?php if ($i === $page): ?>
                <span class="page-link current"><?= $i ?></span>
            <?php else: ?>
                <a href="?<?= buildQuery(['seite' => $i]) ?>" class="page-link"><?= $i ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $totalPages): ?>
            <a href="?<?= buildQuery(['seite' => $page + 1]) ?>" class="page-link">
                Weiter <i class="bi bi-chevron-right"></i>
            </a>
        <?php endif; ?>
    </div>
    
    <div class="pagination-info">
        Seite <?= $page ?> von <?= $totalPages ?> • <?= number_format($total) ?> Filme gesamt
    </div>
</nav>
<?php endif; ?>

<style>
/* FILM LIST STYLES */
.search-section {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    backdrop-filter: blur(10px);
}

.search-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.search-row {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.search-field {
    flex: 1;
}

.search-input {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    font-size: 1rem;
}

.search-input::placeholder {
    color: rgba(255, 255, 255, 0.6);
}

.search-btn {
    padding: 0.75rem 1.5rem;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-weight: 500;
    transition: background-color 0.3s;
}

.search-btn:hover {
    background: #2980b9;
}

.filter-row {
    display: flex;
    gap: 1rem;
}

.filter-select {
    padding: 0.5rem 1rem;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    cursor: pointer;
}

.results-header {
    margin-bottom: 2rem;
}

.results-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.results-info h2 {
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.page-info {
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
}

.filter-label {
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.filter-tag {
    background: rgba(52, 152, 219, 0.2);
    color: #3498db;
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-size: 0.85rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.remove-filter {
    color: #e74c3c;
    text-decoration: none;
    font-weight: bold;
    margin-left: 0.25rem;
}

.remove-filter:hover {
    color: #c0392b;
}

.film-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.film-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.3s, box-shadow 0.3s;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.film-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
}

.film-cover {
    aspect-ratio: 2/3;
    overflow: hidden;
}

.film-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s;
}

.film-card:hover .film-cover img {
    transform: scale(1.05);
}

.film-info {
    padding: 1rem;
}

.film-title {
    margin: 0 0 0.5rem 0;
    font-size: 1rem;
    font-weight: 600;
}

.film-link {
    color: #fff;
    text-decoration: none;
    transition: color 0.3s;
}

.film-link:hover {
    color: #3498db;
}

.film-meta {
    display: flex;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    color: rgba(255, 255, 255, 0.7);
}

.film-genre {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.6);
    background: rgba(255, 255, 255, 0.1);
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    text-align: center;
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem 1rem;
    color: rgba(255, 255, 255, 0.8);
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 1rem;
    color: rgba(255, 255, 255, 0.5);
}

.empty-state h3 {
    margin-bottom: 1rem;
    color: #fff;
}

.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.btn-primary {
    background: #3498db;
    color: white;
}

.btn-primary:hover {
    background: #2980b9;
    transform: translateY(-2px);
}

.pagination-nav {
    margin-top: 2rem;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.page-link {
    padding: 0.5rem 1rem;
    background: rgba(255, 255, 255, 0.1);
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.page-link:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.page-link.current {
    background: #3498db;
    cursor: default;
}

.page-link.current:hover {
    transform: none;
}

.pagination-info {
    text-align: center;
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 768px) {
    .film-grid {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .search-row {
        flex-direction: column;
    }
    
    .results-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }
    
    .pagination {
        flex-wrap: wrap;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 Film-List geladen:', <?= count($films) ?>, 'Filme von', <?= $total ?>);
    
    // Film-Links enhancen für Detail-Panel
    const filmLinks = document.querySelectorAll('.film-link');
    filmLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Nur bei Home-Page Detail-Panel verwenden
            if (window.location.search === '' || window.location.search === '?page=home') {
                e.preventDefault();
                
                const url = this.href;
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const detailContainer = document.getElementById('detail-container');
                        if (detailContainer) {
                            detailContainer.innerHTML = html;
                            detailContainer.scrollIntoView({ behavior: 'smooth' });
                        }
                    })
                    .catch(error => {
                        console.error('Error loading film details:', error);
                        window.location.href = url;
                    });
            }
        });
    });
});
</script>