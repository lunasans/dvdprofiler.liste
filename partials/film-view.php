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

<div class="detail-inline" itemscope itemtype="https://schema.org/Movie">
    <!-- Film-Titel mit Schema.org Markup -->
    <header class="film-header">
        <h2 itemprop="name">
            <?= htmlspecialchars($dvd['title']) ?>
            <span class="film-year" itemprop="datePublished">(<?= htmlspecialchars((string)($dvd['year'] ?? '')) ?>)</span>
        </h2>
        
        <!-- User-Status Badges -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="user-status-badges">
                <?php if ($isOnWishlist): ?>
                    <span class="badge badge-wishlist">
                        <i class="bi bi-heart-fill"></i> Auf Wunschliste
                    </span>
                <?php endif; ?>
                <?php if ($isWatched): ?>
                    <span class="badge badge-watched">
                        <i class="bi bi-check-circle-fill"></i> Gesehen
                    </span>
                <?php endif; ?>
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
            <?php else: ?>
                <div class="no-cover">
                    <i class="bi bi-film"></i>
                    <span>Kein Cover</span>
                </div>
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
                <span class="value fsk-badge">
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

    <!-- TMDb Ratings -->
    <?php 
    if (getSetting('tmdb_show_ratings_details', '1') == '1') {
        $film = $dvd; // rating-details.php erwartet $film Variable
        include __DIR__ . '/rating-details.php';
    }
    ?>

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
                    
                    <!-- Community Bewertung -->
                    <?php if ($averageRating > 0): ?>
                    <div class="rating-card community">
                        <div class="rating-logo">
                            <i class="bi bi-people-fill" style="font-size: 32px; color: #4caf50;"></i>
                        </div>
                        <div class="rating-score" style="color: #4caf50;">
                            <?= $averageRating ?><span class="rating-max">/5</span>
                        </div>
                        <div class="stars-display">
                            <?= generateStarRating($averageRating) ?>
                        </div>
                        <div class="rating-votes">
                            <?= $ratingCount ?> Bewertung<?= $ratingCount !== 1 ? 'en' : '' ?>
                        </div>
                        <div class="rating-meta">
                            Community-Durchschnitt
                        </div>
                    </div>
                    <?php endif; ?>
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

    <!-- Film-Aktionen -->
    <section class="film-actions">
        <button class="close-detail-button btn btn-secondary" onclick="closeDetail()">
            <i class="bi bi-x-lg"></i> Schließen
        </button>
        
        <?php if (isset($_SESSION['user_id'])): ?>
            <button class="btn btn-outline-primary add-to-wishlist <?= $isOnWishlist ? 'active' : '' ?>" 
                    data-film-id="<?= $dvd['id'] ?>">
                <i class="bi bi-heart<?= $isOnWishlist ? '-fill' : '' ?>"></i>
                <?= $isOnWishlist ? 'Auf Wunschliste' : 'Zur Wunschliste' ?>
            </button>
            
            <button class="btn btn-outline-secondary mark-as-watched <?= $isWatched ? 'active' : '' ?>" 
                    data-film-id="<?= $dvd['id'] ?>">
                <i class="bi bi-check-circle<?= $isWatched ? '-fill' : '' ?>"></i>
                <?= $isWatched ? 'Gesehen' : 'Als gesehen markieren' ?>
            </button>
        <?php endif; ?>
        
        <button class="btn btn-outline-info share-film" data-film-id="<?= $dvd['id'] ?>" data-film-title="<?= htmlspecialchars($dvd['title']) ?>">
            <i class="bi bi-share"></i> Teilen
        </button>
    </section>
</div>

<!-- Enhanced JavaScript -->
<script>
(function() {
    // Trailer-Funktionalität mit Age-Verification (sofort ausgeführt)
    const trailerContainer = document.querySelector('.trailer-container');
    const ageModal = document.getElementById('ageVerificationModal');
    const ageConfirmBtn = document.getElementById('ageConfirmBtn');
    const ageDenyBtn = document.getElementById('ageDenyBtn');
    
    if (trailerContainer) {
        // Event Delegation - fängt Clicks auf Child-Elemente
        trailerContainer.addEventListener('click', function(e) {
            const trailerBox = e.target.closest('.trailer-box');
            if (!trailerBox) return; // Nicht auf trailer-box geklickt
            
            e.preventDefault();
            e.stopPropagation();
            
            const ratingAge = parseInt(trailerBox.dataset.ratingAge || 0);
            const trailerUrl = trailerBox.dataset.src;
            
            // Cookie-Check
            function hasAgeConfirmation() {
                return document.cookie.includes('age_confirmed_18=true');
            }
            
            // Cookie setzen (30 Tage)
            function setAgeConfirmation() {
                const expires = new Date();
                expires.setDate(expires.getDate() + 30);
                document.cookie = `age_confirmed_18=true; expires=${expires.toUTCString()}; path=/; SameSite=Strict`;
            }
            
            if (ratingAge >= 18 && !hasAgeConfirmation()) {
                // Zeige Age-Verification
                if (ageModal) {
                    ageModal.style.display = 'flex';
                    document.body.style.overflow = 'hidden';
                }
            } else {
                // Spiele Trailer ab
                playTrailer(trailerBox, trailerUrl);
            }
            
            function playTrailer(box, url) {
                if (!url) return;
                
                const container = box.closest('.trailer-container');
                const embedUrl = convertToEmbedUrl(url);
                
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
            }
            
            function convertToEmbedUrl(url) {
                // YouTube
                if (url.includes('youtube.com') || url.includes('youtu.be')) {
                    const videoId = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/)?.[1];
                    if (videoId) return `https://www.youtube.com/embed/${videoId}?autoplay=1`;
                }
                // Vimeo
                if (url.includes('vimeo.com')) {
                    const videoId = url.match(/vimeo\.com\/(\d+)/)?.[1];
                    if (videoId) return `https://player.vimeo.com/video/${videoId}?autoplay=1`;
                }
                // Dailymotion
                if (url.includes('dailymotion.com')) {
                    const videoId = url.match(/dailymotion\.com\/video\/([^_]+)/)?.[1];
                    if (videoId) return `https://www.dailymotion.com/embed/video/${videoId}?autoplay=1`;
                }
                return url;
            }
        });
        
        // Bestätigen
        if (ageConfirmBtn) {
            ageConfirmBtn.addEventListener('click', function() {
                const expires = new Date();
                expires.setDate(expires.getDate() + 30);
                document.cookie = `age_confirmed_18=true; expires=${expires.toUTCString()}; path=/; SameSite=Strict`;
                
                if (ageModal) {
                    ageModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
                
                // Spiele Trailer ab
                const trailerBox = document.querySelector('.trailer-box');
                const trailerUrl = trailerBox?.dataset.src;
                if (trailerBox && trailerUrl) {
                    const container = trailerBox.closest('.trailer-container');
                    const embedUrl = (function(url) {
                        if (url.includes('youtube.com') || url.includes('youtu.be')) {
                            const videoId = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/)?.[1];
                            if (videoId) return `https://www.youtube.com/embed/${videoId}?autoplay=1`;
                        }
                        if (url.includes('vimeo.com')) {
                            const videoId = url.match(/vimeo\.com\/(\d+)/)?.[1];
                            if (videoId) return `https://player.vimeo.com/video/${videoId}?autoplay=1`;
                        }
                        return url;
                    })(trailerUrl);
                    
                    const iframe = document.createElement('iframe');
                    iframe.src = embedUrl;
                    iframe.width = '100%';
                    iframe.style.aspectRatio = '16/9';
                    iframe.style.border = 'none';
                    iframe.style.borderRadius = '8px';
                    iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
                    iframe.allowFullscreen = true;
                    
                    trailerBox.style.display = 'none';
                    container.appendChild(iframe);
                }
            });
        }
        
        // Ablehnen
        if (ageDenyBtn) {
            ageDenyBtn.addEventListener('click', function() {
                if (ageModal) {
                    ageModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
        
        // ESC-Taste
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && ageModal && ageModal.style.display === 'flex') {
                ageModal.style.display = 'none';
                document.body.style.overflow = '';
            }
        });
        
        // Click außerhalb
        if (ageModal) {
            ageModal.addEventListener('click', function(e) {
                if (e.target === ageModal) {
                    ageModal.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
    }
    
    // Rating-System mit Debug-Ausgaben
    const ratingStars = document.querySelectorAll('.rating-star');
    const saveRatingBtn = document.querySelector('.save-rating');
    const ratingDisplay = document.querySelector('.rating-display');
    const currentRating = parseFloat(document.querySelector('.star-rating-input')?.dataset.currentRating || 0);
    let selectedRating = currentRating;
    
    console.log('Rating System Debug:', {
        ratingStars: ratingStars.length,
        saveRatingBtn: !!saveRatingBtn,
        ratingDisplay: !!ratingDisplay,
        currentRating: currentRating
    });
    
    ratingStars.forEach((star, index) => {
        star.addEventListener('mouseenter', function() {
            console.log('Mouse enter star:', index + 1);
            const rating = parseInt(this.dataset.rating);
            highlightStars(rating);
        });
        
        star.addEventListener('mouseleave', function() {
            console.log('Mouse leave star');
            highlightStars(selectedRating);
        });
        
        star.addEventListener('click', function() {
            selectedRating = parseInt(this.dataset.rating);
            console.log('Star clicked, selected rating:', selectedRating);
            highlightStars(selectedRating);
            if (saveRatingBtn) {
                saveRatingBtn.style.display = 'inline-block';
                console.log('Save button shown');
            }
            if (ratingDisplay) {
                ratingDisplay.textContent = selectedRating + '/5';
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
            toggleWishlist(filmId, this);
        });
    }
    
    // Als gesehen markieren
    const watchedBtn = document.querySelector('.mark-as-watched');
    if (watchedBtn) {
        watchedBtn.addEventListener('click', function() {
            const filmId = this.dataset.filmId;
            toggleWatched(filmId, this);
        });
    }
    
    // Share-Funktionalität
    const shareBtn = document.querySelector('.share-film');
    if (shareBtn) {
        shareBtn.addEventListener('click', function() {
            const filmId = this.dataset.filmId;
            const filmTitle = this.dataset.filmTitle;
            shareFilm(filmId, filmTitle);
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
})(); // IIFE - sofort ausgeführt

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
            
            // Seite nach kurzer Zeit neu laden um Community-Rating zu aktualisieren
            setTimeout(() => {
                location.reload();
            }, 1500);
        }
    } catch (error) {
        showNotification('Fehler beim Speichern der Bewertung', 'error');
    }
}

async function toggleWishlist(filmId, button) {
    try {
        const response = await fetch('api/toggle-wishlist.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ film_id: filmId })
        });
        
        if (response.ok) {
            const result = await response.json();
            const icon = button.querySelector('i');
            if (result.added) {
                button.innerHTML = '<i class="bi bi-heart-fill"></i> Auf Wunschliste';
                button.classList.add('active');
                showNotification('Zur Wunschliste hinzugefügt!', 'success');
            } else {
                button.innerHTML = '<i class="bi bi-heart"></i> Zur Wunschliste';
                button.classList.remove('active');
                showNotification('Von Wunschliste entfernt!', 'info');
            }
        }
    } catch (error) {
        showNotification('Fehler bei Wunschliste', 'error');
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
                button.innerHTML = '<i class="bi bi-check-circle-fill"></i> Gesehen';
                button.classList.add('active');
                showNotification('Als gesehen markiert!', 'success');
            } else {
                button.innerHTML = '<i class="bi bi-check-circle"></i> Als gesehen markieren';
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

/* Cover Gallery - Nebeneinander */
.cover-gallery {
    margin: var(--space-lg, 1.5rem) 0;
}

.cover-pair {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-md, 1rem);
    max-width: 800px;
    margin: 0 auto;
}

.cover-pair a,
.cover-pair .no-cover {
    display: block;
    border-radius: var(--radius-md, 8px);
    overflow: hidden;
    transition: transform 0.3s ease;
}

.cover-pair a:hover {
    transform: scale(1.02);
}

.cover-pair img.thumb {
    width: 100%;
    height: auto;
    display: block;
    border-radius: var(--radius-md, 8px);
}

.cover-pair .no-cover {
    aspect-ratio: 2/3;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--bg-tertiary, rgba(255, 255, 255, 0.05));
    border: 2px dashed var(--border-color, rgba(255, 255, 255, 0.2));
    color: var(--text-muted, rgba(228, 228, 231, 0.5));
}

.cover-pair .no-cover i {
    font-size: 3rem;
    margin-bottom: var(--space-sm, 0.5rem);
}

/* Responsive - Mobile untereinander */
@media (max-width: 600px) {
    .cover-pair {
        grid-template-columns: 1fr;
        max-width: 300px;
    }
}

.user-status-badges {
    display: flex;
    gap: var(--space-sm);
    justify-content: center;
    margin-top: var(--space-md);
}

.badge {
    padding: var(--space-xs) var(--space-sm);
    border-radius: var(--radius-lg);
    font-size: 0.85rem;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: var(--space-xs);
}

.badge-wishlist {
    background: linear-gradient(135deg, #e91e63, #ad1457);
    color: white;
}

.badge-watched {
    background: linear-gradient(135deg, #4caf50, #2e7d32);
    color: white;
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
    flex-direction: column;
    align-items: center;
    gap: var(--space-md);
    margin-top: var(--space-md);
}

.community-rating, .user-rating {
    display: flex;
    align-items: center;
    gap: var(--space-sm);
    flex-wrap: wrap;
    justify-content: center;
}

.rating-label {
    font-size: 0.9rem;
    color: var(--text-glass);
    font-weight: 500;
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

.no-cover {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--glass-bg);
    border: 2px dashed var(--glass-border);
    border-radius: var(--radius-md);
    height: 240px;
    width: 160px;
    color: var(--text-glass);
    margin: 0 auto;
}

.no-cover i {
    font-size: 3rem;
    margin-bottom: var(--space-sm);
    opacity: 0.5;
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
    transition: all var(--transition-fast);
}

.actor-item:hover {
    background: var(--glass-bg-strong);
    transform: translateY(-1px);
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

.trailer-box {
    position: relative;
    cursor: pointer;
    border-radius: var(--radius-md);
    overflow: hidden;
    transition: transform var(--transition-fast);
}

.trailer-box:hover {
    transform: scale(1.02);
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

.play-icon {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 60px;
    height: 60px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: var(--color-primary);
    transition: all var(--transition-fast);
}

.trailer-box:hover .play-icon {
    background: rgba(255, 255, 255, 1);
    transform: translate(-50%, -50%) scale(1.1);
}

.user-rating-section {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
}

.rating-input {
    display: flex;
    align-items: center;
    gap: var(--space-md);
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

.rating-display {
    font-weight: 600;
    color: var(--text-white);
    min-width: 120px;
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
    border-color: transparent;
}

/* Film-Info Grid Styles */
.film-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-md);
    margin: var(--space-lg) 0;
}

.film-info-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    background: var(--glass-bg);
    border: 1px solid var(--glass-border);
    border-radius: var(--radius-md);
    padding: var(--space-md);
    gap: var(--space-xs);
    transition: all var(--transition-fast);
}

.film-info-item:hover {
    background: var(--glass-bg-strong);
    transform: translateY(-2px);
}

.film-info-item .label {
    font-size: 0.9rem;
    color: var(--text-glass);
    opacity: 0.8;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.film-info-item .value {
    font-size: 1rem;
    color: var(--text-white);
    font-weight: 600;
}

/* FSK-Logo Styles */
.fsk-badge {
    display: flex;
    align-items: center;
    justify-content: center;
}

.fsk-logo {
    height: 24px;
    width: auto;
    max-width: 40px;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
}

.fsk-text {
    background: var(--gradient-primary);
    color: var(--text-white);
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    font-weight: 600;
}

@media (max-width: 768px) {
    .film-rating {
        gap: var(--space-sm);
    }
    
    .community-rating, .user-rating {
        flex-direction: column;
        gap: var(--space-xs);
    }
    
    .user-rating-section {
        align-items: flex-start;
    }
    
    .rating-input {
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
    
    .film-info-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: var(--space-sm);
    }
    
    .user-status-badges {
        flex-direction: column;
        align-items: center;
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
/* Age Verification Modal Styles */
.age-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    backdrop-filter: blur(10px);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.age-modal-content {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 2px solid #f39c12;
    border-radius: 16px;
    max-width: 500px;
    width: 90%;
    padding: 2rem;
    box-shadow: 0 10px 40px rgba(243, 156, 18, 0.3);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(50px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.age-modal-header {
    text-align: center;
    margin-bottom: 1.5rem;
}

.age-modal-header i {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    display: block;
}

.age-modal-header h3 {
    color: #fff;
    margin: 0;
    font-size: 1.5rem;
}

.age-modal-body {
    text-align: center;
    color: #bdc3c7;
    margin-bottom: 1.5rem;
}

.age-warning {
    background: rgba(243, 156, 18, 0.2);
    border: 1px solid #f39c12;
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1rem;
    color: #f39c12;
}

.age-warning strong {
    color: #fff;
    font-size: 1.2rem;
}

.age-question {
    font-size: 1.1rem;
    margin-top: 1rem;
    color: #fff;
}

.age-modal-actions {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
}

.age-modal-actions .btn {
    flex: 1;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.age-confirm {
    background: #27ae60;
    color: white;
}

.age-confirm:hover {
    background: #229954;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
}

.age-deny {
    background: #e74c3c;
    color: white;
}

.age-deny:hover {
    background: #c0392b;
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(231, 76, 60, 0.4);
}

.age-disclaimer {
    text-align: center;
    color: #7f8c8d;
    font-size: 0.85rem;
    margin: 0;
}

@media (max-width: 576px) {
    .age-modal-content { padding: 1.5rem; }
    .age-modal-actions { flex-direction: column; }
    .age-modal-header i { font-size: 2.5rem; }
}

/* User Rating Card - TMDb Style */
.user-rating-card {
    margin: var(--space-lg, 1.5rem) 0;
}

.user-rating-card .rating-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-md, 1rem);
}

.user-rating-card .rating-card {
    padding: var(--space-md, 1rem);
    background: var(--bg-tertiary, rgba(255, 255, 255, 0.03));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-md, 8px);
    text-align: center;
    transition: all 0.3s ease;
}

.user-rating-card .rating-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.user-rating-card .rating-logo {
    margin-bottom: var(--space-sm, 0.5rem);
    display: flex;
    justify-content: center;
    align-items: center;
    height: 40px;
}

.user-rating-card .rating-score {
    font-size: 2.5rem;
    font-weight: 700;
    line-height: 1;
    margin: var(--space-sm, 0.5rem) 0;
}

.user-rating-card .rating-max {
    font-size: 1.2rem;
    opacity: 0.6;
    font-weight: 400;
}

.user-rating-card .stars-display {
    font-size: 1.2rem;
    color: var(--accent-primary, #ffd700);
    margin: var(--space-xs, 0.35rem) 0;
}

.user-rating-card .rating-votes {
    font-size: 0.9rem;
    color: var(--text-secondary, rgba(228, 228, 231, 0.8));
    margin-top: var(--space-xs, 0.35rem);
}

.user-rating-card .rating-meta {
    font-size: 0.85rem;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
    margin-top: var(--space-xs, 0.35rem);
}

/* Star Rating Input - Interaktiv */
.star-rating-input {
    display: flex;
    justify-content: center;
    gap: 4px;
    margin: var(--space-md, 1rem) 0;
}

.star-rating-input .rating-star {
    font-size: 1.8rem;
    cursor: pointer;
    color: var(--text-muted, rgba(228, 228, 231, 0.3));
    transition: all 0.2s ease;
}

.star-rating-input .rating-star:hover,
.star-rating-input .rating-star.hover {
    color: var(--accent-primary, #ffd700);
    transform: scale(1.1);
}

.star-rating-input .rating-star.bi-star-fill {
    color: var(--accent-primary, #ffd700);
}

/* Save Button */
.btn-rate {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs, 0.35rem);
    padding: var(--space-sm, 0.5rem) var(--space-md, 1rem);
    background: var(--accent-primary, #667eea);
    color: white;
    border: none;
    border-radius: var(--radius-sm, 6px);
    font-size: 0.9rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    margin-top: var(--space-sm, 0.5rem);
}

.btn-rate:hover {
    background: var(--accent-hover, #764ba2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Login Required */
.login-required {
    text-align: center;
    padding: var(--space-xl, 2rem);
}

.login-required i {
    font-size: 3rem;
    color: var(--accent-primary, #667eea);
    margin-bottom: var(--space-md, 1rem);
}

.login-required p {
    margin: var(--space-md, 1rem) 0;
    color: var(--text-secondary, rgba(228, 228, 231, 0.8));
}

.btn-login {
    display: inline-flex;
    align-items: center;
    gap: var(--space-xs, 0.35rem);
    padding: var(--space-sm, 0.5rem) var(--space-lg, 1.5rem);
    background: var(--accent-primary, #667eea);
    color: white;
    text-decoration: none;
    border-radius: var(--radius-sm, 6px);
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-login:hover {
    background: var(--accent-hover, #764ba2);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Responsive */
@media (max-width: 600px) {
    .user-rating-card .rating-grid {
        grid-template-columns: 1fr;
    }
    
    .user-rating-card .rating-score {
        font-size: 2rem;
    }
}

</style>