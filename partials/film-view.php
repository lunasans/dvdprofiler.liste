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

// Crew-Mitglieder laden (Regie, Drehbuch, etc.)
$crew = null;
if (getSetting('tmdb_api_key', '')) {
    $crew = getFilmCrew($dvd['title'], $dvd['year'] ?? null);
}

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

<?php
// Backcover als Backdrop verwenden
$backdropUrl = $backCover ?? '';
?>

<style>
/* Backdrop Hintergrund */
.detail-inline {
    position: relative;
    overflow: hidden;
}

.backdrop-container {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    height: 100%;
    z-index: 0;
    overflow: hidden;
    background-color: var(--bg-primary, #0f0f23);
}

/* Hero-Wrapper für Backdrop-Container */
.hero-wrapper {
    position: relative;
    overflow: hidden;
    margin-bottom: 3rem;
    border-radius: 16px;
}

/* Titel auf Backdrop - Weiß mit starkem Shadow */
.detail-inline .hero-wrapper .hero-section .hero-content .film-header h2,
.hero-content .film-header h2,
h2[itemprop="name"] {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
    color: #ffffff !important;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.9), 0 4px 8px rgba(0, 0, 0, 0.7);
}

.detail-inline .hero-wrapper .hero-section .hero-content .film-header .film-year,
.hero-content .film-header .film-year,
span[itemprop="datePublished"] {
    font-weight: 400;
    opacity: 0.9;
    color: #ffffff !important;
}

/* Moderne Action-Buttons */
.film-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    padding: 1.5rem 0;
    margin-top: 2rem;
}

/* User-Status Badges (Gesehen) */
.user-status-badges {
    display: flex;
    gap: 0.75rem;
    margin-top: 0.75rem;
    flex-wrap: wrap;
}

.badge-watched {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, rgba(40, 167, 69, 0.2) 0%, rgba(40, 167, 69, 0.1) 100%);
    border: 2px solid rgba(40, 167, 69, 0.4);
    border-radius: 20px;
    color: #28a745;
    font-size: 0.9rem;
    font-weight: 600;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
    box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
    backdrop-filter: blur(10px);
}

.badge-watched i {
    font-size: 1rem;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
}

.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 8px;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.action-btn i {
    font-size: 1.1rem;
}

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.action-btn:active {
    transform: translateY(0);
}

/* Schließen Button */
.action-close {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.action-close:hover {
    background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
}

/* Als gesehen Button */
.action-watched {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
    border: 2px solid rgba(40, 167, 69, 0.3);
}

.action-watched:hover {
    background: rgba(40, 167, 69, 0.2);
    border-color: #28a745;
}

.action-watched.active {
    background: #28a745;
    color: white;
    border-color: #28a745;
}

.action-watched.active:hover {
    background: #218838;
}

/* Teilen Button */
.action-share {
    background: rgba(23, 162, 184, 0.1);
    color: #17a2b8;
    border: 2px solid rgba(23, 162, 184, 0.3);
}

.action-share:hover {
    background: rgba(23, 162, 184, 0.2);
    border-color: #17a2b8;
}

/* Responsive */
@media (max-width: 600px) {
    .film-actions {
        flex-direction: column;
    }
    
    .action-btn {
        width: 100%;
        justify-content: center;
    }
}

.backdrop-image {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    filter: blur(2px);
    transform: scale(1.1);
}

.backdrop-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
}

/* Default Theme - Dunkles Blau (#0f0f23) */
:root .backdrop-overlay,
[data-theme="default"] .backdrop-overlay {
    background: linear-gradient(
        to bottom,
        rgba(15, 15, 35, 0.3) 0%,
        rgba(15, 15, 35, 0.5) 20%,
        rgba(15, 15, 35, 0.7) 40%,
        rgba(15, 15, 35, 0.85) 60%,
        rgba(15, 15, 35, 0.95) 80%,
        rgba(15, 15, 35, 1) 100%
    );
}

/* Dark Theme - Schwarz (#000000) */
[data-theme="dark"] .backdrop-overlay {
    background: linear-gradient(
        to bottom,
        rgba(0, 0, 0, 0.3) 0%,
        rgba(0, 0, 0, 0.5) 20%,
        rgba(0, 0, 0, 0.7) 40%,
        rgba(0, 0, 0, 0.85) 60%,
        rgba(0, 0, 0, 0.95) 80%,
        rgba(0, 0, 0, 1) 100%
    );
}

/* Blue Theme - Dunkles Blau (#001f3f) */
[data-theme="blue"] .backdrop-overlay {
    background: linear-gradient(
        to bottom,
        rgba(0, 31, 63, 0.3) 0%,
        rgba(0, 31, 63, 0.5) 20%,
        rgba(0, 31, 63, 0.7) 40%,
        rgba(0, 31, 63, 0.85) 60%,
        rgba(0, 31, 63, 0.95) 80%,
        rgba(0, 31, 63, 1) 100%
    );
}

/* Purple Theme - Dunkles Lila (#1a0a1f) */
[data-theme="purple"] .backdrop-overlay {
    background: linear-gradient(
        to bottom,
        rgba(26, 10, 31, 0.3) 0%,
        rgba(26, 10, 31, 0.5) 20%,
        rgba(26, 10, 31, 0.7) 40%,
        rgba(26, 10, 31, 0.85) 60%,
        rgba(26, 10, 31, 0.95) 80%,
        rgba(26, 10, 31, 1) 100%
    );
}

/* Green Theme - Dunkles Grün (#0a1f0a) */
[data-theme="green"] .backdrop-overlay {
    background: linear-gradient(
        to bottom,
        rgba(10, 31, 10, 0.3) 0%,
        rgba(10, 31, 10, 0.5) 20%,
        rgba(10, 31, 10, 0.7) 40%,
        rgba(10, 31, 10, 0.85) 60%,
        rgba(10, 31, 10, 0.95) 80%,
        rgba(10, 31, 10, 1) 100%
    );
}

/* Red Theme - Dunkles Rot (#1a0a0a) */
[data-theme="red"] .backdrop-overlay {
    background: linear-gradient(
        to bottom,
        rgba(26, 10, 10, 0.3) 0%,
        rgba(26, 10, 10, 0.5) 20%,
        rgba(26, 10, 10, 0.7) 40%,
        rgba(26, 10, 10, 0.85) 60%,
        rgba(26, 10, 10, 0.95) 80%,
        rgba(26, 10, 10, 1) 100%
    );
}

/* Content über Backdrop */
.film-header,
.cover-gallery,
.film-info,
.film-meta,
.ratings-section,
.film-description,
.cast-section,
.seasons-section,
.boxset-children,
.trailer-section,
.actions-bar,
.close-detail {
    position: relative;
    z-index: 1;
}

/* Hero Section - Horizontales Layout (TMDb-Style) */
.hero-section {
    position: relative;
    z-index: 1;
    display: flex;
    gap: 3rem;
    padding: 3rem 2rem;
    min-height: 500px;
}

/* WICHTIG: Alle Elemente in Hero transparent halten */
.hero-section .film-header,
.hero-section .meta-card,
.hero-section .rating-card,
.hero-section .ratings-section {
    background: transparent !important;
    border: none !important;
    box-shadow: none !important;
    padding: 0 !important;
}

/* Ratings in Hero-Section - transparent und kompakt */
.hero-section .ratings-container {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
}

.hero-section .ratings-title {
    font-size: 1.1rem;
    margin-bottom: 0.75rem;
}

.hero-section .rating-card {
    background: rgba(0, 0, 0, 0.4) !important;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.15) !important;
}

.hero-section .rating-card.tmdb {
    background: rgba(1, 180, 228, 0.1) !important;
}

.hero-section .rating-card.imdb {
    background: rgba(245, 197, 24, 0.1) !important;
}

.hero-cover {
    flex-shrink: 0;
    width: 300px;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.hero-cover img {
    width: 100%;
    height: auto;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
}

.hero-cover .no-cover {
    width: 100%;
    height: 450px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 12px;
    color: var(--text-muted);
}

/* Crew-Info unter dem Cover */
.crew-info {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem;
    background: rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(10px);
    border-radius: 8px;
    border: 1px solid rgba(255, 255, 255, 0.1);
}

.crew-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.crew-role {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.crew-name {
    font-size: 0.9rem;
    color: var(--text-primary, #e4e4e7);
    line-height: 1.4;
}

.hero-content {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.hero-meta-line {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    font-size: 1rem;
    color: rgba(255, 255, 255, 0.9);
}

.hero-meta-line .fsk-badge {
    display: inline-flex;
    align-items: center;
}

.hero-meta-line .fsk-logo {
    height: 20px;
    width: auto;
}

.hero-overview {
    font-size: 1.05rem;
    line-height: 1.7;
    color: rgba(255, 255, 255, 0.95);
    max-width: 800px;
}

.hero-overview h3 {
    color: var(--text-primary, #e4e4e7);
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.6);
}

/* Responsive */
@media (max-width: 968px) {
    .hero-section {
        flex-direction: column;
        gap: 2rem;
        padding: 2rem 1.5rem;
    }
    
    .hero-cover {
        width: 250px;
        margin: 0 auto;
    }
    
    .hero-content .film-header h2 {
        font-size: 2rem;
    }
}

/* Cover-Gallery transparent, damit Backdrop sichtbar bleibt */
.cover-gallery {
    background: transparent !important;
}
</style>

<div class="detail-inline" itemscope itemtype="https://schema.org/Movie">
    <!-- Hero Wrapper - für Backdrop-Begrenzung -->
    <div class="hero-wrapper">
        <!-- Backdrop Hintergrund -->
        <?php if ($backdropUrl): ?>
        <div class="backdrop-container">
            <div class="backdrop-image" style="background-image: url('<?= htmlspecialchars($backdropUrl) ?>');"></div>
            <div class="backdrop-overlay"></div>
        </div>
        <?php endif; ?>
        
        <!-- Hero Section - Cover + Film-Infos nebeneinander (TMDb-Style) -->
        <section class="hero-section">
        <!-- Cover links -->
        <div class="hero-cover">
            <?php if ($frontCover): ?>
                <a href="<?= htmlspecialchars($frontCover) ?>" 
                   data-fancybox="gallery" 
                   data-caption="<?= htmlspecialchars($dvd['title']) ?> - Frontcover">
                    <img src="<?= htmlspecialchars($frontCover) ?>" 
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
            
            <!-- Crew-Informationen (Regie, Drehbuch, etc.) -->
            <?php if ($crew): ?>
            <div class="crew-info">
                <?php if (!empty($crew['director'])): ?>
                <div class="crew-item">
                    <span class="crew-role">Regie</span>
                    <span class="crew-name"><?= htmlspecialchars($crew['director']) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($crew['writers'])): ?>
                <div class="crew-item">
                    <span class="crew-role">Drehbuch</span>
                    <span class="crew-name"><?= htmlspecialchars(implode(', ', $crew['writers'])) ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($crew['composer'])): ?>
                <div class="crew-item">
                    <span class="crew-role">Musik</span>
                    <span class="crew-name"><?= htmlspecialchars($crew['composer']) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Film-Infos rechts -->
        <div class="hero-content">
            <!-- Film-Titel -->
            <header class="film-header">
                <h2 itemprop="name">
                    <?= htmlspecialchars($dvd['title']) ?>
                    <span class="film-year" itemprop="datePublished">(<?= htmlspecialchars((string)($dvd['year'] ?? '')) ?>)</span>
                </h2>
                
                <!-- User-Status Badges (nur Gesehen) -->
                <?php if (isset($_SESSION['user_id']) && $isWatched): ?>
                    <div class="user-status-badges">
                        <span class="badge badge-watched">
                            <i class="bi bi-check-circle-fill"></i> Gesehen
                        </span>
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
            
            <!-- Meta-Infos in einer Zeile -->
            <div class="hero-meta-line">
                <?php if (isset($dvd['rating_age']) && $dvd['rating_age'] !== null && $dvd['rating_age'] !== ''): ?>
                    <span class="fsk-badge">
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
                    <span>•</span>
                <?php endif; ?>
                
                <?php if (!empty($dvd['genre'])): ?>
                    <span itemprop="genre"><?= htmlspecialchars($dvd['genre']) ?></span>
                    <span>•</span>
                <?php endif; ?>
                
                <?php if (!empty($dvd['runtime'])): ?>
                    <span itemprop="duration"><?= formatRuntime((int)$dvd['runtime']) ?></span>
                <?php endif; ?>
                
                <?php if ($filmStats['created_at']): ?>
                    <span>•</span>
                    <span title="Hinzugefügt am">
                        <i class="bi bi-calendar-plus"></i> <?= formatDate($filmStats['created_at']) ?>
                    </span>
                <?php endif; ?>
            </div>
            
            <!-- TMDb Ratings -->
            <?php 
            if (getSetting('tmdb_show_ratings_details', '1') == '1') {
                $film = $dvd;
                include __DIR__ . '/rating-details.php';
            }
            ?>
            
            <!-- Handlung -->
            <?php if (!empty($dvd['overview'])): ?>
                <div class="hero-overview">
                    <h3 style="margin-bottom: 0.75rem; font-size: 1.2rem;">Handlung</h3>
                    <div itemprop="description">
                        <?php
                        if (function_exists('purifyHTML')) {
                            echo purifyHTML($dvd['overview'], true);
                        } else {
                            echo nl2br(htmlspecialchars($dvd['overview'], ENT_QUOTES, 'UTF-8'));
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>
    </div><!-- Ende hero-wrapper -->

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

<script>
// ===================================================================
// ALTERSVERIFIZIERUNG - Event-Delegation auf Document-Level
// Funktioniert auch bei AJAX-Loads
// ===================================================================
(function() {
    console.log('🎬 Trailer Age-Verification Script geladen');
    
    // Event-Delegation auf document für trailer-box clicks
    document.addEventListener('click', function(e) {
        const trailerBox = e.target.closest('.trailer-box');
        if (!trailerBox) return;
        
        // Prüfe ob es wirklich ein Trailer in film-view ist (nicht in trailers.php)
        const isFilmView = trailerBox.closest('.meta-card') !== null;
        if (!isFilmView) return;
        
        e.preventDefault();
        e.stopPropagation();
        
        const ratingAge = parseInt(trailerBox.dataset.ratingAge || 0);
        const trailerUrl = trailerBox.dataset.src;
        
        console.log('🎬 Trailer geklickt - FSK:', ratingAge);
        
        // Cookie-Check
        function hasAgeConfirmation() {
            return document.cookie.includes('age_confirmed_18=true');
        }
        
        if (ratingAge >= 18 && !hasAgeConfirmation()) {
            console.log('🔞 Zeige Altersverifizierung');
            const ageModal = document.getElementById('ageVerificationModal');
            if (ageModal) {
                ageModal.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                // Speichere Trailer-Info für Bestätigung
                window._pendingTrailerBox = trailerBox;
                window._pendingTrailerUrl = trailerUrl;
            } else {
                console.error('❌ Age Modal nicht gefunden');
            }
        } else {
            console.log('✅ Spiele Trailer direkt ab');
            playTrailerNow(trailerBox, trailerUrl);
        }
    }, true); // useCapture = true, damit wir VOR anderen Handlern greifen
    
    // Age-Verification Buttons Setup
    function setupAgeButtons() {
        const ageConfirmBtn = document.getElementById('ageConfirmBtn');
        const ageDenyBtn = document.getElementById('ageDenyBtn');
        const ageModal = document.getElementById('ageVerificationModal');
        
        if (!ageModal) return;
        
        if (ageConfirmBtn && !ageConfirmBtn.dataset.listenerAdded) {
            ageConfirmBtn.dataset.listenerAdded = 'true';
            ageConfirmBtn.addEventListener('click', function() {
                console.log('✅ Alter bestätigt');
                
                // Cookie setzen (30 Tage)
                const expires = new Date();
                expires.setDate(expires.getDate() + 30);
                document.cookie = `age_confirmed_18=true; expires=${expires.toUTCString()}; path=/; SameSite=Strict`;
                
                ageModal.style.display = 'none';
                document.body.style.overflow = '';
                
                // Trailer abspielen
                if (window._pendingTrailerBox && window._pendingTrailerUrl) {
                    playTrailerNow(window._pendingTrailerBox, window._pendingTrailerUrl);
                    window._pendingTrailerBox = null;
                    window._pendingTrailerUrl = null;
                }
            });
        }
        
        if (ageDenyBtn && !ageDenyBtn.dataset.listenerAdded) {
            ageDenyBtn.dataset.listenerAdded = 'true';
            ageDenyBtn.addEventListener('click', function() {
                console.log('❌ Alter abgelehnt');
                ageModal.style.display = 'none';
                document.body.style.overflow = '';
                window._pendingTrailerBox = null;
                window._pendingTrailerUrl = null;
            });
        }
        
        // ESC Key
        if (!ageModal.dataset.escListenerAdded) {
            ageModal.dataset.escListenerAdded = 'true';
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && ageModal.style.display === 'flex') {
                    ageModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
            
            // Click outside
            ageModal.addEventListener('click', function(e) {
                if (e.target === ageModal) {
                    ageModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
    }
    
    // Trailer abspielen
    function playTrailerNow(box, url) {
        if (!url) return;
        
        const container = box.closest('.trailer-container');
        if (!container) return;
        
        // Convert URL to embed
        let embedUrl = url;
        if (url.includes('youtube.com') || url.includes('youtu.be')) {
            const videoId = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/)?.[1];
            if (videoId) embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1&modestbranding=1`;
        }
        
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
        
        console.log('▶️ Trailer wird abgespielt');
    }
    
    // Setup sofort und bei DOM-ready
    setupAgeButtons();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupAgeButtons);
    }
})();
</script>

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
        <button class="action-btn action-close" onclick="closeDetail()">
            <i class="bi bi-x-lg"></i>
            <span>Schließen</span>
        </button>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <button class="action-btn action-watched mark-as-watched <?= $isWatched ? 'active' : '' ?>" 
                    data-film-id="<?= $dvd['id'] ?>">
                <i class="bi bi-check-circle<?= $isWatched ? '-fill' : '' ?>"></i>
                <span><?= $isWatched ? 'Gesehen' : 'Als gesehen markieren' ?></span>
            </button>
        <?php endif; ?>
        
        <button class="action-btn action-share share-film" 
                data-film-id="<?= $dvd['id'] ?>" 
                data-film-title="<?= htmlspecialchars($dvd['title']) ?>">
            <i class="bi bi-share"></i>
            <span>Teilen</span>
        </button>
    </section>
</div>

<!-- Enhanced JavaScript -->
<script>
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
            
            // Seite neu laden um Bewertung anzuzeigen
            setTimeout(() => {
                location.reload();
            }, 1500);
        }
    } catch (error) {
        showNotification('Fehler beim Speichern der Bewertung', 'error');
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
                button.innerHTML = '<i class="bi bi-check-circle-fill"></i><span>Gesehen</span>';
                button.classList.add('active');
                showNotification('Als gesehen markiert!', 'success');
            } else {
                button.innerHTML = '<i class="bi bi-check-circle"></i><span>Als gesehen markieren</span>';
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