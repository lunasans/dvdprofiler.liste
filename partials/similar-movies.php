<?php
/**
 * similar-movies.php
 * Zeigt ähnliche Filme basierend auf TMDb
 * 
 * Variablen die gesetzt sein müssen:
 * $film - Array mit Film-Daten (title, year)
 */

// TMDb aktiviert?
if (getSetting('tmdb_show_similar_movies', '1') != '1' || empty(getSetting('tmdb_api_key', ''))) {
    return;
}

// TMDb Helper initialisieren
$apiKey = getSetting('tmdb_api_key', '');
$tmdb = new TMDbHelper($apiKey);

// Ähnliche Filme laden
$similarMovies = $tmdb->getSimilarMovies($film['title'], $film['year'] ?? null, 8);

if (empty($similarMovies)) {
    return;
}
?>

<section class="similar-movies-section">
    <h3>
        <i class="bi bi-film"></i>
        Das könnte dir auch gefallen
    </h3>
    
    <div class="similar-movies-grid">
        <?php foreach ($similarMovies as $movie): ?>
            <div class="similar-movie-card">
                <?php if ($movie['poster_path']): ?>
                    <img src="https://image.tmdb.org/t/p/w185<?= htmlspecialchars($movie['poster_path']) ?>" 
                         alt="<?= htmlspecialchars($movie['title']) ?>"
                         loading="lazy">
                <?php else: ?>
                    <div class="no-poster">
                        <i class="bi bi-film"></i>
                    </div>
                <?php endif; ?>
                
                <div class="similar-movie-info">
                    <h4><?= htmlspecialchars($movie['title']) ?></h4>
                    
                    <div class="similar-movie-meta">
                        <?php if ($movie['year']): ?>
                            <span class="year"><?= $movie['year'] ?></span>
                        <?php endif; ?>
                        
                        <?php if ($movie['rating'] > 0): ?>
                            <span class="rating">
                                <i class="bi bi-star-fill"></i>
                                <?= number_format($movie['rating'], 1) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($movie['overview'])): ?>
                        <p class="overview"><?= htmlspecialchars(mb_substr($movie['overview'], 0, 100)) ?>...</p>
                    <?php endif; ?>
                    
                    <a href="https://www.themoviedb.org/movie/<?= $movie['tmdb_id'] ?>" 
                       target="_blank" 
                       rel="noopener"
                       class="btn-tmdb-link">
                        Auf TMDb ansehen <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<style>
.similar-movies-section {
    margin: var(--space-xl, 2rem) 0;
    padding: var(--space-lg, 1.5rem);
    background: var(--bg-secondary, rgba(255, 255, 255, 0.05));
    border-radius: var(--radius-lg, 12px);
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
}

.similar-movies-section h3 {
    margin-bottom: var(--space-lg, 1.5rem);
    display: flex;
    align-items: center;
    gap: var(--space-sm, 0.5rem);
    color: var(--text-primary, #e4e4e7);
}

.similar-movies-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: var(--space-md, 1rem);
}

.similar-movie-card {
    background: var(--bg-tertiary, rgba(255, 255, 255, 0.03));
    border-radius: var(--radius-md, 8px);
    overflow: hidden;
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.08));
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.similar-movie-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    border-color: var(--accent-primary, #667eea);
}

.similar-movie-card img {
    width: 100%;
    aspect-ratio: 2/3;
    object-fit: cover;
    display: block;
}

.similar-movie-card .no-poster {
    width: 100%;
    aspect-ratio: 2/3;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-quaternary, rgba(255, 255, 255, 0.02));
    color: var(--text-muted, rgba(228, 228, 231, 0.4));
}

.similar-movie-card .no-poster i {
    font-size: 3rem;
}

.similar-movie-info {
    padding: var(--space-sm, 0.5rem);
    flex: 1;
    display: flex;
    flex-direction: column;
}

.similar-movie-info h4 {
    font-size: 0.9rem;
    margin: 0 0 var(--space-xs, 0.35rem) 0;
    color: var(--text-primary, #e4e4e7);
    line-height: 1.3;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.similar-movie-meta {
    display: flex;
    gap: var(--space-sm, 0.5rem);
    align-items: center;
    font-size: 0.8rem;
    margin-bottom: var(--space-xs, 0.35rem);
}

.similar-movie-meta .year {
    color: var(--text-secondary, rgba(228, 228, 231, 0.7));
}

.similar-movie-meta .rating {
    color: var(--accent-primary, #ffd700);
    display: flex;
    align-items: center;
    gap: 2px;
}

.similar-movie-info .overview {
    font-size: 0.75rem;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
    line-height: 1.4;
    margin: var(--space-xs, 0.35rem) 0;
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}

.btn-tmdb-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    color: var(--accent-primary, #667eea);
    text-decoration: none;
    margin-top: auto;
    padding-top: var(--space-xs, 0.35rem);
    transition: color 0.2s ease;
}

.btn-tmdb-link:hover {
    color: var(--accent-hover, #764ba2);
}

.btn-tmdb-link i {
    font-size: 0.7rem;
}

/* Responsive */
@media (max-width: 768px) {
    .similar-movies-grid {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    }
}
</style>