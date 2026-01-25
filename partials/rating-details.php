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
    </div>
    
    <!-- External Links - Logo-Leiste -->
    <div class="external-links">
        <?php if (!empty($ratings['imdb_id'])): ?>
        <a href="https://www.imdb.com/title/<?= htmlspecialchars($ratings['imdb_id']) ?>/" 
           target="_blank" 
           rel="noopener"
           class="external-logo-link"
           title="Auf IMDb ansehen">
            <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 48 48'%3E%3Cpath fill='%23f5c518' d='M0 0h48v48H0z'/%3E%3Cpath d='M9 13h4v22H9zm6 0h4l2 8 2-8h4v22h-3V20l-2 7h-2l-2-7v15h-3zm17 0h8v3h-5v5h5v3h-5v8h5v3h-8z'/%3E%3C/svg%3E" 
                 alt="IMDb">
        </a>
        <?php endif; ?>
        
        <?php if (!empty($ratings['tmdb_rating'])): ?>
        <a href="https://www.themoviedb.org/search?query=<?= urlencode($film['title']) ?>" 
           target="_blank" 
           rel="noopener"
           class="external-logo-link"
           title="Auf TMDb ansehen">
            <img src="https://www.themoviedb.org/assets/2/v4/logos/v2/blue_square_2-d537fb228cf3ded904ef09b136fe3fec72548ebc1fea3fbbd1ad9e36364db38b.svg" 
                 alt="TMDb">
        </a>
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

/* External Links - Logo-Leiste */
.external-links {
    display: flex;
    gap: 1.5rem;
    align-items: center;
    margin-top: var(--space-lg, 1.5rem);
    padding-top: var(--space-lg, 1.5rem);
    border-top: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
}

.external-logo-link {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.75rem 1.25rem;
    background: var(--bg-tertiary, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-md, 8px);
    transition: all 0.3s ease;
    text-decoration: none;
}

.external-logo-link:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
    border-color: var(--border-color, rgba(255, 255, 255, 0.3));
}

.external-logo-link img {
    height: 36px;
    width: auto;
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