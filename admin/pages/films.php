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

// Sorting
$sortColumn = $_GET['sort'] ?? 'id';
$sortOrder = $_GET['order'] ?? 'desc';

// Validate sort column
$allowedColumns = ['id', 'title', 'year', 'genre', 'rating_age', 'collection_type', 'created_at'];
if (!in_array($sortColumn, $allowedColumns)) {
    $sortColumn = 'id';
}

// Validate sort order
$sortOrder = strtolower($sortOrder);
if (!in_array($sortOrder, ['asc', 'desc'])) {
    $sortOrder = 'desc';
}

// Build ORDER BY clause
$orderBy = "$sortColumn $sortOrder";

// Helper function for sortable column headers
function getSortUrl($column, $currentSort, $currentOrder, $search, $collectionType) {
    // Toggle order if same column, otherwise default to ASC
    if ($currentSort === $column) {
        $newOrder = ($currentOrder === 'asc') ? 'desc' : 'asc';
    } else {
        $newOrder = 'asc';
    }
    
    // Build URL with all parameters
    $params = [
        'page' => 'films',
        'sort' => $column,
        'order' => $newOrder
    ];
    
    if (!empty($search)) {
        $params['search'] = $search;
    }
    
    if (!empty($collectionType)) {
        $params['collection'] = $collectionType;
    }
    
    return '?' . http_build_query($params);
}

// Helper function for pagination URLs
function getPaginationUrl($page, $search, $collectionType, $sortColumn, $sortOrder) {
    $params = [
        'page' => 'films',
        'p' => $page
    ];
    
    if (!empty($search)) {
        $params['search'] = $search;
    }
    
    if (!empty($collectionType)) {
        $params['collection'] = $collectionType;
    }
    
    if (!empty($sortColumn) && $sortColumn !== 'id') {
        $params['sort'] = $sortColumn;
    }
    
    if (!empty($sortOrder) && $sortOrder !== 'desc') {
        $params['order'] = $sortOrder;
    }
    
    return '?' . http_build_query($params);
}

// Helper function to get sort icon
function getSortIcon($column, $currentSort, $currentOrder) {
    if ($currentSort !== $column) {
        return '<i class="bi bi-arrow-down-up text-muted ms-1" style="font-size: 0.8em;"></i>';
    }
    
    if ($currentOrder === 'asc') {
        return '<i class="bi bi-arrow-up ms-1" style="font-size: 0.8em;"></i>';
    } else {
        return '<i class="bi bi-arrow-down ms-1" style="font-size: 0.8em;"></i>';
    }
}


// Build Query
$where = ["deleted = 0"];
$params = [];

if (!empty($search)) {
    $where[] = "(title LIKE ? OR genre LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if (!empty($collectionType)) {
    $where[] = "collection_type = ?";
    $params[] = $collectionType;
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
    ORDER BY " . $orderBy . "
    LIMIT ? OFFSET ?
";

// Add limit and offset to params
$allParams = array_merge($params, [$perPage, $offset]);

$stmt = $pdo->prepare($sql);
$stmt->execute($allParams);
$films = $stmt->fetchAll();
?>

<style>
/* ============================================
   FILMS PAGE - ULTRA-DUNKLES THEME
   Mit MAXIMALER Priorität!
   ============================================ */

/* TABELLE & CONTAINER - SEHR DUNKEL! */
.table-responsive {
    background: var(--clr-card) !important;
    border-radius: var(--radius);
}

.table {
    background: var(--clr-card) !important;
    color: var(--clr-text) !important;
    margin-bottom: 0 !important;
}

.table thead {
    background: rgba(255, 255, 255, 0.1) !important;
}

.table thead th {
    background: rgba(255, 255, 255, 0.1) !important;
    color: var(--clr-text) !important;
    border-bottom: 1px solid var(--clr-border) !important;
    font-weight: 600;
    padding: 1rem !important;
}

.table tbody {
    background: var(--clr-card) !important;
}

.table tbody tr {
    background: var(--clr-card) !important;
}

.table tbody td {
    background: transparent !important;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    color: var(--clr-text) !important;
    padding: 1rem !important;
    vertical-align: middle;
}

.table tbody tr:hover {
    background: rgba(255, 255, 255, 0.05) !important;
}

.table tbody tr:hover td {
    background: transparent !important;
}

/* Titel in Tabelle - WEIß */
.table tbody td strong {
    color: var(--clr-text) !important;
    font-weight: 600;
}

/* Normaler Text in Tabelle - WEIß */
.table tbody td,
.table tbody td span:not(.badge) {
    color: var(--clr-text) !important;
}

/* BADGES - BEHALTEN IHRE FARBEN! */
.badge {
    font-weight: 600;
    padding: 0.25rem 0.6rem;
    border-radius: 12px;
    font-size: 0.75rem;
}

.badge.bg-secondary {
    background: #6c757d !important;
    color: #ffffff !important;
}

.badge.bg-warning {
    background: var(--clr-warning) !important;
    color: #ffffff !important;
}

.badge.bg-success {
    background: var(--clr-success) !important;
    color: #ffffff !important;
}

.badge.bg-info {
    background: var(--clr-info) !important;
    color: #ffffff !important;
}

.badge.bg-primary {
    background: var(--clr-accent) !important;
    color: #ffffff !important;
}

.badge.bg-danger {
    background: var(--clr-danger) !important;
    color: #ffffff !important;
}

/* MODAL - DUNKEL */
.modal-content {
    background: var(--clr-card) !important;
    color: var(--clr-text) !important;
    border: 1px solid var(--clr-border) !important;
}

.modal-header {
    background: rgba(255, 255, 255, 0.05) !important;
    border-bottom: 1px solid var(--clr-border) !important;
}

.modal-title {
    color: var(--clr-text) !important;
    font-weight: 600;
}

.modal-body {
    color: var(--clr-text) !important;
}

.modal-body .form-label,
.modal-body label {
    color: var(--clr-text) !important;
    font-weight: 500;
}

.modal-body .form-control,
.modal-body .form-select,
.modal-body textarea.form-control {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid var(--clr-border) !important;
    color: var(--clr-text) !important;
}

.modal-body .form-control:focus,
.modal-body .form-select:focus,
.modal-body textarea:focus {
    background: rgba(255, 255, 255, 0.15) !important;
    border-color: var(--clr-accent) !important;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2) !important;
}

.modal-body .form-control::placeholder {
    color: var(--clr-text-muted) !important;
}

.modal-footer {
    background: rgba(255, 255, 255, 0.05) !important;
    border-top: 1px solid var(--clr-border) !important;
}

/* CARDS */
.card {
    background: var(--clr-card) !important;
    border: 1px solid var(--clr-border) !important;
}

.card-header {
    background: rgba(255, 255, 255, 0.05) !important;
    border-bottom: 1px solid var(--clr-border) !important;
    color: var(--clr-text) !important;
}

.card-body {
    color: var(--clr-text) !important;
}

/* FORM CONTROLS - Hauptseite */
.form-label {
    color: var(--clr-text) !important;
    font-weight: 500;
}

.form-control,
.form-select {
    background: rgba(255, 255, 255, 0.1) !important;
    border: 1px solid var(--clr-border) !important;
    color: var(--clr-text) !important;
}

.form-control:focus,
.form-select:focus {
    background: rgba(255, 255, 255, 0.15) !important;
    border-color: var(--clr-accent) !important;
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2) !important;
}

.form-control::placeholder {
    color: var(--clr-text-muted) !important;
}

/* BREADCRUMB */
.breadcrumb-item,
.breadcrumb-item a {
    color: var(--clr-text-muted) !important;
}

.breadcrumb-item.active {
    color: var(--clr-text) !important;
}

/* TEXT UTILITIES */
.text-muted {
    color: var(--clr-text-muted) !important;
}

small.text-muted {
    color: var(--clr-text-muted) !important;
}

/* BUTTONS */
.btn-group .btn {
    border: 1px solid;
}

.btn-outline-primary {
    border-color: var(--clr-accent);
    color: var(--clr-accent);
}

.btn-outline-info {
    border-color: var(--clr-info);
    color: var(--clr-info);
}

.btn-outline-danger {
    border-color: var(--clr-danger);
    color: var(--clr-danger);
}

/* Icons */
.bi-play-circle.text-danger {
    color: var(--clr-danger) !important;
}

/* Pagination */
.pagination .page-link {
    background: var(--clr-card) !important;
    border-color: var(--clr-border) !important;
    color: var(--clr-text) !important;
}

.pagination .page-item.active .page-link {
    background: var(--clr-accent) !important;
    border-color: var(--clr-accent) !important;
    color: #ffffff !important;
}

/* Sortable Table Headers */
.table thead th a {
    color: var(--clr-text) !important;
    text-decoration: none !important;
    display: inline-flex;
    align-items: center;
    transition: var(--transition);
}

.table thead th a:hover {
    color: var(--clr-accent) !important;
}

.table thead th a i {
    margin-left: 0.25rem;
    font-size: 0.8em;
}

.table thead th a i.text-muted {
    opacity: 0.5;
}

.table thead th a:hover i.text-muted {
    opacity: 1;
    color: var(--clr-accent) !important;
}

/* TMDB MODAL - LIST GROUP ITEMS */
.list-group-item {
    background: var(--clr-card) !important;
    border: 1px solid var(--clr-border) !important;
    color: var(--clr-text) !important;
}

.list-group-item h5,
.list-group-item h6,
.list-group-item p,
.list-group-item small {
    color: var(--clr-text) !important;
}

.list-group-item .text-muted {
    color: var(--clr-text-muted) !important;
}

.list-group-item:hover {
    background: rgba(255, 255, 255, 0.05) !important;
}
</style>


<div class="container-fluid px-4">
    
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
        <li class="breadcrumb-item active" style="font-color:#ffff">Filme</li>
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
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <i class="bi bi-list"></i> <?= number_format($totalFilms) ?> Filme
                <?php if (!empty($search) || !empty($collectionType)): ?>
                    <span class="badge bg-primary">Gefiltert</span>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#tmdbImportModal">
                <i class="bi bi-cloud-download"></i> TMDb Import
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($films)): ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i> Keine Filme gefunden.
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th width="60">
                                    <a href="<?= getSortUrl('id', $sortColumn, $sortOrder, $search, $collectionType) ?>" class="text-decoration-none text-white">
                                        ID<?= getSortIcon('id', $sortColumn, $sortOrder) ?>
                                    </a>
                                </th>
                                <th width="50" class="text-center"><i class="bi bi-play-circle"></i></th>
                                <th>
                                    <a href="<?= getSortUrl('title', $sortColumn, $sortOrder, $search, $collectionType) ?>" class="text-decoration-none text-white">
                                        Titel<?= getSortIcon('title', $sortColumn, $sortOrder) ?>
                                    </a>
                                </th>
                                <th width="80">
                                    <a href="<?= getSortUrl('year', $sortColumn, $sortOrder, $search, $collectionType) ?>" class="text-decoration-none text-white">
                                        Jahr<?= getSortIcon('year', $sortColumn, $sortOrder) ?>
                                    </a>
                                </th>
                                <th>
                                    <a href="<?= getSortUrl('genre', $sortColumn, $sortOrder, $search, $collectionType) ?>" class="text-decoration-none text-white">
                                        Genre<?= getSortIcon('genre', $sortColumn, $sortOrder) ?>
                                    </a>
                                </th>
                                <th width="80">
                                    <a href="<?= getSortUrl('rating_age', $sortColumn, $sortOrder, $search, $collectionType) ?>" class="text-decoration-none text-white">
                                        FSK<?= getSortIcon('rating_age', $sortColumn, $sortOrder) ?>
                                    </a>
                                </th>
                                <th width="100">
                                    <a href="<?= getSortUrl('collection_type', $sortColumn, $sortOrder, $search, $collectionType) ?>" class="text-decoration-none text-white">
                                        Type<?= getSortIcon('collection_type', $sortColumn, $sortOrder) ?>
                                    </a>
                                </th>
                                <th width="120">
                                    <a href="<?= getSortUrl('created_at', $sortColumn, $sortOrder, $search, $collectionType) ?>" class="text-decoration-none text-white">
                                        Hinzugefügt<?= getSortIcon('created_at', $sortColumn, $sortOrder) ?>
                                    </a>
                                </th>
                                <th width="150" class="text-end">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($films as $film): ?>
                            <tr>
                                <td><?= $film['id'] ?></td>
                                <td class="text-center">
                                    <?php if (!empty($film['trailer_url'])): ?>
                                        <i class="bi bi-play-circle text-danger" title="Hat Trailer"></i>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($film['title']) ?></strong>
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
                                                class="btn btn-outline-success"
                                                onclick='updateFromTmdb(<?= $film["id"] ?>, <?= json_encode($film["title"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>, <?= $film["year"] ?>)'
                                                title="Mit TMDb aktualisieren">
                                            <i class="bi bi-arrow-repeat"></i>
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-danger"
                                                onclick='deleteFilm(<?= $film["id"] ?>, <?= json_encode($film["title"], JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
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
                            <a class="page-link" href="<?= getPaginationUrl($page - 1, $search, $collectionType, $sortColumn, $sortOrder) ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                        <?php endif; ?>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                            <a class="page-link" href="<?= getPaginationUrl($i, $search, $collectionType, $sortColumn, $sortOrder) ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>

                        <?php if ($page < $totalPages): ?>
                        <li class="page-item">
                            <a class="page-link" href="<?= getPaginationUrl($page + 1, $search, $collectionType, $sortColumn, $sortOrder) ?>">
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
                            <label for="edit_title" class="form-label text-dark">Titel *</label>
                            <input type="text" class="form-control" id="edit_title" name="title" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit_year" class="form-label text-dark">Jahr *</label>
                            <input type="number" class="form-control" id="edit_year" name="year" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_genre" class="form-label text-dark">Genre</label>
                            <input type="text" class="form-control" id="edit_genre" name="genre">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="edit_runtime" class="form-label text-dark">Laufzeit (Min)</label>
                            <input type="number" class="form-control" id="edit_runtime" name="runtime">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="edit_rating_age" class="form-label text-dark">FSK</label>
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
                            <label for="edit_collection_type" class="form-label text-dark">Collection Type *</label>
                            <select class="form-select" id="edit_collection_type" name="collection_type" required>
                                <option value="Owned">Owned</option>
                                <option value="Serie">Serie</option>
                                <option value="Stream">Stream</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_created_at" class="form-label text-dark">Hinzugefügt am</label>
                        <input type="date" class="form-control" id="edit_created_at" name="created_at">
                        <div class="form-text text-dark">Datum, an dem der Film zur Sammlung hinzugefügt wurde</div>
                    </div>

                    <div class="mb-3">
                        <label for="edit_trailer_url" class="form-label text-dark">Trailer URL</label>
                        <input type="url" class="form-control" id="edit_trailer_url" name="trailer_url" placeholder="https://youtube.com/watch?v=...">
                    </div>

                    <div class="mb-3">
                        <label for="edit_overview" class="form-label text-dark">Handlung</label>
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

<style>
/* Modal Text Lesbarkeit - SEHR spezifisch mit ID! */
#editFilmModal .modal-content {
    background-color: #ffffff !important;
    color: #212529 !important;
}

#editFilmModal .modal-header,
#editFilmModal .modal-body,
#editFilmModal .modal-footer {
    background-color: #ffffff !important;
}

#editFilmModal .modal-title {
    color: #212529 !important;
}

#editFilmModal .modal-body label,
#editFilmModal .modal-body .form-label {
    color: #212529 !important;
    font-weight: 500 !important;
}

#editFilmModal .modal-body .text-dark {
    color: #212529 !important;
}

#editFilmModal .form-control,
#editFilmModal .form-select,
#editFilmModal textarea.form-control {
    color: #212529 !important;
    background-color: #ffffff !important;
    border: 1px solid #ced4da !important;
}

#editFilmModal .form-control::placeholder {
    color: #6c757d !important;
    opacity: 0.6 !important;
}
</style>

<script>
// Edit Film
function editFilm(filmId) {
    console.log('Loading film:', filmId);
    
    // Load film data via AJAX
    fetch(`actions/get-film.php?id=${filmId}`)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));
            return response.text(); // Erstmal als Text holen
        })
        .then(text => {
            console.log('Response text:', text);
            
            // Versuche JSON zu parsen
            try {
                const film = JSON.parse(text);
                console.log('Parsed film:', film);
                
                if (film.error) {
                    alert('Fehler: ' + film.error);
                    return;
                }
                
                document.getElementById('edit_film_id').value = film.id;
                document.getElementById('edit_title').value = film.title;
                document.getElementById('edit_year').value = film.year;
                document.getElementById('edit_genre').value = film.genre || '';
                document.getElementById('edit_runtime').value = film.runtime || '';
                document.getElementById('edit_rating_age').value = film.rating_age || '';
                document.getElementById('edit_collection_type').value = film.collection_type || 'Owned';
                document.getElementById('edit_trailer_url').value = film.trailer_url || '';
                document.getElementById('edit_overview').value = film.overview || '';
                
                // created_at: Konvertiere von MySQL DATETIME zu HTML date format (YYYY-MM-DD)
                if (film.created_at) {
                    const createdDate = film.created_at.split(' ')[0]; // Nur Datum, keine Zeit
                    document.getElementById('edit_created_at').value = createdDate;
                } else {
                    document.getElementById('edit_created_at').value = '';
                }
                
                new bootstrap.Modal(document.getElementById('editFilmModal')).show();
            } catch (e) {
                console.error('JSON Parse Error:', e);
                alert('Fehler beim Parsen der Film-Daten. Server-Antwort: ' + text.substring(0, 200));
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            alert('Netzwerk-Fehler: ' + error);
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

// ============================================
// TMDb Import Modal & Funktionen
// ============================================

// TMDb Search
function tmdbSearch() {
    const title = document.getElementById('tmdb-search-title').value.trim();
    const year = document.getElementById('tmdb-search-year').value.trim();
    
    if (!title) {
        alert('Bitte Titel eingeben');
        return;
    }
    
    const searchBtn = document.getElementById('tmdb-search-btn');
    searchBtn.disabled = true;
    searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Suche...';
    
    // Ergebnis-Container
    const resultsDiv = document.getElementById('tmdb-results');
    resultsDiv.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Lädt...</span></div></div>';
    
    // AJAX Call
    fetch('actions/tmdb-search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'csrf_token': '<?= $csrfToken ?>',
            'title': title,
            'year': year
        })
    })
    .then(response => response.json())
    .then(data => {
        searchBtn.disabled = false;
        searchBtn.innerHTML = '<i class="bi bi-search"></i> Suchen';
        
        if (!data.success) {
            resultsDiv.innerHTML = `<div class="alert alert-danger">${data.error || 'Fehler bei der Suche'}</div>`;
            return;
        }
        
        if (data.count === 0) {
            resultsDiv.innerHTML = '<div class="alert alert-info">Keine Filme gefunden</div>';
            return;
        }
        
        // Ergebnisse anzeigen
        let html = `<div class="alert alert-success">${data.count} Film(e) gefunden</div>`;
        html += '<div class="list-group">';
        
        data.results.forEach(movie => {
            const posterUrl = movie.poster_path 
                ? `https://image.tmdb.org/t/p/w200${movie.poster_path}`
                : 'https://via.placeholder.com/200x300?text=Kein+Cover';
            
            const rating = movie.rating > 0 ? `⭐ ${movie.rating}` : '';
            const overview = movie.overview.length > 150 
                ? movie.overview.substring(0, 150) + '...'
                : movie.overview;
            
            html += `
                <div class="list-group-item">
                    <div class="row">
                        <div class="col-md-2">
                            <img src="${posterUrl}" class="img-fluid rounded" alt="${movie.title}">
                        </div>
                        <div class="col-md-8">
                            <h5 class="mb-1">${movie.title} (${movie.year || 'N/A'})</h5>
                            <p class="mb-1 text-muted">${movie.genre || 'Genre unbekannt'} • ${rating}</p>
                            <p class="mb-1 small">${overview || 'Keine Beschreibung verfügbar'}</p>
                        </div>
                        <div class="col-md-2 d-flex align-items-center">
                            <button class="btn btn-success btn-sm w-100" onclick="tmdbImport(${movie.tmdb_id}, '${movie.title.replace(/'/g, "\\'")}')">
                                <i class="bi bi-download"></i> Importieren
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        resultsDiv.innerHTML = html;
    })
    .catch(error => {
        searchBtn.disabled = false;
        searchBtn.innerHTML = '<i class="bi bi-search"></i> Suchen';
        resultsDiv.innerHTML = `<div class="alert alert-danger">Netzwerk-Fehler: ${error.message}</div>`;
    });
}

// TMDb Import
function tmdbImport(tmdbId, title) {
    if (!confirm(`Film "${title}" importieren?`)) {
        return;
    }
    
    const collectionType = document.getElementById('tmdb-collection-type').value;
    
    // Button disablen
    event.target.disabled = true;
    event.target.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Importiere...';
    
    // AJAX Call
    fetch('actions/tmdb-import-quick.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'csrf_token': '<?= $csrfToken ?>',
            'tmdb_id': tmdbId,
            'collection_type': collectionType
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Fehler beim Import: ' + (data.error || 'Unbekannter Fehler'));
            event.target.disabled = false;
            event.target.innerHTML = '<i class="bi bi-download"></i> Importieren';
            return;
        }
        
        // Success
        alert(data.message || 'Film erfolgreich importiert!');
        
        // Modal schließen
        const modal = bootstrap.Modal.getInstance(document.getElementById('tmdbImportModal'));
        modal.hide();
        
        // Seite neu laden
        window.location.reload();
    })
    .catch(error => {
        alert('Netzwerk-Fehler: ' + error.message);
        event.target.disabled = false;
        event.target.innerHTML = '<i class="bi bi-download"></i> Importieren';
    });
}

// === TMDb UPDATE FUNCTIONS ===
let currentUpdateFilmId = null;

function updateFromTmdb(filmId, currentTitle, currentYear) {
    currentUpdateFilmId = filmId;
    
    // Setze Werte im Modal
    document.getElementById('update-current-film').textContent = `${currentTitle} (${currentYear})`;
    document.getElementById('tmdb-update-search-title').value = currentTitle;
    document.getElementById('tmdb-update-search-year').value = currentYear;
    
    // Reset results
    document.getElementById('tmdb-update-results').innerHTML = '<div class="alert alert-info"><i class="bi bi-info-circle"></i> Suchen Sie nach dem Film in TMDb</div>';
    
    // Open modal
    new bootstrap.Modal(document.getElementById('tmdbUpdateModal')).show();
}

function tmdbUpdateSearch() {
    const title = document.getElementById('tmdb-update-search-title').value.trim();
    const year = document.getElementById('tmdb-update-search-year').value.trim();
    const searchBtn = document.getElementById('tmdb-update-search-btn');
    const resultsDiv = document.getElementById('tmdb-update-results');
    
    if (!title) {
        alert('Bitte geben Sie einen Film-Titel ein.');
        return;
    }
    
    searchBtn.disabled = true;
    searchBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Suche...';
    resultsDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Suche in TMDb...</div>';
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('actions/tmdb-search.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            csrf_token: csrfToken,
            title: title,
            year: year || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        searchBtn.disabled = false;
        searchBtn.innerHTML = '<i class="bi bi-search"></i> Suchen';
        
        if (!data.success) {
            resultsDiv.innerHTML = `<div class="alert alert-danger">Fehler: ${data.error || 'Unbekannter Fehler'}</div>`;
            return;
        }
        
        if (data.count === 0) {
            resultsDiv.innerHTML = '<div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Keine Filme gefunden.</div>';
            return;
        }
        
        let html = `<div class="alert alert-success mb-3"><i class="bi bi-check-circle"></i> ${data.count} Film(e) gefunden</div>`;
        html += '<div class="list-group">';
        
        data.results.forEach(movie => {
            const posterUrl = movie.poster_path 
                ? `https://image.tmdb.org/t/p/w200${movie.poster_path}`
                : 'https://via.placeholder.com/200x300?text=Kein+Cover';
            
            const rating = movie.rating > 0 ? `⭐ ${movie.rating}` : '';
            const overview = movie.overview.length > 150 
                ? movie.overview.substring(0, 150) + '...'
                : movie.overview;
            
            html += `
                <div class="list-group-item">
                    <div class="row">
                        <div class="col-md-2">
                            <img src="${posterUrl}" class="img-fluid rounded" alt="${movie.title}">
                        </div>
                        <div class="col-md-8">
                            <h5 class="mb-1">${movie.title} (${movie.year || 'N/A'})</h5>
                            <p class="mb-1 text-muted">${movie.genre || 'Genre unbekannt'} • ${rating}</p>
                            <p class="mb-1 small">${overview || 'Keine Beschreibung'}</p>
                        </div>
                        <div class="col-md-2 d-flex align-items-center">
                            <button class="btn btn-success btn-sm w-100" 
                                    data-film-id="${currentUpdateFilmId}" 
                                    data-tmdb-id="${movie.tmdb_id}" 
                                    data-title="${movie.title.replace(/"/g, '&quot;')}" 
                                    onclick="tmdbUpdate(this.dataset.filmId, this.dataset.tmdbId, this.dataset.title)">
                                <i class="bi bi-arrow-repeat"></i> Aktualisieren
                            </button>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        resultsDiv.innerHTML = html;
    })
    .catch(error => {
        searchBtn.disabled = false;
        searchBtn.innerHTML = '<i class="bi bi-search"></i> Suchen';
        resultsDiv.innerHTML = `<div class="alert alert-danger">Netzwerk-Fehler: ${error.message}</div>`;
    });
}

function tmdbUpdate(filmId, tmdbId, title) {
    if (!confirm(`Film mit TMDb-Daten aktualisieren?\n\nFilm: ${title}\n\nCover, Trailer, Schauspieler werden überschrieben!`)) {
        return;
    }
    
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    const resultsDiv = document.getElementById('tmdb-update-results');
    
    resultsDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split"></i> Film wird aktualisiert...</div>';
    
    fetch('actions/tmdb-update-film.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: csrfToken, film_id: filmId, tmdb_id: tmdbId })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            alert('Fehler: ' + (data.error || 'Unbekannter Fehler'));
            resultsDiv.innerHTML = `<div class="alert alert-danger">Fehler: ${data.error}</div>`;
            return;
        }
        
        resultsDiv.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> Film erfolgreich aktualisiert!</div>';
        
        setTimeout(() => {
            const modal = bootstrap.Modal.getInstance(document.getElementById('tmdbUpdateModal'));
            if (modal) modal.hide();
            location.reload();
        }, 1000);
    })
    .catch(error => {
        alert('Netzwerk-Fehler: ' + error.message);
        resultsDiv.innerHTML = `<div class="alert alert-danger">Netzwerk-Fehler: ${error.message}</div>`;
    });
}

// Enter-Taste im Suchfeld
document.addEventListener('DOMContentLoaded', function() {
    const titleInput = document.getElementById('tmdb-search-title');
    const yearInput = document.getElementById('tmdb-search-year');
    
    if (titleInput) {
        titleInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                tmdbSearch();
            }
        });
    }
    
    if (yearInput) {
        yearInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                tmdbSearch();
            }
        });
    }
});
</script>

<!-- TMDb Import Modal -->
<div class="modal fade" id="tmdbImportModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cloud-download"></i> Film von TMDb importieren</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Suchformular -->
                <div class="row mb-4">
                    <div class="col-md-5">
                        <label for="tmdb-search-title" class="form-label">Film-Titel</label>
                        <input type="text" class="form-control" id="tmdb-search-title" placeholder="z.B. Bloody Mary">
                    </div>
                    <div class="col-md-2">
                        <label for="tmdb-search-year" class="form-label">Jahr (optional)</label>
                        <input type="number" class="form-control" id="tmdb-search-year" placeholder="2006">
                    </div>
                    <div class="col-md-3">
                        <label for="tmdb-collection-type" class="form-label">Collection Type</label>
                        <select class="form-select" id="tmdb-collection-type">
                            <option value="Owned" selected>Owned</option>
                            <option value="Serie">Serie</option>
                            <option value="Stream">Stream</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary w-100" id="tmdb-search-btn" onclick="tmdbSearch()">
                            <i class="bi bi-search"></i> Suchen
                        </button>
                    </div>
                </div>
                
                <!-- Ergebnisse -->
                <div id="tmdb-results">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Geben Sie einen Film-Titel ein und klicken Sie auf "Suchen"
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<!-- TMDb Update Modal -->
<div class="modal fade" id="tmdbUpdateModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Film mit TMDb aktualisieren</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info mb-4">
                    <strong>Aktueller Film:</strong> <span id="update-current-film"></span>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="tmdb-update-search-title" class="form-label">Film-Titel</label>
                        <input type="text" class="form-control" id="tmdb-update-search-title" placeholder="z.B. Matrix">
                    </div>
                    <div class="col-md-3">
                        <label for="tmdb-update-search-year" class="form-label">Jahr (optional)</label>
                        <input type="number" class="form-control" id="tmdb-update-search-year" placeholder="1999">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary w-100" id="tmdb-update-search-btn" onclick="tmdbUpdateSearch()">
                            <i class="bi bi-search"></i> Suchen
                        </button>
                    </div>
                </div>
                
                <div id="tmdb-update-results">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Suchen Sie nach dem Film in TMDb
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
            </div>
        </div>
    </div>
</div>
</script>