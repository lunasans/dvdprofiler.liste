<!-- partials/rating-details.php -->
<?php
/**
 * Ausführliche Rating-Anzeige für Film-Detail-Seite
 * Zeigt TMDb + IMDb Ratings mit schönem Design
 * 
 * Usage: include mit $film Array
 */

if (!isset($film)) return;

// Ratings holen
$ratings = getFilmRatings($film['title'], $film['year'] ?? null);

// Wenn keine Ratings, nichts anzeigen
if (!$ratings) {
    return;
}
?>

<div class="ratings-container">
    <h3 class="ratings-title">
        <i class="bi bi-star-fill"></i>
        Bewertungen
    </h3>
    
    <div class="ratings-grid">
        <!-- TMDb Rating -->
        <?php if (!empty($ratings['tmdb_rating']) && $ratings['tmdb_rating'] > 0): ?>
        <div class="rating-card tmdb">
            <div class="rating-logo">
                <img src="https://www.themoviedb.org/assets/2/v4/logos/v2/blue_short-8e7b30f73a4020692ccca9c88bafe5dcb6f8a62a4c6bc55cd9ba82bb2cd95f6c.svg" 
                     alt="TMDb" 
                     style="height: 24px;">
            </div>
            <div class="rating-score" style="color: <?= TMDbHelper::getRatingColor($ratings['tmdb_rating']) ?>;">
                <?= number_format($ratings['tmdb_rating'], 1) ?><span class="rating-max">/10</span>
            </div>
            <div class="rating-votes">
                <?= TMDbHelper::formatRating($ratings['tmdb_rating'], $ratings['tmdb_votes']) ?>
            </div>
            <?php if (!empty($ratings['tmdb_popularity'])): ?>
            <div class="rating-meta">
                Popularität: <?= $ratings['tmdb_popularity'] ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- IMDb Link (wenn ID vorhanden) -->
        <?php if (!empty($ratings['imdb_id'])): ?>
        <div class="rating-card imdb">
            <div class="rating-logo">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Cpath fill='%23f5c518' d='M0 0h48v48H0z'/%3E%3Cpath d='M9 13h4v22H9zm6 0h4l2 8 2-8h4v22h-3V20l-2 7h-2l-2-7v15h-3zm17 0h8v3h-5v5h5v3h-5v8h5v3h-8z'/%3E%3C/svg%3E" 
                     alt="IMDb" 
                     style="height: 32px;">
            </div>
            <div class="rating-link">
                <a href="https://www.imdb.com/title/<?= htmlspecialchars($ratings['imdb_id']) ?>/" 
                   target="_blank" 
                   rel="noopener">
                    Auf IMDb ansehen
                    <i class="bi bi-box-arrow-up-right"></i>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
.ratings-container {
    margin: var(--space-lg, 1.5rem) 0;
    padding: var(--space-lg, 1.5rem);
    background: var(--bg-secondary, rgba(255, 255, 255, 0.05));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-lg, 12px);
}

.ratings-title {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 0.5rem);
    margin: 0 0 var(--space-md, 1rem) 0;
    font-size: 1.3rem;
    color: var(--text-primary, #e4e4e7);
}

.ratings-title i {
    color: var(--accent-primary, #667eea);
}

.ratings-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-md, 1rem);
}

.rating-card {
    padding: var(--space-md, 1rem);
    background: var(--bg-tertiary, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-md, 8px);
    text-align: center;
}

.rating-logo {
    margin-bottom: var(--space-sm, 0.5rem);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 36px;
}

.rating-logo img {
    max-height: 100%;
}

.rating-score {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin: var(--space-sm, 0.5rem) 0;
}

.rating-max {
    font-size: 1.2rem;
    opacity: 0.6;
    font-weight: 400;
}

.rating-votes {
    font-size: 0.9rem;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
    margin-top: var(--space-xs, 0.35rem);
}

.rating-meta {
    font-size: 0.85rem;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
    margin-top: var(--space-xs, 0.35rem);
}

.rating-link {
    margin-top: var(--space-md, 1rem);
}

.rating-link a {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs, 0.35rem);
    padding: var(--space-sm, 0.5rem) var(--space-md, 1rem);
    background: var(--accent-primary, #667eea);
    color: white;
    text-decoration: none;
    border-radius: var(--radius-sm, 6px);
    font-size: 0.9rem;
    font-weight: 600;
    transition: all 0.3s ease;
}

.rating-link a:hover {
    background: var(--accent-hover, #764ba2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Responsive */
@media (max-width: 600px) {
    .ratings-grid {
        grid-template-columns: 1fr;
    }
    
    .rating-score {
        font-size: 2rem;
    }
}
</style>