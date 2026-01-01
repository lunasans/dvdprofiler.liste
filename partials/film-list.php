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
function renderFilmCard(array $dvd): string {
    $title = htmlspecialchars($dvd['title'] ?? 'Unbekannt');
    $year = (int)($dvd['year'] ?? 0);
    $genre = htmlspecialchars($dvd['genre'] ?? 'Unbekannt');
    $id = (int)($dvd['id'] ?? 0);
    $cover = 'cover/placeholder.png';
    
    // Cover finden
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
    
    // TMDb Rating Badge (wenn aktiviert)
    $ratingBadge = '';
    if (getSetting('tmdb_show_ratings_on_cards', '1') == '1' && !empty(getSetting('tmdb_api_key', ''))) {
        $ratings = getFilmRatings($dvd['title'], $year);
        if ($ratings && isset($ratings['tmdb_rating'])) {
            $rating = $ratings['tmdb_rating'];
            $votes = $ratings['tmdb_votes'] ?? 0;
            
            // Farbe basierend auf Rating
            if ($rating >= 8) {
                $color = '#4caf50'; // Grün
            } elseif ($rating >= 6) {
                $color = '#ff9800'; // Orange
            } else {
                $color = '#f44336'; // Rot
            }
            
            $ratingBadge = '<div class="tmdb-rating-badge" style="background-color: ' . $color . ';">
                <i class="bi bi-star-fill"></i>
                <span>' . number_format($rating, 1) . '</span>
            </div>';
        }
    }
    
    // BoxSet Badge (nur für Parents)
    $badge = '';
    if ($isBoxSet) {
        $badge = '<div class="boxset-badge" onclick="event.stopPropagation(); openBoxSetModal(' . $id . ');">
            <i class="bi bi-collection-play"></i>
            <span>' . $childrenCount . '</span>
        </div>';
    }
    
    $boxsetClass = $isBoxSet ? ' has-boxset' : '';
    
    return '
    <div class="dvd' . $boxsetClass . '" data-dvd-id="' . $id . '" data-children-count="' . $childrenCount . '">
      <div class="cover-area">
        <img src="' . htmlspecialchars($cover) . '" alt="Cover">
        ' . $ratingBadge . '
        ' . $badge . '
      </div>
      <div class="dvd-details">
        <h2><a href="#" class="toggle-detail" data-id="' . $id . '">' . $title . ' (' . $year . ')</a></h2>
        <p><strong>Genre:</strong> ' . $genre . '</p>
      </div>
    </div>';
}
?>

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
    align-items: center;
    justify-content: center;
    padding: 40px 20px;
    overflow-y: auto;
    pointer-events: none; /* Ermöglicht Click-Through auf Film-Liste */
}

.boxset-modal.show {
    display: flex;
}

.modal-content {
    position: relative;
    z-index: 2;
    width: 100%;
    max-width: 1000px;
    margin: auto;
    background: var(--bg-secondary, #1a1a2e);
    border: 2px solid var(--accent-primary, #667eea);
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 25px 80px rgba(0, 0, 0, 0.8), 0 0 0 1px rgba(102, 126, 234, 0.3);
    animation: zoomIn 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    transition: box-shadow 0.3s ease;
    pointer-events: all; /* Modal selbst ist interaktiv */
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
// BoxSet Modal Functions
let isDragging = false;
let currentX;
let currentY;
let initialX;
let initialY;
let xOffset = 0;
let yOffset = 0;

function openBoxSetModal(parentId) {
    const modal = document.getElementById('boxsetModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    // Reset Position
    xOffset = 0;
    yOffset = 0;
    
    // Zeige Modal
    modal.classList.add('show');
    
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
        })
        .catch(error => {
            console.error('BoxSet load error:', error);
            modalBody.innerHTML = '<div class="loading">❌ Fehler beim Laden</div>';
        });
}

function closeBoxSetModal() {
    const modal = document.getElementById('boxsetModal');
    modal.classList.remove('show');
}

function renderModalFilmCard(film) {
    return `
        <div class="dvd" data-dvd-id="${film.id}">
            <div class="cover-area">
                <img src="${film.cover}" alt="Cover">
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
</script>