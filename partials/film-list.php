<?php
require_once __DIR__ . '/../includes/bootstrap.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);

$search   = trim($_GET['q'] ?? '');
$type     = trim($_GET['type'] ?? '');
$page     = max(1, (int)($_GET['seite'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

// CollectionTypes laden
$types = $pdo->query("SELECT DISTINCT collection_type FROM dvds WHERE collection_type IS NOT NULL ORDER BY collection_type")->fetchAll(PDO::FETCH_COLUMN);

// WHERE-Filter - NUR PARENTS UND EINZELFILME
$where = ['boxset_parent IS NULL']; // ← BoxSet-Filter aktiviert!
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

// Gesamtanzahl
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM dvds $whereSql");
foreach ($params as $k => $v) {
    $countStmt->bindValue($k, $v);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

// Hauptfilme holen (nur Parents und Einzelfilme)
$sql = "SELECT * FROM dvds $whereSql ORDER BY title LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mainFilms = $stmt->fetchAll();
?>

<!-- Tabs für CollectionType -->
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
  <div class="view-toggle">
    <button onclick="setViewMode('grid')"><i class="bi bi-grid-fill"></i></button>
    <button onclick="setViewMode('list')"><i class="bi bi-list-ol"></i></button>
  </div>
</div>

<!-- Film-Liste - OHNE automatische Kinder-Anzeige -->
<div class="film-list">
  <?php foreach ($mainFilms as $film): ?>
    <?= renderFilmCard($film) ?>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <nav class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= buildQuery(['seite' => $page - 1]) ?>">«</a>
    <?php endif; ?>
    <?php if ($page > 3): ?>
      <a href="?<?= buildQuery(['seite' => 1]) ?>">1</a>
      <?php if ($page > 4): ?><span class="dots">…</span><?php endif; ?>
    <?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?<?= buildQuery(['seite' => $i]) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages - 2): ?>
      <?php if ($page < $totalPages - 3): ?><span class="dots">…</span><?php endif; ?>
      <a href="?<?= buildQuery(['seite' => $totalPages]) ?>"><?= $totalPages ?></a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?<?= buildQuery(['seite' => $page + 1]) ?>">»</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>

<!-- BoxSet Modal -->
<div class="modal fade" id="boxsetModal" tabindex="-1" aria-labelledby="boxsetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="boxsetModalLabel">
                    <i class="bi bi-collection"></i> <span id="boxsetTitle">BoxSet Inhalte</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="boxsetContent" class="boxset-grid">
                    <!-- Inhalt wird dynamisch geladen -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
                <div id="boxsetStats" class="text-muted small"></div>
            </div>
        </div>
    </div>
</div>

<script>
// BoxSet Modal Handler
document.addEventListener('DOMContentLoaded', function() {
    const boxsetModal = document.getElementById('boxsetModal');
    
    if (boxsetModal) {
        boxsetModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const boxsetId = button.getAttribute('data-boxset-id');
            const boxsetTitle = button.getAttribute('data-boxset-title');
            
            // Modal Titel setzen
            document.getElementById('boxsetTitle').textContent = boxsetTitle;
            
            // Boxset Inhalte laden
            loadBoxsetContent(boxsetId);
        });
    }
});

function loadBoxsetContent(boxsetId) {
    const contentDiv = document.getElementById('boxsetContent');
    const statsDiv = document.getElementById('boxsetStats');
    
    // Loading anzeigen
    contentDiv.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-light" role="status">
                <span class="visually-hidden">Lade BoxSet Inhalte...</span>
            </div>
            <p class="mt-2 text-muted">Lade BoxSet-Inhalte...</p>
        </div>
    `;
    statsDiv.innerHTML = '';
    
    // AJAX Request
    fetch(`api/boxset-content.php?id=${boxsetId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('BoxSet data:', data);
            
            if (data.success && data.films && data.films.length > 0) {
                let html = '';
                
                data.films.forEach(film => {
                    html += `
                        <div class="boxset-child-card" onclick="openFilmDetails(${film.id})">
                            <img src="${film.cover_url}" alt="${film.title_safe}" class="child-cover" loading="lazy">
                            <div class="p-3">
                                <h6 class="text-white mb-2">${film.title_safe}</h6>
                                <p class="text-muted mb-1">${film.year}${film.genre_safe ? ' • ' + film.genre_safe : ''}</p>
                                ${film.formatted_runtime ? '<p class="small text-light mb-1">⏱️ ' + film.formatted_runtime + '</p>' : ''}
                                ${film.rating_display ? '<p class="small text-warning mb-0">🔞 ' + film.rating_display + '</p>' : ''}
                            </div>
                        </div>
                    `;
                });
                
                contentDiv.innerHTML = html;
                
                // Statistik anzeigen
                statsDiv.innerHTML = `${data.count} Filme • ${data.formatted_total_runtime} Gesamtlaufzeit`;
                
            } else {
                contentDiv.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="bi bi-collection" style="font-size: 3rem; opacity: 0.3;"></i>
                        <p class="mt-3">Keine Filme in diesem BoxSet gefunden.</p>
                    </div>
                `;
                statsDiv.innerHTML = '';
            }
        })
        .catch(error => {
            console.error('Error loading boxset content:', error);
            contentDiv.innerHTML = `
                <div class="text-center text-danger">
                    <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                    <p class="mt-3">Fehler beim Laden der BoxSet-Inhalte.</p>
                    <small>Fehler: ${error.message}</small>
                </div>
            `;
            statsDiv.innerHTML = '';
        });
}

function openFilmDetails(filmId) {
    // Bestehende toggle-detail Funktionalität verwenden
    const detailButton = document.querySelector(`[data-id="${filmId}"] .toggle-detail`);
    if (detailButton) {
        detailButton.click();
    } else {
        // Fallback: AJAX-Call für Film-Details
        console.log('Film Details für ID:', filmId);
        // Hier könnten Sie eine direkte AJAX-Abfrage machen
        window.location.hash = `film-${filmId}`;
    }
    
    // Modal schließen
    const modal = bootstrap.Modal.getInstance(document.getElementById('boxsetModal'));
    if (modal) {
        modal.hide();
    }
}
</script>