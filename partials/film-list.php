<?php
// partials/film-list.php - BoxSet mit Overlay-Modal

// Bootstrap laden für Datenbankverbindung
if (!isset($pdo)) {
    require_once __DIR__ . '/../includes/bootstrap.php';
}

// BuildQuery-Funktion für Pagination
function buildQuery($params = []) {
    $currentParams = $_GET;
    foreach ($params as $key => $value) {
        if ($value === '') {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $value;
        }
    }
    return http_build_query($currentParams);
}

// Einfache, sichere Datenabfrage
$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['seite'] ?? 1));

// Filme pro Seite aus Settings laden (Standard: 20)
$perPage = (int)getSetting('items_per_page', '20');

// Validierung (5-100)
if ($perPage < 5) $perPage = 5;
if ($perPage > 100) $perPage = 100;

$offset = ($page - 1) * $perPage;

try {
    // Collection Types laden
    $typesStmt = $pdo->query("SELECT DISTINCT collection_type FROM dvds WHERE collection_type IS NOT NULL AND deleted = 0 ORDER BY collection_type");
    $types = $typesStmt ? $typesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    
    // WHERE-Filter aufbauen
    $where = ['1=1', 'deleted = 0']; // Gelöschte Filme ausschließen
    $params = [];
    
    if ($search !== '') {
        $where[] = "title LIKE :search";
        $params['search'] = "%{$search}%";
    }
    if ($type !== '') {
        $where[] = "collection_type = :type";
        $params['type'] = $type;
    }
    
    $whereSql = 'WHERE ' . implode(' AND ', $where);
    
    // WICHTIG: Filtere Children raus! (boxset_parent IS NULL = Parents + Einzelfilme)
    $whereSql .= ' AND boxset_parent IS NULL';
    
    // Gesamtanzahl (NUR Parents und Einzelfilme - KEINE Children!)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dvds $whereSql");
    if ($countStmt) {
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($total / $perPage);
    } else {
        $total = 0;
        $totalPages = 0;
    }
    
    // Filme laden MIT BoxSet-Info (NUR Parents und Einzelfilme - KEINE Children!)
    $sql = "SELECT d.*, 
                   (SELECT COUNT(*) FROM dvds WHERE boxset_parent = d.id AND deleted = 0) as children_count
            FROM dvds d 
            $whereSql
            ORDER BY title 
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    if ($stmt) {
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $films = $stmt->fetchAll();
    } else {
        $films = [];
    }
    
} catch (Exception $e) {
    error_log('Film-list error: ' . $e->getMessage());
    $types = [];
    $films = [];
    $total = 0;
    $totalPages = 0;
}

// Helper: Film Card mit BoxSet Badge
// Helper: Film Card mit Badge (Grid) und Sternen (List)
function renderFilmCard(array $dvd): string {
    $title = htmlspecialchars($dvd['title'] ?? 'Unbekannt');
    $year = (int)($dvd['year'] ?? 0);
    $genre = htmlspecialchars($dvd['genre'] ?? 'Unbekannt');
    $id = (int)($dvd['id'] ?? 0);
    $cover = 'cover/placeholder.png';
    
    if (!empty($dvd['cover_id'])) {
        $extensions = ['.jpg', '.jpeg', '.png'];
        foreach ($extensions as $ext) {
            $file = __DIR__ . "/../cover/{$dvd['cover_id']}f{$ext}";
            if (file_exists($file)) {
                $cover = "cover/{$dvd['cover_id']}f{$ext}";
                break;
            }
        }
    }
    
    $childrenCount = (int)($dvd['children_count'] ?? 0);
    $isBoxSet = $childrenCount > 0;
    
    $ratingBadge = '';
    $tmdbStarsHtml = '';
    
    if (getSetting('tmdb_show_ratings_on_cards', '1') == '1' && !empty(getSetting('tmdb_api_key', ''))) {
        $ratings = getFilmRatings($dvd['title'], $year);
        if ($ratings && isset($ratings['tmdb_rating'])) {
            $rating = $ratings['tmdb_rating'];
            $votes = $ratings['tmdb_votes'] ?? 0;
            
            if ($rating >= 8) {
                $color = '#4caf50';
            } elseif ($rating >= 6) {
                $color = '#ff9800';
            } else {
                $color = '#f44336';
            }
            
            $ratingFormatted = number_format($rating, 1);
            $ratingBadge = <<<HTML
<div class="tmdb-rating-badge" style="background-color: {$color};">
    <i class="bi bi-star-fill"></i>
    <span>{$ratingFormatted}</span>
</div>
HTML;
            
            $starsRating = $rating / 2;
            $fullStars = floor($starsRating);
            $hasHalfStar = ($starsRating - $fullStars) >= 0.3;
            
            $starsHtml = '';
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $fullStars) {
                    $starsHtml .= '<i class="bi bi-star-fill" style="color: ' . $color . ';"></i>';
                } elseif ($i == $fullStars + 1 && $hasHalfStar) {
                    $starsHtml .= '<i class="bi bi-star-half" style="color: ' . $color . ';"></i>';
                } else {
                    $starsHtml .= '<i class="bi bi-star" style="color: rgba(255,255,255,0.2);"></i>';
                }
            }
            
            $votesFormatted = number_format($votes);
            $tmdbStarsHtml = <<<HTML
<div class="tmdb-rating-stars">
    <span class="tmdb-label">TMDb:</span>
    <div class="tmdb-stars">{$starsHtml}</div>
    <span class="tmdb-score" style="color: {$color};">{$ratingFormatted}</span>
    <span class="tmdb-votes">({$votesFormatted})</span>
</div>
HTML;
        }
    }
    
    $badge = '';
    if ($isBoxSet) {
        $badge = <<<HTML
<div class="boxset-badge" onclick="event.stopPropagation(); openBoxSetModal(event, {$id});">
    <i class="bi bi-collection-play"></i>
    <span>{$childrenCount}</span>
</div>
HTML;
    }
    
    $boxsetClass = $isBoxSet ? ' has-boxset' : '';
    $coverEscaped = htmlspecialchars($cover);
    
    return <<<HTML
<div class="dvd{$boxsetClass}" data-dvd-id="{$id}" data-children-count="{$childrenCount}">
  <div class="cover-area">
    <img src="{$coverEscaped}" alt="Cover">
    {$ratingBadge}
    {$badge}
  </div>
  <div class="dvd-details">
    <div class="film-info">
      <h2><a href="#" class="toggle-detail" data-id="{$id}">{$title} ({$year})</a></h2>
      <p class="genre-info"><strong>Genre:</strong> {$genre}</p>
    </div>
    {$tmdbStarsHtml}
  </div>
</div>
HTML;
}
?>

<!-- ============================================================ -->
<!-- VERSION TEST BANNER - SICHTBAR AUF SEITE -->
<!-- VERSION: V3.1 - OHNE ALERT -->
<!-- ============================================================ -->
<div id="version-test-banner" style="
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    background: #ff0000;
    color: #ffffff;
    padding: 20px;
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    z-index: 999999;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
">
    ✅ VERSION: ROBUST-POSITION-V3.2 GELADEN! ✅
    <div style="font-size: 16px; margin-top: 10px;">
        SYNTAX-FEHLER BEHOBEN! Funktion sollte jetzt funktionieren!
    </div>
    <button onclick="document.getElementById('version-test-banner').remove();" style="
        margin-top: 10px;
        padding: 10px 20px;
        background: white;
        color: black;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 14px;
    ">
        Banner schließen
    </button>
</div>

<!-- Tabs für Collection Types -->
<div class="tabs-wrapper">
  <ul class="tabs">
    <li class="<?= $type === '' ? 'active' : '' ?>">
      <a href="?<?= buildQuery(['type' => '', 'seite' => 1]) ?>">Alle</a>
    </li>
    <?php foreach ($types as $t): ?>
      <li class="<?= $type === $t ? 'active' : '' ?>">
        <a href="?<?= buildQuery(['type' => $t, 'seite' => 1]) ?>"><?= htmlspecialchars($t) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
  
  <!-- View Mode Toggle -->
  <div class="view-toggle">
    <button class="view-btn" data-mode="grid" title="Kachel-Ansicht">
      <i class="bi bi-grid-3x3-gap"></i>
    </button>
    <button class="view-btn" data-mode="list" title="Listen-Ansicht">
      <i class="bi bi-list-ul"></i>
    </button>
  </div>
</div>

<!-- Film-Liste -->
<div class="film-list">
  <?php if (empty($films)): ?>
    <div class="empty-state">
      <i class="bi bi-film"></i>
      <h3>Keine Filme gefunden</h3>
      <p>
        <?php if (!empty($search)): ?>
          Keine Filme gefunden für "<?= htmlspecialchars($search) ?>".
        <?php elseif (!empty($type)): ?>
          Keine Filme im Genre "<?= htmlspecialchars($type) ?>" gefunden.
        <?php else: ?>
          Noch keine Filme in der Sammlung vorhanden.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <?php foreach ($films as $film): ?>
      <?= renderFilmCard($film) ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <nav class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= buildQuery(['seite' => 1]) ?>">« Erste</a>
      <a href="?<?= buildQuery(['seite' => $page - 1]) ?>">‹ Zurück</a>
    <?php endif; ?>
    
    <?php
    $window = 2;
    $start = max(1, $page - $window);
    $end = min($totalPages, $page + $window);
    
    if ($start > 1): ?>
      <a href="?<?= buildQuery(['seite' => 1]) ?>">1</a>
      <?php if ($start > 2): ?>
        <span class="dots">...</span>
      <?php endif; ?>
    <?php endif; ?>
    
    <?php for ($i = $start; $i <= $end; $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?<?= buildQuery(['seite' => $i]) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($end < $totalPages): ?>
      <?php if ($end < $totalPages - 1): ?>
        <span class="dots">...</span>
      <?php endif; ?>
      <a href="?<?= buildQuery(['seite' => $totalPages]) ?>"><?= $totalPages ?></a>
    <?php endif; ?>
    
    <?php if ($page < $totalPages): ?>
      <a href="?<?= buildQuery(['seite' => $page + 1]) ?>">Weiter ›</a>
      <a href="?<?= buildQuery(['seite' => $totalPages]) ?>">Letzte »</a>
    <?php endif; ?>
  </nav>
  
  <div class="pagination-info">
    Seite <?= $page ?> von <?= $totalPages ?> (<?= $total ?> Filme insgesamt)
  </div>
<?php endif; ?>

<!-- BoxSet Overlay Modal -->
<div id="boxsetModal" class="boxset-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">
                <i class="bi bi-collection-play"></i>
                <span>BoxSet</span>
            </h2>
            <button class="modal-close" onclick="closeBoxSetModal()" aria-label="Schließen">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="modal-body" id="modalBody">
            <div class="loading">
                <i class="bi bi-hourglass-split"></i>
                Lade Filme...
            </div>
        </div>
    </div>
</div>

<style>
/* BoxSet Badge */
.boxset-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    background: linear-gradient(135deg, var(--accent-primary, #667eea) 0%, var(--accent-hover, #764ba2) 100%);
    color: white;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 600;
    backdrop-filter: blur(10px);
    box-shadow: 0 2px 12px rgba(102, 126, 234, 0.4);
    cursor: pointer;
    transition: all 0.3s ease;
}

.boxset-badge:hover {
    transform: scale(1.1);
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.6);
}

.boxset-badge i {
    font-size: 1rem;
}

.dvd.has-boxset {
    position: relative;
}

.dvd.has-boxset:hover {
    border-color: var(--accent-primary, #667eea);
    box-shadow: 0 4px 24px rgba(102, 126, 234, 0.3);
}

/* BoxSet Modal - Fixed Position über allem */
.boxset-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 9999;
    display: none;
    padding: 0;
    overflow-y: auto;
    pointer-events: none; /* Ermöglicht Click-Through auf Film-Liste */
}

.boxset-modal.show {
    display: block;
}

.modal-content {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 2;
    width: 90%;
    max-width: 800px;
    max-height: 80vh;
    background: var(--bg-secondary, #1a1a2e);
    border: 2px solid var(--accent-primary, #667eea);
    border-radius: 16px;
    overflow-y: auto;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(102, 126, 234, 0.3);
    animation: zoomIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transition: box-shadow 0.3s ease, opacity 0.2s ease;
    pointer-events: all; /* Modal selbst ist interaktiv */
    /* Transform wird via JavaScript gesetzt */
}

.modal-content:active {
    box-shadow: 0 30px 100px rgba(0, 0, 0, 0.9), 0 0 0 2px var(--accent-primary, #667eea);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    background: var(--bg-tertiary, rgba(255, 255, 255, 0.03));
    cursor: grab;
    user-select: none;
}

.modal-header:active {
    cursor: grabbing;
}

.modal-header h2 {
    display: flex;
    align-items: center;
    gap: 12px;
    margin: 0;
    font-size: 1.3rem;
    color: var(--text-primary, #e4e4e7);
    pointer-events: none;
}

.modal-header i.drag-handle {
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
    font-size: 1.2rem;
    animation: drag-hint 2s ease-in-out infinite;
}

@keyframes drag-hint {
    0%, 100% { transform: translateX(0); }
    50% { transform: translateX(3px); }
}

.modal-header i:not(.drag-handle) {
    color: var(--accent-primary, #667eea);
    font-size: 1.5rem;
}

.modal-close {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 50%;
    color: var(--text-primary, #e4e4e7);
    font-size: 1.1rem;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modal-close:hover {
    background: rgba(255, 71, 87, 0.2);
    color: #ff4757;
    transform: rotate(90deg);
}

.modal-body {
    padding: 24px;
    max-height: 70vh;
    overflow-y: auto;
    overflow-x: hidden;
}

.modal-films-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
    gap: 16px;
}

.loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 60px 20px;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
    font-size: 1.1rem;
}

.loading i {
    font-size: 2rem;
    animation: spin 2s linear infinite;
}

/* TMDb Rating Badge im Modal - mit Farbcodierung */
.tmdb-rating-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    /* background wird via inline-style gesetzt (farbcodiert) */
    backdrop-filter: blur(10px);
    color: white;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.tmdb-rating-badge i {
    font-size: 0.8rem;
    color: white;
}

.tmdb-rating-badge span {
    color: white;
}

@keyframes zoomIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
    .modal-films-grid {
        grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
        gap: 12px;
    }
    
    .modal-header {
        padding: 16px 20px;
    }
    
    .modal-header h2 {
        font-size: 1.1rem;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .boxset-modal {
        padding: 10px;
    }
}
/* TMDb Rating Badge */
.tmdb-rating-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    padding: 4px 8px;
    border-radius: 4px;
    color: white;
    font-size: 0.85rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 4px;
    z-index: 10;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}

.tmdb-rating-badge i {
    font-size: 0.75rem;
}

/* BoxSet Badge rechts oben platzieren wenn Rating links ist */
.cover-area .boxset-badge {
    top: 8px;
    right: 8px;
    left: auto;
}

</style>

<script>
// ============================================================
// VERSION: ROBUST-POSITION-V3.2 - 2025-01-24
// SYNTAX-FEHLER BEHOBEN - Duplizierten Code entfernt!
// ============================================================

console.log('🔴 START: film-list.php lädt...');

// BoxSet Modal Functions
let isDragging = false;
let currentX;
let currentY;
let initialX;
let initialY;
let xOffset = 0;
let yOffset = 0;

console.log('🔴 SCHRITT 1: Variablen definiert');

// WICHTIG: Funktion SOFORT definieren!
function openBoxSetModal(event, parentId) {
    console.log('🎯 openBoxSetModal aufgerufen!', { event, parentId });
    
    const modal = document.getElementById('boxsetModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    const modalContent = modal.querySelector('.modal-content');
    
    // Mausposition speichern
    const clickX = event.clientX;
    const clickY = event.clientY;
    
    // Modal versteckt zeigen (für Größenmessung)
    modal.classList.add('show');
    modalContent.style.opacity = '0';
    modalContent.style.visibility = 'hidden';
    
    // Lade BoxSet-Daten via AJAX
    fetch(`partials/boxset-children.php?parent_id=${parentId}`)
        .then(response => response.json())
        .then(data => {
            // Update Title
            modalTitle.innerHTML = `
                <i class="bi bi-arrows-move drag-handle"></i>
                <span>${data.parent_title} (${data.children.length} Filme)</span>
            `;
            
            // Render Children
            let html = '<div class="modal-films-grid">';
            data.children.forEach(film => {
                html += renderModalFilmCard(film);
            });
            html += '</div>';
            
            modalBody.innerHTML = html;
            
            // Warte kurz, dann positioniere korrekt
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    // Messe ECHTE Modal-Größe
                    const modalWidth = modalContent.offsetWidth;
                    const modalHeight = modalContent.offsetHeight;
                    
                    // Viewport-Dimensionen
                    const viewportWidth = window.innerWidth;
                    const viewportHeight = window.innerHeight;
                    
                    // Berechne ideale Position (50px Offset vom Cursor)
                    let modalX = clickX - 50;
                    let modalY = clickY - 50;
                    
                    // WICHTIG: Stelle sicher, dass Modal KOMPLETT im Viewport ist
                    // Minimale Position (20px Rand)
                    const minX = 20;
                    const minY = 20;
                    
                    // Maximale Position (Modal darf nicht rausragen)
                    const maxX = viewportWidth - modalWidth - 20;
                    const maxY = viewportHeight - modalHeight - 20;
                    
                    // Begrenze X-Position
                    if (modalX < minX) {
                        modalX = minX;
                    } else if (modalX > maxX) {
                        modalX = maxX;
                    }
                    
                    // Begrenze Y-Position
                    if (modalY < minY) {
                        modalY = minY;
                    } else if (modalY > maxY) {
                        modalY = maxY;
                    }
                    
                    // Falls Modal größer als Viewport, zentriere es
                    if (modalWidth > viewportWidth - 40) {
                        modalX = 20;
                    }
                    if (modalHeight > viewportHeight - 40) {
                        modalY = 20;
                    }
                    
                    // Setze finale Position
                    xOffset = modalX;
                    yOffset = modalY;
                    modalContent.style.transform = `translate(${modalX}px, ${modalY}px)`;
                    
                    // Modal sichtbar machen
                    modalContent.style.visibility = 'visible';
                    modalContent.style.opacity = '1';
                });
            });
        })
        .catch(error => {
            console.error('BoxSet load error:', error);
            modalBody.innerHTML = '<div class="loading">❌ Fehler beim Laden</div>';
            modalContent.style.visibility = 'visible';
            modalContent.style.opacity = '1';
        });
}

function closeBoxSetModal() {
    const modal = document.getElementById('boxsetModal');
    const modalContent = modal.querySelector('.modal-content');
    
    // Modal ausblenden
    modal.classList.remove('show');
    
    // Reset für nächstes Öffnen
    xOffset = 0;
    yOffset = 0;
    modalContent.style.transform = 'translate(0, 0)';
    modalContent.style.visibility = 'visible';
    modalContent.style.opacity = '1';
}

function renderModalFilmCard(film) {
    // TMDb Rating Badge (falls vorhanden) - mit Farbcodierung
    let ratingBadge = '';
    if (film.tmdb_rating && film.tmdb_rating > 0) {
        // Farbcodierung wie in Haupt-Liste
        let color;
        if (film.tmdb_rating >= 8) {
            color = '#4caf50';  // Grün
        } else if (film.tmdb_rating >= 6) {
            color = '#ff9800';  // Orange
        } else {
            color = '#f44336';  // Rot
        }
        
        // Rating mit 1 Dezimalstelle
        const ratingFormatted = parseFloat(film.tmdb_rating).toFixed(1);
        
        ratingBadge = `
            <div class="tmdb-rating-badge" style="background-color: ${color};">
                <i class="bi bi-star-fill"></i>
                <span>${ratingFormatted}</span>
            </div>
        `;
    }
    
    return `
        <div class="dvd" data-dvd-id="${film.id}">
            <div class="cover-area">
                <img src="${film.cover}" alt="Cover">
                ${ratingBadge}
            </div>
            <div class="dvd-details">
                <h2><a href="#" class="toggle-detail" data-id="${film.id}">${film.title} (${film.year})</a></h2>
                <p><strong>Genre:</strong> ${film.genre}</p>
            </div>
        </div>
    `;
}

// Drag Functionality - Als globale Funktion damit sie nach AJAX neu initialisiert werden kann
function initBoxSetModalDrag() {
    const modal = document.getElementById('boxsetModal');
    if (!modal) return;
    
    const modalContent = modal.querySelector('.modal-content');
    if (!modalContent) return;
    
    // Entferne alte Event-Listener falls vorhanden
    modal.removeEventListener('mousedown', dragStart);
    modal.removeEventListener('mousemove', drag);
    modal.removeEventListener('mouseup', dragEnd);
    modal.removeEventListener('mouseleave', dragEnd);
    modal.removeEventListener('touchstart', dragStart);
    modal.removeEventListener('touchmove', drag);
    modal.removeEventListener('touchend', dragEnd);
    
    // Füge neue Event-Listener hinzu
    modal.addEventListener('mousedown', dragStart);
    modal.addEventListener('mousemove', drag);
    modal.addEventListener('mouseup', dragEnd);
    modal.addEventListener('mouseleave', dragEnd);
    
    // Touch Events für Mobile
    modal.addEventListener('touchstart', dragStart);
    modal.addEventListener('touchmove', drag);
    modal.addEventListener('touchend', dragEnd);
    
    function dragStart(e) {
        const target = e.target;
        
        // Nur Header oder drag-handle ist draggable
        if (!target.closest('.modal-header') && !target.classList.contains('drag-handle')) {
            return;
        }
        
        // Nicht draggable wenn Close-Button geklickt
        if (target.closest('.modal-close')) {
            return;
        }
        
        isDragging = true;
        modalContent.style.cursor = 'grabbing';
        
        if (e.type === 'touchstart') {
            initialX = e.touches[0].clientX - xOffset;
            initialY = e.touches[0].clientY - yOffset;
        } else {
            initialX = e.clientX - xOffset;
            initialY = e.clientY - yOffset;
        }
    }
    
    function drag(e) {
        if (!isDragging) return;
        
        e.preventDefault();
        
        if (e.type === 'touchmove') {
            currentX = e.touches[0].clientX - initialX;
            currentY = e.touches[0].clientY - initialY;
        } else {
            currentX = e.clientX - initialX;
            currentY = e.clientY - initialY;
        }
        
        xOffset = currentX;
        yOffset = currentY;
        
        setTranslate(currentX, currentY, modalContent);
    }
    
    function dragEnd(e) {
        if (!isDragging) return;
        
        isDragging = false;
        modalContent.style.cursor = 'default';
        
        initialX = currentX;
        initialY = currentY;
    }
    
    function setTranslate(xPos, yPos, el) {
        el.style.transform = `translate(${xPos}px, ${yPos}px)`;
    }
}

// Initial beim Laden initialisieren
document.addEventListener('DOMContentLoaded', initBoxSetModalDrag);

// Nach jedem AJAX-Reload wieder initialisieren
if (typeof window.reinitBoxSetModal === 'undefined') {
    window.reinitBoxSetModal = function() {
        initBoxSetModalDrag();
    };
}

// ESC-Key schließt Modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeBoxSetModal();
    }
});

// ERFOLG: Alle Funktionen definiert!
console.log('✅ ERFOLG: Alle Funktionen geladen!');
console.log('✅ openBoxSetModal ist definiert:', typeof openBoxSetModal);
console.log('✅ VERSION: ROBUST-POSITION-V3.2 - SYNTAX-FEHLER BEHOBEN!');
</script>
<style>
.tmdb-rating-badge {
    position: absolute;
    top: 8px;
    right: 8px;
    display: flex;
    align-items: center;
    gap: 0.2rem;
    padding: 0.25rem 0.4rem;
    border-radius: 5px;
    font-weight: 600;
    font-size: 0.75rem;
    color: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    z-index: 10;
}

.tmdb-rating-badge i {
    font-size: 0.65rem;
}

.tmdb-rating-stars {
    display: none;
}

.film-list.list-view .tmdb-rating-badge {
    display: none;
}

.film-list.list-view .tmdb-rating-stars {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.85rem;
}

.film-list.list-view .tmdb-label {
    font-weight: 600;
    color: #01d277;
    font-size: 0.8rem;
}

.film-list.list-view .tmdb-stars {
    display: flex;
    gap: 0.1rem;
}

.film-list.list-view .tmdb-stars i {
    font-size: 0.9rem;
}

.film-list.list-view .tmdb-score {
    font-weight: 700;
    font-size: 0.95rem;
    margin-left: 0.2rem;
}

.film-list.list-view .tmdb-votes {
    font-size: 0.75rem;
    color: var(--text-muted, rgba(228, 228, 231, 0.5));
}

.film-list.list-view .dvd {
    display: flex;
    flex-direction: row;
    align-items: center;
    padding: 0.75rem 1rem;
    gap: 1rem;
    border-bottom: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
}

.film-list.list-view .dvd:hover {
    background: var(--glass-bg, rgba(255, 255, 255, 0.03));
}

.film-list.list-view .cover-area {
    flex-shrink: 0;
    width: 60px;
    height: 85px;
    position: relative;
}

.film-list.list-view .cover-area img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 4px;
}

.film-list.list-view .dvd-details {
    flex: 1;
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
    min-width: 0;
}

.film-list.list-view .film-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 0.3rem;
    min-width: 0;
}

.film-list.list-view .dvd-details h2 {
    margin: 0;
    font-size: 1rem;
    line-height: 1.3;
}

.film-list.list-view .genre-info {
    margin: 0;
    font-size: 0.85rem;
    color: var(--text-secondary, rgba(228, 228, 231, 0.7));
}

.film-list.list-view .genre-info strong {
    font-weight: 500;
    color: var(--text-muted, rgba(228, 228, 231, 0.5));
}

.film-list.list-view .tmdb-rating-stars {
    flex-shrink: 0;
    margin-left: auto;
}

.view-btn {
    background: transparent;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.2));
    color: var(--text-secondary, rgba(228, 228, 231, 0.6));
    padding: 0.5rem 0.75rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.view-btn:hover {
    background: var(--glass-bg, rgba(255, 255, 255, 0.05));
    color: var(--text-primary, #e4e4e7);
    border-color: var(--accent-primary, #667eea);
}

.view-btn.active {
    background: var(--accent-primary, #667eea);
    color: white;
    border-color: var(--accent-primary, #667eea);
}

.view-btn i {
    font-size: 1.1rem;
}

@media (max-width: 768px) {
    .tmdb-rating-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.35rem;
    }
    
    .film-list.list-view .dvd {
        padding: 0.6rem 0.75rem;
    }
    
    .film-list.list-view .cover-area {
        width: 50px;
        height: 70px;
    }
    
    .film-list.list-view .film-info h2 {
        font-size: 0.9rem;
    }
    
    .film-list.list-view .genre-info {
        font-size: 0.8rem;
    }
    
    .film-list.list-view .tmdb-rating-stars {
        font-size: 0.75rem;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filmList = document.querySelector('.film-list');
    const viewButtons = document.querySelectorAll('.view-btn');
    
    if (!filmList || !viewButtons.length) return;
    
    const savedView = localStorage.getItem('filmListView') || 'grid';
    setViewMode(savedView);
    
    viewButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const mode = this.getAttribute('data-mode');
            setViewMode(mode);
            localStorage.setItem('filmListView', mode);
        });
    });
    
    function setViewMode(mode) {
        filmList.classList.remove('grid-view', 'list-view');
        filmList.classList.add(mode + '-view');
        
        viewButtons.forEach(btn => {
            if (btn.getAttribute('data-mode') === mode) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });
    }
});
</script>