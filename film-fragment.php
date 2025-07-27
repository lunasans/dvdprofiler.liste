<?php
/**
 * Film-Fragment - VOLLSTÄNDIG repariert für Core-System
 * Version: 1.4.7+ - WORKING VERSION
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

// Sicherheitsheader setzen
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

try {
    // ID-Validierung ZUERST
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        http_response_code(400);
        throw new InvalidArgumentException('Ungültige Film-ID: ' . ($_GET['id'] ?? 'keine'));
    }

    // Output Buffer Management
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();

    // Core-System laden
    require_once __DIR__ . '/includes/bootstrap.php';
    
    // Application-Instance abrufen
    $app = \DVDProfiler\Core\Application::getInstance();
    $database = $app->getDatabase();
    
    // Legacy-Kompatibilität: $pdo für bestehende Funktionen
    $pdo = $database->getPDO();
    
    // Test der Verbindung
    $database->query('SELECT 1');
    
} catch (Exception $e) {
    // Buffer leeren bei Fehler
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    $errorClass = $e instanceof InvalidArgumentException ? 'client-error' : 'server-error';
    $errorIcon = $e instanceof InvalidArgumentException ? 'bi-exclamation-circle' : 'bi-exclamation-triangle';
    $safeErrorMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    
    echo '<div class="error-container ' . $errorClass . '" style="
        padding: 2rem;
        text-align: center;
        background: rgba(0, 0, 0, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        color: #fff;
        margin: 2rem;
    ">
        <div class="error-content">
            <i class="' . $errorIcon . '" style="font-size: 3rem; margin-bottom: 1rem; color: #dc3545;"></i>
            <h2>Fehler beim Laden des Films</h2>
            <p><strong>Fehler:</strong> ' . $safeErrorMsg . '</p>
        </div>
    </div>';
    exit;
}

// ===== HELPER FUNCTIONS =====

function findCoverImage(?string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string {
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

function formatRuntime(?int $minutes): string {
    if (!$minutes || $minutes <= 0) return '';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
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

try {
    // ===== FILM DATEN LADEN =====
    
    // Film-Daten laden
    $dvd = $database->fetchRow("
        SELECT d.*, 
               u.email as added_by_user,
               (SELECT COUNT(*) FROM dvds WHERE boxset_parent = d.id) as boxset_children_count
        FROM dvds d 
        LEFT JOIN users u ON d.user_id = u.id 
        WHERE d.id = ?
    ", [$id]);
    
    if (!$dvd) {
        http_response_code(404);
        throw new Exception("Film mit ID {$id} nicht gefunden");
    }
    
    // Schauspieler laden
    $actors = [];
    try {
        $actors = $database->fetchAll("
            SELECT a.first_name, a.last_name, fa.role 
            FROM film_actor fa 
            JOIN actors a ON fa.actor_id = a.id 
            WHERE fa.film_id = ?
            ORDER BY fa.sort_order ASC, a.last_name ASC
        ", [$id]);
    } catch (Exception $e) {
        // Fallback für alte Struktur
        try {
            $actors = $database->fetchAll("
                SELECT firstname as first_name, lastname as last_name, role 
                FROM actors 
                WHERE dvd_id = ?
                ORDER BY lastname ASC
            ", [$id]);
        } catch (Exception $e2) {
            error_log('Actor query failed: ' . $e2->getMessage());
            $actors = [];
        }
    }
    
    // BoxSet-Kinder laden
    $boxsetChildren = [];
    if (!empty($dvd['boxset_children_count']) && (int)$dvd['boxset_children_count'] > 0) {
        $boxsetChildren = $database->fetchAll("
            SELECT id, title, year, genre, cover_id, runtime, rating_age
            FROM dvds 
            WHERE boxset_parent = ? 
            ORDER BY year ASC, title ASC
        ", [$id]);
    }
    
    // BoxSet-Parent laden
    $boxsetParent = null;
    if (!empty($dvd['boxset_parent'])) {
        $boxsetParent = $database->fetchRow("
            SELECT id, title, year 
            FROM dvds 
            WHERE id = ?
        ", [$dvd['boxset_parent']]);
    }
    
    // Bewertungen laden (falls Tabelle existiert)
    $averageRating = 0;
    $ratingCount = 0;
    $userRating = 0;
    $userHasRated = false;
    
    try {
        // Prüfen ob user_ratings Tabelle existiert
        $database->query("SELECT 1 FROM user_ratings LIMIT 1");
        
        // Durchschnittsbewertung
        $ratingData = $database->fetchRow("
            SELECT AVG(rating) as avg_rating, COUNT(*) as count 
            FROM user_ratings 
            WHERE film_id = ?
        ", [$id]);
        
        if ($ratingData) {
            $averageRating = round((float)($ratingData['avg_rating'] ?? 0), 1);
            $ratingCount = (int)($ratingData['count'] ?? 0);
        }
        
        // User-Bewertung (falls eingeloggt)
        if (isset($_SESSION['user_id'])) {
            $userRatingData = $database->fetchRow("
                SELECT rating FROM user_ratings 
                WHERE film_id = ? AND user_id = ?
            ", [$id, $_SESSION['user_id']]);
            
            if ($userRatingData) {
                $userRating = (float)$userRatingData['rating'];
                $userHasRated = true;
            }
        }
    } catch (Exception $e) {
        // Tabelle existiert nicht - ignorieren
    }
    
    // User-Watch-Status
    $isWatched = false;
    try {
        if (isset($_SESSION['user_id'])) {
            $database->query("SELECT 1 FROM user_watched LIMIT 1");
            $watchedData = $database->fetchRow("
                SELECT 1 FROM user_watched 
                WHERE user_id = ? AND film_id = ?
            ", [$_SESSION['user_id'], $id]);
            $isWatched = !empty($watchedData);
        }
    } catch (Exception $e) {
        // Tabelle existiert nicht - ignorieren
    }
    
    // ===== HTML OUTPUT =====
    
    // Cover-Pfade
    $frontCover = findCoverImage($dvd['cover_id'] ?? '', 'f');
    $backCover = findCoverImage($dvd['cover_id'] ?? '', 'b');
    
    // Sichere Werte extrahieren
    $title = htmlspecialchars($dvd['title'] ?? 'Unbekannt');
    $year = $dvd['year'] ? (int)$dvd['year'] : 0;
    $genre = htmlspecialchars($dvd['genre'] ?? '');
    $runtime = $dvd['runtime'] ? (int)$dvd['runtime'] : 0;
    $ratingAge = $dvd['rating_age'] ? (int)$dvd['rating_age'] : 0;
    $overview = htmlspecialchars($dvd['overview'] ?? '');
    $collectionType = htmlspecialchars($dvd['collection_type'] ?? '');
    $createdAt = formatDate($dvd['created_at'] ?? '');
    
    ob_end_clean(); // Buffer leeren für saubere Ausgabe
    
    ?>
    
<article class="film-detail" data-film-id="<?= $id ?>" itemscope itemtype="https://schema.org/Movie">
    <!-- Header -->
    <header class="film-header">
        <div class="film-title-section">
            <h1 class="film-title" itemprop="name"><?= $title ?></h1>
            
            <div class="film-meta-header">
                <?php if ($year > 0): ?>
                    <span class="film-year" itemprop="datePublished"><?= $year ?></span>
                <?php endif; ?>
                
                <?php if ($runtime > 0): ?>
                    <span class="film-runtime" itemprop="duration"><?= formatRuntime($runtime) ?></span>
                <?php endif; ?>
                
                <?php if ($ratingAge > 0): ?>
                    <span class="film-rating-age">FSK <?= $ratingAge ?></span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Bewertungen -->
        <?php if ($averageRating > 0 || $userHasRated): ?>
            <div class="rating-section">
                <?php if ($averageRating > 0): ?>
                    <div class="community-rating">
                        <span class="rating-label">Community:</span>
                        <div class="stars">
                            <?= generateStarRating($averageRating) ?>
                        </div>
                        <span class="rating-text"><?= $averageRating ?>/5 (<?= $ratingCount ?> Bewertung<?= $ratingCount !== 1 ? 'en' : '' ?>)</span>
                    </div>
                <?php endif; ?>
                
                <?php if ($userHasRated): ?>
                    <div class="user-rating">
                        <span class="rating-label">Ihre Bewertung:</span>
                        <div class="stars">
                            <?= generateStarRating($userRating) ?>
                        </div>
                        <span class="rating-text"><?= $userRating ?>/5</span>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <!-- Cover Gallery -->
    <section class="cover-gallery">
        <div class="cover-pair">
            <?php if ($frontCover !== 'cover/placeholder.png'): ?>
                <a href="<?= htmlspecialchars($frontCover) ?>" 
                   data-fancybox="gallery" 
                   data-caption="<?= $title ?> - Frontcover">
                    <img class="thumb" 
                         src="<?= htmlspecialchars($frontCover) ?>" 
                         alt="<?= $title ?> Frontcover"
                         itemprop="image"
                         loading="lazy">
                </a>
            <?php else: ?>
                <div class="no-cover">
                    <i class="bi bi-film"></i>
                    <span>Kein Cover</span>
                </div>
            <?php endif; ?>
            
            <?php if ($backCover !== 'cover/placeholder.png'): ?>
                <a href="<?= htmlspecialchars($backCover) ?>" 
                   data-fancybox="gallery" 
                   data-caption="<?= $title ?> - Backcover">
                    <img class="thumb" 
                         src="<?= htmlspecialchars($backCover) ?>" 
                         alt="<?= $title ?> Backcover"
                         loading="lazy">
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Film-Informationen -->
    <section class="film-info-grid">
        <div class="film-info-item">
            <span class="label">Genre</span>
            <span class="value" itemprop="genre"><?= $genre ?: 'Unbekannt' ?></span>
        </div>
        
        <?php if ($collectionType): ?>
            <div class="film-info-item">
                <span class="label">Typ</span>
                <span class="value"><?= $collectionType ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($runtime > 0): ?>
            <div class="film-info-item">
                <span class="label">Laufzeit</span>
                <span class="value"><?= formatRuntime($runtime) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($ratingAge > 0): ?>
            <div class="film-info-item">
                <span class="label">Altersfreigabe</span>
                <span class="value">FSK <?= $ratingAge ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($createdAt): ?>
            <div class="film-info-item">
                <span class="label">Hinzugefügt</span>
                <span class="value"><?= $createdAt ?></span>
            </div>
        <?php endif; ?>
    </section>

    <!-- Handlung -->
    <?php if ($overview): ?>
        <section class="film-overview">
            <h3><i class="bi bi-card-text"></i> Handlung</h3>
            <div class="overview-text" itemprop="description">
                <?= nl2br($overview) ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Schauspieler -->
    <?php if (!empty($actors)): ?>
        <section class="cast-section">
            <h3><i class="bi bi-people"></i> Besetzung <span class="count-badge"><?= count($actors) ?></span></h3>
            <div class="actor-list" itemprop="actor" itemscope itemtype="https://schema.org/Person">
                <?php foreach ($actors as $actor): ?>
                    <div class="actor-item">
                        <span class="actor-name" itemprop="name">
                            <?= htmlspecialchars(trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''))) ?>
                        </span>
                        <?php if (!empty($actor['role'])): ?>
                            <span class="actor-role" itemprop="characterName">
                                als <?= htmlspecialchars($actor['role']) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- BoxSet-Informationen -->
    <?php if ($boxsetParent): ?>
        <section class="boxset-info">
            <h3><i class="bi bi-collection"></i> Teil einer BoxSet</h3>
            <div class="boxset-parent">
                <a href="film-fragment.php?id=<?= $boxsetParent['id'] ?>" class="boxset-link">
                    <?= htmlspecialchars($boxsetParent['title']) ?> (<?= $boxsetParent['year'] ?>)
                </a>
            </div>
        </section>
    <?php endif; ?>

    <!-- BoxSet-Inhalte -->
    <?php if (!empty($boxsetChildren)): ?>
        <section class="boxset-contents">
            <h3><i class="bi bi-collection"></i> BoxSet-Inhalte <span class="count-badge"><?= count($boxsetChildren) ?></span></h3>
            <div class="boxset-grid">
                <?php foreach ($boxsetChildren as $child): ?>
                    <div class="boxset-item">
                        <a href="film-fragment.php?id=<?= $child['id'] ?>" class="boxset-child-link">
                            <div class="boxset-cover">
                                <img src="<?= findCoverImage($child['cover_id'] ?? '') ?>" 
                                     alt="<?= htmlspecialchars($child['title']) ?> Cover" 
                                     loading="lazy">
                            </div>
                            <div class="boxset-info">
                                <h4 class="boxset-title"><?= htmlspecialchars($child['title']) ?></h4>
                                <?php if ($child['year']): ?>
                                    <span class="boxset-year">(<?= $child['year'] ?>)</span>
                                <?php endif; ?>
                                <?php if ($child['runtime']): ?>
                                    <span class="boxset-runtime"><?= formatRuntime($child['runtime']) ?></span>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</article>

<style>
/* FILM DETAIL STYLES */
.film-detail {
    max-width: 800px;
    margin: 0 auto;
    padding: 1rem;
    color: #fff;
}

.film-header {
    margin-bottom: 2rem;
    text-align: center;
}

.film-title {
    font-size: 2rem;
    margin: 0 0 1rem 0;
    color: #fff;
}

.film-meta-header {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 1rem;
}

.film-meta-header > span {
    background: rgba(255, 255, 255, 0.1);
    padding: 0.25rem 0.75rem;
    border-radius: 16px;
    font-size: 0.9rem;
}

.rating-section {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    align-items: center;
}

.community-rating, .user-rating {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.stars {
    display: flex;
    gap: 0.2rem;
}

.star-filled { color: #ffd700; }
.star-half { color: #ffd700; }
.star-empty { color: rgba(255, 255, 255, 0.3); }

.cover-gallery {
    margin-bottom: 2rem;
}

.cover-pair {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
    max-width: 400px;
    margin: 0 auto;
}

.cover-pair img {
    width: 100%;
    height: auto;
    border-radius: 8px;
    cursor: pointer;
    transition: transform 0.3s;
}

.cover-pair img:hover {
    transform: scale(1.05);
}

.no-cover {
    aspect-ratio: 2/3;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: rgba(255, 255, 255, 0.6);
}

.film-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.film-info-item {
    background: rgba(255, 255, 255, 0.1);
    padding: 1rem;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.film-info-item .label {
    font-weight: bold;
    color: rgba(255, 255, 255, 0.8);
    font-size: 0.9rem;
}

.film-info-item .value {
    color: #fff;
}

.film-overview {
    background: rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.film-overview h3 {
    margin: 0 0 1rem 0;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.overview-text {
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.9);
}

.cast-section, .boxset-info, .boxset-contents {
    background: rgba(255, 255, 255, 0.1);
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
}

.cast-section h3, .boxset-info h3, .boxset-contents h3 {
    margin: 0 0 1rem 0;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.count-badge {
    background: rgba(52, 152, 219, 0.3);
    color: #3498db;
    padding: 0.2rem 0.5rem;
    border-radius: 12px;
    font-size: 0.8rem;
    margin-left: 0.5rem;
}

.actor-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 0.75rem;
}

.actor-item {
    background: rgba(255, 255, 255, 0.05);
    padding: 0.75rem;
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.actor-name {
    font-weight: 500;
    color: #fff;
}

.actor-role {
    font-size: 0.9rem;
    color: rgba(255, 255, 255, 0.7);
    font-style: italic;
}

.boxset-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
}

.boxset-item {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.3s;
}

.boxset-item:hover {
    transform: translateY(-5px);
}

.boxset-child-link {
    color: inherit;
    text-decoration: none;
}

.boxset-cover {
    aspect-ratio: 2/3;
    overflow: hidden;
}

.boxset-cover img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.boxset-info {
    padding: 0.75rem;
}

.boxset-title {
    margin: 0 0 0.25rem 0;
    font-size: 0.9rem;
    color: #fff;
}

.boxset-year, .boxset-runtime {
    font-size: 0.8rem;
    color: rgba(255, 255, 255, 0.7);
    margin-right: 0.5rem;
}

.error-container {
    max-width: 600px;
    margin: 2rem auto;
}

/* Responsive */
@media (max-width: 768px) {
    .film-detail {
        padding: 1rem 0.5rem;
    }
    
    .film-title {
        font-size: 1.5rem;
    }
    
    .cover-pair {
        grid-template-columns: 1fr;
        max-width: 200px;
    }
    
    .film-info-grid {
        grid-template-columns: 1fr;
    }
    
    .boxset-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
    
    .actor-list {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Fancybox für Cover-Bilder (falls verfügbar)
if (typeof Fancybox !== 'undefined') {
    Fancybox.bind('[data-fancybox="gallery"]', {
        animated: true,
        showClass: "fancybox-fadeIn",
        hideClass: "fancybox-fadeOut"
    });
}

console.log('🎬 Film-Detail geladen:', <?= json_encode($title) ?>);
</script>

<?php

} catch (Exception $e) {
    // Buffer leeren bei Fehler
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log('Film-fragment error: ' . $e->getMessage());
    
    $errorClass = strpos($e->getMessage(), 'nicht gefunden') !== false ? 'not-found' : 'server-error';
    $errorIcon = $errorClass === 'not-found' ? 'bi-search' : 'bi-exclamation-triangle';
    $safeErrorMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    
    echo '<div class="error-container ' . $errorClass . '" style="
        padding: 2rem;
        text-align: center;
        background: rgba(0, 0, 0, 0.8);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        color: #fff;
        margin: 2rem;
    ">
        <div class="error-content">
            <i class="' . $errorIcon . '" style="font-size: 3rem; margin-bottom: 1rem; color: #dc3545;"></i>
            <h2>Film konnte nicht geladen werden</h2>
            <p><strong>Fehler:</strong> ' . $safeErrorMsg . '</p>
            <a href="javascript:history.back()" style="
                display: inline-block;
                margin-top: 1rem;
                padding: 0.75rem 1.5rem;
                background: #3498db;
                color: white;
                text-decoration: none;
                border-radius: 8px;
                transition: background-color 0.3s;
            ">← Zurück</a>
        </div>
    </div>';
}
?>