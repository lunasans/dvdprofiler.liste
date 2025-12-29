<?php
// partials/film-list.php - Mit BoxSet Gruppierung

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
    $typesStmt = $pdo->query("SELECT DISTINCT collection_type FROM dvds WHERE collection_type IS NOT NULL ORDER BY collection_type");
    $types = $typesStmt ? $typesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    
    // WHERE-Filter aufbauen
    $where = ['1=1']; // Immer wahr
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
    
    // Gesamtanzahl (nur Parent-Filme und Einzelfilme zählen)
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dvds $whereSql AND (boxset_parent IS NULL OR boxset_parent = 0)");
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
    
    // Filme laden (nur Parents und Einzelfilme)
    $sql = "SELECT * FROM dvds $whereSql AND (boxset_parent IS NULL OR boxset_parent = 0) ORDER BY title LIMIT :limit OFFSET :offset";
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
    
    // Für jeden Film: Lade BoxSet-Kinder falls vorhanden
    $filmsWithChildren = [];
    foreach ($films as $film) {
        $filmsWithChildren[] = $film;
        
        // Prüfe ob dieser Film ein BoxSet-Parent ist
        $childStmt = $pdo->prepare("SELECT * FROM dvds WHERE boxset_parent = ? ORDER BY title");
        $childStmt->execute([$film['id']]);
        $children = $childStmt->fetchAll();
        
        if (!empty($children)) {
            $film['_children'] = $children;
            $film['_is_boxset'] = true;
            $filmsWithChildren[count($filmsWithChildren) - 1] = $film;
        }
    }
    
    $films = $filmsWithChildren;
    
} catch (Exception $e) {
    error_log('Film-list error: ' . $e->getMessage());
    $types = [];
    $films = [];
    $total = 0;
    $totalPages = 0;
}

// Helper: Film Card rendern
function renderFilmCardWithBoxSet(array $dvd, bool $isChild = false): string {
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
    
    $childClass = $isChild ? ' boxset-child' : '';
    $boxsetIcon = isset($dvd['_is_boxset']) && $dvd['_is_boxset'] ? '<i class="bi bi-collection-play boxset-icon" title="BoxSet"></i>' : '';
    
    return '
    <div class="dvd' . $childClass . '" data-dvd-id="' . $id . '">
      <div class="cover-area">
        <img src="' . htmlspecialchars($cover) . '" alt="Cover">
        ' . $boxsetIcon . '
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
      <?php if (isset($film['_is_boxset']) && $film['_is_boxset']): ?>
        <!-- BoxSet Gruppe -->
        <div class="boxset-group" data-boxset-id="<?= $film['id'] ?>">
          <div class="boxset-header">
            <button class="boxset-toggle" aria-expanded="false" aria-label="BoxSet aufklappen">
              <i class="bi bi-chevron-right"></i>
            </button>
            <div class="boxset-title">
              <i class="bi bi-collection-play"></i>
              <span><?= htmlspecialchars($film['title']) ?> (<?= $film['year'] ?>)</span>
              <span class="boxset-count"><?= count($film['_children']) ?> Filme</span>
            </div>
          </div>
          
          <div class="boxset-content">
            <!-- Parent Film -->
            <?= renderFilmCardWithBoxSet($film, false) ?>
            
            <!-- Child Filme -->
            <div class="boxset-children">
              <?php foreach ($film['_children'] as $child): ?>
                <?= renderFilmCardWithBoxSet($child, true) ?>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      <?php else: ?>
        <!-- Einzelner Film -->
        <?= renderFilmCardWithBoxSet($film, false) ?>
      <?php endif; ?>
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
    Seite <?= $page ?> von <?= $totalPages ?> (<?= $total ?> Einträge)
  </div>
<?php endif; ?>

<style>
/* BoxSet Gruppierung Styles */
.boxset-group {
    margin-bottom: var(--space-lg, 1.5rem);
    background: var(--bg-secondary, rgba(255, 255, 255, 0.05));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-lg, 12px);
    overflow: hidden;
    transition: all 0.3s ease;
}

.boxset-group:hover {
    border-color: var(--accent-primary, #667eea);
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.2);
}

.boxset-header {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 0.5rem);
    padding: var(--space-md, 1rem);
    background: var(--bg-tertiary, rgba(255, 255, 255, 0.03));
    cursor: pointer;
    transition: all 0.3s ease;
}

.boxset-header:hover {
    background: var(--bg-primary, rgba(255, 255, 255, 0.08));
}

.boxset-toggle {
    background: none;
    border: none;
    color: var(--accent-primary, #667eea);
    font-size: 1.5rem;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.3s ease;
}

.boxset-toggle:hover {
    background: var(--accent-light, rgba(102, 126, 234, 0.1));
}

.boxset-toggle i {
    transition: transform 0.3s ease;
}

.boxset-group.expanded .boxset-toggle i {
    transform: rotate(90deg);
}

.boxset-title {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 0.5rem);
    flex: 1;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-primary, #e4e4e7);
}

.boxset-title i {
    color: var(--accent-primary, #667eea);
    font-size: 1.3rem;
}

.boxset-count {
    font-size: 0.85rem;
    font-weight: 400;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
    background: var(--accent-light, rgba(102, 126, 234, 0.1));
    padding: 2px 8px;
    border-radius: 12px;
}

.boxset-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.5s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 0 var(--space-md, 1rem);
}

.boxset-group.expanded .boxset-content {
    max-height: 5000px;
    padding: var(--space-md, 1rem);
}

.boxset-children {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: var(--space-md, 1rem);
    margin-top: var(--space-md, 1rem);
    padding-top: var(--space-md, 1rem);
    border-top: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
}

.boxset-child {
    opacity: 0.9;
    transform: scale(0.98);
}

.boxset-child:hover {
    opacity: 1;
    transform: scale(1);
}

.boxset-icon {
    position: absolute;
    top: 8px;
    right: 8px;
    background: var(--accent-primary, #667eea);
    color: white;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 1rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

/* Responsive */
@media (max-width: 768px) {
    .boxset-children {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: var(--space-sm, 0.5rem);
    }
    
    .boxset-title {
        font-size: 1rem;
    }
    
    .boxset-count {
        font-size: 0.75rem;
    }
}
</style>

<script>
// BoxSet Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    const boxsetGroups = document.querySelectorAll('.boxset-group');
    
    boxsetGroups.forEach(group => {
        const header = group.querySelector('.boxset-header');
        const toggle = group.querySelector('.boxset-toggle');
        
        header.addEventListener('click', function() {
            group.classList.toggle('expanded');
            
            const isExpanded = group.classList.contains('expanded');
            toggle.setAttribute('aria-expanded', isExpanded);
        });
    });
});
</script>