<?php
/**
 * Import from TMDb - Movies & TV Shows with Seasons/Episodes
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['import_error'] = 'Sie müssen eingeloggt sein.';
    header('Location: ../index.php?page=tmdb-import');
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['import_error'] = 'Ungültiger CSRF-Token.';
    header('Location: ../index.php?page=tmdb-import');
    exit;
}

// Serien-Handler laden
require_once __DIR__ . '/../../includes/import-series-handler.php';

try {
    // Input-Validierung
    $mediaType = trim($_POST['media_type'] ?? 'movie');
    $tmdbId = (int)($_POST['tmdb_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $genre = trim($_POST['genre'] ?? '');
    $runtime = (int)($_POST['runtime'] ?? 0);
    $overview = trim($_POST['overview'] ?? '');
    $trailer = trim($_POST['trailer_url'] ?? '');
    $posterPath = trim($_POST['poster_path'] ?? '');
    $backdropPath = trim($_POST['backdrop_path'] ?? '');
    $purchaseDate = trim($_POST['purchase_date'] ?? '');
    $ratingAge = !empty($_POST['rating_age']) ? (int)$_POST['rating_age'] : null;
    $collectionType = trim($_POST['collection_type'] ?? 'Owned');
    $actors = $_POST['actors'] ?? [];
    
    // Serien-Daten
    $seasonsData = $_POST['seasons'] ?? [];
    $selectedSeasons = $_POST['import_seasons'] ?? [];
    
    $userId = $_SESSION['user_id'];
    
    // Validierung
    if (empty($title)) {
        throw new Exception('Titel darf nicht leer sein.');
    }
    
    if ($year < 1800 || $year > 2100) {
        throw new Exception('Ungültiges Jahr.');
    }
    
    if ($ratingAge !== null && !in_array($ratingAge, [0, 6, 12, 16, 18])) {
        throw new Exception('Ungültige Altersfreigabe.');
    }
    
    if (!in_array($collectionType, ['Owned', 'Serie', 'Stream'])) {
        throw new Exception('Ungültiger Collection Type.');
    }
    
    if (!in_array($mediaType, ['movie', 'tv'])) {
        throw new Exception('Ungültiger Media Type.');
    }
    
    // Kaufdatum verarbeiten
    if (!empty($purchaseDate)) {
        $dateCheck = DateTime::createFromFormat('Y-m-d', $purchaseDate);
        if (!$dateCheck || $dateCheck->format('Y-m-d') !== $purchaseDate) {
            throw new Exception('Ungültiges Kaufdatum Format.');
        }
        $createdAt = $purchaseDate . ' 00:00:00';
    } else {
        $createdAt = date('Y-m-d H:i:s');
    }
    
    // Nächste freie Collection Number
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM dvds");
    $maxId = $stmt->fetchColumn();
    $nextId = $maxId ? ($maxId + 1) : 1;
    
    error_log("TMDb Import - Next Collection Number: {$nextId}");
    
    // Cover ID generieren
    $coverId = 'tmdb_' . $tmdbId;
    
    // Transaktion starten
    $pdo->beginTransaction();
    
    // Film/Serie in Datenbank einfügen
    $stmt = $pdo->prepare("
        INSERT INTO dvds (
            id, title, year, genre, runtime, overview, 
            cover_id, trailer_url, rating_age, collection_type, user_id, 
            created_at, updated_at
        ) VALUES (
            :id, :title, :year, :genre, :runtime, :overview,
            :cover_id, :trailer_url, :rating_age, :collection_type, :user_id,
            :created_at, NOW()
        )
    ");
    
    $params = [
        'id' => $nextId,
        'title' => $title,
        'year' => $year,
        'genre' => !empty($genre) ? $genre : null,
        'runtime' => $runtime > 0 ? $runtime : null,
        'overview' => !empty($overview) ? $overview : null,
        'cover_id' => $coverId,
        'trailer_url' => !empty($trailer) ? $trailer : null,
        'rating_age' => $ratingAge,
        'collection_type' => $collectionType,
        'user_id' => $userId,
        'created_at' => $createdAt
    ];
    
    $stmt->execute($params);
    
    $filmId = $nextId;
    
    error_log("TMDb Import - Media saved with ID: {$filmId}");
    
    // Schauspieler importieren
    if (!empty($actors)) {
        error_log("TMDb Import - Importing " . count($actors) . " actors");
        try {
            importActors($pdo, $filmId, $actors);
            error_log("TMDb Import - Actors imported successfully");
        } catch (Exception $e) {
            error_log("TMDb Import - Actor import failed: " . $e->getMessage());
        }
    }
    
    // Staffeln & Episoden importieren (nur bei TV Shows)
    if ($mediaType === 'tv' && !empty($seasonsData) && !empty($selectedSeasons)) {
        error_log("TMDb Import - Importing " . count($selectedSeasons) . " seasons");
        try {
            importSeries($pdo, $filmId, $seasonsData, $selectedSeasons);
            error_log("TMDb Import - Seasons/Episodes imported successfully");
        } catch (Exception $e) {
            error_log("TMDb Import - Series import failed: " . $e->getMessage());
        }
    }
    
    // Cover herunterladen und speichern
    if (!empty($posterPath)) {
        $posterUrl = 'https://image.tmdb.org/t/p/original' . $posterPath;
        $coverDir = __DIR__ . '/../../cover';
        
        if (!is_dir($coverDir)) {
            mkdir($coverDir, 0755, true);
        }
        
        // Front Cover (ohne Unterstrich!)
        $coverFile = $coverDir . '/' . $coverId . 'f.jpg';
        
        $posterData = @file_get_contents($posterUrl);
        if ($posterData !== false) {
            $written = file_put_contents($coverFile, $posterData);
            error_log("TMDb Import - Front cover saved: {$coverFile} ({$written} bytes)");
        } else {
            error_log("TMDb Import - Failed to download front cover from: {$posterUrl}");
        }
    }
    
    // Back Cover / Backdrop
    if (!empty($backdropPath)) {
        $backdropUrl = 'https://image.tmdb.org/t/p/original' . $backdropPath;
        $coverDir = __DIR__ . '/../../cover';
        
        if (!is_dir($coverDir)) {
            mkdir($coverDir, 0755, true);
        }
        
        // Back Cover (ohne Unterstrich!)
        $backdropFile = $coverDir . '/' . $coverId . 'b.jpg';
        
        $backdropData = @file_get_contents($backdropUrl);
        if ($backdropData !== false) {
            $written = file_put_contents($backdropFile, $backdropData);
            error_log("TMDb Import - Back cover saved: {$backdropFile} ({$written} bytes)");
        } else {
            error_log("TMDb Import - Failed to download back cover from: {$backdropUrl}");
        }
    }
    
    // Transaktion committen
    $pdo->commit();
    
    // Session leeren
    unset($_SESSION['tmdb_media_data']);
    
    // Success Message
    $coverInfo = [];
    if (file_exists(__DIR__ . '/../../cover/' . $coverId . 'f.jpg')) {
        $coverInfo[] = "✓ Front";
    }
    if (file_exists(__DIR__ . '/../../cover/' . $coverId . 'b.jpg')) {
        $coverInfo[] = "✓ Back";
    }
    
    $coverStatus = !empty($coverInfo) ? " (" . implode(", ", $coverInfo) . ")" : " (⚠️ Keine Cover)";
    
    if ($mediaType === 'tv' && !empty($selectedSeasons)) {
        $seasonCount = count($selectedSeasons);
        $_SESSION['import_success'] = "✅ Serie \"{$title}\" mit {$seasonCount} Staffel(n) erfolgreich importiert! (ID: {$filmId}){$coverStatus}";
    } else {
        $_SESSION['import_success'] = "✅ Film \"{$title}\" erfolgreich importiert! (ID: {$filmId}){$coverStatus}";
    }
    
    // Zurück zum TMDb Import
    header('Location: ../index.php?page=tmdb-import');
    exit;
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    $_SESSION['import_error'] = 'Fehler beim Import: ' . $e->getMessage();
    error_log('TMDb Import Error: ' . $e->getMessage());
    header('Location: ../index.php?page=tmdb-import');
    exit;
}

/**
 * Importiert Schauspieler für einen Film/Serie
 */
function importActors($pdo, $filmId, $actors) {
    if (empty($actors)) {
        return;
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'actors'");
    if (!$stmt->fetch()) {
        error_log("TMDb Import - actors table does not exist");
        return;
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'film_actor'");
    if (!$stmt->fetch()) {
        error_log("TMDb Import - film_actor table does not exist");
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