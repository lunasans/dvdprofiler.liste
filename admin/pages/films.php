<?php
/**
 * DVD Profiler Liste - Films Management
 * Verwalte, bearbeite und lösche Filme
 * 
 * @package    dvdprofiler.liste
 * @version    1.4.8
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSRF-Token generieren
$csrfToken = generateCSRFToken();

// Success/Error Messages
$success = '';
$error = '';

if (isset($_SESSION['films_success'])) {
    $success = $_SESSION['films_success'];
    unset($_SESSION['films_success']);
}

if (isset($_SESSION['films_error'])) {
    $error = $_SESSION['films_error'];
    unset($_SESSION['films_error']);
}

// Pagination
$page = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$perPage = 25;
$offset = ($page - 1) * $perPage;

// Search & Filter
$search = $_GET['search'] ?? '';
$collectionType = $_GET['collection'] ?? '';

// Build Query
$where = ["deleted = 0"];
$params = [];

if (!empty($search)) {
    $where[] = "(title LIKE :search OR genre LIKE :search)";
    $params['search'] = '%' . $search . '%';
}

if (!empty($collectionType)) {
    $where[] = "collection_type = :collection_type";
    $params['collection_type'] = $collectionType;
}

$whereClause = implode(' AND ', $where);

// Count total
$stmt = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE " . $whereClause);
$stmt->execute($params);
$totalFilms = $stmt->fetchColumn();
$totalPages = ceil($totalFilms / $perPage);

// Load films
$sql = "
    SELECT id, title, year, genre, runtime, rating_age, collection_type, 
           cover_id, trailer_url, created_at
    FROM dvds 
    WHERE " . $whereClause . "
    ORDER BY id DESC
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$films = $stmt->fetchAll();
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">
        <i class="bi bi-film"></i> Filme verwalten
    </h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
        <li class="breadcrumb-item active">Filme</li>
    </ol>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Search & Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3">
                <input type="hidden" name="page" value="films">
                
                <div class="col-md-4">
                    <label for="search" class="form-label">Suche</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="Titel oder Genre...">
                </div>
                
                <div class="col-md-3">
                    <label for="collection" class="form-label">Collection Type</label>
                    <select class="form-select" id="collection" name="collection">
                        <option value="">Alle</option>
                        <option value="Owned" <?= $collectionType === 'Owned' ? 'selected' : '' ?>>Owned</option>
                        <option value="Serie" <?= $collectionType === 'Serie' ? 'selected' : '' ?>>Serie</option>
                        <option value="Stream" <?= $collectionType === 'Stream' ? 'selected' : '' ?>>Stream</option>
                    </select>
                </div>
                
                <div class="col-md-5 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i> Suchen
                    </button>
                    <a href="?page=films" class="btn btn-secondary">
                        <i class="bi bi-x-circle"></i> Zurücksetzen
                    </a>
                    <a href="?page=tmdb-import" class="btn btn-success ms-auto">
                        <i class="bi bi-plus-circle"></i> Film importieren
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Films Table -->
    <div class="card">
        <div class="card-header">
            <i class="bi bi-list"></i> <?= number_format($totalFilms) ?> Filme
            <?php if (!empty($search) || !empty($collectionType)): ?>
                <span class="badge bg-primary">Gefiltert</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($films)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Keine Filme gefunden.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="60">ID</th>
                                <th>Titel</th>
                                <th width="80">Jahr</th>
                                <th>Genre</th>
                                <th width="80">FSK</th>
                                <th width="100">Type</th>
                                <th width="120">Hinzugefügt</th>
                                <th width="150" class="text-end">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($films as $film): ?>
                            <tr>
                                <td><?= $film['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($film['title']) ?></strong>
                                    <?php if (!empty($film['trailer_url'])): ?>
                                        <i class="bi bi-play-circle text-danger" title="Hat Trailer"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?= $film['year'] ?></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($film['genre']) ?></span></td>
                                <td>
                                    <?php if ($film['rating_age'] !== null): ?>
                                        <span class="badge bg-warning">FSK <?= $film['rating_age'] ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $typeColors = [
                                        'Owned' => 'success',
                                        'Serie' => 'info',
                                        'Stream' => 'primary'
                                    ];
                                    $color = $typeColors[$film['collection_type']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>"><?= htmlspecialchars($film['collection_type']) ?></span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('d.m.Y', strtotime($film['created_at'])) ?>
                                    </small>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" 
                                                class="btn btn-outline-primary"
                                                onclick="editFilm(<?= $film['id'] ?>)"
                                                title="Bearbeiten">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="../film-view.php?id=<?= $film['id'] ?>" 
                                           class="btn btn-outline-info"
                                           target="_blank"
                                           title="Ansehen">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-danger"
                                                onclick="deleteFilm(<?= $film['id'] ?>, '<?= htmlspecialchars($film['title'], ENT_QUOTES) ?>')"
                                                title="Löschen">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <nav aria-label="Pagination" class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=films&p=<?= $page - 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($collectionType) ? '&collection=' . urlencode($collectionType) : '' ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=films&p=<?= $i ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($collectionType) ? '&collection=' . urlencode($collectionType) : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=films&p=<?= $page + 1 ?><?= !empty($search) ? '&search=' . urlencode($search) : '' ?><?= !empty($collectionType) ? '&collection=' . urlencode($collectionType) : '' ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Film Modal -->
<div class="modal fade" id="editFilmModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pencil"></i> Film bearbeiten
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editFilmForm" action="actions/edit-film.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="film_id" id="edit_film_id">
                <input type="hidden" name="current_page" value="<?= $page ?>">
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                <input type="hidden" name="collection" value="<?= htmlspecialchars($collectionType) ?>">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="edit_title" class="form-label">Titel *</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_year" class="form-label">Jahr *</label>
                            <input type="number" class="form-control" id="edit_year" name="year" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_genre" class="form-label">Genre</label>
                            <input type="text" class="form-control" id="edit_genre" name="genre">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_runtime" class="form-label">Laufzeit (Min)</label>
                            <input type="number" class="form-control" id="edit_runtime" name="runtime">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_rating_age" class="form-label">FSK</label>
                            <select class="form-select" id="edit_rating_age" name="rating_age">
                                <option value="">Keine Angabe</option>
                                <option value="0">FSK 0</option>
                                <option value="6">FSK 6</option>
                                <option value="12">FSK 12</option>
                                <option value="16">FSK 16</option>
                                <option value="18">FSK 18</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_collection_type" class="form-label">Collection Type *</label>
                            <select class="form-select" id="edit_collection_type" name="collection_type" required>
                                <option value="Owned">Owned</option>
                                <option value="Serie">Serie</option>
                                <option value="Stream">Stream</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_trailer_url" class="form-label">Trailer URL</label>
                        <input type="url" class="form-control" id="edit_trailer_url" name="trailer_url" placeholder="https://youtube.com/watch?v=...">
                    </div>

                    <div class="mb-3">
                        <label for="edit_overview" class="form-label">Handlung</label>
                        <textarea class="form-control" id="edit_overview" name="overview" rows="4"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Speichern
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit Film
function editFilm(filmId) {
    // Load film data via AJAX
    fetch(`actions/get-film.php?id=${filmId}`)
        .then(response => response.json())
        .then(film => {
            document.getElementById('edit_film_id').value = film.id;
            document.getElementById('edit_title').value = film.title;
            document.getElementById('edit_year').value = film.year;
            document.getElementById('edit_genre').value = film.genre || '';
            document.getElementById('edit_runtime').value = film.runtime || '';
            document.getElementById('edit_rating_age').value = film.rating_age || '';
            document.getElementById('edit_collection_type').value = film.collection_type || 'Owned';
            document.getElementById('edit_trailer_url').value = film.trailer_url || '';
            document.getElementById('edit_overview').value = film.overview || '';
            
            new bootstrap.Modal(document.getElementById('editFilmModal')).show();
        })
        .catch(error => {
            alert('Fehler beim Laden der Film-Daten: ' + error);
        });
}

// Delete Film
function deleteFilm(filmId, filmTitle) {
    if (!confirm(`Film "${filmTitle}" wirklich löschen?`)) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'actions/delete-film.php';
    
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = '<?= $csrfToken ?>';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'film_id';
    idInput.value = filmId;
    
    form.appendChild(csrfInput);
    form.appendChild(idInput);
    document.body.appendChild(form);
    form.submit();
}
</script>