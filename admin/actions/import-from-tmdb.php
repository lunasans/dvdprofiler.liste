<?php
/**
 * DVD Profiler Liste - TMDb Import Action
 * Speichert importierte Filme in die Datenbank
 * 
 * @package    dvdprofiler.liste
 * @version    1.4.8
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

// Nur POST erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// Nur für eingeloggte User
if (!isset($_SESSION['user_id'])) {
    $_SESSION['import_error'] = 'Sie müssen eingeloggt sein.';
    header('Location: ../index.php?page=tmdb-import');
    exit;
}

// CSRF-Check
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['import_error'] = 'Ungültiger CSRF-Token.';
    header('Location: ../index.php?page=tmdb-import');
    exit;
}

try {
    // DEBUG: Komplettes POST array
    error_log('TMDb Import - POST data: ' . print_r($_POST, true));
    
    // Input-Validierung
    $tmdbId = (int)($_POST['tmdb_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $genre = trim($_POST['genre'] ?? '');
    $runtime = (int)($_POST['runtime'] ?? 0);
    $overview = trim($_POST['overview'] ?? '');
    $trailer = trim($_POST['trailer'] ?? '');
    $posterPath = trim($_POST['poster_path'] ?? '');
    $backdropPath = trim($_POST['backdrop_path'] ?? '');
    $purchaseDate = trim($_POST['purchase_date'] ?? '');
    $ratingAge = !empty($_POST['rating_age']) ? (int)$_POST['rating_age'] : null;
    $collectionType = trim($_POST['collection_type'] ?? 'Owned'); // NEU: Collection Type
    $actors = $_POST['actors'] ?? []; // NEU: Schauspieler
    $userId = $_SESSION['user_id'];
    
    // DEBUG: Kaufdatum
    error_log('TMDb Import - purchase_date POST: ' . var_export($purchaseDate, true));
    error_log('TMDb Import - purchase_date empty? ' . (empty($purchaseDate) ? 'YES' : 'NO'));
    
    if (empty($title)) {
        throw new Exception('Titel darf nicht leer sein.');
    }
    
    if ($year < 1800 || $year > 2100) {
        throw new Exception('Ungültiges Jahr.');
    }
    
    // Rating Age validieren (falls gesetzt)
    if ($ratingAge !== null && !in_array($ratingAge, [0, 6, 12, 16, 18])) {
        throw new Exception('Ungültige Altersfreigabe.');
    }
    
    // Collection Type validieren
    if (!in_array($collectionType, ['Owned', 'Serie', 'Stream'])) {
        throw new Exception('Ungültiger Collection Type.');
    }
    
    // Kaufdatum validieren und aufbereiten
    if (!empty($purchaseDate)) {
        // Prüfe ob gültiges Datum
        $dateCheck = DateTime::createFromFormat('Y-m-d', $purchaseDate);
        if (!$dateCheck || $dateCheck->format('Y-m-d') !== $purchaseDate) {
            error_log('TMDb Import - Invalid date format: ' . $purchaseDate);
            throw new Exception('Ungültiges Kaufdatum.');
        }
        $createdAt = $purchaseDate . ' 00:00:00';
        error_log('TMDb Import - Using purchase date: ' . $createdAt);
    } else {
        // Fallback: Aktuelles Datum
        $createdAt = date('Y-m-d H:i:s');
        error_log('TMDb Import - Using fallback date: ' . $createdAt);
    }
    
    // Cover-ID generieren (basierend auf TMDb ID)
    $coverId = 'tmdb_' . $tmdbId;
    
    // Nächste freie Collection Number finden (wie DVD Profiler)
    $stmt = $pdo->query("SELECT MAX(id) as max_id FROM dvds");
    $maxId = $stmt->fetchColumn();
    $nextId = $maxId ? ($maxId + 1) : 1;
    
    error_log("TMDb Import - Next Collection Number: {$nextId}");
    
    // Prüfen ob Film schon existiert
    $checkStmt = $pdo->prepare("SELECT id FROM dvds WHERE cover_id = ?");
    $checkStmt->execute([$coverId]);
    $existingFilm = $checkStmt->fetch();
    
    if ($existingFilm) {
        throw new Exception('Dieser Film wurde bereits importiert (ID: ' . $existingFilm['id'] . ')');
    }
    
    // Film in Datenbank einfügen (MIT manueller ID!)
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
        'genre' => $genre,
        'runtime' => $runtime,
        'overview' => $overview,
        'cover_id' => $coverId,
        'trailer_url' => !empty($trailer) ? $trailer : null,
        'rating_age' => $ratingAge,
        'collection_type' => $collectionType,
        'user_id' => $userId,
        'created_at' => $createdAt
    ];
    
    error_log('TMDb Import - SQL Params: ' . print_r($params, true));
    
    try {
        $stmt->execute($params);
    } catch (PDOException $e) {
        error_log('TMDb Import - SQL Error: ' . $e->getMessage());
        error_log('TMDb Import - SQL Code: ' . $e->getCode());
        
        // Spezifische Fehlermeldung für AUTO_INCREMENT Problem
        if (strpos($e->getMessage(), "doesn't have a default value") !== false) {
            throw new Exception('Datenbank-Konfigurationsfehler: Die ID-Spalte ist nicht korrekt konfiguriert. Bitte führe das SQL-Fix-Script aus (siehe Dokumentation).');
        }
        
        throw $e;
    }
    
    $filmId = $nextId; // Verwende die manuelle ID statt lastInsertId()
    
    error_log("TMDb Import - Film saved with ID: {$filmId}");
    
    // Schauspieler importieren
    if (!empty($actors)) {
        error_log("TMDb Import - Importing " . count($actors) . " actors");
        try {
            importActors($pdo, $filmId, $actors);
            error_log("TMDb Import - Actors imported successfully");
        } catch (Exception $e) {
            error_log("TMDb Import - Actor import failed: " . $e->getMessage());
            // Nicht kritisch, Film ist bereits gespeichert
        }
    }
    
    // Cover herunterladen und speichern
    if (!empty($posterPath)) {
        $posterUrl = 'https://image.tmdb.org/t/p/original' . $posterPath;
        $coverDir = __DIR__ . '/../../cover';
        
        error_log("TMDb Import - Poster URL: {$posterUrl}");
        error_log("TMDb Import - Cover Dir: {$coverDir}");
        
        // Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir($coverDir)) {
            mkdir($coverDir, 0755, true);
            error_log("TMDb Import - Created cover directory");
        }
        
        // Cover herunterladen
        $coverData = @file_get_contents($posterUrl);
        if ($coverData !== false) {
            $coverFile = $coverDir . '/' . $coverId . 'f.jpg';  // OHNE Unterstrich!
            $written = file_put_contents($coverFile, $coverData);
            error_log("TMDb Import - Front cover saved: {$coverFile} ({$written} bytes)");
        } else {
            error_log("TMDb Import - Failed to download front cover from {$posterUrl}");
        }
    } else {
        error_log("TMDb Import - No poster_path provided");
    }
    
    // Backdrop als Backcover speichern (optional)
    if (!empty($backdropPath)) {
        $backdropUrl = 'https://image.tmdb.org/t/p/original' . $backdropPath;
        error_log("TMDb Import - Backdrop URL: {$backdropUrl}");
        
        $backdropData = @file_get_contents($backdropUrl);
        if ($backdropData !== false) {
            $backdropFile = __DIR__ . '/../../cover/' . $coverId . 'b.jpg';  // OHNE Unterstrich!
            $written = file_put_contents($backdropFile, $backdropData);
            error_log("TMDb Import - Back cover saved: {$backdropFile} ({$written} bytes)");
        } else {
            error_log("TMDb Import - Failed to download backdrop from {$backdropUrl}");
        }
    } else {
        error_log("TMDb Import - No backdrop_path provided");
    }
    
    // Prüfe welche Cover erfolgreich heruntergeladen wurden
    $coverInfo = [];
    if (file_exists(__DIR__ . '/../../cover/' . $coverId . 'f.jpg')) {  // OHNE Unterstrich!
        $coverInfo[] = "✓ Front";
    }
    if (file_exists(__DIR__ . '/../../cover/' . $coverId . 'b.jpg')) {  // OHNE Unterstrich!
        $coverInfo[] = "✓ Back";
    }
    
    $coverStatus = !empty($coverInfo) ? " (" . implode(", ", $coverInfo) . ")" : " (⚠️ Keine Cover)";
    
    $_SESSION['import_success'] = "✅ Film \"{$title}\" erfolgreich importiert! (ID: {$filmId}){$coverStatus}";
    
    // Lösche gespeicherte Movie-Data aus Session
    unset($_SESSION['tmdb_movie_data']);
    
    // Zurück zum TMDb Import für nächsten Film
    header('Location: ../index.php?page=tmdb-import');
    exit;
    
} catch (Exception $e) {
    $_SESSION['import_error'] = 'Fehler beim Import: ' . $e->getMessage();
    error_log('TMDb Import Error: ' . $e->getMessage());
    header('Location: ../index.php?page=tmdb-import');
    exit;
}

/**
 * Importiert Schauspieler für einen Film
 * 
 * @param PDO $pdo Datenbank-Verbindung
 * @param int $filmId Film-ID
 * @param array $actors Array mit Schauspieler-Daten
 */
function importActors($pdo, $filmId, $actors) {
    if (empty($actors)) {
        return;
    }
    
    // Prüfe ob Tabellen existieren
    $stmt = $pdo->query("SHOW TABLES LIKE 'actors'");
    if (!$stmt->fetch()) {
        error_log("TMDb Import - actors table does not exist, skipping actor import");
        return;
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'film_actor'");
    if (!$stmt->fetch()) {
        error_log("TMDb Import - film_actor table does not exist, skipping actor import");
        return;
    }
    
    foreach ($actors as $actorData) {
        $fullName = trim($actorData['name'] ?? '');
        $character = trim($actorData['character'] ?? '');
        $order = (int)($actorData['order'] ?? 0);
        
        if (empty($fullName)) {
            continue;
        }
        
        // Namen aufteilen (z.B. "Keanu Reeves" -> first="Keanu", last="Reeves")
        $nameParts = explode(' ', $fullName, 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        
        try {
            // Suche Schauspieler nach Namen
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
                // Schauspieler existiert bereits
                $actorId = $existingActor['id'];
                error_log("TMDb Import - Found existing actor: {$fullName} (ID: {$actorId})");
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
                error_log("TMDb Import - Created new actor: {$fullName} (ID: {$actorId})");
            }
            
            // Verknüpfung Film <-> Schauspieler erstellen
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
                'is_main_role' => ($order < 3) ? 1 : 0, // Top 3 = Hauptrollen
                'sort_order' => $order
            ]);
            
            error_log("TMDb Import - Linked actor {$fullName} to film {$filmId} as '{$character}'");
            
        } catch (PDOException $e) {
            error_log("TMDb Import - Failed to import actor {$fullName}: " . $e->getMessage());
            // Weitermachen mit nächstem Schauspieler
        }
    }
}