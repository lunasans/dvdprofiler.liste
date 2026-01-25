<?php
/**
 * admin/actions/tmdb-update-film.php
 * Aktualisiert einen existierenden Film mit TMDb-Daten
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/tmdb-helper.php';

header('Content-Type: application/json');

// Nur für eingeloggte User
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

// CSRF Token prüfen
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// POST Daten
$filmId = (int)($_POST['film_id'] ?? 0);
$tmdbId = (int)($_POST['tmdb_id'] ?? 0);

if ($filmId === 0 || $tmdbId === 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid film_id or tmdb_id']);
    exit;
}

try {
    // Prüfe ob Film existiert
    $stmt = $pdo->prepare("SELECT id, title, year, cover_id FROM dvds WHERE id = ? AND deleted = 0");
    $stmt->execute([$filmId]);
    $existingFilm = $stmt->fetch();
    
    if (!$existingFilm) {
        throw new Exception('Film not found');
    }
    
    // API Key holen
    $apiKey = getSetting('tmdb_api_key', '');
    if (empty($apiKey)) {
        throw new Exception('TMDb API Key is not configured');
    }
    
    // TMDb Helper initialisieren
    $tmdb = new TMDbHelper($apiKey);
    
    // Film-Details von TMDb holen
    $movieDetails = $tmdb->getMovieDetails($tmdbId);
    
    if (!$movieDetails) {
        throw new Exception('Could not fetch movie details from TMDb');
    }
    
    // === DATEN VERARBEITEN (TMDb API → DVD Profiler Format) ===
    
    // Genre: Erstes Genre aus Array nehmen
    $genre = !empty($movieDetails['genres']) ? $movieDetails['genres'][0]['name'] : null;
    
    // Runtime: Direkt aus API
    $runtime = $movieDetails['runtime'] ?? null;
    
    // Overview: Direkt aus API
    $overview = $movieDetails['overview'] ?? null;
    
    // Trailer URL: Durchsuche videos.results nach YouTube-Trailer
    $trailerUrl = null;
    if (!empty($movieDetails['videos']['results'])) {
        foreach ($movieDetails['videos']['results'] as $video) {
            if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                $trailerUrl = 'https://www.youtube.com/watch?v=' . $video['key'];
                break; // Nehme ersten Trailer
            }
        }
    }
    
    // FSK / Rating Age: Durchsuche release_dates für Deutschland
    $ratingAge = null;
    if (!empty($movieDetails['release_dates']['results'])) {
        foreach ($movieDetails['release_dates']['results'] as $country) {
            if ($country['iso_3166_1'] === 'DE' && !empty($country['release_dates'])) {
                foreach ($country['release_dates'] as $release) {
                    if (!empty($release['certification'])) {
                        $cert = $release['certification'];
                        // Extrahiere Zahl aus "FSK 12" oder "12" oder "ab 12"
                        if (preg_match('/(\d+)/', $cert, $matches)) {
                            $ratingAge = (int)$matches[1];
                        } elseif (stripos($cert, 'ohne') !== false || $cert === '0') {
                            $ratingAge = 0;
                        }
                        break 2; // Breche beide Schleifen ab
                    }
                }
            }
        }
    }
    
    // Cast: Alle Schauspieler aus credits.cast
    $cast = [];
    if (!empty($movieDetails['credits']['cast'])) {
        $cast = $movieDetails['credits']['cast'];
    }
    
    // Poster und Backdrop Pfade
    $posterPath = $movieDetails['poster_path'] ?? null;
    $backdropPath = $movieDetails['backdrop_path'] ?? null;
    
    // Cover-ID für neues Cover
    $oldCoverId = $existingFilm['cover_id'];
    $newCoverId = $oldCoverId; // Erstmal behalten
    
    // Cover herunterladen (falls vorhanden)
    if (!empty($posterPath)) {
        // Generiere neue Cover-ID
        $newCoverId = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $existingFilm['title'])) . '-' . time();
        
        // Download Front Cover (Poster)
        $posterUrl = 'https://image.tmdb.org/t/p/w500' . $posterPath;
        $frontPath = __DIR__ . '/../../cover/' . $newCoverId . 'f.jpg';
        
        $posterContent = @file_get_contents($posterUrl);
        if ($posterContent !== false) {
            file_put_contents($frontPath, $posterContent);
        }
        
        // Download Back Cover (Backdrop) falls vorhanden
        if (!empty($backdropPath)) {
            $backdropUrl = 'https://image.tmdb.org/t/p/w1280' . $backdropPath;
            $backPath = __DIR__ . '/../../cover/' . $newCoverId . 'b.jpg';
            
            $backdropContent = @file_get_contents($backdropUrl);
            if ($backdropContent !== false) {
                file_put_contents($backPath, $backdropContent);
            }
        }
        
        // Lösche altes Cover (falls anders)
        if ($oldCoverId && $oldCoverId !== $newCoverId) {
            @unlink(__DIR__ . '/../../cover/' . $oldCoverId . 'f.jpg');
            @unlink(__DIR__ . '/../../cover/' . $oldCoverId . 'b.jpg');
        }
    }
    
    // Film in Datenbank UPDATEN
    $updateStmt = $pdo->prepare("
        UPDATE dvds SET
            genre = :genre,
            runtime = :runtime,
            overview = :overview,
            trailer_url = :trailer_url,
            rating_age = :rating_age,
            cover_id = :cover_id,
            updated_at = NOW()
        WHERE id = :film_id
    ");
    
    $updateStmt->execute([
        'genre' => $genre,
        'runtime' => $runtime,
        'overview' => $overview,
        'trailer_url' => $trailerUrl,
        'rating_age' => $ratingAge,
        'cover_id' => $newCoverId,
        'film_id' => $filmId
    ]);
    
    // Schauspieler aktualisieren (OPTIONAL - nur wenn Tabellen existieren)
    if (!empty($cast)) {
        try {
            // Prüfe ob film_actor Tabelle existiert
            $checkTableStmt = $pdo->query("SHOW TABLES LIKE 'film_actor'");
            
            if ($checkTableStmt->fetch()) {
                // Tabellen existieren → Schauspieler aktualisieren
                
                // Lösche alte Verknüpfungen für diesen Film
                $deleteStmt = $pdo->prepare("DELETE FROM film_actor WHERE film_id = ?");
                $deleteStmt->execute([$filmId]);
                
                // Füge neue Schauspieler hinzu (Top 5)
                $castOrder = 1;
                foreach ($cast as $actor) {
                    $actorId = $actor['id']; // TMDb Actor ID
                    $actorName = $actor['name'];
                    $role = $actor['character'] ?? null;
                    $profilePath = $actor['profile_path'] ?? null;
                    
                    // Name in first_name und last_name aufteilen
                    $nameParts = explode(' ', $actorName, 2);
                    $firstName = $nameParts[0];
                    $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
                    
                    // Schauspieler einfügen oder aktualisieren
                    $insertActorStmt = $pdo->prepare("
                        INSERT INTO actors (id, first_name, last_name)
                        VALUES (?, ?, ?)
                        ON DUPLICATE KEY UPDATE
                            first_name = ?,
                            last_name = ?
                    ");
                    $insertActorStmt->execute([$actorId, $firstName, $lastName, $firstName, $lastName]);
                    
                    // Verknüpfung in film_actor erstellen
                    $linkStmt = $pdo->prepare("
                        INSERT INTO film_actor (film_id, actor_id, role, sort_order)
                        VALUES (?, ?, ?, ?)
                    ");
                    $linkStmt->execute([$filmId, $actorId, $role, $castOrder]);
                    
                    $castOrder++;
                }
            }
            // Wenn Tabellen nicht existieren → Einfach überspringen (kein Fehler)
            
        } catch (Exception $e) {
            // Fehler beim Schauspieler-Update → Loggen aber nicht abbrechen
            error_log('TMDb Update - Actors skipped: ' . $e->getMessage());
            // Film-Update wird trotzdem fortgesetzt!
        }
    }
    
    // Activity Log
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, user_agent)
            VALUES (:user_id, :action, :details, :ip, :user_agent)
        ");
        
        $logStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'action' => 'FILM_UPDATE_TMDB',
            'details' => json_encode([
                'film_id' => $filmId,
                'film_title' => $existingFilm['title'],
                'tmdb_id' => $tmdbId,
                'updated_fields' => ['genre', 'runtime', 'overview', 'trailer_url', 'rating_age', 'cover', 'actors']
            ]),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log('Activity log error: ' . $e->getMessage());
    }
    
    // Erfolg
    echo json_encode([
        'success' => true,
        'film_id' => $filmId,
        'message' => 'Film successfully updated from TMDb'
    ]);
    
} catch (Exception $e) {
    error_log('TMDb Update Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}