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

// Bewertung berechnen (falls vorhanden)
$averageRating = 0;
$ratingCount = 0;
try {
    $ratingStmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as count 
        FROM user_ratings 
        WHERE film_id = ?
    ");
    $ratingStmt->execute([$dvd['id']]);
    $ratingData = $ratingStmt->fetch(PDO::FETCH_ASSOC);
    $averageRating = round((float)($ratingData['avg_rating'] ?? 0), 1);
    $ratingCount = (int)($ratingData['count'] ?? 0);
} catch (PDOException $e) {
    error_log("Rating query error: " . $e->getMessage());
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
        
        <?php if ($boxsetParent): ?>
            <div class="boxset-breadcrumb">
                <i class="bi bi-collection"></i>
                Teil von: 
                <a href="#" class="toggle-detail" data-id="<?= $boxsetParent['id'] ?>">
                    <?= htmlspecialchars($boxsetParent['title']) ?> (<?= $boxsetParent['year'] ?>)
                </a>
            </div>
        <?php endif; ?>
        
        <?php if ($averageRating > 0): ?>
            <div class="film-rating">
                <div class="stars">
                    <?= generateStarRating($averageRating) ?>
                </div>
                <span class="rating-text">
                    <?= $averageRating ?>/5 
                    <small>(<?= $ratingCount ?> Bewertung<?= $ratingCount !== 1 ? 'en' : '' ?>)</small>
                </span>
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
        
        <?php if (!empty($dvd['rating_age'])): ?>
            <div class="film-info-item">
                <span class="label">Altersfreigabe</span>
                <span class="value">ab <?= htmlspecialchars((string)$dvd['rating_age']) ?> Jahren</span>
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
                <?= nl2br(htmlspecialchars($dvd['overview'])) ?>
            </div>
        </section>
    <?php endif; ?>

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
                <div class="trailer-box" data-src="<?= htmlspecialchars($dvd['trailer_url']) ?>">
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

    <!-- User-Bewertung (falls eingeloggt) -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <section class="meta-card">
            <h3><i class="bi bi-star"></i> Bewertung</h3>
            <div class="user-rating-section">
                <div class="rating-input">
                    <span>Ihre Bewertung:</span>
                    <div class="star-rating-input" data-film-id="<?= $dvd['id'] ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="bi bi-star rating-star" data-rating="<?= $i ?>"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <button class="btn btn-primary save-rating" style="display: none;">
                    <i class="bi bi-check"></i> Bewertung speichern
                </button>
            </div>
        </section>
    <?php endif; ?>

    <!-- Film-Aktionen -->
    <section class="film-actions">
        <button class="close-detail-button btn btn-secondary" onclick="closeDetail()">
            <i class="bi bi-x-lg"></i> Schließen
        </button>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <button class="btn btn-outline-primary add-to-wishlist" data-film-id="<?= $dvd['id'] ?>">
                <i class="bi bi-heart"></i> Zur Wunschliste
            </button>
            
            <button class="btn btn-outline-secondary mark-as-watched" data-film-id="<?= $dvd['id'] ?>">
                <i class="bi bi-check-circle"></i> Als gesehen markieren
            </button>
        <?php endif; ?>
        
        <button class="btn btn-outline-info share-film" data-film-id="<?= $dvd['id'] ?>">
            <i class="bi bi-share"></i> Teilen
        </button>
    </section>
</div>

<!-- Enhanced JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Trailer-Funktionalität
    const trailerBox = document.querySelector('.trailer-box');
    if (trailerBox) {
        trailerBox.addEventListener('click', function() {
            const trailerUrl = this.dataset.src;
            if (trailerUrl) {
                // Fancybox oder direktes Popup für Trailer
                window.open(trailerUrl, 'trailer', 'width=800,height=600');
            }
        });
    }
    
    // Rating-System
    const ratingStars = document.querySelectorAll('.rating-star');
    const saveRatingBtn = document.querySelector('.save-rating');
    let selectedRating = 0;
    
    ratingStars.forEach(star => {
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            highlightStars(rating);
        });
        
        star.addEventListener('click', function() {
            selectedRating = parseInt(this.dataset.rating);
            highlightStars(selectedRating);
            if (saveRatingBtn) {
                saveRatingBtn.style.display = 'inline-block';
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
            toggleWishlist(filmId);
        });
    }
    
    // Als gesehen markieren
    const watchedBtn = document.querySelector('.mark-as-watched');
    if (watchedBtn) {
        watchedBtn.addEventListener('click', function() {
            const filmId = this.dataset.filmId;
            markAsWatched(filmId);
        });
    }
    
    // Share-Funktionalität
    const shareBtn = document.querySelector('.share-film');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            const filmId = this.dataset.filmId;
            shareFilm(filmId);
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
});

// AJAX-Funktionen
async function saveUserRating(filmId, rating) {
    try {
        const response = await fetch('ajax/save-rating.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ film_id: filmId, rating: rating })
        });
        
        if (response.ok) {
            showNotification('Bewertung gespeichert!', 'success');
            document.querySelector('.save-rating').style.display = 'none';
        }
    } catch (error) {
        showNotification('Fehler beim Speichern der Bewertung', 'error');
    }
}

async function toggleWishlist(filmId) {
    try {
        const response = await fetch('ajax/toggle-wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ film_id: filmId })
        });
        
        if (response.ok) {
            const result = await response.json();
            const btn = document.querySelector('.add-to-wishlist');
            if (result.added) {
                btn.innerHTML = '<i class="bi bi-heart-fill"></i> Auf Wunschliste';
                btn.classList.add('active');
            } else {
                btn.innerHTML = '<i class="bi bi-heart"></i> Zur Wunschliste';
                btn.classList.remove('active');
            }
        }
    } catch (error) {
        showNotification('Fehler bei Wunschliste', 'error');
    }
}

async function markAsWatched(filmId) {
    try {
        const response = await fetch('ajax/mark-watched.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ film_id: filmId })
        });
        
        if (response.ok) {
            showNotification('Als gesehen markiert!', 'success');
            const btn = document.querySelector('.mark-as-watched');
            btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Gesehen';
            btn.classList.add('active');
        }
    } catch (error) {
        showNotification('Fehler beim Markieren', 'error');
    }
}

function shareFilm(filmId) {
    if (navigator.share) {
        navigator.share({
            title: document.querySelector('h2[itemprop="name"]').textContent,
            url: window.location.href + '?id=' + filmId
        });
    } else {
        // Fallback: URL in Zwischenablage kopieren
        const url = window.location.href + '?id=' + filmId;
        navigator.clipboard.writeText(url).then(() => {
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
        padding: 1rem;
        background: var(--glass-bg-strong);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-md);
        color: var(--text-white);
        z-index: 10000;
        backdrop-filter: blur(10px);
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>

<style>
/* Enhanced Film View Styles */
.film-header {
    margin-bottom: var(--space-xl);
    text-align: center;
}

.film-year {
    color: var(--text-glass);
    opacity: 0.8;
    font-weight: 400;
}

.boxset-breadcrumb {
    margin-top: var(--space-sm);
    font-size: 0.9rem;
    color: var(--text-glass);
}

.boxset-breadcrumb a {
    color: var(--text-white);
    text-decoration: underline;
}

.film-rating {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-md);
    margin-top: var(--space-md);
    flex-wrap: wrap;
}

.stars {
    display: flex;
    gap: 2px;
}

.star-filled {
    color: #ffd700;
}

.star-half {
    color: #ffd700;
}

.star-empty {
    color: rgba(255, 255, 255, 0.3);
}

.additional-covers {
    display: flex;
    gap: var(--space-sm);
    justify-content: center;
    margin-top: var(--space-md);
}

.small-cover {
    width: 60px;
    height: 80px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    border: 1px solid var(--glass-border);
    transition: transform var(--transition-fast);
}

.small-cover:hover {
    transform: scale(1.1);
}

.meta-card.full-width {
    grid-column: 1 / -1;
}

.meta-card h3 {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    color: var(--text-white);
    font-size: 1.2rem;
    margin-bottom: var(--space-lg);
    border-bottom: 1px solid var(--glass-border);
    padding-bottom: var(--space-sm);
}

.overview-text {
    line-height: 1.7;
    font-size: 1rem;
}

.actor-item {
    display: flex;
    flex-direction: column;
    margin-bottom: var(--space-sm);
    padding: var(--space-sm);
    background: var(--glass-bg);
    border-radius: var(--radius-sm);
    border: 1px solid var(--glass-border);
}

.actor-name {
    font-weight: 600;
    color: var(--text-white);
}

.actor-role {
    font-size: 0.9rem;
    color: var(--text-glass);
    opacity: 0.8;
}

.boxset-children-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: var(--space-md);
}

.boxset-child-item {
    background: var(--glass-bg);
    border-radius: var(--radius-md);
    overflow: hidden;
    border: 1px solid var(--glass-border);
    transition: transform var(--transition-fast);
}

.boxset-child-item:hover {
    transform: translateY(-4px);
}

.boxset-child-item a {
    display: block;
    text-decoration: none;
    color: inherit;
}

.child-cover {
    width: 100%;
    height: 120px;
    object-fit: cover;
}

.child-info {
    padding: var(--space-sm);
}

.child-info h4 {
    font-size: 0.9rem;
    margin-bottom: var(--space-xs);
    color: var(--text-white);
}

.child-info p {
    font-size: 0.8rem;
    color: var(--text-glass);
    margin-bottom: 2px;
}

.trailer-container {
    position: relative;
}

.trailer-overlay {
    position: absolute;
    bottom: var(--space-md);
    left: var(--space-md);
    right: var(--space-md);
    background: var(--glass-bg-strong);
    backdrop-filter: blur(10px);
    padding: var(--space-sm);
    border-radius: var(--radius-sm);
    text-align: center;
    color: var(--text-white);
    opacity: 0;
    transition: opacity var(--transition-fast);
}

.trailer-box:hover .trailer-overlay {
    opacity: 1;
}

.user-rating-section {
    display: flex;
    align-items: center;
    gap: var(--space-lg);
    flex-wrap: wrap;
}

.star-rating-input {
    display: flex;
    gap: 4px;
}

.rating-star {
    font-size: 1.5rem;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.3);
    transition: color var(--transition-fast);
}

.rating-star:hover,
.rating-star.bi-star-fill {
    color: #ffd700;
}

.film-actions {
    display: flex;
    gap: var(--space-md);
    flex-wrap: wrap;
    justify-content: center;
    margin-top: var(--space-xl);
    padding-top: var(--space-xl);
    border-top: 1px solid var(--glass-border);
}

.btn.active {
    background: var(--gradient-primary);
    color: var(--text-white);
}

@media (max-width: 768px) {
    .film-rating {
        flex-direction: column;
        gap: var(--space-sm);
    }
    
    .user-rating-section {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .film-actions {
        flex-direction: column;
    }
    
    .film-actions .btn {
        width: 100%;
    }
    
    .boxset-children-grid {
        grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
        gap: var(--space-sm);
    }
}

.fade-in {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.notification {
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>