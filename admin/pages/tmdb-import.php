<?php
/**
 * DVD Profiler Liste - TMDb Movie Importer
 * Importiere Filme automatisch von TMDb URLs
 * 
 * @package    dvdprofiler.liste
 * @version    1.4.8
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSRF-Token generieren
$csrfToken = generateCSRFToken();

// TMDb Helper laden
require_once __DIR__ . '/../../includes/tmdb-helper.php';

// Success/Error Messages
$success = '';
$error = '';
$movieData = null;

if (isset($_SESSION['import_success'])) {
    $success = $_SESSION['import_success'];
    unset($_SESSION['import_success']);
}

if (isset($_SESSION['import_error'])) {
    $error = $_SESSION['import_error'];
    unset($_SESSION['import_error']);
}

// Lade movieData aus Session falls vorhanden
if (isset($_SESSION['tmdb_movie_data'])) {
    $movieData = $_SESSION['tmdb_movie_data'];
}

// Clear Action - Session leeren
if (isset($_GET['action']) && $_GET['action'] === 'clear') {
    unset($_SESSION['tmdb_movie_data']);
    header('Location: ?page=tmdb-import');
    exit;
}

// URL verarbeiten wenn submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fetch_url'])) {
    error_log('=== TMDb Import POST received ===');
    
    $url = trim($_POST['tmdb_url'] ?? '');
    error_log('URL: ' . $url);
    
    if (empty($url)) {
        $error = 'Bitte geben Sie eine TMDb URL ein.';
        error_log('Error: Empty URL');
    } else {
        // TMDb ID aus URL extrahieren
        if (preg_match('/themoviedb\.org\/movie\/(\d+)/', $url, $matches)) {
            $tmdbId = (int)$matches[1];
            error_log('TMDb ID: ' . $tmdbId);
            
            try {
                // Prüfe ob getTMDbMovie existiert
                if (!function_exists('getTMDbMovie')) {
                    throw new Exception('getTMDbMovie() Funktion nicht verfügbar');
                }
                
                // Film-Daten von TMDb holen
                $tmdbMovie = getTMDbMovie($tmdbId);
                error_log('TMDb Response received: ' . (!empty($tmdbMovie) ? 'YES' : 'NO'));
                
                if ($tmdbMovie && isset($tmdbMovie['title'])) {
                    error_log('Movie found: ' . $tmdbMovie['title']);
                    // Daten aufbereiten
                    $movieData = [
                        'tmdb_id' => $tmdbId,
                        'title' => $tmdbMovie['title'] ?? '',
                        'original_title' => $tmdbMovie['original_title'] ?? '',
                        'year' => !empty($tmdbMovie['release_date']) ? date('Y', strtotime($tmdbMovie['release_date'])) : '',
                        'genre' => !empty($tmdbMovie['genres']) ? $tmdbMovie['genres'][0]['name'] : '',
                        'runtime' => $tmdbMovie['runtime'] ?? 0,
                        'overview' => $tmdbMovie['overview'] ?? '',
                        'rating' => $tmdbMovie['vote_average'] ?? 0,
                        'poster_path' => $tmdbMovie['poster_path'] ?? '',
                        'backdrop_path' => $tmdbMovie['backdrop_path'] ?? '',
                        'rating_age' => null, // Default
                    ];
                    
                    // FSK/Age Rating von TMDb Release Dates holen
                    if (!empty($tmdbMovie['release_dates']['results'])) {
                        foreach ($tmdbMovie['release_dates']['results'] as $country) {
                            // Deutsche FSK-Freigabe suchen
                            if ($country['iso_3166_1'] === 'DE' && !empty($country['release_dates'])) {
                                foreach ($country['release_dates'] as $release) {
                                    if (!empty($release['certification'])) {
                                        $cert = $release['certification'];
                                        // FSK zu numerischem Wert konvertieren
                                        if (preg_match('/(\d+)/', $cert, $matches)) {
                                            $movieData['rating_age'] = (int)$matches[1];
                                        } else if (stripos($cert, '0') !== false || stripos($cert, 'ohne') !== false) {
                                            $movieData['rating_age'] = 0;
                                        }
                                        break 2; // Beide Schleifen verlassen
                                    }
                                }
                            }
                        }
                    }
                    
                    // Trailer URL suchen
                    if (!empty($tmdbMovie['videos']['results'])) {
                        foreach ($tmdbMovie['videos']['results'] as $video) {
                            if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                                $movieData['trailer'] = 'https://www.youtube.com/watch?v=' . $video['key'];
                                break;
                            }
                        }
                    }
                    
                    // In Session speichern für nach dem Reload
                    $_SESSION['tmdb_movie_data'] = $movieData;
                    
                    $success = 'Film-Daten erfolgreich von TMDb geladen!';
                } else {
                    $error = 'Film konnte nicht bei TMDb gefunden werden.';
                    unset($_SESSION['tmdb_movie_data']);
                }
            } catch (Exception $e) {
                $error = 'Fehler beim Abrufen der TMDb-Daten: ' . $e->getMessage();
                unset($_SESSION['tmdb_movie_data']);
            }
        } else {
            $error = 'Ungültige TMDb URL. Bitte verwenden Sie eine URL wie: https://www.themoviedb.org/movie/603';
            unset($_SESSION['tmdb_movie_data']);
        }
    }
}
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">
        <i class="bi bi-cloud-download"></i> Film von TMDb importieren
    </h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
        <li class="breadcrumb-item"><a href="?page=import">Import</a></li>
        <li class="breadcrumb-item active">TMDb Import</li>
    </ol>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success) ?>
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
        <!-- URL Eingabe -->
        <div class="col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-link-45deg"></i> TMDb URL eingeben
                </div>
                <div class="card-body">
                    <form method="post" id="fetchForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        
                        <div class="mb-3">
                            <label for="tmdb_url" class="form-label">TMDb Film-URL</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-link"></i></span>
                                <input type="url" 
                                       class="form-control" 
                                       id="tmdb_url" 
                                       name="tmdb_url" 
                                       placeholder="https://www.themoviedb.org/movie/603"
                                       value="<?= htmlspecialchars($_POST['tmdb_url'] ?? '') ?>"
                                       required>
                                <button type="submit" name="fetch_url" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Film laden
                                </button>
                            </div>
                            <small class="form-text text-muted">
                                Geben Sie die URL eines Films von themoviedb.org ein
                            </small>
                        </div>
                    </form>
                    
                    <div class="alert alert-info mt-3">
                        <strong><i class="bi bi-info-circle"></i> Beispiel:</strong><br>
                        <code>https://www.themoviedb.org/movie/603</code> (The Matrix)
                    </div>
                </div>
            </div>

            <?php if ($movieData): ?>
            <!-- Film Vorschau & Bearbeiten -->
            <div class="card mb-4">
                <div class="card-header bg-success text-white">
                    <i class="bi bi-film"></i> Film gefunden - Bearbeiten & Importieren
                    <?php
                    // Zeige nächste Collection Number
                    try {
                        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM dvds");
                        $maxId = $stmt->fetchColumn();
                        $nextCollectionNumber = $maxId ? ($maxId + 1) : 1;
                        echo '<span class="badge bg-light text-dark float-end">
                                Collection #' . $nextCollectionNumber . '
                              </span>';
                    } catch (Exception $e) {
                        // Ignoriere Fehler
                    }
                    ?>
                </div>
                <div class="card-body">
                    <form method="post" action="actions/import-from-tmdb.php" id="importForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="tmdb_id" value="<?= $movieData['tmdb_id'] ?>">
                        
                        <div class="row">
                            <!-- Linke Spalte: Poster -->
                            <div class="col-md-4">
                                <?php if (!empty($movieData['poster_path'])): ?>
                                    <img src="https://image.tmdb.org/t/p/w500<?= htmlspecialchars($movieData['poster_path']) ?>" 
                                         class="img-fluid rounded shadow mb-3"
                                         alt="<?= htmlspecialchars($movieData['title']) ?>">
                                <?php else: ?>
                                    <div class="bg-secondary text-white text-center p-5 rounded">
                                        <i class="bi bi-image" style="font-size: 3rem;"></i>
                                        <p>Kein Poster</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="badge bg-primary mb-2 w-100">
                                    <i class="bi bi-star-fill"></i> TMDb: <?= number_format($movieData['rating'], 1) ?>/10
                                </div>
                            </div>
                            
                            <!-- Rechte Spalte: Formular -->
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label for="title" class="form-label">Titel *</label>
                                    <input type="text" class="form-control" id="title" name="title" 
                                           value="<?= htmlspecialchars($movieData['title']) ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="year" class="form-label">Jahr *</label>
                                        <input type="number" class="form-control" id="year" name="year" 
                                               value="<?= htmlspecialchars($movieData['year']) ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="runtime" class="form-label">Laufzeit (Min)</label>
                                        <input type="number" class="form-control" id="runtime" name="runtime" 
                                               value="<?= htmlspecialchars($movieData['runtime']) ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="purchase_date" class="form-label">
                                        <i class="bi bi-calendar-event"></i> Kaufdatum
                                    </label>
                                    <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                                           value="<?= date('Y-m-d') ?>">
                                    <small class="form-text text-muted">
                                        Wann hast du diesen Film gekauft/erhalten?
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="rating_age" class="form-label">
                                        <i class="bi bi-shield-check"></i> Altersfreigabe (FSK)
                                    </label>
                                    <select class="form-select" id="rating_age" name="rating_age">
                                        <option value="">Keine Angabe</option>
                                        <option value="0" <?= ($movieData['rating_age'] ?? null) === 0 ? 'selected' : '' ?>>FSK 0 - Ohne Altersbeschränkung</option>
                                        <option value="6" <?= ($movieData['rating_age'] ?? null) === 6 ? 'selected' : '' ?>>FSK 6 - Ab 6 Jahren</option>
                                        <option value="12" <?= ($movieData['rating_age'] ?? null) === 12 ? 'selected' : '' ?>>FSK 12 - Ab 12 Jahren</option>
                                        <option value="16" <?= ($movieData['rating_age'] ?? null) === 16 ? 'selected' : '' ?>>FSK 16 - Ab 16 Jahren</option>
                                        <option value="18" <?= ($movieData['rating_age'] ?? null) === 18 ? 'selected' : '' ?>>FSK 18 - Keine Jugendfreigabe</option>
                                    </select>
                                    <small class="form-text text-muted">
                                        <?php if (!empty($movieData['rating_age']) && $movieData['rating_age'] !== null): ?>
                                            <i class="bi bi-check-circle text-success"></i> Automatisch von TMDb erkannt
                                        <?php else: ?>
                                            Wähle die FSK-Einstufung für diesen Film
                                        <?php endif; ?>
                                    </small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="genre" class="form-label">Genre</label>
                                    <input type="text" class="form-control" id="genre" name="genre" 
                                           value="<?= htmlspecialchars($movieData['genre']) ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="trailer" class="form-label">Trailer URL</label>
                                    <input type="url" class="form-control" id="trailer" name="trailer" 
                                           value="<?= htmlspecialchars($movieData['trailer'] ?? '') ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="overview" class="form-label">Handlung</label>
                                    <textarea class="form-control" id="overview" name="overview" rows="5"><?= htmlspecialchars($movieData['overview']) ?></textarea>
                                </div>
                                
                                <!-- Hidden Fields für Poster -->
                                <input type="hidden" name="poster_path" value="<?= htmlspecialchars($movieData['poster_path']) ?>">
                                <input type="hidden" name="backdrop_path" value="<?= htmlspecialchars($movieData['backdrop_path']) ?>">
                                
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-download"></i> Film importieren
                                    </button>
                                    <a href="?page=tmdb-import&action=clear" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Neuer Film
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sidebar -->
        <div class="col-xl-4">
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-info-circle"></i> Anleitung
                </div>
                <div class="card-body">
                    <h6>Wie funktioniert's?</h6>
                    <ol class="small">
                        <li>Suche einen Film auf <a href="https://www.themoviedb.org" target="_blank">TMDb</a></li>
                        <li>Kopiere die URL (z.B. themoviedb.org/movie/603)</li>
                        <li>Füge die URL oben ein</li>
                        <li>Klicke "Film laden"</li>
                        <li>Bearbeite die Daten falls nötig</li>
                        <li>Klicke "Importieren"</li>
                    </ol>
                    
                    <hr>
                    
                    <h6>Was wird importiert?</h6>
                    <ul class="small">
                        <li><i class="bi bi-check-circle text-success"></i> Titel & Originaltitel</li>
                        <li><i class="bi bi-check-circle text-success"></i> Jahr</li>
                        <li><i class="bi bi-check-circle text-success"></i> Genre</li>
                        <li><i class="bi bi-check-circle text-success"></i> Laufzeit</li>
                        <li><i class="bi bi-check-circle text-success"></i> Handlung</li>
                        <li><i class="bi bi-check-circle text-success"></i> Poster & Cover</li>
                        <li><i class="bi bi-check-circle text-success"></i> Trailer (wenn verfügbar)</li>
                        <li><i class="bi bi-check-circle text-success"></i> TMDb Bewertung</li>
                    </ul>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-lightbulb"></i> Tipps
                </div>
                <div class="card-body">
                    <p class="small mb-2">
                        <strong>Mehrere Filme importieren?</strong><br>
                        Nutze den XML-Import für große Sammlungen.
                    </p>
                    <p class="small mb-0">
                        <strong>Cover nicht gefunden?</strong><br>
                        Du kannst das Cover nach dem Import manuell hochladen.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.img-fluid.shadow {
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
</style>