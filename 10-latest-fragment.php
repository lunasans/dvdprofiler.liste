<?php
// 10-latest-fragment.php - Zeigt die neuesten Filme (dynamisch konfigurierbar)
require_once __DIR__ . '/includes/bootstrap.php';

// Anzahl aus Settings laden (Standard: 10)
$latestCount = (int)getSetting('latest_films_count', '10');

// Validierung (5-50)
if ($latestCount < 5) $latestCount = 5;
if ($latestCount > 50) $latestCount = 50;

// Neueste Filme laden (sortiert nach Kaufdatum)
$sql = "SELECT * FROM dvds WHERE deleted = 0 ORDER BY created_at DESC, id DESC LIMIT :limit";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $latestCount, PDO::PARAM_INT);
$stmt->execute();
$latest = $stmt->fetchAll();

// Total Count für Anzeige
$countStmt = $pdo->query("SELECT COUNT(*) FROM dvds WHERE deleted = 0");
$totalRecords = (int)$countStmt->fetchColumn();

// Helper function
function safeFormatRuntime($minutes) {
    if (!$minutes || $minutes <= 0) return '';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}
?>

<header class="latest-header">
    <h2>
        <i class="bi bi-stars"></i>
        Neu hinzugefügt 
        <span class="item-count">(<?= count($latest) ?> neueste von <?= number_format($totalRecords) ?> Filmen)</span>
    </h2>
</header>
<section class="latest-grid" style="padding-top: 37px;">
    <?php if (empty($latest)): ?>
        <div class="empty-state">
            <i class="bi bi-film"></i>
            <h3>Keine Filme gefunden</h3>
            <p>Noch keine Filme in der Sammlung vorhanden.</p>
        </div>
    <?php else: ?>
        <div class="latest-list">
            <?php foreach ($latest as $dvd): 
                // Sichere Werte
                $title = isset($dvd['title']) ? htmlspecialchars($dvd['title']) : 'Unbekannt';
                $year = isset($dvd['year']) && $dvd['year'] ? (int)$dvd['year'] : 0;
                $id = isset($dvd['id']) ? (int)$dvd['id'] : 0;
                $runtime = isset($dvd['runtime']) && $dvd['runtime'] ? (int)$dvd['runtime'] : 0;
                $genre = isset($dvd['genre']) && $dvd['genre'] ? htmlspecialchars($dvd['genre']) : 'Unbekannt';
                
                // Cover mit Fallback
                $cover = 'cover/placeholder.png'; // Default
                if (isset($dvd['cover_id']) && $dvd['cover_id']) {
                    if (function_exists('findCoverImage')) {
                        $cover = findCoverImage($dvd['cover_id'], 'f');
                    } else {
                        // Manueller Fallback falls Funktion fehlt
                        $testCover = "cover/{$dvd['cover_id']}f.jpg";
                        if (file_exists($testCover)) {
                            $cover = $testCover;
                        }
                    }
                }
                
                // Added Date
                $addedDate = '';
                if (isset($dvd['created_at']) && $dvd['created_at']) {
                    try {
                        $date = new DateTime($dvd['created_at']);
                        $now = new DateTime();
                        $diff = $now->diff($date);
                        
                        if ($diff->days === 0) {
                            $addedDate = 'Heute';
                        } elseif ($diff->days === 1) {
                            $addedDate = 'Gestern';
                        } elseif ($diff->days < 7) {
                            $addedDate = 'Vor ' . $diff->days . ' Tagen';
                        } else {
                            $addedDate = $date->format('d.m.Y');
                        }
                    } catch (Exception $e) {
                        $addedDate = '';
                    }
                }
                
                // View count
                $viewCount = isset($dvd['view_count']) ? (int)$dvd['view_count'] : 0;
                $isPopular = $viewCount > 20;
                $isRecent = false;
                
                if (isset($dvd['created_at']) && $dvd['created_at']) {
                    $isRecent = (time() - strtotime($dvd['created_at'])) < (7 * 24 * 60 * 60);
                }
            ?>
                <div class="latest-card <?= $isPopular ? 'popular' : '' ?>" data-film-id="<?= $id ?>">
                    
                    <?php if ($isRecent): ?>
                        <div class="new-badge">
                            <i class="bi bi-star-fill"></i>
                            <span>NEU</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isPopular): ?>
                        <div class="popularity-badge" title="<?= $viewCount ?> Aufrufe">
                            <i class="bi bi-eye-fill"></i>
                        </div>
                    <?php endif; ?>
                    
                    <a href="#" class="toggle-detail" data-id="<?= $id ?>">
                        <div class="card-image">
                            <img src="<?= htmlspecialchars($cover) ?>" 
                                 alt="Cover von <?= $title ?>"
                                 loading="lazy"
                                 onerror="this.src='cover/placeholder.png'">
                            
                            <div class="image-overlay">
                                <div class="play-button">
                                    <i class="bi bi-play-fill"></i>
                                </div>
                            </div>
                            
                            <div class="hover-info">
                                <div class="info-item">
                                    <i class="bi bi-tag"></i>
                                    <span><?= $genre ?></span>
                                </div>
                                
                                <?php if ($runtime > 0): ?>
                                    <div class="info-item">
                                        <i class="bi bi-clock"></i>
                                        <span><?= safeFormatRuntime($runtime) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($viewCount > 0): ?>
                                    <div class="info-item">
                                        <i class="bi bi-eye"></i>
                                        <span><?= number_format($viewCount) ?> Views</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="latest-title">
                            <h3><?= $title ?></h3>
                            <div class="film-meta">
                                <span class="year"><?= $year > 0 ? $year : '?' ?></span>
                                <?php if ($addedDate): ?>
                                    <span class="added-date">
                                        <i class="bi bi-plus-circle"></i>
                                        <?= $addedDate ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                    
                    <!-- Quick Actions (nur wenn eingeloggt) -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <div class="card-actions">
                            <button class="action-btn wishlist-btn" 
                                    data-film-id="<?= $id ?>"
                                    title="Zur Wunschliste">
                                <i class="bi bi-heart"></i>
                            </button>
                            
                            <button class="action-btn watched-btn" 
                                    data-film-id="<?= $id ?>"
                                    title="Als gesehen markieren">
                                <i class="bi bi-check-circle"></i>
                            </button>
                            
                            <button class="action-btn share-btn" 
                                    data-film-id="<?= $id ?>"
                                    title="Film teilen">
                                <i class="bi bi-share"></i>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Latest fragment loaded with', <?= count($latest) ?>, 'films');
    
    // Quick Actions
    document.querySelectorAll('.wishlist-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Wishlist clicked for film', this.dataset.filmId);
            // TODO: Implement wishlist functionality
        });
    });
    
    document.querySelectorAll('.watched-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            console.log('Watched clicked for film', this.dataset.filmId);
            // TODO: Implement watched functionality
        });
    });
    
    document.querySelectorAll('.share-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            const filmId = this.dataset.filmId;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Film teilen',
                    url: window.location.origin + window.location.pathname + '?id=' + filmId
                });
            } else {
                // Fallback
                const url = window.location.origin + window.location.pathname + '?id=' + filmId;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(() => {
                        alert('Link kopiert!');
                    });
                } else {
                    prompt('Film-Link:', url);
                }
            }
        });
    });
    
    // Lazy Loading für Bilder
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.classList.add('loaded');
                    imageObserver.unobserve(img);
                }
            });
        });
        
        document.querySelectorAll('.latest-card img').forEach(img => {
            imageObserver.observe(img);
        });
    }
});
</script>

<style>
/* Basis-Styles für die Latest Cards */
.latest-header {
    margin-bottom: 2rem;
    text-align: center;
}

.latest-header h2 {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    color: var(--text-white, #fff);
    margin: 0;
}

.item-count {
    font-size: 0.8em;
    opacity: 0.7;
    font-weight: 400;
}

.page-info {
    margin-top: 0.5rem;
    color: var(--text-glass, rgba(255,255,255,0.8));
    font-size: 0.9rem;
}

.latest-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.latest-card {
    position: relative;
    background: var(--glass-bg, rgba(255,255,255,0.1));
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--glass-border, rgba(255,255,255,0.2));
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.latest-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}

.new-badge {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    background: linear-gradient(45deg, #ff6b6b, #ff8e53);
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    z-index: 3;
    display: flex;
    align-items: center;
    gap: 2px;
}

.popularity-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: linear-gradient(45deg, #4facfe, #00f2fe);
    color: white;
    padding: 4px;
    border-radius: 50%;
    font-size: 0.8rem;
    z-index: 3;
}

.card-image {
    position: relative;
    overflow: hidden;
}

.card-image img {
    width: 100%;
    height: 240px;
    object-fit: cover;
    transition: transform 0.4s ease;
}

.latest-card:hover .card-image img {
    transform: scale(1.1);
}

.image-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.3);
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.latest-card:hover .image-overlay {
    opacity: 1;
}

.play-button {
    background: rgba(255,255,255,0.2);
    backdrop-filter: blur(10px);
    border: 2px solid rgba(255,255,255,0.3);
    border-radius: 50%;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
    transform: scale(0.8);
    transition: transform 0.3s ease;
}

.latest-card:hover .play-button {
    transform: scale(1);
}

.hover-info {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    background: linear-gradient(transparent, rgba(0,0,0,0.8));
    color: white;
    padding: 2rem 1rem 1rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    font-size: 0.8rem;
}

.latest-card:hover .hover-info {
    opacity: 1;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
}

.latest-title {
    padding: 1rem;
}

.latest-title h3 {
    font-size: 1rem;
    font-weight: 600;
    color: var(--text-white, #fff);
    margin: 0 0 0.5rem 0;
    line-height: 1.3;
}

.film-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--text-glass, rgba(255,255,255,0.8));
}

.added-date {
    display: flex;
    align-items: center;
    gap: 2px;
    opacity: 0.8;
    font-size: 0.8rem;
}

.card-actions {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: 4;
}

.latest-card:hover .card-actions {
    opacity: 1;
}

.action-btn {
    background: rgba(0,0,0,0.7);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.action-btn:hover {
    background: var(--gradient-primary, #667eea);
    transform: scale(1.1);
}

.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 3rem;
    color: var(--text-glass, rgba(255,255,255,0.8));
}

.empty-state i {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.pagination-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    margin-top: 2rem;
}

.pagination {
    display: flex;
    gap: 0.5rem;
    align-items: center;
}

.pagination a,
.pagination .current,
.pagination .dots {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 40px;
    padding: 0.5rem;
    border-radius: 8px;
    color: var(--text-glass, rgba(255,255,255,0.8));
    text-decoration: none;
    transition: all 0.2s ease;
    background: var(--glass-bg, rgba(255,255,255,0.1));
    border: 1px solid var(--glass-border, rgba(255,255,255,0.2));
}

.pagination .current {
    background: var(--gradient-primary, #667eea);
    color: white;
    font-weight: 600;
}

.pagination a:hover {
    background: var(--gradient-accent, #4facfe);
    color: white;
    transform: translateY(-2px);
}

.pagination-info {
    color: var(--text-glass, rgba(255,255,255,0.8));
    font-size: 0.9rem;
    text-align: center;
}

/* Mobile Optimierungen */
@media (max-width: 768px) {
    .latest-list {
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 1rem;
    }
    
    .card-image img {
        height: 200px;
    }
    
    .latest-title {
        padding: 0.75rem;
    }
    
    .latest-title h3 {
        font-size: 0.9rem;
    }
    
    .card-actions {
        position: static;
        flex-direction: row;
        justify-content: center;
        opacity: 1;
        padding: 0.5rem;
        background: rgba(0,0,0,0.5);
    }
}

@media (max-width: 480px) {
    .latest-list {
        grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
        gap: 0.75rem;
    }
    
    .card-image img {
        height: 160px;
    }
}
</style>