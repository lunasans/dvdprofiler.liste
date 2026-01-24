<?php
/**
 * Edit Film Action
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['films_error'] = 'Sie müssen eingeloggt sein.';
    header('Location: ../index.php?page=films');
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['films_error'] = 'Ungültiger CSRF-Token.';
    header('Location: ../index.php?page=films');
    exit;
}

try {
    $filmId = (int)($_POST['film_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $genre = trim($_POST['genre'] ?? '');
    $runtime = isset($_POST['runtime']) && $_POST['runtime'] !== '' ? (int)$_POST['runtime'] : null;
    $ratingAge = isset($_POST['rating_age']) && $_POST['rating_age'] !== '' ? (int)$_POST['rating_age'] : null;
    $collectionType = trim($_POST['collection_type'] ?? 'Owned');
    $createdAt = trim($_POST['created_at'] ?? '');
    $trailerUrl = trim($_POST['trailer_url'] ?? '');
    $overview = trim($_POST['overview'] ?? '');
    
    // Pagination params for redirect
    $currentPage = (int)($_POST['current_page'] ?? 1);
    $search = trim($_POST['search'] ?? '');
    $collection = trim($_POST['collection'] ?? '');
    
    if ($filmId === 0) {
        throw new Exception('Ungültige Film-ID.');
    }
    
    if (empty($title)) {
        throw new Exception('Titel darf nicht leer sein.');
    }
    
    if ($year < 1800 || $year > 2100) {
        throw new Exception('Ungültiges Jahr.');
    }
    
    if (!in_array($collectionType, ['Owned', 'Serie', 'Stream'])) {
        throw new Exception('Ungültiger Collection Type.');
    }
    
    // created_at verarbeiten
    if (!empty($createdAt)) {
        // Validiere Datum
        $dateCheck = DateTime::createFromFormat('Y-m-d', $createdAt);
        if (!$dateCheck || $dateCheck->format('Y-m-d') !== $createdAt) {
            throw new Exception('Ungültiges Datum-Format für "Hinzugefügt am".');
        }
        // Konvertiere zu MySQL DATETIME Format (Datum + Zeit)
        $createdAtFormatted = $createdAt . ' 00:00:00';
    } else {
        // Falls leer, behalte aktuelles Datum aus DB
        $stmt = $pdo->prepare("SELECT created_at FROM dvds WHERE id = ?");
        $stmt->execute([$filmId]);
        $currentFilm = $stmt->fetch();
        $createdAtFormatted = $currentFilm['created_at'] ?? date('Y-m-d H:i:s');
    }
    
    // Update Film
    $stmt = $pdo->prepare("
        UPDATE dvds SET
            title = :title,
            year = :year,
            genre = :genre,
            runtime = :runtime,
            rating_age = :rating_age,
            collection_type = :collection_type,
            created_at = :created_at,
            trailer_url = :trailer_url,
            overview = :overview,
            updated_at = NOW()
        WHERE id = :id AND deleted = 0
    ");
    
    $stmt->execute([
        'id' => $filmId,
        'title' => $title,
        'year' => $year,
        'genre' => !empty($genre) ? $genre : null,
        'runtime' => $runtime,
        'rating_age' => $ratingAge,
        'collection_type' => $collectionType,
        'created_at' => $createdAtFormatted,
        'trailer_url' => !empty($trailerUrl) ? $trailerUrl : null,
        'overview' => !empty($overview) ? $overview : null
    ]);
    
    $_SESSION['films_success'] = "Film \"{$title}\" erfolgreich aktualisiert!";
    
    // Redirect with pagination
    $redirectParams = ['page' => 'films', 'p' => $currentPage];
    if (!empty($search)) $redirectParams['search'] = $search;
    if (!empty($collection)) $redirectParams['collection'] = $collection;
    
    header('Location: ../index.php?' . http_build_query($redirectParams));
    exit;
    
} catch (Exception $e) {
    $_SESSION['films_error'] = 'Fehler beim Speichern: ' . $e->getMessage();
    error_log('Edit Film Error: ' . $e->getMessage());
    header('Location: ../index.php?page=films');
    exit;
}