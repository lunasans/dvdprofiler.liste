<?php
/**
 * 10-Latest-Fragment - Vollständig migriert auf neues Core-System
 * Zeigt die neuesten Filme mit Pagination und erweiterten Features
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+ - Core Integration
 */

declare(strict_types=1);

try {
    // Core-System laden
    require_once __DIR__ . '/includes/bootstrap.php';
    
    // Application-Instance abrufen
    $app = \DVDProfiler\Core\Application::getInstance();
    $database = $app->getDatabase();
    $security = $app->getSecurity();
    $validation = new \DVDProfiler\Core\Validation();
    
    // Sicherheitsheader setzen
    $security->setSecurityHeaders();
    
    // Input-Validierung mit Core-System
    $validator = $validation::make($_GET, [
        'seite' => 'integer|min:1|max:1000',
        'per_page' => 'integer|min:5|max:50'
    ]);
    
    if ($validator->hasErrors()) {
        http_response_code(400);
        throw new InvalidArgumentException('Ungültige Parameter: ' . implode(', ', $validator->getErrors()));
    }
    
    $validatedData = $validator->getValidatedData();
    $page = $validatedData['seite'] ?? 1;
    $perPage = $validatedData['per_page'] ?? 15;
    $offset = ($page - 1) * $perPage;
    
    // Rate-Limiting für Latest-Anfragen
    $clientIP = $security::getClientIP();
    if (!$app->checkRateLimit("latest_{$clientIP}", 120, 60)) {
        http_response_code(429);
        throw new Exception('Zu viele Anfragen. Bitte warten Sie eine Minute.');
    }
    
    // Output Buffer Management
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
} catch (Exception $e) {
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    $errorClass = $e instanceof InvalidArgumentException ? 'client-error' : 'server-error';
    $errorIcon = $e instanceof InvalidArgumentException ? 'bi-exclamation-circle' : 'bi-exclamation-triangle';
    $safeErrorMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    
    echo '<div class="error-container ' . $errorClass . '" style="
        padding: 2rem;
        text-align: center;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        color: var(--text-glass);
    ">
        <div class="error-content">
            <i class="' . $errorIcon . '" style="font-size: 3rem; margin-bottom: 1rem; color: #dc3545;"></i>
            <h2>Fehler beim Laden der neuesten Filme</h2>
            <p><strong>Fehler:</strong> ' . $safeErrorMsg . '</p>
        </div>
    </div>';
    exit;
}

try {
    // Helper-Funktionen (Core-System kompatibel)
    function safeFormatRuntime(?int $minutes): string {
        if (!$minutes || $minutes <= 0) return '';
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;
        return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
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
    
    function formatDate(?string $date): string {
        if (!$date) return '';
        try {
            return date('d.m.Y', strtotime($date));
        } catch (Exception $e) {
            return '';
        }
    }
    
    // Performance-Start-Zeit
    $startTime = microtime(true);
    
    // Gesamtanzahl der Filme (Core Database)
    $totalRecords = (int)$database->fetchValue("SELECT COUNT(*) FROM dvds");
    $totalPages = (int)ceil($totalRecords / $perPage);
    
    // Pagination-Validierung
    if ($page > $totalPages && $totalPages > 0) {
        $page = $totalPages;
        $offset = ($page - 1) * $perPage;
    }
    
    // Sichere Film-Abfrage mit Core Database
    $latest = $database->fetchAll("
        SELECT d.*, 
               u.email as added_by_user,
               d.created_at,
               d.updated_at,
               CASE 
                   WHEN d.created_at > DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 
                   ELSE 0 
               END as is_recent,
               COALESCE(d.view_count, 0) as view_count
        FROM dvds d 
        LEFT JOIN users u ON d.user_id = u.id 
        ORDER BY d.id DESC 
        LIMIT ? OFFSET ?
    ", [$perPage, $offset]);
    
    // User-spezifische Daten laden (falls eingeloggt)
    $userWatchedFilms = [];
    $userRatings = [];
    
    if (isset($_SESSION['user_id']) && !empty($latest)) {
        $filmIds = array_column($latest, 'id');
        $placeholders = str_repeat('?,', count($filmIds) - 1) . '?';
        
        // Watched-Status laden
        if ($database->tableExists('user_watched')) {
            $watchedData = $database->fetchAll("
                SELECT film_id FROM user_watched 
                WHERE user_id = ? AND film_id IN ({$placeholders})
            ", array_merge([$_SESSION['user_id']], $filmIds));
            
            $userWatchedFilms = array_column($watchedData, 'film_id');
        }
        
        // User-Ratings laden
        if ($database->tableExists('user_ratings')) {
            $ratingData = $database->fetchAll("
                SELECT film_id, rating FROM user_ratings 
                WHERE user_id = ? AND film_id IN ({$placeholders})
            ", array_merge([$_SESSION['user_id']], $filmIds));
            
            foreach ($ratingData as $rating) {
                $userRatings[$rating['film_id']] = (float)$rating['rating'];
            }
        }
    }
    
    // Debug-Logging (Development)
    if ($app->getSettings()->get('environment') === 'development') {
        error_log("[DVDProfiler:INFO] Latest-Fragment: Geladen - Seite: {$page}, Filme: " . count($latest) . ", Total: {$totalRecords}");
    }

} catch (Exception $e) {
    // Buffer leeren bei Fehler
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    error_log("[DVDProfiler:ERROR] Latest-Fragment ERROR: " . $e->getMessage());
    
    http_response_code(500);
    echo '<div class="error-container server-error" style="
        padding: 2rem;
        text-align: center;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        color: var(--text-glass);
    ">
        <div class="error-content">
            <i class="bi bi-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem; color: #dc3545;"></i>
            <h2>Fehler beim Laden der Filme</h2>
            <p>Die neuesten Filme konnten nicht geladen werden. Bitte versuchen Sie es später erneut.</p>
        </div>
    </div>';
    exit;
}
?>

<header class="latest-header">
    <h2>
        <i class="bi bi-stars"></i>
        Neu hinzugefügt 
        <span class="item-count">(<?= number_format($totalRecords) ?> Filme)</span>
    </h2>
    
    <?php if ($totalPages > 1): ?>
        <div class="page-info">
            Seite <?= $page ?> von <?= $totalPages ?> 
            <span class="separator">•</span>
            <?= count($latest) ?> von <?= number_format($totalRecords) ?> Filmen
        </div>
    <?php endif; ?>
</header>

<section class="latest-grid">
    <?php if (empty($latest)): ?>
        <div class="empty-state">
            <i class="bi bi-film"></i>
            <h3>Keine Filme gefunden</h3>
            <p>Noch keine Filme in der Sammlung vorhanden.</p>
            
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="admin/" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="bi bi-plus-circle"></i>
                    Ersten Film hinzufügen
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="latest-list">
            <?php foreach ($latest as $dvd): 
                // Sichere Werte extrahieren
                $title = htmlspecialchars($dvd['title'] ?? 'Unbekannt', ENT_QUOTES, 'UTF-8');
                $year = (int)($dvd['year'] ?? 0);
                $id = (int)($dvd['id'] ?? 0);
                $runtime = (int)($dvd['runtime'] ?? 0);
                $genre = htmlspecialchars($dvd['genre'] ?? 'Unbekannt', ENT_QUOTES, 'UTF-8');
                $viewCount = (int)($dvd['view_count'] ?? 0);
                $isRecent = (bool)($dvd['is_recent'] ?? false);
                $isPopular = $viewCount > 20;
                $cover = findCoverImage($dvd['cover_id'] ?? '');
                
                // Hinzugefügt-Datum formatieren
                $addedDate = '';
                if (!empty($dvd['created_at'])) {
                    $addedDate = formatDate($dvd['created_at']);
                }
                
                // User-spezifische Daten
                $isWatched = in_array($id, $userWatchedFilms);
                $userRating = $userRatings[$id] ?? 0;
            ?>
                <div class="latest-card <?= $isPopular ? 'popular' : '' ?> <?= $isWatched ? 'watched' : '' ?>" 
                     data-film-id="<?= $id ?>"
                     data-title="<?= $title ?>"
                     data-year="<?= $year ?>">
                    
                    <?php if ($isRecent): ?>
                        <div class="new-badge">
                            <i class="bi bi-star-fill"></i>
                            <span>NEU</span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isPopular): ?>
                        <div class="popularity-badge" title="<?= number_format($viewCount) ?> Aufrufe">
                            <i class="bi bi-eye-fill"></i>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($isWatched): ?>
                        <div class="watched-badge" title="Bereits gesehen">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    <?php endif; ?>
                    
                    <a href="#" class="toggle-detail" data-id="<?= $id ?>" 
                       aria-label="Details für <?= $title ?> anzeigen">
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
                                
                                <?php if ($userRating > 0): ?>
                                    <div class="info-item">
                                        <i class="bi bi-star-fill"></i>
                                        <span>Ihre Bewertung: <?= $userRating ?>/5</span>
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
                            <button class="action-btn watched-btn <?= $isWatched ? 'active' : '' ?>" 
                                    data-film-id="<?= $id ?>"
                                    data-action="toggle-watched"
                                    title="<?= $isWatched ? 'Als nicht gesehen markieren' : 'Als gesehen markieren' ?>">
                                <i class="bi bi-<?= $isWatched ? 'check-circle-fill' : 'check-circle' ?>"></i>
                            </button>
                            
                            <button class="action-btn rating-btn" 
                                    data-film-id="<?= $id ?>"
                                    data-action="quick-rating"
                                    title="Schnell bewerten">
                                <i class="bi bi-star<?= $userRating > 0 ? '-fill' : '' ?>"></i>
                            </button>
                            
                            <button class="action-btn share-btn" 
                                    data-film-id="<?= $id ?>"
                                    data-action="share-film"
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

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
    <div class="pagination-wrapper">
        <nav class="pagination" aria-label="Seitennavigation">
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            
            // Erste Seite
            if ($page > 1): ?>
                <a href="?seite=1" class="pagination-link" title="Erste Seite">
                    <i class="bi bi-chevron-double-left"></i>
                </a>
                <a href="?seite=<?= $page - 1 ?>" class="pagination-link" title="Vorherige Seite">
                    <i class="bi bi-chevron-left"></i>
                </a>
            <?php endif;
            
            // Seitenzahlen
            if ($start > 1): ?>
                <span class="dots">...</span>
            <?php endif;
            
            for ($i = $start; $i <= $end; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current" aria-current="page"><?= $i ?></span>
                <?php else: ?>
                    <a href="?seite=<?= $i ?>" class="pagination-link"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor;
            
            if ($end < $totalPages): ?>
                <span class="dots">...</span>
            <?php endif;
            
            // Letzte Seite
            if ($page < $totalPages): ?>
                <a href="?seite=<?= $page + 1 ?>" class="pagination-link" title="Nächste Seite">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <a href="?seite=<?= $totalPages ?>" class="pagination-link" title="Letzte Seite">
                    <i class="bi bi-chevron-double-right"></i>
                </a>
            <?php endif; ?>
        </nav>
        
        <div class="pagination-info">
            Seite <?= $page ?> von <?= $totalPages ?> 
            <span class="separator">•</span>
            <?= number_format($totalRecords) ?> Filme gesamt
        </div>
    </div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 Latest-Fragment (Core-System) geladen mit', <?= count($latest) ?>, 'Filmen');
    
    try {
        // Performance-Info (Development)
        <?php if ($app->getSettings()->get('environment') === 'development'): ?>
            const loadTime = <?= (microtime(true) - $startTime) * 1000 ?>;
            console.log(`📊 Latest-Fragment geladen in ${loadTime.toFixed(2)}ms`);
        <?php endif; ?>
        
        // Quick Actions Event-Handler
        document.querySelectorAll('.action-btn').forEach(btn => {
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
                    case 'quick-rating':
                        showQuickRating(filmId, this);
                        break;
                    case 'share-film':
                        shareFilm(filmId, this);
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
                const card = button.closest('.latest-card');
                
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
                
                // Toast-Benachrichtigung
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
        
        // Quick Rating Modal/Popup
        function showQuickRating(filmId, button) {
            // Erstelle einfaches Rating-Popup
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
        
        // Film teilen
        function shareFilm(filmId, button) {
            const card = button.closest('.latest-card');
            const title = card.dataset.title;
            const year = card.dataset.year;
            const url = `${window.location.origin}${window.location.pathname}?id=${filmId}`;
            
            if (navigator.share) {
                navigator.share({
                    title: `${title} (${year}) - Film teilen`,
                    url: url
                });
            } else {
                // Fallback: Link kopieren
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(() => {
                        if (window.showToast) {
                            window.showToast('Link kopiert!', 'success');
                        } else {
                            alert('Link kopiert!');
                        }
                    });
                } else {
                    prompt('Film-Link:', url);
                }
            }
        }
        
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
        
        // Pagination Keyboard Navigation
        document.addEventListener('keydown', function(e) {
            if (e.target.matches('input, textarea, select')) return;
            
            if (e.key === 'ArrowLeft' && <?= $page ?> > 1) {
                window.location.href = '?seite=<?= $page - 1 ?>';
            } else if (e.key === 'ArrowRight' && <?= $page ?> < <?= $totalPages ?>) {
                window.location.href = '?seite=<?= $page + 1 ?>';
            }
        });
        
    } catch (error) {
        console.error('❌ Latest-Fragment JavaScript-Fehler:', error);
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

.latest-card.watched {
    border-color: rgba(34, 197, 94, 0.5);
    background: rgba(34, 197, 94, 0.1);
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

.popularity-badge, .watched-badge {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    color: white;
    padding: 4px;
    border-radius: 50%;
    font-size: 0.8rem;
    z-index: 3;
}

.popularity-badge {
    background: linear-gradient(45deg, #4facfe, #00f2fe);
}

.watched-badge {
    background: linear-gradient(45deg, #22c55e, #16a34a);
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
    left: 0.5rem;
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

.action-btn.active {
    background: var(--gradient-primary, #22c55e);
    color: white;
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

.separator {
    opacity: 0.5;
    margin: 0 0.5rem;
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
    
    .pagination {
        flex-wrap: wrap;
        justify-content: center;
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