<?php
/**
 * DVD Profiler Liste - TMDb Movie & TV Show Importer
 * Importiere Filme UND Serien automatisch von TMDb URLs
 * 
 * @package    dvdprofiler.liste
 * @version    1.4.9
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSRF-Token generieren
$csrfToken = generateCSRFToken();

// TMDb Helper laden
if (!file_exists(__DIR__ . '/../../includes/tmdb-helper.php')) {
    die('ERROR: tmdb-helper.php nicht gefunden in: ' . __DIR__ . '/../../includes/');
}
require_once __DIR__ . '/../../includes/tmdb-helper.php';

// Success/Error Messages
$success = '';
$error = '';
$mediaData = null;
$mediaType = null; // 'movie' oder 'tv'

if (isset($_SESSION['import_success'])) {
    $success = $_SESSION['import_success'];
    unset($_SESSION['import_success']);
}

if (isset($_SESSION['import_error'])) {
    $error = $_SESSION['import_error'];
    unset($_SESSION['import_error']);
}

// Lade mediaData aus Session falls vorhanden
if (isset($_SESSION['tmdb_media_data'])) {
    $mediaData = $_SESSION['tmdb_media_data'];
    $mediaType = $mediaData['media_type'] ?? null;
}

// Clear Action - Session leeren
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    unset($_SESSION['tmdb_media_data']);
    header('Location: ?page=tmdb-import');
    exit;
}

// URL verarbeiten wenn submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_url'])) {
    $url = trim($_POST['tmdb_url'] ?? '');
    
    if (empty($url)) {
        $error = 'Bitte geben Sie eine TMDb URL ein.';
    } else {
        // TMDb ID und Type (movie/tv) aus URL extrahieren
        $type = null;
        $tmdbId = null;
        
        if (preg_match('/themoviedb\.org\/movie\/(\d+)/', $url, $matches)) {
            $type = 'movie';
            $tmdbId = (int)$matches[1];
        } elseif (preg_match('/themoviedb\.org\/tv\/(\d+)/', $url, $matches)) {
            $type = 'tv';
            $tmdbId = (int)$matches[1];
        }
        
        if (!$type || !$tmdbId) {
            $error = 'Ungültige TMDb URL. Bitte Film- oder Serien-URL verwenden.';
        } else {
            try {
                // Film oder Serie laden
                if ($type === 'movie') {
                    $tmdbData = getTMDbMovie($tmdbId);
                } else {
                    $tmdbData = getTMDbTVShow($tmdbId);
                }
                
                if ($tmdbData && (isset($tmdbData['title']) || isset($tmdbData['name']))) {
                    // Daten aufbereiten
                    $mediaData = [
                        'media_type' => $type,
                        'tmdb_id' => $tmdbId,
                        'title' => $tmdbData['title'] ?? $tmdbData['name'] ?? '',
                        'original_title' => $tmdbData['original_title'] ?? $tmdbData['original_name'] ?? '',
                        'year' => !empty($tmdbData['release_date']) ? date('Y', strtotime($tmdbData['release_date'])) : 
                                 (!empty($tmdbData['first_air_date']) ? date('Y', strtotime($tmdbData['first_air_date'])) : ''),
                        'genre' => !empty($tmdbData['genres']) ? $tmdbData['genres'][0]['name'] : '',
                        'runtime' => $tmdbData['runtime'] ?? ($tmdbData['episode_run_time'][0] ?? 0),
                        'overview' => $tmdbData['overview'] ?? '',
                        'rating' => $tmdbData['vote_average'] ?? 0,
                        'poster_path' => $tmdbData['poster_path'] ?? '',
                        'backdrop_path' => $tmdbData['backdrop_path'] ?? '',
                        'rating_age' => null,
                        'actors' => [],
                    ];
                    
                    // Schauspieler extrahieren (Top 10)
                    if (!empty($tmdbData['credits']['cast'])) {
                        $cast = array_slice($tmdbData['credits']['cast'], 0, 10);
                        foreach ($cast as $index => $actor) {
                            $mediaData['actors'][] = [
                                'id' => $actor['id'],
                                'name' => $actor['name'],
                                'character' => $actor['character'] ?? '',
                                'profile_path' => $actor['profile_path'] ?? '',
                                'order' => $index
                            ];
                        }
                    }
                    
                    // FSK/Age Rating
                    if ($type === 'movie' && !empty($tmdbData['release_dates']['results'])) {
                        foreach ($tmdbData['release_dates']['results'] as $country) {
                            if ($country['iso_3166_1'] === 'DE' && !empty($country['release_dates'])) {
                                foreach ($country['release_dates'] as $release) {
                                    if (!empty($release['certification'])) {
                                        $cert = $release['certification'];
                                        if (preg_match('/(\d+)/', $cert, $matches)) {
                                            $mediaData['rating_age'] = (int)$matches[1];
                                        } else if (stripos($cert, '0') !== false || stripos($cert, 'ohne') !== false) {
                                            $mediaData['rating_age'] = 0;
                                        }
                                        break 2;
                                    }
                                }
                            }
                        }
                    } elseif ($type === 'tv' && !empty($tmdbData['content_ratings']['results'])) {
                        foreach ($tmdbData['content_ratings']['results'] as $rating) {
                            if ($rating['iso_3166_1'] === 'DE' && !empty($rating['rating'])) {
                                $cert = $rating['rating'];
                                if (preg_match('/(\d+)/', $cert, $matches)) {
                                    $mediaData['rating_age'] = (int)$matches[1];
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Trailer URL
                    if (!empty($tmdbData['videos']['results'])) {
                        foreach ($tmdbData['videos']['results'] as $video) {
                            if ($video['site'] === 'YouTube' && $video['type'] === 'Trailer') {
                                $mediaData['trailer_url'] = 'https://www.youtube.com/watch?v=' . $video['key'];
                                break;
                            }
                        }
                    }
                    
                    // Staffeln & Episoden (nur bei TV Shows)
                    if ($type === 'tv' && !empty($tmdbData['seasons_detailed'])) {
                        $mediaData['seasons'] = [];
                        foreach ($tmdbData['seasons_detailed'] as $season) {
                            $seasonData = [
                                'season_number' => $season['season_number'],
                                'name' => $season['name'],
                                'overview' => $season['overview'] ?? '',
                                'episode_count' => $season['episodes'] ? count($season['episodes']) : 0,
                                'air_date' => $season['air_date'] ?? '',
                                'poster_path' => $season['poster_path'] ?? '',
                                'episodes' => []
                            ];
                            
                            // Episoden
                            if (!empty($season['episodes'])) {
                                foreach ($season['episodes'] as $episode) {
                                    $seasonData['episodes'][] = [
                                        'episode_number' => $episode['episode_number'],
                                        'title' => $episode['name'],
                                        'overview' => $episode['overview'] ?? '',
                                        'air_date' => $episode['air_date'] ?? '',
                                        'runtime' => $episode['runtime'] ?? null,
                                        'still_path' => $episode['still_path'] ?? ''
                                    ];
                                }
                            }
                            
                            $mediaData['seasons'][] = $seasonData;
                        }
                    }
                    
                    // In Session speichern
                    $_SESSION['tmdb_media_data'] = $mediaData;
                    $mediaType = $type;
                    
                    $success = $type === 'movie' ? 
                        '✅ Film gefunden und geladen!' : 
                        '✅ Serie gefunden und geladen!';
                    
                } else {
                    $error = 'Film/Serie konnte nicht von TMDb geladen werden.';
                }
                
            } catch (Exception $e) {
                $error = 'Fehler beim Laden: ' . $e->getMessage();
                error_log('TMDb Import Error: ' . $e->getMessage());
            }
        }
    }
}
?>

<div class="container-fluid">
    <h2 class="mb-4">
        <i class="bi bi-cloud-download"></i> TMDb Import
        <small class="text-muted">Filme & Serien</small>
    </h2>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <?php if (!$mediaData): ?>
            <!-- URL Eingabe -->
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <i class="bi bi-link-45deg"></i> TMDb URL eingeben
                </div>
                <div class="card-body">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        
                        <div class="input-group input-group-lg">
                            <span class="input-group-text"><i class="bi bi-link"></i></span>
                            <input type="url" 
                                   class="form-control" 
                                   name="tmdb_url" 
                                   placeholder="https://www.themoviedb.org/movie/603 oder .../tv/1396"
                                   required>
                            <button type="submit" name="fetch_url" class="btn btn-primary">
                                <i class="bi bi-search"></i> Film/Serie laden
                            </button>
                        </div>
                        
                        <div class="form-text mt-2">
                            <strong>Unterstützt:</strong>
                            <ul class="mb-0 mt-2">
                                <li>Filme: <code>themoviedb.org/movie/...</code></li>
                                <li>Serien: <code>themoviedb.org/tv/...</code></li>
                            </ul>
                        </div>
                    </form>
                </div>
            </div>
            <?php else: ?>
            <!-- Film/Serie Vorschau -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-<?= $mediaType === 'movie' ? 'film' : 'tv' ?>"></i>
                        <?= $mediaType === 'movie' ? 'Film' : 'Serie' ?> gefunden
                    </span>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM dvds");
                        $maxId = $stmt->fetchColumn();
                        $nextCollectionNumber = $maxId ? ($maxId + 1) : 1;
                        echo '<span class="badge bg-light text-dark">Collection #' . $nextCollectionNumber . '</span>';
                    } catch (Exception $e) {}
                    ?>
                </div>
                <div class="card-body">
                    <form action="actions/import-from-tmdb.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="media_type" value="<?= htmlspecialchars($mediaType) ?>">
                        <input type="hidden" name="tmdb_id" value="<?= htmlspecialchars($mediaData['tmdb_id']) ?>">
                        
                        <div class="row">
                            <!-- Poster Preview -->
                            <div class="col-md-4 mb-3">
                                <?php if (!empty($mediaData['poster_path'])): ?>
                                    <img src="https://image.tmdb.org/t/p/w500<?= htmlspecialchars($mediaData['poster_path']) ?>" 
                                         alt="Poster" 
                                         class="img-fluid rounded shadow">
                                <?php else: ?>
                                    <div class="bg-secondary text-white d-flex align-items-center justify-content-center rounded" style="height: 400px;">
                                        <i class="bi bi-image" style="font-size: 4rem;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Titel *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?= htmlspecialchars($mediaData['title']) ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="year" class="form-label">Jahr *</label>
                                        <input type="number" class="form-control" id="year" name="year" 
                                               value="<?= htmlspecialchars($mediaData['year']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="runtime" class="form-label">Laufzeit (Min)</label>
                                        <input type="number" class="form-control" id="runtime" name="runtime" 
                                               value="<?= htmlspecialchars($mediaData['runtime']) ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="genre" class="form-label">Genre</label>
                                    <input type="text" class="form-control" id="genre" name="genre" 
                                           value="<?= htmlspecialchars($mediaData['genre']) ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="purchase_date" class="form-label">
                                        <i class="bi bi-calendar-event"></i> Kaufdatum
                                    </label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="rating_age" class="form-label">
                                        <i class="bi bi-shield-check"></i> Altersfreigabe (FSK)
                                    </label>
                                    <select class="form-select" id="rating_age" name="rating_age">
                                        <option value="">Keine Angabe</option>
                                        <option value="0" <?= ($mediaData['rating_age'] ?? null) === 0 ? 'selected' : '' ?>>FSK 0</option>
                                        <option value="6" <?= ($mediaData['rating_age'] ?? null) === 6 ? 'selected' : '' ?>>FSK 6</option>
                                        <option value="12" <?= ($mediaData['rating_age'] ?? null) === 12 ? 'selected' : '' ?>>FSK 12</option>
                                        <option value="16" <?= ($mediaData['rating_age'] ?? null) === 16 ? 'selected' : '' ?>>FSK 16</option>
                                        <option value="18" <?= ($mediaData['rating_age'] ?? null) === 18 ? 'selected' : '' ?>>FSK 18</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="collection_type" class="form-label">
                                        <i class="bi bi-collection"></i> Collection Type *
                                    </label>
                                    <select class="form-select" id="collection_type" name="collection_type" required>
                                        <option value="Owned" <?= $mediaType === 'movie' ? 'selected' : '' ?>>Owned - DVD/Blu-ray</option>
                                        <option value="Serie" <?= $mediaType === 'tv' ? 'selected' : '' ?>>Serie - TV Show</option>
                                        <option value="Stream">Stream - Streaming</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="overview" class="form-label">Handlung</label>
                            <textarea class="form-control" id="overview" name="overview" rows="5"><?= htmlspecialchars($mediaData['overview']) ?></textarea>
                        </div>
                        
                        <!-- Schauspieler -->
                        <?php if (!empty($mediaData['actors'])): ?>
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-people"></i> Hauptdarsteller (Top <?= count($mediaData['actors']) ?>)
                            </label>
                            <div class="row g-2">
                                <?php foreach ($mediaData['actors'] as $actor): ?>
                                <div class="col-md-6">
                                    <div class="card border-0 bg-light">
                                        <div class="card-body py-2 px-3">
                                            <div class="d-flex align-items-center">
                                                <?php if (!empty($actor['profile_path'])): ?>
                                                <img src="https://image.tmdb.org/t/p/w92<?= htmlspecialchars($actor['profile_path']) ?>" 
                                                     alt="<?= htmlspecialchars($actor['name']) ?>"
                                                     class="rounded-circle me-2"
                                                     style="width: 40px; height: 40px; object-fit: cover;">
                                                <?php else: ?>
                                                <div class="rounded-circle bg-secondary me-2 d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="bi bi-person text-white"></i>
                                                </div>
                                                <?php endif; ?>
                                                <div class="flex-grow-1" style="min-width: 0;">
                                                    <div class="fw-bold text-truncate small"><?= htmlspecialchars($actor['name']) ?></div>
                                                    <small class="text-muted text-truncate d-block" style="font-size: 0.75rem;">
                                                        <?= htmlspecialchars($actor['character']) ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="actors[<?= $actor['order'] ?>][id]" value="<?= $actor['id'] ?>">
                                    <input type="hidden" name="actors[<?= $actor['order'] ?>][name]" value="<?= htmlspecialchars($actor['name']) ?>">
                                    <input type="hidden" name="actors[<?= $actor['order'] ?>][character]" value="<?= htmlspecialchars($actor['character']) ?>">
                                    <input type="hidden" name="actors[<?= $actor['order'] ?>][profile_path]" value="<?= htmlspecialchars($actor['profile_path']) ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Staffeln & Episoden (nur bei TV Shows) -->
                        <?php if ($mediaType === 'tv' && !empty($mediaData['seasons'])): ?>
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="bi bi-collection-play"></i> Staffeln & Episoden
                            </label>
                            
                            <div class="accordion" id="seasonsAccordion">
                                <?php foreach ($mediaData['seasons'] as $sIndex => $season): ?>
                                <?php if ($season['season_number'] == 0) continue; // Skip Specials ?>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button <?= $sIndex > 0 ? 'collapsed' : '' ?>" 
                                                type="button" 
                                                data-bs-toggle="collapse" 
                                                data-bs-target="#season<?= $season['season_number'] ?>">
                                            <input type="checkbox" 
                                                   class="form-check-input me-3" 
                                                   name="import_seasons[]" 
                                                   value="<?= $season['season_number'] ?>"
                                                   checked
                                                   onclick="event.stopPropagation()">
                                            <strong>Staffel <?= $season['season_number'] ?></strong>
                                            <span class="ms-2 text-muted">(<?= $season['episode_count'] ?> Episoden)</span>
                                        </button>
                                    </h2>
                                    <div id="season<?= $season['season_number'] ?>" 
                                         class="accordion-collapse collapse <?= $sIndex === 0 ? 'show' : '' ?>" 
                                         data-bs-parent="#seasonsAccordion">
                                        <div class="accordion-body">
                                            <?php if (!empty($season['overview'])): ?>
                                            <p class="text-muted small"><?= htmlspecialchars($season['overview']) ?></p>
                                            <?php endif; ?>
                                            
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($season['episodes'] as $episode): ?>
                                                <div class="list-group-item px-0">
                                                    <div class="d-flex">
                                                        <div class="me-3">
                                                            <span class="badge bg-secondary">E<?= $episode['episode_number'] ?></span>
                                                        </div>
                                                        <div class="flex-grow-1">
                                                            <div class="fw-bold"><?= htmlspecialchars($episode['title']) ?></div>
                                                            <?php if (!empty($episode['overview'])): ?>
                                                            <small class="text-muted"><?= htmlspecialchars(mb_substr($episode['overview'], 0, 120)) ?>...</small>
                                                            <?php endif; ?>
                                                            <?php if (!empty($episode['air_date'])): ?>
                                                            <small class="text-muted d-block mt-1">
                                                                <i class="bi bi-calendar"></i> <?= date('d.m.Y', strtotime($episode['air_date'])) ?>
                                                            </small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Hidden Episode Data -->
                                                    <input type="hidden" name="seasons[<?= $season['season_number'] ?>][episodes][<?= $episode['episode_number'] ?>][title]" value="<?= htmlspecialchars($episode['title']) ?>">
                                                    <input type="hidden" name="seasons[<?= $season['season_number'] ?>][episodes][<?= $episode['episode_number'] ?>][overview]" value="<?= htmlspecialchars($episode['overview']) ?>">
                                                    <input type="hidden" name="seasons[<?= $season['season_number'] ?>][episodes][<?= $episode['episode_number'] ?>][air_date]" value="<?= htmlspecialchars($episode['air_date']) ?>">
                                                    <input type="hidden" name="seasons[<?= $season['season_number'] ?>][episodes][<?= $episode['episode_number'] ?>][runtime]" value="<?= $episode['runtime'] ?? '' ?>">
                                                    <input type="hidden" name="seasons[<?= $season['season_number'] ?>][episodes][<?= $episode['episode_number'] ?>][still_path]" value="<?= htmlspecialchars($episode['still_path']) ?>">
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Hidden Season Data -->
                                    <input type="hidden" name="seasons[<?= $season['season_number'] ?>][name]" value="<?= htmlspecialchars($season['name']) ?>">
                                    <input type="hidden" name="seasons[<?= $season['season_number'] ?>][overview]" value="<?= htmlspecialchars($season['overview']) ?>">
                                    <input type="hidden" name="seasons[<?= $season['season_number'] ?>][air_date]" value="<?= htmlspecialchars($season['air_date']) ?>">
                                    <input type="hidden" name="seasons[<?= $season['season_number'] ?>][poster_path]" value="<?= htmlspecialchars($season['poster_path']) ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <small class="form-text text-muted d-block mt-2">
                                <i class="bi bi-info-circle"></i> Wähle die Staffeln die importiert werden sollen
                            </small>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Hidden Fields -->
                        <input type="hidden" name="poster_path" value="<?= htmlspecialchars($mediaData['poster_path']) ?>">
                        <input type="hidden" name="backdrop_path" value="<?= htmlspecialchars($mediaData['backdrop_path']) ?>">
                        <input type="hidden" name="trailer_url" value="<?= htmlspecialchars($mediaData['trailer_url'] ?? '') ?>">
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="bi bi-download"></i> 
                                <?= $mediaType === 'movie' ? 'Film importieren' : 'Serie mit Staffeln importieren' ?>
                            </button>
                            <a href="?page=tmdb-import&action=clear" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-clockwise"></i> Neuer Import
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header bg-info text-white">
                    <i class="bi bi-info-circle"></i> Anleitung
                </div>
                <div class="card-body">
                    <h6>🎬 Filme importieren:</h6>
                    <ol class="small">
                        <li>Öffne <a href="https://www.themoviedb.org" target="_blank">TheMovieDB.org</a></li>
                        <li>Suche nach einem Film</li>
                        <li>Kopiere die URL (z.B. <code>.../movie/603</code>)</li>
                        <li>Füge die URL hier ein</li>
                        <li>Klicke "Film laden"</li>
                    </ol>
                    
                    <h6 class="mt-3">📺 Serien importieren:</h6>
                    <ol class="small">
                        <li>Öffne <a href="https://www.themoviedb.org" target="_blank">TheMovieDB.org</a></li>
                        <li>Suche nach einer Serie</li>
                        <li>Kopiere die URL (z.B. <code>.../tv/1396</code>)</li>
                        <li>Füge die URL hier ein</li>
                        <li>Klicke "Serie laden"</li>
                        <li>Wähle die Staffeln aus</li>
                    </ol>
                    
                    <div class="alert alert-info mt-3 mb-0">
                        <small>
                            <strong>Hinweis:</strong> Bei Serien werden alle Episoden der ausgewählten Staffeln importiert!
                        </small>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-lightbulb"></i> Tipps
                </div>
                <div class="card-body">
                    <ul class="small mb-0">
                        <li>Titel und Jahr sind anpassbar</li>
                        <li>FSK wird automatisch erkannt (wenn verfügbar)</li>
                        <li>Top 10 Schauspieler werden importiert</li>
                        <li>Cover werden automatisch heruntergeladen</li>
                        <li>Bei Serien: Episoden können später noch hinzugefügt werden</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>