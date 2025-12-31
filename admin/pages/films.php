<?php
/**
 * DVD Profiler Liste - Film-Verwaltung
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.8
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSRF-Token generieren
$csrfToken = generateCSRFToken();

// Variablen initialisieren
$error = '';
$success = '';

// Success-Message aus Session
if (isset($_SESSION['film_success'])) {
    $success = $_SESSION['film_success'];
    unset($_SESSION['film_success']);
}

// Error-Message aus Session
if (isset($_SESSION['film_error'])) {
    $error = $_SESSION['film_error'];
    unset($_SESSION['film_error']);
}

// Pagination und Filter
$page = max(1, (int)($_GET['p'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$search = trim($_GET['search'] ?? '');
$collectionType = trim($_GET['collection'] ?? '');

// SQL Query aufbauen
$where = [];
$params = [];

if ($search !== '') {
    $where[] = "(title LIKE :search OR overview LIKE :search)";
    $params['search'] = "%{$search}%";
}

if ($collectionType !== '') {
    $where[] = "collection_type = :collection";
    $params['collection'] = $collectionType;
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Gesamtanzahl ermitteln
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM dvds {$whereSql}");
$countStmt->execute($params);
$totalFilms = (int)$countStmt->fetchColumn();
$totalPages = ceil($totalFilms / $perPage);

// Filme laden
$stmt = $pdo->prepare("
    SELECT 
        id, title, year, genre, collection_type, 
        runtime, rating_age, trailer_url, cover_id,
        created_at, updated_at
    FROM dvds 
    {$whereSql}
    ORDER BY title ASC
    LIMIT :limit OFFSET :offset
");

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$films = $stmt->fetchAll();

// Collection Types für Filter laden
$typesStmt = $pdo->query("
    SELECT DISTINCT collection_type 
    FROM dvds 
    WHERE collection_type IS NOT NULL AND collection_type != ''
    ORDER BY collection_type
");
$collectionTypes = $typesStmt->fetchAll(PDO::FETCH_COLUMN);

// Helper-Funktionen
function formatRuntime(int $minutes): string {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return $hours > 0 ? "{$hours}h {$mins}min" : "{$mins}min";
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-film"></i> Film-Verwaltung</h2>
            <p class="text-muted mb-0">Verwalten Sie Ihre Film-Sammlung (<?= number_format($totalFilms) ?> Filme)</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Filter und Suche -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="" class="row g-3" id="searchForm">
                
                <div class="col-md-5">
                    <label for="search" class="form-label">Suche</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" 
                               class="form-control" 
                               id="search" 
                               name="search" 
                               placeholder="Titel oder Beschreibung..."
                               value="<?= htmlspecialchars($search) ?>">
                    </div>
                </div>
                
                <div class="col-md-4">
                    <label for="collection" class="form-label">Sammlung</label>
                    <select class="form-select" id="collection" name="collection">
                        <option value="">Alle Sammlungen</option>
                        <?php foreach ($collectionTypes as $type): ?>
                            <option value="<?= htmlspecialchars($type) ?>" 
                                    <?= $collectionType === $type ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary flex-grow-1">
                        <i class="bi bi-funnel"></i> Filtern
                    </button>
                    <?php if ($search || $collectionType): ?>
                        <a href="?page=films" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Film-Liste -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Filme</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($films)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-film fs-1"></i>
                    <p class="mt-2">Keine Filme gefunden</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th>Titel</th>
                                <th style="width: 80px;">Jahr</th>
                                <th style="width: 150px;">Genre</th>
                                <th style="width: 120px;">Sammlung</th>
                                <th style="width: 100px; text-align: center;">Trailer</th>
                                <th style="width: 150px; text-align: center;">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($films as $film): ?>
                                <tr>
                                    <td><?= htmlspecialchars($film['id']) ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($film['title']) ?></strong>
                                        <?php if ($film['runtime']): ?>
                                            <br><small class="text-muted">
                                                <i class="bi bi-clock"></i> <?= formatRuntime($film['runtime']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($film['year'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($film['genre'] ?? '-') ?></td>
                                    <td>
                                        <?php if ($film['collection_type']): ?>
                                            <span class="badge bg-secondary">
                                                <?= htmlspecialchars($film['collection_type']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <?php if (!empty($film['trailer_url'])): ?>
                                            <span class="badge bg-success" title="<?= htmlspecialchars($film['trailer_url']) ?>">
                                                <i class="bi bi-check-lg"></i> Vorhanden
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">
                                                <i class="bi bi-dash"></i> Kein Trailer
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <button class="btn btn-sm btn-outline-primary edit-film-btn"
                                                data-film-id="<?= $film['id'] ?>"
                                                data-film-title="<?= htmlspecialchars($film['title']) ?>"
                                                data-film-year="<?= htmlspecialchars($film['year'] ?? '') ?>"
                                                data-film-trailer="<?= htmlspecialchars($film['trailer_url'] ?? '') ?>"
                                                title="Film bearbeiten">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Seitennummerierung">
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=films&p=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&collection=<?= urlencode($collectionType) ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=films&p=<?= $i ?>&search=<?= urlencode($search) ?>&collection=<?= urlencode($collectionType) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=films&p=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&collection=<?= urlencode($collectionType) ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <div class="text-center text-muted mt-2">
                    <small>Seite <?= $page ?> von <?= $totalPages ?> (<?= number_format($totalFilms) ?> Filme gesamt)</small>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Film Modal -->
<div class="modal fade" id="editFilmModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post" action="actions/edit_film.php" id="editFilmForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="film_id" id="edit-film-id">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square"></i> 
                        Film bearbeiten: <span id="edit-film-title-display"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit-trailer-url" class="form-label">
                            <i class="bi bi-play-circle"></i> Trailer-URL
                        </label>
                        <input type="url" 
                               class="form-control" 
                               id="edit-trailer-url" 
                               name="trailer_url" 
                               placeholder="https://www.youtube.com/watch?v=..." 
                               maxlength="500">
                        <div class="form-text">
                            Fügen Sie eine YouTube-, Vimeo- oder andere Video-URL ein.
                            <br>Unterstützt: YouTube, Vimeo, Dailymotion
                        </div>
                        <div id="trailer-preview" class="mt-3" style="display: none;">
                            <label class="form-label">Vorschau:</label>
                            <div class="ratio ratio-16x9">
                                <iframe id="trailer-preview-frame" 
                                        src="" 
                                        allowfullscreen 
                                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture">
                                </iframe>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Hinweis:</strong> Aktuell können nur Trailer-URLs bearbeitet werden. 
                        Weitere Film-Details können über den XML-Import aktualisiert werden.
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg"></i> Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap Modal initialisieren
    const editModal = new bootstrap.Modal(document.getElementById('editFilmModal'));
    
    // Edit-Button Handler
    document.querySelectorAll('.edit-film-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const filmId = this.dataset.filmId;
            const filmTitle = this.dataset.filmTitle;
            const filmYear = this.dataset.filmYear;
            const filmTrailer = this.dataset.filmTrailer || '';
            
            // Modal-Felder füllen
            document.getElementById('edit-film-id').value = filmId;
            document.getElementById('edit-film-title-display').textContent = `${filmTitle} (${filmYear})`;
            document.getElementById('edit-trailer-url').value = filmTrailer;
            
            // Vorschau aktualisieren wenn URL vorhanden
            if (filmTrailer) {
                updateTrailerPreview(filmTrailer);
            } else {
                document.getElementById('trailer-preview').style.display = 'none';
            }
            
            // Modal anzeigen
            editModal.show();
        });
    });
    
    // Trailer-URL Input Handler für Live-Vorschau
    const trailerInput = document.getElementById('edit-trailer-url');
    let previewTimeout;
    
    trailerInput.addEventListener('input', function() {
        clearTimeout(previewTimeout);
        const url = this.value.trim();
        
        if (url) {
            previewTimeout = setTimeout(() => {
                updateTrailerPreview(url);
            }, 500); // 500ms Debounce
        } else {
            document.getElementById('trailer-preview').style.display = 'none';
        }
    });
    
    // Funktion zum Aktualisieren der Trailer-Vorschau
    function updateTrailerPreview(url) {
        const preview = document.getElementById('trailer-preview');
        const frame = document.getElementById('trailer-preview-frame');
        
        // YouTube-URL konvertieren
        let embedUrl = convertToEmbedUrl(url);
        
        if (embedUrl) {
            frame.src = embedUrl;
            preview.style.display = 'block';
        } else {
            preview.style.display = 'none';
        }
    }
    
    // URL zu Embed-URL konvertieren
    function convertToEmbedUrl(url) {
        // YouTube
        const youtubeRegex = /(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/;
        const youtubeMatch = url.match(youtubeRegex);
        if (youtubeMatch) {
            return `https://www.youtube.com/embed/${youtubeMatch[1]}`;
        }
        
        // Vimeo
        const vimeoRegex = /vimeo\.com\/(\d+)/;
        const vimeoMatch = url.match(vimeoRegex);
        if (vimeoMatch) {
            return `https://player.vimeo.com/video/${vimeoMatch[1]}`;
        }
        
        // Dailymotion
        const dailymotionRegex = /dailymotion\.com\/video\/([a-zA-Z0-9]+)/;
        const dailymotionMatch = url.match(dailymotionRegex);
        if (dailymotionMatch) {
            return `https://www.dailymotion.com/embed/video/${dailymotionMatch[1]}`;
        }
        
        return null;
    }
    
    // Form-Submission Handler
    document.getElementById('editFilmForm').addEventListener('submit', function(e) {
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Speichern...';
    });
});
</script>

<style>
.edit-film-btn:hover {
    transform: scale(1.1);
    transition: transform 0.2s ease;
}

.table td {
    vertical-align: middle;
}

#trailer-preview {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
}

.modal-lg {
    max-width: 800px;
}
</style>

<script>
// Form Submit - page Parameter erhalten
document.getElementById('searchForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const params = new URLSearchParams();
    
    // page Parameter immer hinzufügen
    params.set('page', 'films');
    
    // Andere Felder nur wenn nicht leer
    const search = formData.get('search');
    const collection = formData.get('collection');
    
    if (search && search.trim() !== '') {
        params.set('search', search.trim());
    }
    
    if (collection && collection !== '') {
        params.set('collection', collection);
    }
    
    // Redirect
    window.location.href = '?' + params.toString();
});
</script>