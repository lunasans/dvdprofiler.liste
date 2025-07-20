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
<!-- ERSETZEN Sie das JavaScript in film-list.php mit diesem: -->

<script>
// Modal-Funktionen SOFORT definieren
window.openBoxsetModal = function(boxsetId, boxsetTitle) {
    console.log('Opening modal for:', boxsetId, boxsetTitle);
    
    const modal = document.getElementById('boxsetModal');
    const title = document.getElementById('boxsetTitle');
    
    if (modal && title) {
        title.textContent = boxsetTitle;
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
        
        // Content laden
        loadBoxsetContent(boxsetId);
    } else {
        console.error('Modal elements not found!');
    }
};

window.closeModal = function() {
    const modal = document.getElementById('boxsetModal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
};

window.loadBoxsetContent = function(boxsetId) {
    const contentDiv = document.getElementById('boxsetContent');
    const statsDiv = document.getElementById('boxsetStats');
    
    if (!contentDiv) {
        console.error('Content div not found!');
        return;
    }
    
    contentDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: white;">Lade BoxSet-Inhalte...</div>';
    
    console.log('Loading content for BoxSet ID:', boxsetId);
    
    fetch(`api/boxset-content.php?id=${boxsetId}`)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            
            if (data.success && data.films && data.films.length > 0) {
                let html = '';
                data.films.forEach(film => {
                    html += `
                        <div class="boxset-child-card" onclick="openFilmDetails(${film.id})">
                            <img src="${film.cover_url}" alt="${film.title_safe}" class="child-cover" loading="lazy">
                            <div class="p-3">
                                <h6>${film.title_safe}</h6>
                                <p>${film.year}${film.genre_safe ? ' • ' + film.genre_safe : ''}</p>
                                ${film.formatted_runtime ? '<p>⏱️ ' + film.formatted_runtime + '</p>' : ''}
                                ${film.rating_display ? '<p>🔞 ' + film.rating_display + '</p>' : ''}
                            </div>
                        </div>
                    `;
                });
                contentDiv.innerHTML = `<div class="boxset-grid">${html}</div>`;
                if (statsDiv) {
                    statsDiv.textContent = `${data.count} Filme • ${data.formatted_total_runtime || 'Unbekannte Laufzeit'}`;
                }
            } else {
                contentDiv.innerHTML = '<div style="text-align: center; padding: 2rem; color: #bdc3c7;">Keine Filme in diesem BoxSet gefunden.</div>';
                if (statsDiv) {
                    statsDiv.textContent = '';
                }
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            contentDiv.innerHTML = `<div style="text-align: center; padding: 2rem; color: #e74c3c;">Fehler beim Laden: ${error.message}</div>`;
            if (statsDiv) {
                statsDiv.textContent = '';
            }
        });
};

window.openFilmDetails = function(filmId) {
    console.log('Opening film details for:', filmId);
    const detailButton = document.querySelector(`[data-id="${filmId}"] .toggle-detail`);
    if (detailButton) {
        detailButton.click();
        closeModal();
    } else {
        console.log('Detail button not found for film:', filmId);
        alert(`Film-Details für ID ${filmId} - Details-Button nicht gefunden`);
    }
};

// Event Listener für BoxSet-Buttons
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, setting up BoxSet event listeners...');
    
    // Delegated Event Listener für dynamische Inhalte
    document.addEventListener('click', function(e) {
        const trigger = e.target.closest('.boxset-modal-trigger');
        if (trigger) {
            e.preventDefault();
            console.log('BoxSet button clicked!');
            
            const boxsetId = trigger.getAttribute('data-boxset-id');
            const boxsetTitle = trigger.getAttribute('data-boxset-title');
            
            console.log('BoxSet data:', { boxsetId, boxsetTitle });
            
            if (boxsetId && boxsetTitle) {
                openBoxsetModal(boxsetId, boxsetTitle);
            } else {
                console.error('Missing BoxSet data on button:', trigger);
            }
        }
    });
    
    // ESC zum Schließen
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
    
    // Test ob Modal da ist
    const modal = document.getElementById('boxsetModal');
    console.log('Modal element found:', !!modal);
    
    // Test ob BoxSet-Buttons da sind
    const buttons = document.querySelectorAll('.boxset-modal-trigger');
    console.log('BoxSet buttons found:', buttons.length);
});
</script>