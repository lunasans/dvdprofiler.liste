<?php
/**
 * Partials/film-view.php - Vollständig migriert auf neues Core-System
 * Detail-Ansicht für einzelne Filme mit erweiterten Features
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+ - Core Integration
 */

declare(strict_types=1);

// Sicherheitscheck - Film-Array und Core-System validieren
if (!isset($dvd) || !is_array($dvd) || empty($dvd['id'])) {
    throw new InvalidArgumentException('Invalid DVD data provided to film-view.php');
}

// Core-System sollte bereits durch film-fragment.php geladen sein
if (!class_exists('DVDProfiler\\Core\\Application')) {
    throw new Exception('Core-System nicht verfügbar für film-view.php');
}

try {
    // Application-Instance abrufen
    $app = \DVDProfiler\Core\Application::getInstance();
    $database = $app->getDatabase();
    $security = $app->getSecurity();
    
    // Film-ID validieren
    $filmId = (int)($dvd['id'] ?? 0);
    if ($filmId <= 0) {
        throw new InvalidArgumentException('Ungültige Film-ID in film-view.php');
    }
    
    // Performance-Start-Zeit
    $viewStartTime = microtime(true);
    
} catch (Exception $e) {
    error_log("[DVDProfiler:ERROR] Film-View Initialization: " . $e->getMessage());
    throw new Exception('Film-View konnte nicht initialisiert werden: ' . $e->getMessage());
}

/**
 * Helper-Funktionen für Film-View (Core-System kompatibel)
 */

function findCoverImageView(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string {
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

function formatFileSize(?int $bytes): string {
    if (!$bytes) return '';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen((string)$bytes) - 1) / 3);
    return sprintf("%.1f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
}

function formatDate(?string $date): string {
    if (!$date) return '';
    try {
        return (new DateTime($date))->format('d.m.Y H:i');
    } catch (Exception $e) {
        return $date;
    }
}

function formatRuntime(?int $minutes): string {
    if (!$minutes || $minutes <= 0) return '';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
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
    // Cover-Pfade generieren
    $frontCover = findCoverImageView($dvd['cover_id'] ?? '', 'f');
    $backCover = findCoverImageView($dvd['cover_id'] ?? '', 'b');
    
    // Schauspieler laden mit Core Database
    $actors = $database->fetchAll("
        SELECT a.first_name, a.last_name, fa.role, fa.sort_order
        FROM film_actor fa 
        JOIN actors a ON fa.actor_id = a.id 
        WHERE fa.film_id = ?
        ORDER BY fa.sort_order ASC, a.last_name ASC, a.first_name ASC
    ", [$filmId]);
    
    // BoxSet-Kinder laden (falls vorhanden)
    $boxsetChildren = [];
    if (!empty($dvd['boxset_children_count']) && (int)$dvd['boxset_children_count'] > 0) {
        $boxsetChildren = $database->fetchAll("
            SELECT id, title, year, genre, cover_id, runtime, rating_age,
                   COALESCE(view_count, 0) as view_count
            FROM dvds 
            WHERE boxset_parent = ? 
            ORDER BY year ASC, title ASC
        ", [$filmId]);
    }
    
    // BoxSet-Parent laden (falls dieser Film zu einem BoxSet gehört)
    $boxsetParent = null;
    if (!empty($dvd['boxset_parent'])) {
        $boxsetParent = $database->fetchRow("
            SELECT id, title, year, 
                   (SELECT COUNT(*) FROM dvds WHERE boxset_parent = dvds.id) as total_children
            FROM dvds 
            WHERE id = ?
        ", [$dvd['boxset_parent']]);
    }
    
    // Bewertungen und User-Daten laden
    $averageRating = 0;
    $ratingCount = 0;
    $userRating = 0;
    $userHasRated = false;
    $isWatched = false;
    $isFavorite = false;
    
    // Community-Bewertungen
    if ($database->tableExists('user_ratings')) {
        $ratingData = $database->fetchRow("
            SELECT AVG(rating) as avg_rating, COUNT(*) as count 
            FROM user_ratings 
            WHERE film_id = ?
        ", [$filmId]);
        
        if ($ratingData) {
            $averageRating = round((float)($ratingData['avg_rating'] ?? 0), 1);
            $ratingCount = (int)($ratingData['count'] ?? 0);
        }
    }
    
    // User-spezifische Daten (falls eingeloggt)
    if (isset($_SESSION['user_id'])) {
        $userId = (int)$_SESSION['user_id'];
        
        // User-Rating
        if ($database->tableExists('user_ratings')) {
            $userRatingData = $database->fetchRow("
                SELECT rating FROM user_ratings 
                WHERE film_id = ? AND user_id = ?
            ", [$filmId, $userId]);
            
            if ($userRatingData) {
                $userRating = (float)$userRatingData['rating'];
                $userHasRated = true;
            }
        }
        
        // Watch-Status
        if ($database->tableExists('user_watched')) {
            $watchedData = $database->fetchRow("
                SELECT 1, watched_at FROM user_watched 
                WHERE film_id = ? AND user_id = ?
            ", [$filmId, $userId]);
            
            $isWatched = (bool)$watchedData;
        }
        
        // Favorite-Status
        if ($database->tableExists('user_favorites')) {
            $favoriteData = $database->fetchRow("
                SELECT 1 FROM user_favorites 
                WHERE film_id = ? AND user_id = ?
            ", [$filmId, $userId]);
            
            $isFavorite = (bool)$favoriteData;
        }
    }
    
    // Film-Statistiken erweitern
    $filmStats = [
        'view_count' => (int)($dvd['view_count'] ?? 0),
        'created_at' => $dvd['created_at'] ?? null,
        'updated_at' => $dvd['updated_at'] ?? null,
        'last_viewed' => $dvd['last_viewed'] ?? null,
        'file_size' => $dvd['file_size'] ?? null,
    ];
    
    // View-Count erhöhen (asynchron, nicht blockierend)
    try {
        $database->execute("
            UPDATE dvds 
            SET view_count = COALESCE(view_count, 0) + 1,
                last_viewed = NOW()
            WHERE id = ?
        ", [$filmId]);
        
        $filmStats['view_count']++; // Lokale Anzeige aktualisieren
    } catch (Exception $e) {
        error_log("[DVDProfiler:ERROR] View count update failed: " . $e->getMessage());
        // Nicht kritisch - weiter machen
    }
    
    // Ähnliche Filme laden (gleiche Genre/Jahr)
    $similarFilms = [];
    if (!empty($dvd['genre'])) {
        $similarFilms = $database->fetchAll("
            SELECT id, title, year, cover_id, COALESCE(view_count, 0) as view_count
            FROM dvds 
            WHERE genre = ? AND id != ? 
            ORDER BY view_count DESC, year DESC 
            LIMIT 4
        ", [$dvd['genre'], $filmId]);
    }
    
    // Performance-Logging (Development)
    if ($app->getSettings()->get('environment') === 'development') {
        $loadTime = microtime(true) - $viewStartTime;
        error_log("[DVDProfiler:INFO] Film-View: Geladen in " . round($loadTime * 1000, 2) . "ms - Film-ID: {$filmId}");
    }
    
} catch (Exception $e) {
    error_log('[DVDProfiler:ERROR] Film-View Data Loading: ' . $e->getMessage());
    
    // Fallback-Werte bei Fehlern
    $frontCover = $backCover = 'cover/placeholder.png';
    $actors = $boxsetChildren = $similarFilms = [];
    $boxsetParent = null;
    $averageRating = $userRating = 0;
    $ratingCount = 0;
    $userHasRated = $isWatched = $isFavorite = false;
    $filmStats = ['view_count' => 0, 'created_at' => null, 'updated_at' => null, 'last_viewed' => null, 'file_size' => null];
}
?>

<div class="detail-inline" itemscope itemtype="https://schema.org/Movie">
    <!-- Film-Header mit Schema.org Markup -->
    <header class="film-header">
        <h2 itemprop="name">
            <?= htmlspecialchars($dvd['title']) ?>
            <span class="film-year" itemprop="datePublished">(<?= htmlspecialchars((string)($dvd['year'] ?? '')) ?>)</span>
        </h2>
        
        <!-- BoxSet-Breadcrumb (falls Teil eines BoxSets) -->
        <?php if ($boxsetParent): ?>
            <div class="boxset-breadcrumb">
                <i class="bi bi-collection"></i>
                Teil von BoxSet: 
                <a href="#" class="toggle-detail" data-id="<?= $boxsetParent['id'] ?>">
                    <?= htmlspecialchars($boxsetParent['title']) ?>
                </a>
                (<?= $boxsetParent['total_children'] ?> Filme)
            </div>
        <?php endif; ?>
        
        <!-- User-Status-Badges -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-status-badges">
                <?php if ($isWatched): ?>
                    <span class="badge badge-watched">
                        <i class="bi bi-check-circle-fill"></i>
                        Gesehen
                    </span>
                <?php endif; ?>
                
                <?php if ($isFavorite): ?>
                    <span class="badge badge-favorite">
                        <i class="bi bi-heart-fill"></i>
                        Favorit
                    </span>
                <?php endif; ?>
                
                <?php if ($userHasRated): ?>
                    <span class="badge badge-rating">
                        <i class="bi bi-star-fill"></i>
                        Bewertet: <?= $userRating ?>/5
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Community-Bewertung (falls vorhanden) -->
        <?php if ($ratingCount > 0): ?>
            <div class="film-rating">
                <div class="community-rating">
                    <div class="rating-stars">
                        <?= generateStarRating($averageRating) ?>
                    </div>
                    <div class="rating-info">
                        <span class="rating-value"><?= $averageRating ?>/5</span>
                        <span class="rating-count">(<?= $ratingCount ?> Bewertung<?= $ratingCount !== 1 ? 'en' : '' ?>)</span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </header>

    <!-- Cover Gallery mit Lightbox -->
    <section class="cover-gallery" aria-label="Film-Cover">
        <div class="cover-pair">
            <?php if ($frontCover !== 'cover/placeholder.png'): ?>
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
                    <span>Kein Cover verfügbar</span>
                </div>
            <?php endif; ?>
            
            <?php if ($backCover !== 'cover/placeholder.png'): ?>
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
        
        <?php if (!empty($dvd['collection_type'])): ?>
            <div class="film-info-item">
                <span class="label">Medientyp</span>
                <span class="value"><?= htmlspecialchars($dvd['collection_type']) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($dvd['runtime'])): ?>
            <div class="film-info-item">
                <span class="label">Laufzeit</span>
                <span class="value" itemprop="duration"><?= formatRuntime((int)$dvd['runtime']) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($dvd['rating_age'])): ?>
            <div class="film-info-item">
                <span class="label">FSK</span>
                <span class="value">ab <?= (int)$dvd['rating_age'] ?> Jahren</span>
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
        
        <?php if ($filmStats['last_viewed']): ?>
            <div class="film-info-item">
                <span class="label">Zuletzt angesehen</span>
                <span class="value"><?= formatDate($filmStats['last_viewed']) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if ($filmStats['file_size']): ?>
            <div class="film-info-item">
                <span class="label">Dateigröße</span>
                <span class="value"><?= formatFileSize((int)$filmStats['file_size']) ?></span>
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
                                <span class="actor-role" itemprop="characterName">
                                    als <?= htmlspecialchars($actor['role']) ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
    <?php endif; ?>

    <!-- BoxSet-Inhalte (falls BoxSet-Parent) -->
    <?php if (!empty($boxsetChildren)): ?>
        <section class="meta-card full-width">
            <h3>
                <i class="bi bi-collection"></i> 
                BoxSet-Inhalte 
                <span class="count-badge"><?= count($boxsetChildren) ?> Filme</span>
            </h3>
            <div class="boxset-grid">
                <?php foreach ($boxsetChildren as $child): ?>
                    <div class="boxset-item">
                        <a href="#" class="toggle-detail" data-id="<?= $child['id'] ?>">
                            <div class="boxset-cover">
                                <img src="<?= findCoverImageView($child['cover_id'] ?? '') ?>" 
                                     alt="Cover von <?= htmlspecialchars($child['title']) ?>"
                                     loading="lazy">
                            </div>
                            <div class="boxset-info">
                                <h4><?= htmlspecialchars($child['title']) ?></h4>
                                <div class="boxset-meta">
                                    <?= $child['year'] ?> • <?= htmlspecialchars($child['genre']) ?>
                                    <?php if ($child['runtime']): ?>
                                        • <?= formatRuntime((int)$child['runtime']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($child['view_count'] > 0): ?>
                                    <div class="boxset-stats">
                                        <i class="bi bi-eye"></i> <?= number_format($child['view_count']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- Ähnliche Filme -->
    <?php if (!empty($similarFilms)): ?>
        <section class="meta-card full-width">
            <h3>
                <i class="bi bi-search"></i> 
                Ähnliche Filme 
                <span class="genre-info">Genre: <?= htmlspecialchars($dvd['genre']) ?></span>
            </h3>
            <div class="similar-films-grid">
                <?php foreach ($similarFilms as $similar): ?>
                    <div class="similar-film-item">
                        <a href="#" class="toggle-detail" data-id="<?= $similar['id'] ?>">
                            <div class="similar-cover">
                                <img src="<?= findCoverImageView($similar['cover_id'] ?? '') ?>" 
                                     alt="Cover von <?= htmlspecialchars($similar['title']) ?>"
                                     loading="lazy">
                            </div>
                            <div class="similar-info">
                                <h4><?= htmlspecialchars($similar['title']) ?></h4>
                                <div class="similar-meta">
                                    <?= $similar['year'] ?>
                                    <?php if ($similar['view_count'] > 0): ?>
                                        • <i class="bi bi-eye"></i> <?= number_format($similar['view_count']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <!-- User-Aktionen (nur für eingeloggte User) -->
    <?php if (isset($_SESSION['user_id'])): ?>
        <section class="film-actions">
            <!-- Watch-Status Toggle -->
            <button class="btn btn-outline <?= $isWatched ? 'active' : '' ?>" 
                    data-action="toggle-watched" 
                    data-film-id="<?= $filmId ?>"
                    title="<?= $isWatched ? 'Als nicht gesehen markieren' : 'Als gesehen markieren' ?>">
                <i class="bi bi-<?= $isWatched ? 'check-circle-fill' : 'check-circle' ?>"></i>
                <span class="btn-text"><?= $isWatched ? 'Gesehen' : 'Als gesehen markieren' ?></span>
            </button>
            
            <!-- Favorite Toggle -->
            <button class="btn btn-outline <?= $isFavorite ? 'active' : '' ?>" 
                    data-action="toggle-favorite" 
                    data-film-id="<?= $filmId ?>"
                    title="<?= $isFavorite ? 'Aus Favoriten entfernen' : 'Zu Favoriten hinzufügen' ?>">
                <i class="bi bi-<?= $isFavorite ? 'heart-fill' : 'heart' ?>"></i>
                <span class="btn-text"><?= $isFavorite ? 'Favorit' : 'Zu Favoriten' ?></span>
            </button>
            
            <!-- Rating Section -->
            <div class="user-rating-section">
                <h4>Ihre Bewertung:</h4>
                <div class="rating-input">
                    <div class="star-rating-input" data-film-id="<?= $filmId ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="rating-star bi-star<?= $i <= $userRating ? '-fill' : '' ?>" 
                               data-rating="<?= $i ?>"
                               title="<?= $i ?> Stern<?= $i !== 1 ? 'e' : '' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <div class="rating-display">
                        <?= $userHasRated ? "{$userRating}/5 Sterne" : 'Noch nicht bewertet' ?>
                    </div>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Trailer/Video (falls vorhanden) -->
    <?php if (!empty($dvd['trailer_url'])): ?>
        <section class="meta-card full-width">
            <h3><i class="bi bi-play-circle"></i> Trailer</h3>
            <div class="trailer-container">
                <?php if (str_contains($dvd['trailer_url'], 'youtube.com') || str_contains($dvd['trailer_url'], 'youtu.be')): ?>
                    <div class="trailer-box" data-yt="<?= htmlspecialchars($dvd['trailer_url']) ?>">
                        <div class="trailer-placeholder">
                            <div class="play-icon">
                                <i class="bi bi-play-fill"></i>
                            </div>
                            <span>Trailer abspielen</span>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?= htmlspecialchars($dvd['trailer_url']) ?>" target="_blank" rel="noopener" class="btn btn-primary">
                        <i class="bi bi-play-circle"></i>
                        Trailer ansehen
                    </a>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 Film-View (Core-System) geladen für Film-ID:', <?= $filmId ?>);
    
    try {
        // Star-Rating Interaktion
        document.querySelectorAll('.rating-star').forEach(star => {
            star.addEventListener('click', function() {
                const rating = parseInt(this.dataset.rating);
                const filmId = parseInt(this.closest('.star-rating-input').dataset.filmId);
                
                if (!filmId || !rating) return;
                
                submitRating(filmId, rating);
            });
            
            // Hover-Effekt für Rating-Sterne
            star.addEventListener('mouseenter', function() {
                const rating = parseInt(this.dataset.rating);
                const container = this.closest('.star-rating-input');
                
                container.querySelectorAll('.rating-star').forEach((s, index) => {
                    s.className = index < rating ? 'rating-star bi-star-fill' : 'rating-star bi-star';
                });
            });
        });
        
        // Original Rating beim Mouse-Leave wiederherstellen
        document.querySelectorAll('.star-rating-input').forEach(container => {
            container.addEventListener('mouseleave', function() {
                const currentRating = <?= $userRating ?>;
                
                this.querySelectorAll('.rating-star').forEach((star, index) => {
                    star.className = index < currentRating ? 'rating-star bi-star-fill' : 'rating-star bi-star';
                });
            });
        });
        
        // Watch/Favorite Toggle Actions
        document.querySelectorAll('[data-action]').forEach(btn => {
            btn.addEventListener('click', function() {
                const action = this.dataset.action;
                const filmId = parseInt(this.dataset.filmId);
                
                if (!filmId) return;
                
                switch (action) {
                    case 'toggle-watched':
                        toggleWatchedStatus(filmId, this);
                        break;
                    case 'toggle-favorite':
                        toggleFavoriteStatus(filmId, this);
                        break;
                }
            });
        });
        
        // YouTube Trailer Integration
        document.querySelectorAll('[data-yt]').forEach(box => {
            box.addEventListener('click', function() {
                loadYouTubeTrailer(this);
            });
        });
        
    } catch (error) {
        console.error('❌ Film-View JavaScript-Fehler:', error);
    }
});

// Rating absenden
function submitRating(filmId, rating) {
    fetch('api/save-rating.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({
            film_id: filmId,
            rating: rating
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            throw new Error(data.error);
        }
        
        // UI aktualisieren
        const ratingDisplay = document.querySelector('.rating-display');
        if (ratingDisplay) {
            ratingDisplay.textContent = `${rating}/5 Sterne`;
        }
        
        showNotification('Bewertung gespeichert!', 'success');
        
        // Seite neu laden für aktualisierte Community-Bewertung
        setTimeout(() => {
            location.reload();
        }, 1500);
    })
    .catch(error => {
        console.error('Rating-Fehler:', error);
        showNotification('Fehler beim Speichern der Bewertung', 'error');
    });
}

// Watch-Status Toggle
function toggleWatchedStatus(filmId, button) {
    fetch('api/toggle-watched.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({film_id: filmId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            throw new Error(data.error);
        }
        
        // UI aktualisieren
        const icon = button.querySelector('i');
        const text = button.querySelector('.btn-text');
        
        if (data.watched) {
            icon.className = 'bi bi-check-circle-fill';
            text.textContent = 'Gesehen';
            button.classList.add('active');
            button.title = 'Als nicht gesehen markieren';
        } else {
            icon.className = 'bi bi-check-circle';
            text.textContent = 'Als gesehen markieren';
            button.classList.remove('active');
            button.title = 'Als gesehen markieren';
        }
        
        showNotification(data.message, 'success');
    })
    .catch(error => {
        console.error('Watch-Status Fehler:', error);
        showNotification('Fehler beim Ändern des Watch-Status', 'error');
    });
}

// Favorite-Status Toggle
function toggleFavoriteStatus(filmId, button) {
    fetch('api/toggle-favorite.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({film_id: filmId})
    })
    .then(response => response.json())
    .then(data => {
        if (data.error) {
            throw new Error(data.error);
        }
        
        // UI aktualisieren
        const icon = button.querySelector('i');
        const text = button.querySelector('.btn-text');
        
        if (data.favorite) {
            icon.className = 'bi bi-heart-fill';
            text.textContent = 'Favorit';
            button.classList.add('active');
            button.title = 'Aus Favoriten entfernen';
        } else {
            icon.className = 'bi bi-heart';
            text.textContent = 'Zu Favoriten';
            button.classList.remove('active');
            button.title = 'Zu Favoriten hinzufügen';
        }
        
        showNotification(data.message, 'success');
    })
    .catch(error => {
        console.error('Favorite-Status Fehler:', error);
        showNotification('Fehler beim Ändern der Favoriten', 'error');
    });
}

// YouTube Trailer laden
function loadYouTubeTrailer(placeholder) {
    const ytUrl = placeholder.dataset.yt;
    
    // YouTube-URL zu Embed-URL konvertieren
    let embedUrl = ytUrl;
    if (ytUrl.includes('watch?v=')) {
        const videoId = ytUrl.split('watch?v=')[1].split('&')[0];
        embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
    } else if (ytUrl.includes('youtu.be/')) {
        const videoId = ytUrl.split('youtu.be/')[1].split('?')[0];
        embedUrl = `https://www.youtube.com/embed/${videoId}?autoplay=1`;
    }
    
    const iframe = document.createElement('iframe');
    iframe.src = embedUrl;
    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
    iframe.allowFullscreen = true;
    iframe.style.cssText = `
        width: 100%; 
        height: 100%; 
        border: none; 
        border-radius: 8px;
        min-height: 315px;
    `;
    
    placeholder.replaceWith(iframe);
}

// Toast-Benachrichtigung
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 1.5rem;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        border-radius: 8px;
        color: white;
        z-index: 10000;
        backdrop-filter: blur(10px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        font-weight: 500;
        font-size: 0.9rem;
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

// Performance-Info (Development)
<?php if ($app->getSettings()->get('environment') === 'development'): ?>
    console.log('📊 Film-View Performance:', {
        'loadTime': '<?= round((microtime(true) - $viewStartTime) * 1000, 2) ?>ms',
        'filmId': <?= $filmId ?>,
        'actors': <?= count($actors) ?>,
        'boxsetChildren': <?= count($boxsetChildren) ?>,
        'similarFilms': <?= count($similarFilms) ?>,
        'memoryUsage': '<?= \DVDProfiler\Core\Utils::formatBytes(memory_get_peak_usage(true)) ?>'
    });
<?php endif; ?>
</script>

<style>
/* FILM-VIEW - CORE-SYSTEM OPTIMIERTE STYLES */

.detail-inline {
    max-width: 100%;
    margin: 0 auto;
    padding: 1rem;
}

/* Film Header */
.film-header {
    margin-bottom: 2rem;
    text-align: center;
}

.film-header h2 {
    font-size: 2rem;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin-bottom: 0.5rem;
    line-height: 1.2;
}

.film-year {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    opacity: 0.8;
    font-weight: 400;
    font-size: 1.5rem;
}

.boxset-breadcrumb {
    margin-top: 1rem;
    font-size: 0.9rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    padding: 0.5rem 1rem;
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 8px;
    display: inline-block;
}

.boxset-breadcrumb a {
    color: var(--text-white, #fff);
    text-decoration: underline;
}

.boxset-breadcrumb a:hover {
    color: #60a5fa;
}

/* User Status Badges */
.user-status-badges {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 1rem;
    flex-wrap: wrap;
}

.badge {
    padding: 0.25rem 0.75rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.badge-watched {
    background: linear-gradient(135deg, #4caf50, #2e7d32);
    color: white;
}

.badge-favorite {
    background: linear-gradient(135deg, #e91e63, #ad1457);
    color: white;
}

.badge-rating {
    background: linear-gradient(135deg, #ff9800, #e65100);
    color: white;
}

/* Film Rating */
.film-rating {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.community-rating {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
}

.rating-stars {
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

.rating-info {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
}

.rating-value {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--text-white, #fff);
}

.rating-count {
    font-size: 0.85rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
}

/* Cover Gallery */
.cover-gallery {
    margin-bottom: 2rem;
}

.cover-pair {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

.cover-pair img.thumb {
    max-height: 300px;
    width: auto;
    border-radius: 8px;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    transition: transform 0.3s ease;
    cursor: pointer;
}

.cover-pair img.thumb:hover {
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
}

.no-cover {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    width: 200px;
    height: 300px;
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 2px dashed var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 8px;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
}

.no-cover i {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

/* Film Info Grid */
.film-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin: 2rem 0;
}

.film-info-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 8px;
    padding: 1rem;
    gap: 0.5rem;
    transition: all 0.3s ease;
}

.film-info-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
}

.film-info-item .label {
    font-size: 0.85rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    opacity: 0.8;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.film-info-item .value {
    font-size: 1rem;
    color: var(--text-white, #fff);
    font-weight: 600;
}

/* Meta Cards */
.meta-card {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    backdrop-filter: blur(10px);
}

.meta-card.full-width {
    grid-column: 1 / -1;
}

.meta-card h3 {
    color: var(--text-white, #fff);
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 1.2rem;
    font-weight: 600;
}

.count-badge {
    background: var(--primary-color, #007bff);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 500;
}

.genre-info {
    font-size: 0.85rem;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-weight: 400;
}

.overview-text {
    color: var(--text-glass, rgba(255, 255, 255, 0.9));
    line-height: 1.6;
    font-size: 0.95rem;
}

/* Actor List */
.actor-list ul {
    list-style: none;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 0.75rem;
}

.actor-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.75rem;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 8px;
    transition: background-color 0.2s;
}

.actor-item:hover {
    background: rgba(255, 255, 255, 0.1);
}

.actor-name {
    color: var(--text-white, #fff);
    font-weight: 600;
    font-size: 0.95rem;
}

.actor-role {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 0.85rem;
    font-style: italic;
}

/* BoxSet Grid */
.boxset-grid, .similar-films-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.boxset-item, .similar-film-item {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.boxset-item:hover, .similar-film-item:hover {
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.boxset-item a, .similar-film-item a {
    display: block;
    text-decoration: none;
    color: inherit;
}

.boxset-cover, .similar-cover {
    position: relative;
    overflow: hidden;
}

.boxset-cover img, .similar-cover img {
    width: 100%;
    height: 200px;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.boxset-item:hover .boxset-cover img,
.similar-film-item:hover .similar-cover img {
    transform: scale(1.05);
}

.boxset-info, .similar-info {
    padding: 1rem;
}

.boxset-info h4, .similar-info h4 {
    color: var(--text-white, #fff);
    font-size: 0.95rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.boxset-meta, .similar-meta {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 0.8rem;
    margin-bottom: 0.5rem;
}

.boxset-stats {
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

/* Film Actions */
.film-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.btn {
    padding: 0.75rem 1.5rem;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: 8px;
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    color: var(--text-white, #fff);
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
}

.btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.btn.active {
    background: var(--primary-color, #007bff);
    border-color: var(--primary-color, #007bff);
    color: white;
}

.btn-outline {
    background: transparent;
}

.btn-outline:hover {
    background: var(--primary-color, #007bff);
    border-color: var(--primary-color, #007bff);
}

.btn-primary {
    background: var(--primary-color, #007bff);
    border-color: var(--primary-color, #007bff);
    color: white;
}

/* User Rating Section */
.user-rating-section {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    align-items: center;
    background: rgba(255, 255, 255, 0.05);
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.user-rating-section h4 {
    color: var(--text-white, #fff);
    margin: 0;
    font-size: 1.1rem;
}

.rating-input {
    display: flex;
    align-items: center;
    gap: 1rem;
    flex-wrap: wrap;
    justify-content: center;
}

.star-rating-input {
    display: flex;
    gap: 4px;
}

.rating-star {
    font-size: 1.8rem;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.3);
    transition: color 0.2s ease;
}

.rating-star:hover,
.rating-star.bi-star-fill {
    color: #ffd700;
}

.rating-display {
    font-weight: 600;
    color: var(--text-white, #fff);
    min-width: 140px;
    text-align: center;
    font-size: 0.95rem;
}

/* Trailer Container */
.trailer-container {
    position: relative;
}

.trailer-box {
    position: relative;
    background: rgba(0, 0, 0, 0.5);
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s ease;
    min-height: 200px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.trailer-box:hover {
    background: rgba(0, 0, 0, 0.7);
}

.trailer-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1rem;
    color: white;
}

.play-icon {
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.8);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #000;
    transition: all 0.3s ease;
}

.trailer-box:hover .play-icon {
    background: rgba(255, 255, 255, 1);
    transform: scale(1.1);
}

/* Mobile Responsiveness */
@media (max-width: 768px) {
    .detail-inline {
        padding: 0.5rem;
    }
    
    .film-header h2 {
        font-size: 1.5rem;
    }
    
    .film-year {
        font-size: 1.2rem;
    }
    
    .cover-pair {
        flex-direction: column;
        align-items: center;
    }
    
    .cover-pair img.thumb {
        max-height: 250px;
    }
    
    .film-info-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
    }
    
    .film-info-item {
        padding: 0.75rem;
    }
    
    .meta-card {
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .actor-list ul {
        grid-template-columns: 1fr;
    }
    
    .boxset-grid, .similar-films-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 0.75rem;
    }
    
    .boxset-cover img, .similar-cover img {
        height: 150px;
    }
    
    .film-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .btn {
        justify-content: center;
    }
    
    .user-rating-section {
        padding: 1rem;
    }
    
    .rating-input {
        flex-direction: column;
        gap: 0.75rem;
    }
    
    .rating-star {
        font-size: 1.5rem;
    }
}

@media (max-width: 480px) {
    .film-header h2 {
        font-size: 1.3rem;
    }
    
    .user-status-badges {
        flex-direction: column;
        align-items: center;
    }
    
    .film-info-grid {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    
    .film-info-item {
        padding: 0.5rem;
    }
    
    .film-info-item .label {
        font-size: 0.75rem;
    }
    
    .film-info-item .value {
        font-size: 0.9rem;
    }
}
</style>