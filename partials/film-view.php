<?php
// Bootstrap sollte bereits in film-fragment.php geladen sein
// require_once __DIR__ . '/../includes/bootstrap.php';

// Sicherheitscheck - Film-Array validieren
if (!isset($dvd) || !is_array($dvd) || empty($dvd['id'])) {
    throw new InvalidArgumentException('Invalid DVD data provided to film-view.php');
}

// Cover-Pfade generieren
$frontCover = findCoverImage($dvd['cover_id'] ?? '', 'f');
$backCover = findCoverImage($dvd['cover_id'] ?? '', 'b');

// Schauspieler laden
$actors = getActorsByDvdId($pdo, (int)$dvd['id']);

// Staffeln & Episoden laden (für Serien)
$seasons = [];
$totalEpisodes = 0;
try {
    // Prüfen ob seasons Tabelle existiert
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'seasons'");
    if ($tableCheck && $tableCheck->rowCount() > 0) {
        // Staffeln laden
        $seasonsStmt = $pdo->prepare("
            SELECT id, season_number, name, overview, episode_count, air_date, poster_path
            FROM seasons
            WHERE series_id = ?
            ORDER BY season_number ASC
        ");
        $seasonsStmt->execute([$dvd['id']]);
        $seasonsData = $seasonsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($seasonsData)) {
            // Für jede Staffel die Episoden laden
            foreach ($seasonsData as $season) {
                $episodesStmt = $pdo->prepare("
                    SELECT id, episode_number, title, overview, air_date, runtime, still_path
                    FROM episodes
                    WHERE season_id = ?
                    ORDER BY episode_number ASC
                ");
                $episodesStmt->execute([$season['id']]);
                $episodes = $episodesStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $season['episodes'] = $episodes;
                $seasons[] = $season;
                $totalEpisodes += count($episodes);
            }
        }
    }
} catch (PDOException $e) {
    error_log("Seasons/Episodes query error: " . $e->getMessage());
}


// BoxSet-Kinder laden
$boxChildren = [];
if (!empty($dvd['id'])) {
    try {
        $boxsetStmt = $pdo->prepare("
            SELECT id, title, year, genre, cover_id, runtime, rating_age 
            FROM dvds 
            WHERE boxset_parent = ? 
            ORDER BY year ASC, title ASC
        ");
        $boxsetStmt->execute([$dvd['id']]);
        $boxChildren = $boxsetStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("BoxSet query error: " . $e->getMessage());
    }
}

// BoxSet-Parent laden (falls dieser Film zu einem BoxSet gehört)
$boxsetParent = null;
if (!empty($dvd['boxset_parent'])) {
    try {
        $parentStmt = $pdo->prepare("SELECT id, title, year FROM dvds WHERE id = ?");
        $parentStmt->execute([$dvd['boxset_parent']]);
        $boxsetParent = $parentStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("BoxSet parent query error: " . $e->getMessage());
    }
}

// Film-Statistiken laden
$filmStats = [
    'view_count' => $dvd['view_count'] ?? 0,
    'created_at' => $dvd['created_at'] ?? null,
    'updated_at' => $dvd['updated_at'] ?? null,
];

// Bewertung berechnen (falls vorhanden) - Robuster mit Tabellen-Check
$averageRating = 0;
$ratingCount = 0;
$userRating = 0;
$userHasRated = false;

try {
    // Prüfen ob user_ratings Tabelle existiert
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'user_ratings'");
    if ($tableCheck && $tableCheck->rowCount() > 0) {
        // Allgemeine Bewertungen laden
        $ratingStmt = $pdo->prepare("
            SELECT AVG(rating) as avg_rating, COUNT(*) as count 
            FROM user_ratings 
            WHERE film_id = ?
        ");
        $ratingStmt->execute([$dvd['id']]);
        $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);
        $averageRating = round((float)($ratingData['avg_rating'] ?? 0), 1);
        $ratingCount = (int)($ratingData['count'] ?? 0);
        
        // User-spezifische Bewertung laden (falls eingeloggt)
        if (isset($_SESSION['user_id'])) {
            $userRatingStmt = $pdo->prepare("
                SELECT rating FROM user_ratings 
                WHERE film_id = ? AND user_id = ?
            ");
            $userRatingStmt->execute([$dvd['id'], $_SESSION['user_id']]);
            $userRatingData = $userRatingStmt->fetch();
            if ($userRatingData) {
                $userRating = (float)$userRatingData['rating'];
                $userHasRated = true;
            }
        }
    }
} catch (PDOException $e) {
    error_log("Rating query error: " . $e->getMessage());
}

// User-Status laden (Wishlist, Watched) falls eingeloggt
$isOnWishlist = false;
$isWatched = false;
if (isset($_SESSION['user_id'])) {
    try {
        // Wishlist-Status
        $wishCheck = $pdo->query("SHOW TABLES LIKE 'user_wishlist'");
        if ($wishCheck && $wishCheck->rowCount() > 0) {
            $wishStmt = $pdo->prepare("SELECT COUNT(*) FROM user_wishlist WHERE user_id = ? AND film_id = ?");
            $wishStmt->execute([$_SESSION['user_id'], $dvd['id']]);
            $isOnWishlist = $wishStmt->fetchColumn() > 0;
        }
        
        // Watched-Status
        $watchedCheck = $pdo->query("SHOW TABLES LIKE 'user_watched'");
        if ($watchedCheck && $watchedCheck->rowCount() > 0) {
            $watchedStmt = $pdo->prepare("SELECT COUNT(*) FROM user_watched WHERE user_id = ? AND film_id = ?");
            $watchedStmt->execute([$_SESSION['user_id'], $dvd['id']]);
            $isWatched = $watchedStmt->fetchColumn() > 0;
        }
    } catch (PDOException $e) {
        error_log("User status query error: " . $e->getMessage());
    }
}

// View-Count erhöhen
try {
    if (!empty($dvd['id'])) {
        $updateViewStmt = $pdo->prepare("UPDATE dvds SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?");
        $updateViewStmt->execute([$dvd['id']]);
        $filmStats['view_count']++; // Lokale Variable aktualisieren
    }
} catch (PDOException $e) {
    error_log("View count update error: " . $e->getMessage());
}

// Helper-Funktionen
function formatFileSize(?int $bytes): string {
    if (!$bytes) return '';
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen((string)$bytes) - 1) / 3);
    return sprintf("%.1f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}

function formatDate(?string $date): string {
    if (!$date) return '';
    try {
        return (new DateTime($date))->format('d.m.Y');
    } catch (Exception $e) {
        return $date;
    }
}

function generateStarRating(float $rating, int $maxStars = 5): string {
    $stars = '';
    for ($i = 1; $i <= $maxStars; $i++) {
        if ($i <= $rating) {
            $stars .= '<i class="bi bi-star-fill star-filled"></i>';
        } elseif ($i - 0.5 <= $rating) {
            $stars .= '<i class="bi bi-star-half star-half"></i>';
        } else {
            $stars .= '<i class="bi bi-star star-empty"></i>';
        }
    }
    return $stars;
}
?>

<div class="detail-inline" itemscope itemtype="https://schema.org/Movie">
    <!-- Film-Titel mit Schema.org Markup -->
    <header class="film-header">
        <h2 itemprop="name">
            <?= htmlspecialchars($dvd['title']) ?>
            <span class="film-year" itemprop="datePublished">(<?= htmlspecialchars((string)($dvd['year'] ?? '')) ?>)</span>
        </h2>
        
        <!-- User-Status Badges -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-status-badges">
                <?php if ($isOnWishlist): ?>
                    <span class="badge badge-wishlist">
                        <i class="bi bi-heart-fill"></i> Auf Wunschliste
                    </span>
                <?php endif; ?>
                <?php if ($isWatched): ?>
                    <span class="badge badge-watched">
                        <i class="bi bi-check-circle-fill"></i> Gesehen
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($boxsetParent): ?>
            <div class="boxset-breadcrumb">
                <i class="bi bi-collection"></i>
                Teil von: 
                <a href="#" class="toggle-detail" data-id="<?= $boxsetParent['id'] ?>">
                    <?= htmlspecialchars($boxsetParent['title']) ?> (<?= $boxsetParent['year'] ?>)
                </a>
            </div>
        <?php endif; ?>
    </header>

    <!-- Cover Gallery mit Lightbox -->
    <section class="cover-gallery" aria-label="Film-Cover">
        <div class="cover-pair">
            <?php if ($frontCover): ?>
                <a href="<?= htmlspecialchars($frontCover) ?>" 
                   data-fancybox="gallery" 
                   data-caption="<?= htmlspecialchars($dvd['title']) ?> - Frontcover">
                    <img class="thumb" 
                         src="<?= htmlspecialchars($frontCover) ?>" 
                         alt="<?= htmlspecialchars($dvd['title']) ?> Frontcover"
                         itemprop="image"
                         loading="lazy">
                </a>
            <?php else: ?>
                <div class="no-cover">
                    <i class="bi bi-film"></i>
                    <span>Kein Cover</span>
                </div>
            <?php endif; ?>
            
            <?php if ($backCover): ?>
                <a href="<?= htmlspecialchars($backCover) ?>" 
                   data-fancybox="gallery" 
                   data-caption="<?= htmlspecialchars($dvd['title']) ?> - Backcover">
                    <img class="thumb" 
                         src="<?= htmlspecialchars($backCover) ?>" 
                         alt="<?= htmlspecialchars($dvd['title']) ?> Backcover"
                         loading="lazy">
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Film-Informationen in Grid-Layout -->
    <section class="film-info-grid">
        <div class="film-info-item">
            <span class="label">Genre</span>
            <span class="value" itemprop="genre"><?= htmlspecialchars($dvd['genre'] ?? 'Unbekannt') ?></span>
        </div>
        
        <?php if (!empty($dvd['runtime'])): ?>
            <div class="film-info-item">
                <span class="label">Laufzeit</span>
                <span class="value" itemprop="duration"><?= formatRuntime((int)$dvd['runtime']) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (isset($dvd['rating_age']) && $dvd['rating_age'] !== null && $dvd['rating_age'] !== ''): ?>
            <div class="film-info-item">
                <span class="label">Altersfreigabe</span>
                <span class="value fsk-badge">
                    <?php
                    $fskAge = (int)$dvd['rating_age'];
                    $fskSvgPath = "assets/svg/fsk/fsk-{$fskAge}.svg";
                    
                    if (file_exists($fskSvgPath)): ?>
                        <img src="<?= htmlspecialchars($fskSvgPath) ?>" 
                             alt="FSK <?= $fskAge ?>" 
                             class="fsk-logo"
                             title="Freigegeben ab <?= $fskAge ?> Jahren">
                    <?php else: ?>
                        <span class="fsk-text">FSK <?= $fskAge ?></span>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($dvd['collection_type'])): ?>
            <div class="film-info-item">
                <span class="label">Sammlung</span>
                <span class="value"><?= htmlspecialchars($dvd['collection_type']) ?></span>
            </div>
        <?php endif; ?>
        
        <div class="film-info-item">
            <span class="label">Aufrufe</span>
            <span class="value"><?= number_format($filmStats['view_count']) ?></span>
        </div>
        
        <?php if ($filmStats['created_at']): ?>
            <div class="film-info-item">
                <span class="label">Hinzugefügt</span>
                <span class="value"><?= formatDate($filmStats['created_at']) ?></span>
            </div>
        <?php endif; ?>
    </section>

    <!-- Handlung -->
    <?php if (!empty($dvd['overview'])): ?>
        <section class="meta-card full-width">
            <h3><i class="bi bi-card-text"></i> Handlung</h3>
            <div class="overview-text" itemprop="description">
                <?php
                // HTML sicher anzeigen mit nl2br + htmlspecialchars
                // Falls purifyHTML() Funktion existiert, verwende sie, sonst Fallback
                if (function_exists('purifyHTML')) {
                    echo purifyHTML($dvd['overview'], true);
                } else {
                    echo nl2br(htmlspecialchars($dvd['overview'], ENT_QUOTES, 'UTF-8'));
                }
                ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- TMDb Ratings -->
    <?php 
    if (getSetting('tmdb_show_ratings_details', '1') == '1') {
        $film = $dvd; // rating-details.php erwartet $film Variable
        include __DIR__ . '/rating-details.php';
    }
    ?>

    <!-- Schauspieler -->
    <?php if (!empty($actors)): ?>
        <section class="meta-card">
            <h3><i class="bi bi-people"></i> Besetzung</h3>
            <div class="actor-list">
                <ul itemprop="actor" itemscope itemtype="https://schema.org/Person">
                    <?php foreach ($actors as $actor): ?>
                        <li class="actor-item">
                            <span class="actor-name" itemprop="name">
                                <?= htmlspecialchars("{$actor['first_name']} {$actor['last_name']}") ?>
                            </span>
                            <?php if (!empty($actor['role'])): ?>
                                <span class="actor-role">als <em><?= htmlspecialchars($actor['role']) ?></em></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <!-- Staffeln & Episoden (für Serien) -->
    <?php if (!empty($seasons)): ?>
        <section class="meta-card">
            <h3>
                <i class="bi bi-collection-play"></i> 
                Staffeln & Episoden
                <span class="badge bg-primary ms-2"><?= count($seasons) ?> Staffel<?= count($seasons) > 1 ? 'n' : '' ?></span>
                <span class="badge bg-secondary ms-1"><?= $totalEpisodes ?> Episoden</span>
            </h3>
            
            <div class="seasons-accordion">
                <?php foreach ($seasons as $sIndex => $season): ?>
                <div class="season-section">
                    <div class="season-header" data-season="<?= $season['season_number'] ?>">
                        <div class="season-title">
                            <i class="bi bi-caret-right-fill season-caret" data-caret="<?= $season['season_number'] ?>"></i>
                            <strong>Staffel <?= $season['season_number'] ?></strong>
                            <span class="text-muted ms-2">(<?= count($season['episodes']) ?> Episoden)</span>
                        </div>
                        <?php if (!empty($season['air_date'])): ?>
                        <small class="text-muted"><?= date('Y', strtotime($season['air_date'])) ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="season-content" data-content="<?= $season['season_number'] ?>" style="display: <?= $sIndex === 0 ? 'block' : 'none' ?>">
                        <?php if (!empty($season['overview'])): ?>
                        <div class="season-overview">
                            <p class="text-muted small"><?= htmlspecialchars($season['overview']) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="episodes-list">
                            <?php foreach ($season['episodes'] as $episode): ?>
                            <div class="episode-item">
                                <div class="episode-header">
                                    <span class="episode-badge">E<?= $episode['episode_number'] ?></span>
                                    <strong class="episode-title"><?= htmlspecialchars($episode['title']) ?></strong>
                                    <?php if (!empty($episode['runtime'])): ?>
                                    <span class="episode-runtime text-muted"><?= $episode['runtime'] ?> Min.</span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($episode['overview'])): ?>
                                <div class="episode-overview">
                                    <p class="text-muted small"><?= htmlspecialchars($episode['overview']) ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($episode['air_date'])): ?>
                                <div class="episode-meta">
                                    <small class="text-muted">
                                        <i class="bi bi-calendar"></i> <?= date('d.m.Y', strtotime($episode['air_date'])) ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        
    <?php endif; ?>

    <!-- BoxSet-Inhalte -->
    <?php if (!empty($boxChildren)): ?>
        <section class="meta-card">
            <h3><i class="bi bi-collection"></i> BoxSet-Inhalte</h3>
            <div class="boxset-children-grid">
                <?php foreach ($boxChildren as $child): ?>
                    <div class="boxset-child-item">
                        <a href="#" class="toggle-detail" data-id="<?= $child['id'] ?>">
                            <img src="<?= htmlspecialchars(findCoverImage($child['cover_id'], 'f')) ?>" 
                                 alt="<?= htmlspecialchars($child['title']) ?>"
                                 class="child-cover"
                                 loading="lazy">
                            <div class="child-info">
                                <h4><?= htmlspecialchars($child['title']) ?></h4>
                                <p><?= htmlspecialchars((string)$child['year']) ?></p>
                                <?php if (!empty($child['runtime'])): ?>
                                    <p class="runtime"><?= formatRuntime((int)$child['runtime']) ?></p>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Trailer -->
    <?php if (!empty($dvd['trailer_url'])): ?>
        <section class="meta-card">
            <h3><i class="bi bi-play-circle"></i> Trailer</h3>
            <div class="trailer-container">
                <div class="trailer-box" 
                     data-src="<?= htmlspecialchars($dvd['trailer_url']) ?>"
                     data-rating-age="<?= (int)($dvd['rating_age'] ?? 0) ?>">
                    <img src="<?= htmlspecialchars($frontCover) ?>" 
                         alt="Trailer Thumbnail"
                         loading="lazy">
                    <div class="play-icon">
                        <i class="bi bi-play-fill"></i>
                    </div>
                    <div class="trailer-overlay">
                        <span>Trailer abspielen</span>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Age Verification Modal für FSK 18+ -->
    <?php if (!empty($dvd['trailer_url']) && (int)($dvd['rating_age'] ?? 0) >= 18): ?>
    <div id="ageVerificationModal" class="age-modal" style="display: none;">
        <div class="age-modal-content">
            <div class="age-modal-header">
                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                <h3>Altersbeschränkung</h3>
            </div>
            <div class="age-modal-body">
                <p class="age-warning">
                    Dieser Film ist <strong>FSK <?= (int)$dvd['rating_age'] ?></strong> eingestuft.
                </p>
                <p>
                    Der Trailer enthält möglicherweise Inhalte, die für Personen unter <?= (int)$dvd['rating_age'] ?> Jahren nicht geeignet sind.
                </p>
                <p class="age-question">
                    <strong>Bist du mindestens <?= (int)$dvd['rating_age'] ?> Jahre alt?</strong>
                </p>
            </div>
            <div class="age-modal-actions">
                <button class="btn btn-success age-confirm" id="ageConfirmBtn">
                    <i class="bi bi-check-circle"></i> Ja, ich bin <?= (int)$dvd['rating_age'] ?>+
                </button>
                <button class="btn btn-danger age-deny" id="ageDenyBtn">
                    <i class="bi bi-x-circle"></i> Nein, abbrechen
                </button>
            </div>
            <p class="age-disclaimer">
                <small>
                    <i class="bi bi-info-circle"></i>
                    Mit der Bestätigung erklärst du, dass du das gesetzliche Mindestalter erreicht hast.
                </small>
            </p>
        </div>
    </div>
    <?php endif; ?>

    <!-- User-Bewertung (falls eingeloggt) -->
    <section class="meta-card user-rating-card">
        <h3><i class="bi bi-star-fill"></i> Ihre Bewertung</h3>
        <div class="user-rating-section">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="rating-grid">
                    <!-- Eigene Bewertung -->
                    <div class="rating-card user">
                        <div class="rating-logo">
                            <i class="bi bi-person-circle" style="font-size: 32px; color: var(--accent-primary);"></i>
                        </div>
                        
                        <?php if ($userHasRated): ?>
                            <div class="rating-score" style="color: var(--accent-primary);">
                                <?= $userRating ?><span class="rating-max">/5</span>
                            </div>
                            <div class="stars-display">
                                <?= generateStarRating($userRating) ?>
                            </div>
                            <div class="rating-meta">
                                Ihre Bewertung
                            </div>
                        <?php else: ?>
                            <div class="rating-score" style="color: var(--text-muted); font-size: 1.5rem;">
                                -<span class="rating-max">/5</span>
                            </div>
                            <div class="rating-meta">
                                Noch nicht bewertet
                            </div>
                        <?php endif; ?>
                        
                        <!-- Bewertungs-Input -->
                        <div class="star-rating-input" data-film-id="<?= $dvd['id'] ?>" data-current-rating="<?= $userRating ?>">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="bi rating-star <?= $i <= $userRating ? 'bi-star-fill' : 'bi-star' ?>" 
                                   data-rating="<?= $i ?>"></i>
                            <?php endfor; ?>
                        </div>
                        
                        <button class="btn-rate save-rating" style="display: none;">
                            <i class="bi bi-check-circle"></i> Speichern
                        </button>
                    </div>
                    
                    <!-- Community Bewertung -->
                    <?php if ($averageRating > 0): ?>
                    <div class="rating-card community">
                        <div class="rating-logo">
                            <i class="bi bi-people-fill" style="font-size: 32px; color: #4caf50;"></i>
                        </div>
                        <div class="rating-score" style="color: #4caf50;">
                            <?= $averageRating ?><span class="rating-max">/5</span>
                        </div>
                        <div class="stars-display">
                            <?= generateStarRating($averageRating) ?>
                        </div>
                        <div class="rating-votes">
                            <?= $ratingCount ?> Bewertung<?= $ratingCount !== 1 ? 'en' : '' ?>
                        </div>
                        <div class="rating-meta">
                            Community-Durchschnitt
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="login-required">
                    <i class="bi bi-info-circle"></i>
                    <p>Melden Sie sich an, um Filme zu bewerten.</p>
                    <a href="login.php" class="btn-login">
                        <i class="bi bi-person"></i> Anmelden
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Ähnliche Filme -->
    <?php 
    $film = $dvd; // similar-movies.php erwartet $film Variable
    include __DIR__ . '/similar-movies.php';
    ?>

    <!-- Film-Aktionen -->
    <section class="film-actions">
        <button class="close-detail-button btn btn-secondary" onclick="closeDetail()">
            <i class="bi bi-x-lg"></i> Schließen
        </button>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <button class="btn btn-outline-primary add-to-wishlist <?= $isOnWishlist ? 'active' : '' ?>" 
                    data-film-id="<?= $dvd['id'] ?>">
                <i class="bi bi-heart<?= $isOnWishlist ? '-fill' : '' ?>"></i>
                <?= $isOnWishlist ? 'Auf Wunschliste' : 'Zur Wunschliste' ?>
            </button>
            
            <button class="btn btn-outline-secondary mark-as-watched <?= $isWatched ? 'active' : '' ?>" 
                    data-film-id="<?= $dvd['id'] ?>">
                <i class="bi bi-check-circle<?= $isWatched ? '-fill' : '' ?>"></i>
                <?= $isWatched ? 'Gesehen' : 'Als gesehen markieren' ?>
            </button>
        <?php endif; ?>
        
        <button class="btn btn-outline-info share-film" data-film-id="<?= $dvd['id'] ?>" data-film-title="<?= htmlspecialchars($dvd['title']) ?>">
            <i class="bi bi-share"></i> Teilen
        </button>
    </section>
</div>

<!-- Enhanced JavaScript -->
<script>
(function() {
    // Trailer-Funktionalität mit Age-Verification (sofort ausgeführt)
    const trailerContainer = document.querySelector('.trailer-container');
    const ageModal = document.getElementById('ageVerificationModal');
    const ageConfirmBtn = document.getElementById('ageConfirmBtn');
    const ageDenyBtn = document.getElementById('ageDenyBtn');
    
    if (trailerContainer) {
        // Event Delegation - fängt Clicks auf Child-Elemente
        trailerContainer.addEventListener('click', function(e) {
            const trailerBox = e.target.closest('.trailer-box');
            if (!trailerBox) return; // Nicht auf trailer-box geklickt
            
            e.preventDefault();
            e.stopPropagation();
            
            const ratingAge = parseInt(trailerBox.dataset.ratingAge || 0);
            const trailerUrl = trailerBox.dataset.src;
            
            // Cookie-Check
            function hasAgeConfirmation() {
                return document.cookie.includes('age_confirmed_18=true');
            }
            
            // Cookie setzen (30 Tage)
            function setAgeConfirmation() {
                const expires = new Date();
                expires.setDate(expires.getDate() + 30);
                document.cookie = `age_confirmed_18=true; expires=${expires.toUTCString()}; path=/; SameSite=Strict`;
            }
            
            if (ratingAge >= 18 && !hasAgeConfirmation()) {
                // Zeige Age-Verification
                if (ageModal) {
                    ageModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            } else {
                // Spiele Trailer ab
                playTrailer(trailerBox, trailerUrl);
            }
            
            function playTrailer(box, url) {
                if (!url) return;
                
                const container = box.closest('.trailer-container');
                const embedUrl = convertToEmbedUrl(url);
                
                // Iframe erstellen
                const iframe = document.createElement('iframe');
                iframe.src = embedUrl;
                iframe.width = '100%';
                iframe.style.aspectRatio = '16/9';
                iframe.style.border = 'none';
                iframe.style.borderRadius = '8px';
                iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                iframe.allowFullscreen = true;
                
                box.style.display = 'none';
                container.appendChild(iframe);
                
                // Kanal-Link hinzufügen (nur für YouTube)
                if (url.includes('youtube.com') || url.includes('youtu.be')) {
                    const videoId = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/)?.[1];
                    if (videoId) {
                        const channelLink = document.createElement('div');
                        channelLink.className = 'trailer-channel-link';
                        channelLink.innerHTML = `
                            <a href="https://www.youtube.com/watch?v=${videoId}" target="_blank" rel="noopener noreferrer">
                                <i class="bi bi-youtube"></i> Auf YouTube ansehen
                            </a>
                        `;
                        container.appendChild(channelLink);
                    }
                }
            }
            
            function convertToEmbedUrl(url) {
                // YouTube
                if (url.includes('youtube.com') || url.includes('youtu.be')) {
                    const videoId = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/)?.[1];
                    if (videoId) return `https://www.youtube.com/embed/${videoId}?autoplay=1`;
                }
                // Vimeo
                if (url.includes('vimeo.com')) {
                    const videoId = url.match(/vimeo\.com\/(\d+)/)?.[1];
                    if (videoId) return `https://player.vimeo.com/video/${videoId}?autoplay=1`;
                }
                // Dailymotion
                if (url.includes('dailymotion.com')) {
                    const videoId = url.match(/dailymotion\.com\/video\/([^_]+)/)?.[1];
                    if (videoId) return `https://www.dailymotion.com/embed/video/${videoId}?autoplay=1`;
                }
                return url;
            }
        });
        
        // Bestätigen
        if (ageConfirmBtn) {
            ageConfirmBtn.addEventListener('click', function() {
                const expires = new Date();
                expires.setDate(expires.getDate() + 30);
                document.cookie = `age_confirmed_18=true; expires=${expires.toUTCString()}; path=/; SameSite=Strict`;
                
                if (ageModal) {
                    ageModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
                
                // Spiele Trailer ab
                const trailerBox = document.querySelector('.trailer-box');
                const trailerUrl = trailerBox?.dataset.src;
                if (trailerBox && trailerUrl) {
                    const container = trailerBox.closest('.trailer-container');
                    const embedUrl = (function(url) {
                        if (url.includes('youtube.com') || url.includes('youtu.be')) {
                            const videoId = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/)?.[1];
                            if (videoId) return `https://www.youtube.com/embed/${videoId}?autoplay=1`;
                        }
                        if (url.includes('vimeo.com')) {
                            const videoId = url.match(/vimeo\.com\/(\d+)/)?.[1];
                            if (videoId) return `https://player.vimeo.com/video/${videoId}?autoplay=1`;
                        }
                        return url;
                    })(trailerUrl);
                    
                    const iframe = document.createElement('iframe');
                    iframe.src = embedUrl;
                    iframe.width = '100%';
                    iframe.style.aspectRatio = '16/9';
                    iframe.style.border = 'none';
                    iframe.style.borderRadius = '8px';
                    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                    iframe.allowFullscreen = true;
                    
                    trailerBox.style.display = 'none';
                    container.appendChild(iframe);
                }
            });
        }
        
        // Ablehnen
        if (ageDenyBtn) {
            ageDenyBtn.addEventListener('click', function() {
                if (ageModal) {
                    ageModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
        
        // ESC-Taste
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && ageModal && ageModal.style.display === 'flex') {
                ageModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
        
        // Click außerhalb
        if (ageModal) {
            ageModal.addEventListener('click', function(e) {
                if (e.target === ageModal) {
                    ageModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
    }
    
    // Rating-System mit Debug-Ausgaben
    const ratingStars = document.querySelectorAll('.rating-star');
    const saveRatingBtn = document.querySelector('.save-rating');
    const ratingDisplay = document.querySelector('.rating-display');
    const currentRating = parseFloat(document.querySelector('.star-rating-input')?.dataset.currentRating || 0);
    let selectedRating = currentRating;
    
    ratingStars.forEach((star, index) => {
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            highlightStars(rating);
        });
        
        star.addEventListener('mouseleave', function() {
            highlightStars(selectedRating);
        });
        
        star.addEventListener('click', function() {
            selectedRating = parseInt(this.dataset.rating);
            highlightStars(selectedRating);
            if (saveRatingBtn) {
                saveRatingBtn.style.display = 'inline-block';
            }
            if (ratingDisplay) {
                ratingDisplay.textContent = selectedRating + '/5';
            }
        });
    });
    
    function highlightStars(rating) {
        ratingStars.forEach((star, index) => {
            if (index < rating) {
                star.classList.remove('bi-star');
                star.classList.add('bi-star-fill');
            } else {
                star.classList.remove('bi-star-fill');
                star.classList.add('bi-star');
            }
        });
    }
    
    // Rating speichern
    if (saveRatingBtn) {
        saveRatingBtn.addEventListener('click', function() {
            const filmId = document.querySelector('.star-rating-input').dataset.filmId;
            saveUserRating(filmId, selectedRating);
        });
    }
    
    // Wishlist-Funktionalität
    const wishlistBtn = document.querySelector('.add-to-wishlist');
    if (wishlistBtn) {
        wishlistBtn.addEventListener('click', function() {
            const filmId = this.dataset.filmId;
            toggleWishlist(filmId, this);
        });
    }
    
    // Als gesehen markieren
    const watchedBtn = document.querySelector('.mark-as-watched');
    if (watchedBtn) {
        watchedBtn.addEventListener('click', function() {
            const filmId = this.dataset.filmId;
            toggleWatched(filmId, this);
        });
    }
    
    // Share-Funktionalität
    const shareBtn = document.querySelector('.share-film');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            const filmId = this.dataset.filmId;
            const filmTitle = this.dataset.filmTitle;
            shareFilm(filmId, filmTitle);
        });
    }
    
    // Lazy Loading für zusätzliche Bilder
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.classList.add('fade-in');
                }
            });
        });
        
        lazyImages.forEach(img => imageObserver.observe(img));
    }
})(); // IIFE - sofort ausgeführt

// AJAX-Funktionen
async function saveUserRating(filmId, rating) {
    try {
        const response = await fetch('api/save-rating.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ film_id: filmId, rating: rating })
        });
        
        if (response.ok) {
            showNotification('Bewertung gespeichert!', 'success');
            document.querySelector('.save-rating').style.display = 'none';
            
            // Seite nach kurzer Zeit neu laden um Community-Rating zu aktualisieren
            setTimeout(() => {
                location.reload();
            }, 1500);
        }
    } catch (error) {
        showNotification('Fehler beim Speichern der Bewertung', 'error');
    }
}

async function toggleWishlist(filmId, button) {
    try {
        const response = await fetch('api/toggle-wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ film_id: filmId })
        });
        
        if (response.ok) {
            const result = await response.json();
            const icon = button.querySelector('i');
            if (result.added) {
                button.innerHTML = '<i class="bi bi-heart-fill"></i> Auf Wunschliste';
                button.classList.add('active');
                showNotification('Zur Wunschliste hinzugefügt!', 'success');
            } else {
                button.innerHTML = '<i class="bi bi-heart"></i> Zur Wunschliste';
                button.classList.remove('active');
                showNotification('Von Wunschliste entfernt!', 'info');
            }
        }
    } catch (error) {
        showNotification('Fehler bei Wunschliste', 'error');
    }
}

async function toggleWatched(filmId, button) {
    try {
        const response = await fetch('api/toggle-watched.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ film_id: filmId })
        });
        
        if (response.ok) {
            const result = await response.json();
            if (result.watched) {
                button.innerHTML = '<i class="bi bi-check-circle-fill"></i> Gesehen';
                button.classList.add('active');
                showNotification('Als gesehen markiert!', 'success');
            } else {
                button.innerHTML = '<i class="bi bi-check-circle"></i> Als gesehen markieren';
                button.classList.remove('active');
                showNotification('Markierung entfernt!', 'info');
            }
        }
    } catch (error) {
        showNotification('Fehler beim Markieren', 'error');
    }
}

function shareFilm(filmId, filmTitle) {
    const url = window.location.origin + window.location.pathname + '?id=' + filmId;
    
    if (navigator.share) {
        navigator.share({
            title: filmTitle,
            text: 'Schau dir diesen Film an: ' + filmTitle,
            url: url
        });
    } else {
        // Fallback: URL in Zwischenablage kopieren
        navigator.clipboard.writeText(url).then(() => {
            showNotification('Link kopiert!', 'success');
        }).catch(() => {
            // Fallback für ältere Browser
            const textArea = document.createElement('textarea');
            textArea.value = url;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showNotification('Link kopiert!', 'success');
        });
    }
}

function closeDetail() {
    const detailContainer = document.getElementById('detail-container');
    if (detailContainer) {
        detailContainer.innerHTML = `
            <div class="detail-placeholder">
                <i class="bi bi-film"></i>
                <p>Wählen Sie einen Film aus der Liste, um Details anzuzeigen.</p>
            </div>
        `;
        
        // URL State management
        if (history.replaceState) {
            history.replaceState(null, '', window.location.pathname);
        }
    }
}

function showNotification(message, type = 'info') {
    // Einfache Benachrichtigung
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: var(--glass-bg-strong);
        border: 1px solid var(--glass-border);
        border-left: 4px solid ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        border-radius: var(--radius-md);
        color: var(--text-white);
        z-index: 10000;
        backdrop-filter: blur(10px);
        box-shadow: var(--shadow-lg);
        font-weight: 500;
    `;
    
    document.body.appendChild(notification);
    
    // Animation
    notification.style.transform = 'translateX(100%)';
    notification.style.transition = 'transform 0.3s ease-out';
    
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 10);
    
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}
</script>