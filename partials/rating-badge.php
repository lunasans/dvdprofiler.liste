<!-- partials/rating-badge.php -->
<?php
/**
 * Rating Badge für Film-Kacheln
 * Zeigt TMDb Rating als kleines Badge
 * 
 * Usage: include mit $film Array
 */

if (!isset($film)) return;

// Ratings holen (falls noch nicht geladen)
if (!isset($film['_ratings'])) {
    $ratings = getFilmRatings($film['title'], $film['year'] ?? null);
} else {
    $ratings = $film['_ratings'];
}

// Wenn keine Ratings, nichts anzeigen
if (!$ratings || empty($ratings['tmdb_rating'])) {
    return;
}

$rating = $ratings['tmdb_rating'];
$color = TMDbHelper::getRatingColor($rating);
?>

<div class="rating-badge" style="background-color: <?= $color ?>;">
    <i class="bi bi-star-fill"></i>
    <span><?= number_format($rating, 1) ?></span>
</div>

<style>
.rating-badge {
    position: absolute;
    top: 8px;
    left: 8px;
    z-index: 10;
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 4px 8px;
    border-radius: 6px;
    font-size: 0.75rem;
    font-weight: 700;
    color: white;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    backdrop-filter: blur(4px);
}

.rating-badge i {
    font-size: 0.7rem;
}

.rating-badge span {
    line-height: 1;
}
</style>