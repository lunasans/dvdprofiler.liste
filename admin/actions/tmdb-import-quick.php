<?php
/**
 * admin/actions/tmdb-import-quick.php
 * Schneller Import eines Films von TMDb (direkt nach Auswahl)
 */

// Output buffern
ob_start();

// Als AJAX markieren
$_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';

// Error Handling
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => "PHP Error: $errstr"
    ]);
    exit;
});

// Bootstrap laden
session_start();
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/tmdb-helper.php';

// Nur für eingeloggte User
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Unauthorized']));
}

// CSRF Token prüfen
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'CSRF validation failed']));
}

// API Key Check
$apiKey = getSetting('tmdb_api_key', '');
if (empty($apiKey)) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Kein TMDb API Key gesetzt']));
}

// Parameter
$tmdbId = (int)($_POST['tmdb_id'] ?? 0);
$collectionType = trim($_POST['collection_type'] ?? 'Owned');

if ($tmdbId <= 0) {
    ob_clean();
    header('Content-Type: application/json');
    http_response_code(400);
    die(json_encode(['success' => false, 'error' => 'Ungültige TMDb ID']));
}

if (!in_array($collectionType, ['Owned', 'Serie', 'Stream'])) {
    $collectionType = 'Owned';
}

try {
    // TMDb Helper
    $tmdb = new TMDbHelper($apiKey);
    
    // Film-Details von TMDb holen
    $movieData = $tmdb->getMovieDetails($tmdbId);
    
    if (!$movieData) {
        ob_clean();
        header('Content-Type: application/json');
        http_response_code(404);
        die(json_encode(['success' => false, 'error' => 'Film nicht auf TMDb gefunden']));
    }
    
    // Daten extrahieren
    $title = $movieData['title'] ?? 'Unbekannt';
    $year = isset($movieData['release_date']) ? (int)substr($movieData['release_date'], 0, 4) : date('Y');
    $overview = $movieData['overview'] ?? null;
    $runtime = $movieData['runtime'] ?? null;
    $posterPath = $movieData['poster_path'] ?? null;
    $backdropPath = $movieData['backdrop_path'] ?? null;
    
    // Genre (erstes Genre nehmen)
    $genre = null;
    if (!empty($movieData['genres'])) {
        $genre = $movieData['genres'][0]['name'] ?? null;
    }
    
    // Trailer-URL
    $trailerUrl = null;
    if (!empty($movieData['videos']['results'])) {
        foreach ($movieData['videos']['results'] as $video) {
            if ($video['type'] === 'Trailer' && $video['site'] === 'YouTube') {
                $trailerUrl = 'https://www.youtube.com/watch?v=' . $video['key'];
                break;
            }
        }
    }
    
    // Altersfreigabe (FSK)
    $ratingAge = null;
    if (!empty($movieData['release_dates']['results'])) {
        foreach ($movieData['release_dates']['results'] as $release) {
            if ($release['iso_3166_1'] === 'DE') {
                if (!empty($release['release_dates'][0]['certification'])) {
                    $cert = $release['release_dates'][0]['certification'];
                    // FSK konvertieren
                    if ($cert === '0' || $cert === 'FSK 0') $ratingAge = 0;
                    elseif ($cert === '6' || $cert === 'FSK 6') $ratingAge = 6;
                    elseif ($cert === '12' || $cert === 'FSK 12') $ratingAge = 12;
                    elseif ($cert === '16' || $cert === 'FSK 16') $ratingAge = 16;
                    elseif ($cert === '18' || $cert === 'FSK 18') $ratingAge = 18;
                }
                break;
            }
        }
    }
    
    // Schauspieler (Top 5)
    $actors = [];
    if (!empty($movieData['credits']['cast'])) {
        $cast = array_slice($movieData['credits']['cast'], 0, 5);
        foreach ($cast as $index => $person) {
            $actors[] = [
                'name' => $person['name'] ?? '',
                'character' => $person['character'] ?? '',
                'order' => $index
            ];
        }
    }
    
    // Nächste freie ID
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM dvds");
    $maxId = $stmt->fetchColumn();
    $nextId = $maxId ? ($maxId + 1) : 1;
    
    // Cover ID
    $coverId = 'tmdb_' . $tmdbId;
    
    // User ID
    $userId = $_SESSION['user_id'];
    
    // Transaktion starten
    $pdo->beginTransaction();
    
    // Film in DB einfügen
    $stmt = $pdo->prepare("
        INSERT INTO dvds (
            id, title, year, genre, runtime, overview,
            cover_id, trailer_url, rating_age, collection_type, user_id,
            created_at, updated_at
        ) VALUES (
            :id, :title, :year, :genre, :runtime, :overview,
            :cover_id, :trailer_url, :rating_age, :collection_type, :user_id,
            NOW(), NOW()
        )
    ");
    
    $stmt->execute([
        'id' => $nextId,
        'title' => $title,
        'year' => $year,
        'genre' => $genre,
        'runtime' => $runtime,
        'overview' => $overview,
        'cover_id' => $coverId,
        'trailer_url' => $trailerUrl,
        'rating_age' => $ratingAge,
        'collection_type' => $collectionType,
        'user_id' => $userId
    ]);
    
    $filmId = $nextId;
    
    // Schauspieler importieren
    if (!empty($actors)) {
        importActors($pdo, $filmId, $actors);
    }
    
    // Cover herunterladen
    $coverDownloaded = false;
    $backdropDownloaded = false;
    
    if (!empty($posterPath)) {
        $posterUrl = 'https://image.tmdb.org/t/p/original' . $posterPath;
        $coverDir = dirname(__DIR__, 2) . '/cover';
        
        if (!is_dir($coverDir)) {
            mkdir($coverDir, 0755, true);
        }
        
        $coverFile = $coverDir . '/' . $coverId . 'f.jpg';
        $posterData = @file_get_contents($posterUrl);
        if ($posterData !== false) {
            file_put_contents($coverFile, $posterData);
            $coverDownloaded = true;
        }
    }
    
    if (!empty($backdropPath)) {
        $backdropUrl = 'https://image.tmdb.org/t/p/original' . $backdropPath;
        $coverDir = dirname(__DIR__, 2) . '/cover';
        
        $backdropFile = $coverDir . '/' . $coverId . 'b.jpg';
        $backdropData = @file_get_contents($backdropUrl);
        if ($backdropData !== false) {
            file_put_contents($backdropFile, $backdropData);
            $backdropDownloaded = true;
        }
    }
    
    // Transaktion committen
    $pdo->commit();
    
    // Response
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'film_id' => $filmId,
        'title' => $title,
        'year' => $year,
        'cover_downloaded' => $coverDownloaded,
        'backdrop_downloaded' => $backdropDownloaded,
        'message' => "Film \"{$title}\" ({$year}) erfolgreich importiert!"
    ]);
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    ob_clean();
    header('Content-Type: application/json');
    error_log('TMDb Quick Import Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Import fehlgeschlagen: ' . $e->getMessage()
    ]);
    exit;
}

/**
 * Importiert Schauspieler für einen Film
 */
function importActors($pdo, $filmId, $actors) {
    if (empty($actors)) {
        return;
    }
    
    // Prüfe ob Tabellen existieren
    $stmt = $pdo->query("SHOW TABLES LIKE 'actors'");
    if (!$stmt->fetch()) {
        return;
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'film_actor'");
    if (!$stmt->fetch()) {
        return;
    }
    
    foreach ($actors as $actorData) {
        $fullName = trim($actorData['name'] ?? '');
        $character = trim($actorData['character'] ?? '');
        $order = (int)($actorData['order'] ?? 0);
        
        if (empty($fullName)) {
            continue;
        }
        
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        try {
            // Prüfe ob Schauspieler existiert
            $stmt = $pdo->prepare("
                SELECT id FROM actors
                WHERE first_name = :first_name
                AND last_name = :last_name
                LIMIT 1
            ");
            
            $stmt->execute([
                'first_name' => $firstName,
                'last_name' => $lastName
            ]);
            
            $existingActor = $stmt->fetch();
            
            if ($existingActor) {
                $actorId = $existingActor['id'];
            } else {
                // Neuen Schauspieler anlegen
                $stmt = $pdo->prepare("
                    INSERT INTO actors (first_name, last_name, created_at, updated_at)
                    VALUES (:first_name, :last_name, NOW(), NOW())
                ");
                
                $stmt->execute([
                    'first_name' => $firstName,
                    'last_name' => $lastName
                ]);
                
                $actorId = $pdo->lastInsertId();
            }
            
            // Verknüpfung erstellen
            $stmt = $pdo->prepare("
                INSERT INTO film_actor (film_id, actor_id, role, is_main_role, sort_order, created_at)
                VALUES (:film_id, :actor_id, :role, :is_main_role, :sort_order, NOW())
                ON DUPLICATE KEY UPDATE
                    role = VALUES(role),
                    is_main_role = VALUES(is_main_role),
                    sort_order = VALUES(sort_order)
            ");
            
            $stmt->execute([
                'film_id' => $filmId,
                'actor_id' => $actorId,
                'role' => !empty($character) ? $character : null,
                'is_main_role' => ($order < 3) ? 1 : 0,
                'sort_order' => $order
            ]);
            
        } catch (PDOException $e) {
            error_log("TMDb Import - Failed to import actor {$fullName}: " . $e->getMessage());
        }
    }
}