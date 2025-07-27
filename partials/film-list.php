<?php
/**
 * Partials/film-list.php - Vollständig migriert auf neues Core-System
 * Hauptliste der Filme mit erweiterten Such- und Filterfunktionen
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+ - Core Integration
 */

declare(strict_types=1);

try {
    // Core-System sollte bereits durch index.php geladen sein
    if (!class_exists('DVDProfiler\\Core\\Application')) {
        require_once __DIR__ . '/../includes/bootstrap.php';
    }
    
    // Application-Instance abrufen
    $app = \DVDProfiler\Core\Application::getInstance();
    $database = $app->getDatabase();
    $security = $app->getSecurity();
    $validation = new \DVDProfiler\Core\Validation();
    
    // Rate-Limiting für Film-Liste (weniger streng als andere APIs)
    $clientIP = $security::getClientIP();
    if (!$app->checkRateLimit("filmlist_{$clientIP}", 300, 300)) {
        http_response_code(429);
        throw new Exception('Zu viele Suchanfragen. Bitte warten Sie 5 Minuten.');
    }
    
    // Performance-Start-Zeit
    $startTime = microtime(true);
    
} catch (Exception $e) {
    error_log("[DVDProfiler:ERROR] Film-List Initialization: " . $e->getMessage());
    $error_message = 'Fehler beim Laden der Film-Liste: ' . $e->getMessage();
}

/**
 * Helper-Funktionen für Film-Liste
 */

function renderFilmCard(array $dvd, bool $isChild = false, array $userContext = []): string {
    // Sichere Werte extrahieren
    $title = htmlspecialchars($dvd['title'] ?? 'Unbekannt', ENT_QUOTES, 'UTF-8');
    $year = (int)($dvd['year'] ?? 0);
    $genre = htmlspecialchars($dvd['genre'] ?? 'Unbekannt', ENT_QUOTES, 'UTF-8');
    $id = (int)($dvd['id'] ?? 0);
    $runtime = (int)($dvd['runtime'] ?? 0);
    $ratingAge = (int)($dvd['rating_age'] ?? 0);
    $collectionType = htmlspecialchars($dvd['collection_type'] ?? '', ENT_QUOTES, 'UTF-8');
    $viewCount = (int)($dvd['view_count'] ?? 0);
    
    // Cover-Pfad finden
    $cover = findCoverImage($dvd['cover_id'] ?? '');
    
    // BoxSet-Informationen
    $boxsetParent = (int)($dvd['boxset_parent'] ?? 0);
    $boxsetChildCount = (int)($dvd['boxset_children_count'] ?? 0);
    $isBoxsetParent = $boxsetChildCount > 0;
    $isBoxsetChild = $boxsetParent > 0;
    
    // User-spezifische Informationen
    $isWatched = in_array($id, $userContext['watchedFilms'] ?? []);
    $userRating = $userContext['userRatings'][$id] ?? 0;
    $isFavorite = in_array($id, $userContext['favorites'] ?? []);
    
    // CSS-Klassen
    $cssClasses = ['dvd'];
    if ($isBoxsetParent) $cssClasses[] = 'boxset-parent';
    if ($isBoxsetChild) $cssClasses[] = 'boxset-child';
    if ($isWatched) $cssClasses[] = 'watched';
    if ($isFavorite) $cssClasses[] = 'favorite';
    if ($isChild) $cssClasses[] = 'child-film';
    
    $cssClassStr = implode(' ', $cssClasses);
    
    // HTML generieren
    $html = '<div class="' . $cssClassStr . '" data-dvd-id="' . $id . '" data-title="' . $title . '" data-year="' . $year . '">';
    
    // Cover-Bereich
    $html .= '<div class="cover-area">';
    $html .= '<img src="' . htmlspecialchars($cover) . '" alt="Cover von ' . $title . '" loading="lazy" onerror="this.src=\'cover/placeholder.png\'">';
    
    // Overlay-Badges
    if ($isWatched) {
        $html .= '<div class="watched-badge" title="Bereits gesehen"><i class="bi bi-check-circle-fill"></i></div>';
    }
    if ($isFavorite) {
        $html .= '<div class="favorite-badge" title="Favorit"><i class="bi bi-heart-fill"></i></div>';
    }
    if ($isBoxsetParent) {
        $html .= '<div class="boxset-badge" title="BoxSet mit ' . $boxsetChildCount . ' Filmen"><i class="bi bi-collection"></i> ' . $boxsetChildCount . '</div>';
    }
    if ($ratingAge > 0) {
        $html .= '<div class="rating-badge">FSK ' . $ratingAge . '</div>';
    }
    
    $html .= '</div>'; // Ende cover-area
    
    // Film-Details
    $html .= '<div class="dvd-details">';
    $html .= '<h2 class="title-year">';
    $html .= '<a href="#" class="toggle-detail" data-id="' . $id . '" aria-label="Details für ' . $title . ' anzeigen">';
    $html .= $title;
    $html .= '</a>';
    if ($year > 0) {
        $html .= '<span class="year">(' . $year . ')</span>';
    }
    $html .= '</h2>';
    
    // Genre und Typ
    $html .= '<p><strong>Genre:</strong> ' . $genre;
    if ($collectionType) {
        $html .= ' <span class="collection-type">• ' . $collectionType . '</span>';
    }
    $html .= '</p>';
    
    // Zusätzliche Informationen
    if ($runtime > 0 || $viewCount > 0 || $userRating > 0) {
        $html .= '<div class="quick-info">';
        
        if ($runtime > 0) {
            $hours = intdiv($runtime, 60);
            $minutes = $runtime % 60;
            $runtimeStr = $hours > 0 ? "{$hours}h {$minutes}min" : "{$minutes}min";
            $html .= '<span class="runtime"><i class="bi bi-clock"></i> ' . $runtimeStr . '</span>';
        }
        
        if ($viewCount > 0) {
            $html .= '<span class="views"><i class="bi bi-eye"></i> ' . number_format($viewCount) . '</span>';
        }
        
        if ($userRating > 0) {
            $html .= '<span class="user-rating"><i class="bi bi-star-fill"></i> ' . $userRating . '/5</span>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>'; // Ende dvd-details
    
    // Quick-Actions (nur für eingeloggte User)
    if (!empty($userContext['isLoggedIn'])) {
        $html .= '<div class="quick-actions">';
        
        // Watch-Toggle
        $watchedIcon = $isWatched ? 'bi-check-circle-fill' : 'bi-check-circle';
        $watchedTitle = $isWatched ? 'Als nicht gesehen markieren' : 'Als gesehen markieren';
        $html .= '<button class="quick-btn watch-btn ' . ($isWatched ? 'active' : '') . '" data-film-id="' . $id . '" data-action="toggle-watched" title="' . $watchedTitle . '">';
        $html .= '<i class="bi ' . $watchedIcon . '"></i>';
        $html .= '</button>';
        
        // Favorite-Toggle
        $favoriteIcon = $isFavorite ? 'bi-heart-fill' : 'bi-heart';
        $favoriteTitle = $isFavorite ? 'Aus Favoriten entfernen' : 'Zu Favoriten hinzufügen';
        $html .= '<button class="quick-btn favorite-btn ' . ($isFavorite ? 'active' : '') . '" data-film-id="' . $id . '" data-action="toggle-favorite" title="' . $favoriteTitle . '">';
        $html .= '<i class="bi ' . $favoriteIcon . '"></i>';
        $html .= '</button>';
        
        // Quick-Rating
        $html .= '<button class="quick-btn rating-btn" data-film-id="' . $id . '" data-action="quick-rating" title="Schnell bewerten">';
        $html .= '<i class="bi bi-star' . ($userRating > 0 ? '-fill' : '') . '"></i>';
        $html .= '</button>';
        
        $html .= '</div>';
    }
    
    $html .= '</div>'; // Ende dvd
    
    return $html;
}

function buildQuery(array $params = []): string {
    $currentParams = $_GET;
    foreach ($params as $key => $value) {
        if ($value === '' || $value === null) {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $value;
        }
    }
    return http_build_query($currentParams);
}

function findCoverImage(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string {
    if (empty($coverId)) return $fallback;
    
    $extensions = ['.jpg', '.jpeg', '.png', '.webp'];
    foreach ($extensions as $ext) {
        $file = "{$folder}/{$coverId}{$suffix}{$ext}";
        if (file_exists($file)) {
            return $file;
        }
    }
    return $fallback;
}

// Input-Validierung mit Core-System
try {
    $validator = $validation::make($_GET, [
        'q' => 'string|max:100',
        'type' => 'string|max:50|alpha_dash',
        'genre' => 'string|max:50',
        'year' => 'integer|min:1900|max:' . ((int)date('Y') + 5),
        'seite' => 'integer|min:1|max:1000',
        'sort' => 'string|in:title,year,created_at,view_count,rating_age',
        'order' => 'string|in:asc,desc',
        'view' => 'string|in:grid,list',
        'per_page' => 'integer|min:6|max:100'
    ]);
    
    if ($validator->hasErrors()) {
        throw new InvalidArgumentException('Ungültige Suchparameter: ' . implode(', ', array_values($validator->getErrors())));
    }
    
    $validatedData = $validator->getValidatedData();
    
    // Validierte Parameter extrahieren
    $search = trim($validatedData['q'] ?? '');
    $type = trim($validatedData['type'] ?? '');
    $genre = trim($validatedData['genre'] ?? '');
    $year = $validatedData['year'] ?? null;
    $page = $validatedData['seite'] ?? 1;
    $sort = $validatedData['sort'] ?? 'title';
    $order = $validatedData['order'] ?? 'asc';
    $viewMode = $validatedData['view'] ?? 'grid';
    $perPage = $validatedData['per_page'] ?? $app->getSettings()->getInt('items_per_page', 20);
    
    $offset = ($page - 1) * $perPage;
    
} catch (Exception $e) {
    error_log("[DVDProfiler:ERROR] Film-List Input Validation: " . $e->getMessage());
    // Fallback zu sicheren Standardwerten
    $search = $type = $genre = '';
    $year = null;
    $page = 1;
    $sort = 'title';
    $order = 'asc';
    $viewMode = 'grid';
    $perPage = 20;
    $offset = 0;
}

// Daten laden mit Core-Database-System
try {
    // Filter-Optionen laden (für Dropdown-Menüs)
    $filterOptions = [];
    
    // Collection Types
    $filterOptions['types'] = $database->fetchAll("
        SELECT DISTINCT collection_type, COUNT(*) as count 
        FROM dvds 
        WHERE collection_type IS NOT NULL AND collection_type != ''
        GROUP BY collection_type 
        ORDER BY collection_type ASC
    ");
    
    // Genres
    $filterOptions['genres'] = $database->fetchAll("
        SELECT DISTINCT TRIM(genre) as genre, COUNT(*) as count 
        FROM dvds 
        WHERE genre IS NOT NULL AND genre != '' AND genre != 'NULL'
        GROUP BY TRIM(genre) 
        ORDER BY TRIM(genre) ASC
    ");
    
    // Jahre (letzte 20 Jahre + alle verfügbaren)
    $currentYear = (int)date('Y');
    $filterOptions['years'] = $database->fetchAll("
        SELECT DISTINCT year, COUNT(*) as count 
        FROM dvds 
        WHERE year > 0 
        GROUP BY year 
        ORDER BY year DESC
    ");
    
    // WHERE-Bedingungen aufbauen
    $whereConditions = ['1=1'];
    $queryParams = [];
    
    if (!empty($search)) {
        $whereConditions[] = "(title LIKE ? OR genre LIKE ? OR collection_type LIKE ?)";
        $searchTerm = "%{$search}%";
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
        $queryParams[] = $searchTerm;
    }
    
    if (!empty($type)) {
        $whereConditions[] = "collection_type = ?";
        $queryParams[] = $type;
    }
    
    if (!empty($genre)) {
        $whereConditions[] = "TRIM(genre) = ?";
        $queryParams[] = $genre;
    }
    
    if ($year !== null) {
        $whereConditions[] = "year = ?";
        $queryParams[] = $year;
    }
    
    $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
    
    // Sortierung validieren und anwenden
    $validSorts = [
        'title' => 'title ASC',
        'year' => 'year DESC, title ASC',
        'created_at' => 'created_at DESC, title ASC',
        'view_count' => 'COALESCE(view_count, 0) DESC, title ASC',
        'rating_age' => 'COALESCE(rating_age, 0) ASC, title ASC'
    ];
    
    $orderClause = $validSorts[$sort] ?? 'title ASC';
    if ($order === 'desc' && $sort === 'title') {
        $orderClause = 'title DESC';
    }
    
    // Gesamtanzahl ermitteln
    $total = (int)$database->fetchValue("
        SELECT COUNT(*) 
        FROM dvds 
        {$whereClause}
    ", $queryParams);
    
    $totalPages = (int)ceil($total / $perPage);
    
    // Pagination validieren
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }
    
    // Filme laden mit erweiterten Informationen
    $films = $database->fetchAll("
        SELECT d.*, 
               u.email as added_by_user,
               (SELECT COUNT(*) FROM dvds WHERE boxset_parent = d.id) as boxset_children_count,
               COALESCE(d.view_count, 0) as view_count,
               d.created_at,
               d.updated_at
        FROM dvds d 
        LEFT JOIN users u ON d.user_id = u.id 
        {$whereClause}
        ORDER BY {$orderClause}
        LIMIT ? OFFSET ?
    ", array_merge($queryParams, [$perPage, $offset]));
    
    // User-spezifische Daten laden (falls eingeloggt)
    $userContext = ['isLoggedIn' => false];
    
    if (isset($_SESSION['user_id']) && !empty($films)) {
        $userContext['isLoggedIn'] = true;
        $filmIds = array_column($films, 'id');
        $placeholders = str_repeat('?,', count($filmIds) - 1) . '?';
        
        // Watched-Status
        if ($database->tableExists('user_watched')) {
            $watchedData = $database->fetchAll("
                SELECT film_id FROM user_watched 
                WHERE user_id = ? AND film_id IN ({$placeholders})
            ", array_merge([$_SESSION['user_id']], $filmIds));
            $userContext['watchedFilms'] = array_column($watchedData, 'film_id');
        }
        
        // User-Ratings
        if ($database->tableExists('user_ratings')) {
            $ratingData = $database->fetchAll("
                SELECT film_id, rating FROM user_ratings 
                WHERE user_id = ? AND film_id IN ({$placeholders})
            ", array_merge([$_SESSION['user_id']], $filmIds));
            
            $userContext['userRatings'] = [];
            foreach ($ratingData as $rating) {
                $userContext['userRatings'][$rating['film_id']] = (float)$rating['rating'];
            }
        }
        
        // Favorites (falls Tabelle existiert)
        if ($database->tableExists('user_favorites')) {
            $favoriteData = $database->fetchAll("
                SELECT film_id FROM user_favorites 
                WHERE user_id = ? AND film_id IN ({$placeholders})
            ", array_merge([$_SESSION['user_id']], $filmIds));
            $userContext['favorites'] = array_column($favoriteData, 'film_id');
        }
    }
    
    // Performance-Logging (Development)
    if ($app->getSettings()->get('environment') === 'development') {
        $loadTime = microtime(true) - $startTime;
        error_log("[DVDProfiler:INFO] Film-List: Geladen in " . round($loadTime * 1000, 2) . "ms - {$total} Filme, Seite {$page}/{$totalPages}");
    }
    
} catch (Exception $e) {
    error_log('[DVDProfiler:ERROR] Film-List Database Error: ' . $e->getMessage());
    $error_message = 'Fehler beim Laden der Filme: ' . $e->getMessage();
    
    // Fallback-Werte
    $filterOptions = ['types' => [], 'genres' => [], 'years' => []];
    $films = [];
    $total = 0;
    $totalPages = 0;
    $userContext = ['isLoggedIn' => false];
}
?>

<!-- Film-Liste Container -->
<div class="film-list-container">
    
    <?php if (isset($error_message)): ?>
        <div class="error-message" style="
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 2rem;
            color: #fff;
        ">
            <i class="bi bi-exclamation-triangle"></i>
            <?= htmlspecialchars($error_message) ?>
        </div>
    <?php endif; ?>
    
    <!-- Erweiterte Filter & Suche -->
    <section class="film-filters" aria-label="Film-Filter">
        <form class="filter-form" method="get" action="">
            <div class="filter-row">
                <!-- Suchfeld -->
                <div class="search-field">
                    <label for="search-input" class="sr-only">Film suchen</label>
                    <div class="input-group">
                        <input type="text" 
                               id="search-input" 
                               name="q" 
                               placeholder="Film suchen..." 
                               value="<?= htmlspecialchars($search) ?>"
                               maxlength="100">
                        <button type="submit" class="search-btn" aria-label="Suchen">
                            <i class="bi bi-search"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Collection Type Filter -->
                <div class="filter-select">
                    <label for="type-select" class="sr-only">Medientyp</label>
                    <select id="type-select" name="type" onchange="this.form.submit()">
                        <option value="">Alle Medientypen</option>
                        <?php foreach ($filterOptions['types'] as $typeOption): ?>
                            <option value="<?= htmlspecialchars($typeOption['collection_type']) ?>" 
                                    <?= $type === $typeOption['collection_type'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($typeOption['collection_type']) ?> (<?= $typeOption['count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Genre Filter -->
                <div class="filter-select">
                    <label for="genre-select" class="sr-only">Genre</label>
                    <select id="genre-select" name="genre" onchange="this.form.submit()">
                        <option value="">Alle Genres</option>
                        <?php foreach ($filterOptions['genres'] as $genreOption): ?>
                            <option value="<?= htmlspecialchars($genreOption['genre']) ?>" 
                                    <?= $genre === $genreOption['genre'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($genreOption['genre']) ?> (<?= $genreOption['count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Jahr Filter -->
                <div class="filter-select">
                    <label for="year-select" class="sr-only">Erscheinungsjahr</label>
                    <select id="year-select" name="year" onchange="this.form.submit()">
                        <option value="">Alle Jahre</option>
                        <?php foreach ($filterOptions['years'] as $yearOption): ?>
                            <option value="<?= $yearOption['year'] ?>" 
                                    <?= $year == $yearOption['year'] ? 'selected' : '' ?>>
                                <?= $yearOption['year'] ?> (<?= $yearOption['count'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Erweiterte Optionen -->
            <div class="filter-row advanced-filters">
                <!-- Sortierung -->
                <div class="sort-controls">
                    <label for="sort-select" class="sr-only">Sortierung</label>
                    <select id="sort-select" name="sort" onchange="this.form.submit()">
                        <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Titel</option>
                        <option value="year" <?= $sort === 'year' ? 'selected' : '' ?>>Jahr</option>
                        <option value="created_at" <?= $sort === 'created_at' ? 'selected' : '' ?>>Hinzugefügt</option>
                        <option value="view_count" <?= $sort === 'view_count' ? 'selected' : '' ?>>Beliebtheit</option>
                        <option value="rating_age" <?= $sort === 'rating_age' ? 'selected' : '' ?>>Altersfreigabe</option>
                    </select>
                    
                    <select name="order" onchange="this.form.submit()">
                        <option value="asc" <?= $order === 'asc' ? 'selected' : '' ?>>Aufsteigend</option>
                        <option value="desc" <?= $order === 'desc' ? 'selected' : '' ?>>Absteigend</option>
                    </select>
                </div>
                
                <!-- Anzahl pro Seite -->
                <div class="per-page-controls">
                    <label for="per-page-select" class="sr-only">Filme pro Seite</label>
                    <select id="per-page-select" name="per_page" onchange="this.form.submit()">
                        <option value="12" <?= $perPage === 12 ? 'selected' : '' ?>>12 pro Seite</option>
                        <option value="20" <?= $perPage === 20 ? 'selected' : '' ?>>20 pro Seite</option>
                        <option value="36" <?= $perPage === 36 ? 'selected' : '' ?>>36 pro Seite</option>
                        <option value="60" <?= $perPage === 60 ? 'selected' : '' ?>>60 pro Seite</option>
                    </select>
                </div>
                
                <!-- View Mode Toggle -->
                <div class="view-toggle">
                    <label class="sr-only">Ansichtsmodus</label>
                    <div class="toggle-group">
                        <button type="button" 
                                class="toggle-btn <?= $viewMode === 'grid' ? 'active' : '' ?>" 
                                data-view="grid"
                                title="Gitter-Ansicht">
                            <i class="bi bi-grid-3x3"></i>
                        </button>
                        <button type="button" 
                                class="toggle-btn <?= $viewMode === 'list' ? 'active' : '' ?>" 
                                data-view="list"
                                title="Listen-Ansicht">
                            <i class="bi bi-list"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Reset Filter -->
                <?php if (!empty($search) || !empty($type) || !empty($genre) || $year !== null): ?>
                    <div class="reset-filters">
                        <a href="?" class="btn btn-outline">
                            <i class="bi bi-x-circle"></i>
                            Filter zurücksetzen
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Versteckte Felder für Navigation -->
            <input type="hidden" name="view" value="<?= htmlspecialchars($viewMode) ?>">
        </form>
        
        <!-- Filter-Info -->
        <div class="filter-info">
            <span class="result-count">
                <strong><?= number_format($total) ?></strong> 
                <?= $total === 1 ? 'Film' : 'Filme' ?> gefunden
                <?php if ($total > 0): ?>
                    <span class="page-info">
                        (Seite <?= $page ?> von <?= $totalPages ?>)
                    </span>
                <?php endif; ?>
            </span>
            
            <?php if (!empty($search) || !empty($type) || !empty($genre) || $year !== null): ?>
                <div class="active-filters">
                    <span class="filter-label">Aktive Filter:</span>
                    <?php if (!empty($search)): ?>
                        <span class="filter-tag">
                            Suche: "<?= htmlspecialchars($search) ?>"
                            <a href="?<?= buildQuery(['q' => '']) ?>" class="remove-filter" title="Entfernen">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($type)): ?>
                        <span class="filter-tag">
                            Typ: <?= htmlspecialchars($type) ?>
                            <a href="?<?= buildQuery(['type' => '']) ?>" class="remove-filter" title="Entfernen">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($genre)): ?>
                        <span class="filter-tag">
                            Genre: <?= htmlspecialchars($genre) ?>
                            <a href="?<?= buildQuery(['genre' => '']) ?>" class="remove-filter" title="Entfernen">×</a>
                        </span>
                    <?php endif; ?>
                    <?php if ($year !== null): ?>
                        <span class="filter-tag">
                            Jahr: <?= $year ?>
                            <a href="?<?= buildQuery(['year' => '']) ?>" class="remove-filter" title="Entfernen">×</a>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Film-Liste -->
    <section class="film-list-section" aria-label="Film-Ergebnisse">
        <div class="film-list <?= $viewMode === 'list' ? 'list-mode' : 'grid-mode' ?>">
            <?php if (empty($films)): ?>
                <div class="empty-state">
                    <i class="bi bi-film"></i>
                    <h3>Keine Filme gefunden</h3>
                    <p>
                        <?php if (!empty($search)): ?>
                            Keine Filme gefunden für "<strong><?= htmlspecialchars($search) ?></strong>".
                        <?php elseif (!empty($type)): ?>
                            Keine Filme vom Typ "<strong><?= htmlspecialchars($type) ?></strong>" gefunden.
                        <?php elseif (!empty($genre)): ?>
                            Keine Filme im Genre "<strong><?= htmlspecialchars($genre) ?></strong>" gefunden.
                        <?php elseif ($year !== null): ?>
                            Keine Filme aus dem Jahr <strong><?= $year ?></strong> gefunden.
                        <?php else: ?>
                            Noch keine Filme in der Sammlung vorhanden.
                        <?php endif; ?>
                    </p>
                    
                    <?php if (!empty($search) || !empty($type) || !empty($genre) || $year !== null): ?>
                        <a href="?" class="btn btn-primary">
                            <i class="bi bi-arrow-left"></i>
                            Alle Filme anzeigen
                        </a>
                    <?php elseif (isset($_SESSION['user_id'])): ?>
                        <a href="admin/" class="btn btn-primary">
                            <i class="bi bi-plus-circle"></i>
                            Ersten Film hinzufügen
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php foreach ($films as $film): ?>
                    <?= renderFilmCard($film, false, $userContext) ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
    
    <!-- Erweiterte Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="pagination-nav" aria-label="Seitennavigation">
            <div class="pagination-wrapper">
                <div class="pagination">
                    <!-- Erste Seite / Vorherige -->
                    <?php if ($page > 1): ?>
                        <a href="?<?= buildQuery(['seite' => 1]) ?>" 
                           class="page-link page-arrow" 
                           title="Erste Seite"
                           aria-label="Erste Seite">
                            <i class="bi bi-chevron-double-left"></i>
                        </a>
                        <a href="?<?= buildQuery(['seite' => $page - 1]) ?>" 
                           class="page-link page-arrow" 
                           title="Vorherige Seite"
                           aria-label="Vorherige Seite">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <!-- Seitenzahlen -->
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    // Punkte am Anfang
                    if ($start > 1): ?>
                        <a href="?<?= buildQuery(['seite' => 1]) ?>" class="page-link">1</a>
                        <?php if ($start > 2): ?>
                            <span class="page-dots">...</span>
                        <?php endif; ?>
                    <?php endif;
                    
                    // Aktuelle Seitenbereich
                    for ($i = $start; $i <= $end; $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="page-link current" aria-current="page"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?<?= buildQuery(['seite' => $i]) ?>" class="page-link"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor;
                    
                    // Punkte am Ende
                    if ($end < $totalPages): ?>
                        <?php if ($end < $totalPages - 1): ?>
                            <span class="page-dots">...</span>
                        <?php endif; ?>
                        <a href="?<?= buildQuery(['seite' => $totalPages]) ?>" class="page-link"><?= $totalPages ?></a>
                    <?php endif; ?>
                    
                    <!-- Nächste / Letzte Seite -->
                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= buildQuery(['seite' => $page + 1]) ?>" 
                           class="page-link page-arrow" 
                           title="Nächste Seite"
                           aria-label="Nächste Seite">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                        <a href="?<?= buildQuery(['seite' => $totalPages]) ?>" 
                           class="page-link page-arrow" 
                           title="Letzte Seite"
                           aria-label="Letzte Seite">
                            <i class="bi bi-chevron-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination-Info -->
                <div class="pagination-info">
                    Seite <?= $page ?> von <?= $totalPages ?> 
                    <span class="separator">•</span>
                    <?= number_format($total) ?> Filme gesamt
                    <?php if ($perPage < $total): ?>
                        <span class="separator">•</span>
                        <?= number_format(min($perPage, $total - $offset)) ?> angezeigt
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 Film-List (Core-System) geladen mit', <?= count($films) ?>, 'Filmen');
    
    try {
        // View Mode Toggle
        document.querySelectorAll('.toggle-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const view = this.dataset.view;
                const url = new URL(window.location);
                url.searchParams.set('view', view);
                window.location.href = url.toString();
            });
        });
        
        // Quick Actions für eingeloggte User
        <?php if ($userContext['isLoggedIn']): ?>
            document.querySelectorAll('.quick-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const action = this.dataset.action;
                    const filmId = this.dataset.filmId;
                    
                    if (!filmId) return;
                    
                    switch (action) {
                        case 'toggle-watched':
                            toggleWatchedStatus(filmId, this);
                            break;
                        case 'toggle-favorite':
                            toggleFavoriteStatus(filmId, this);
                            break;
                        case 'quick-rating':
                            showQuickRating(filmId, this);
                            break;
                    }
                });
            });
            
            // Watch-Status Toggle
            function toggleWatchedStatus(filmId, button) {
                fetch('api/toggle-watched.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({film_id: parseInt(filmId)})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // UI aktualisieren
                    const icon = button.querySelector('i');
                    const card = button.closest('.dvd');
                    
                    if (data.watched) {
                        icon.className = 'bi bi-check-circle-fill';
                        button.classList.add('active');
                        card.classList.add('watched');
                        button.title = 'Als nicht gesehen markieren';
                    } else {
                        icon.className = 'bi bi-check-circle';
                        button.classList.remove('active');
                        card.classList.remove('watched');
                        button.title = 'Als gesehen markieren';
                    }
                    
                    if (window.showToast) {
                        window.showToast(data.message, 'success');
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Markieren:', error);
                    if (window.showToast) {
                        window.showToast('Fehler beim Markieren des Films', 'error');
                    }
                });
            }
            
            // Favorite-Status Toggle
            function toggleFavoriteStatus(filmId, button) {
                fetch('api/toggle-favorite.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({film_id: parseInt(filmId)})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // UI aktualisieren
                    const icon = button.querySelector('i');
                    const card = button.closest('.dvd');
                    
                    if (data.favorite) {
                        icon.className = 'bi bi-heart-fill';
                        button.classList.add('active');
                        card.classList.add('favorite');
                        button.title = 'Aus Favoriten entfernen';
                    } else {
                        icon.className = 'bi bi-heart';
                        button.classList.remove('active');
                        card.classList.remove('favorite');
                        button.title = 'Zu Favoriten hinzufügen';
                    }
                    
                    if (window.showToast) {
                        window.showToast(data.message, 'success');
                    }
                })
                .catch(error => {
                    console.error('Fehler bei Favoriten:', error);
                    if (window.showToast) {
                        window.showToast('Fehler beim Ändern der Favoriten', 'error');
                    }
                });
            }
            
            // Quick Rating
            function showQuickRating(filmId, button) {
                const rating = prompt('Bewertung für diesen Film (1-5 Sterne):', '');
                
                if (rating && rating >= 1 && rating <= 5) {
                    fetch('api/save-rating.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify({
                            film_id: parseInt(filmId),
                            rating: parseFloat(rating)
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            throw new Error(data.error);
                        }
                        
                        // Button-Icon aktualisieren
                        const icon = button.querySelector('i');
                        icon.className = 'bi bi-star-fill';
                        
                        if (window.showToast) {
                            window.showToast(`Film mit ${rating} Sternen bewertet!`, 'success');
                        }
                    })
                    .catch(error => {
                        console.error('Fehler beim Bewerten:', error);
                        if (window.showToast) {
                            window.showToast('Fehler beim Bewerten des Films', 'error');
                        }
                    });
                }
            }
        <?php endif; ?>
        
        // Keyboard Navigation
        document.addEventListener('keydown', function(e) {
            if (e.target.matches('input, textarea, select')) return;
            
            if (e.key === 'ArrowLeft' && <?= $page ?> > 1) {
                window.location.href = '?<?= buildQuery(['seite' => $page - 1]) ?>';
            } else if (e.key === 'ArrowRight' && <?= $page ?> < <?= $totalPages ?>) {
                window.location.href = '?<?= buildQuery(['seite' => $page + 1]) ?>';
            } else if (e.key === 'f' && !e.ctrlKey && !e.metaKey) {
                document.getElementById('search-input')?.focus();
                e.preventDefault();
            }
        });
        
        // Search Input Enhancement
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (this.value.length >= 3 || this.value.length === 0) {
                        this.form.submit();
                    }
                }, 500);
            });
        }
        
        // Performance-Info (Development)
        <?php if ($app->getSettings()->get('environment') === 'development'): ?>
            console.log('📊 Film-List Performance:', {
                'loadTime': '<?= round((microtime(true) - $startTime) * 1000, 2) ?>ms',
                'totalFilms': <?= $total ?>,
                'displayedFilms': <?= count($films) ?>,
                'currentPage': <?= $page ?>,
                'totalPages': <?= $totalPages ?>,
                'memoryUsage': '<?= \DVDProfiler\Core\Utils::formatBytes(memory_get_peak_usage(true)) ?>'
            });
        <?php endif; ?>
        
    } catch (error) {
        console.error('❌ Film-List JavaScript-Fehler:', error);
    }
});
</script>

<style>
/* FILM-LIST - CORE-SYSTEM OPTIMIERTE STYLES */

.film-list-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

/* Filter Section */
.film-filters {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    backdrop-filter: blur(10px);
}

.filter-form {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.filter-row {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.search-field {
    flex: 1;
    min-width: 200px;
}

.input-group {
    display: flex;
    position: relative;
}

.input-group input {
    flex: 1;
    padding: 0.75rem 1rem;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 8px 0 0 8px;
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    font-size: 0.9rem;
}

.input-group input:focus {
    outline: none;
    border-color: var(--primary-color, #007bff);
    box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.25);
}

.search-btn {
    padding: 0.75rem 1rem;
    background: var(--primary-color, #007bff);
    border: 1px solid var(--primary-color, #007bff);
    border-radius: 0 8px 8px 0;
    color: white;
    cursor: pointer;
    transition: background-color 0.2s;
}

.search-btn:hover {
    background: var(--primary-dark, #0056b3);
}

.filter-select select {
    padding: 0.75rem;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 8px;
    background: rgba(255, 255, 255, 0.05);
    color: #fff;
    font-size: 0.9rem;
    min-width: 120px;
}

.advanced-filters {
    padding-top: 1rem;
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
}

.sort-controls, .per-page-controls {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.view-toggle .toggle-group {
    display: flex;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    overflow: hidden;
}

.toggle-btn {
    padding: 0.75rem;
    border: none;
    background: transparent;
    color: rgba(255, 255, 255, 0.7);
    cursor: pointer;
    transition: all 0.2s;
}

.toggle-btn.active {
    background: var(--primary-color, #007bff);
    color: white;
}

.toggle-btn:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* Filter Info */
.filter-info {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.1));
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 1rem;
}

.result-count {
    color: var(--text-white, #fff);
    font-size: 0.9rem;
}

.page-info {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 0.85rem;
}

.active-filters {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.filter-label {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 0.85rem;
    margin-right: 0.5rem;
}

.filter-tag {
    background: var(--primary-color, #007bff);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.remove-filter {
    color: white;
    text-decoration: none;
    font-weight: bold;
    opacity: 0.8;
}

.remove-filter:hover {
    opacity: 1;
}

/* Film List */
.film-list {
    display: grid;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.film-list.grid-mode {
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
}

.film-list.list-mode {
    grid-template-columns: 1fr;
}

/* Film Cards */
.dvd {
    position: relative;
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.dvd:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
}

.dvd.watched {
    border-color: rgba(34, 197, 94, 0.5);
}

.dvd.favorite {
    border-color: rgba(239, 68, 68, 0.5);
}

.cover-area {
    position: relative;
    overflow: hidden;
}

.cover-area img {
    width: 100%;
    height: 280px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.dvd:hover .cover-area img {
    transform: scale(1.05);
}

/* Badges */
.watched-badge, .favorite-badge, .boxset-badge, .rating-badge {
    position: absolute;
    top: 0.5rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    z-index: 2;
}

.watched-badge {
    right: 0.5rem;
    background: rgba(34, 197, 94, 0.9);
    color: white;
}

.favorite-badge {
    right: 0.5rem;
    background: rgba(239, 68, 68, 0.9);
    color: white;
}

.boxset-badge {
    left: 0.5rem;
    background: rgba(59, 130, 246, 0.9);
    color: white;
}

.rating-badge {
    bottom: 0.5rem;
    left: 0.5rem;
    background: rgba(245, 158, 11, 0.9);
    color: white;
}

/* Film Details */
.dvd-details {
    padding: 1rem;
}

.title-year {
    display: flex;
    align-items: baseline;
    gap: 0.5rem;
    margin-bottom: 0.5rem;
    flex-wrap: wrap;
}

.dvd-details h2 {
    margin: 0;
    font-size: 1rem;
    font-weight: 600;
    line-height: 1.3;
}

.dvd-details h2 a {
    color: var(--text-white, #fff);
    text-decoration: none;
    transition: color 0.2s;
}

.dvd-details h2 a:hover {
    color: var(--primary-color, #007bff);
}

.year {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 0.9rem;
    font-weight: 400;
}

.dvd-details p {
    margin: 0.5rem 0;
    font-size: 0.85rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
}

.collection-type {
    color: var(--text-muted, rgba(255, 255, 255, 0.6));
    font-size: 0.8rem;
}

.quick-info {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.5rem;
    font-size: 0.75rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    flex-wrap: wrap;
}

.quick-info span {
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Quick Actions */
.quick-actions {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 3;
}

.dvd:hover .quick-actions {
    opacity: 1;
}

.quick-btn {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: none;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;
    backdrop-filter: blur(10px);
}

.quick-btn:hover {
    background: var(--primary-color, #007bff);
    transform: scale(1.1);
}

.quick-btn.active {
    background: var(--primary-color, #007bff);
}

/* List Mode Specific */
.film-list.list-mode .dvd {
    display: flex;
    flex-direction: row;
    height: auto;
    padding: 1rem;
    gap: 1rem;
    align-items: center;
}

.film-list.list-mode .cover-area {
    flex-shrink: 0;
    width: 100px;
}

.film-list.list-mode .cover-area img {
    height: 140px;
    border-radius: 8px;
}

.film-list.list-mode .dvd-details {
    flex: 1;
    padding: 0;
}

.film-list.list-mode .quick-actions {
    position: static;
    flex-direction: row;
    opacity: 1;
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Pagination */
.pagination-nav {
    margin-top: 2rem;
}

.pagination-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
    justify-content: center;
}

.page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0.5rem;
    border-radius: 8px;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    text-decoration: none;
    transition: all 0.2s ease;
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.page-link.current {
    background: var(--primary-color, #007bff);
    color: white;
    font-weight: 600;
}

.page-link:hover:not(.current) {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateY(-2px);
}

.page-dots {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    padding: 0.5rem;
}

.pagination-info {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 0.9rem;
    text-align: center;
}

.separator {
    opacity: 0.5;
    margin: 0 0.5rem;
}

/* Screen Reader Only */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .film-filters {
        padding: 1rem;
    }
    
    .filter-row {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-field {
        min-width: auto;
    }
    
    .advanced-filters {
        flex-direction: column;
        align-items: stretch;
        gap: 0.75rem;
    }
    
    .filter-info {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .film-list.grid-mode {
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 1rem;
    }
    
    .cover-area img {
        height: 220px;
    }
    
    .film-list.list-mode .dvd {
        flex-direction: column;
        text-align: center;
    }
    
    .film-list.list-mode .cover-area {
        width: 100%;
    }
    
    .film-list.list-mode .cover-area img {
        height: 200px;
        width: auto;
    }
    
    .quick-actions {
        position: static;
        flex-direction: row;
        justify-content: center;
        opacity: 1;
        margin-top: 0.5rem;
    }
    
    .pagination {
        gap: 0.25rem;
        overflow-x: auto;
        padding: 0.5rem 0;
    }
    
    .page-link {
        min-width: 36px;
        height: 36px;
        font-size: 0.85rem;
    }
}

@media (max-width: 480px) {
    .film-list.grid-mode {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.75rem;
    }
    
    .cover-area img {
        height: 180px;
    }
    
    .dvd-details {
        padding: 0.75rem;
    }
    
    .dvd-details h2 {
        font-size: 0.9rem;
    }
    
    .quick-info {
        font-size: 0.7rem;
        gap: 0.5rem;
    }
}
</style>