<?php
/**
 * DVD Profiler Liste - Film bearbeiten Action Handler
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.6
 */

declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

// Helper: Redirect zurück zur Films-Seite mit erhaltener Pagination
function redirectToFilms() {
    $params = ['page' => 'films'];
    
    // Pagination und Filter erhalten
    if (!empty($_POST['current_page']) && is_numeric($_POST['current_page'])) {
        $params['p'] = $_POST['current_page'];
    }
    if (!empty($_POST['search'])) {
        $params['search'] = $_POST['search'];
    }
    if (!empty($_POST['collection'])) {
        $params['collection'] = $_POST['collection'];
    }
    
    $queryString = http_build_query($params);
    header('Location: ../index.php?' . $queryString);
    exit;
}

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

// Nur für eingeloggte Nutzer
if (!isset($_SESSION['user_id'])) {
    $_SESSION['film_error'] = 'Sie müssen eingeloggt sein, um Filme zu bearbeiten.';
    redirectToFilms();
    exit;
}

// CSRF-Token validieren
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['film_error'] = 'Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.';
    redirectToFilms();
    exit;
}

try {
    // Input-Validierung
    $filmId = filter_input(INPUT_POST, 'film_id', FILTER_VALIDATE_INT);
    if (!$filmId || $filmId <= 0) {
        throw new InvalidArgumentException('Ungültige Film-ID.');
    }
    
    // Trailer-URL validieren und bereinigen
    $trailerUrl = trim($_POST['trailer_url'] ?? '');
    
    // Wenn URL angegeben, validieren
    if ($trailerUrl !== '') {
        // Maximale Länge prüfen
        if (strlen($trailerUrl) > 500) {
            throw new InvalidArgumentException('Trailer-URL ist zu lang (max. 500 Zeichen).');
        }
        
        // URL-Format validieren
        if (!filter_var($trailerUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Ungültiges URL-Format.');
        }
        
        // Nur HTTPS erlauben (Sicherheit)
        if (!str_starts_with($trailerUrl, 'https://')) {
            throw new InvalidArgumentException('Nur HTTPS-URLs sind erlaubt.');
        }
        
        // Optional: Nur erlaubte Domains (YouTube, Vimeo, Dailymotion)
        $allowedDomains = [
            'youtube.com',
            'youtu.be',
            'vimeo.com',
            'dailymotion.com',
            'www.youtube.com',
            'www.vimeo.com',
            'www.dailymotion.com'
        ];
        
        $urlHost = parse_url($trailerUrl, PHP_URL_HOST);
        $isAllowedDomain = false;
        
        foreach ($allowedDomains as $domain) {
            if ($urlHost === $domain || str_ends_with($urlHost, '.' . $domain)) {
                $isAllowedDomain = true;
                break;
            }
        }
        
        if (!$isAllowedDomain) {
            throw new InvalidArgumentException(
                'Nur YouTube-, Vimeo- und Dailymotion-URLs sind erlaubt.'
            );
        }
    }
    
    // Prüfen ob Film existiert
    $checkStmt = $pdo->prepare("SELECT id, title FROM dvds WHERE id = ?");
    $checkStmt->execute([$filmId]);
    $film = $checkStmt->fetch();
    
    if (!$film) {
        throw new RuntimeException('Film nicht gefunden.');
    }
    
    // Film aktualisieren
    $updateStmt = $pdo->prepare("
        UPDATE dvds 
        SET trailer_url = :trailer_url,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :film_id
    ");
    
    $result = $updateStmt->execute([
        'trailer_url' => $trailerUrl !== '' ? $trailerUrl : null,
        'film_id' => $filmId
    ]);
    
    if (!$result) {
        throw new RuntimeException('Fehler beim Aktualisieren des Films.');
    }
    
    // Activity-Log eintragen (optional)
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO activity_log (user_id, action, details, ip_address, user_agent)
            VALUES (:user_id, :action, :details, :ip, :user_agent)
        ");
        
        $logStmt->execute([
            'user_id' => $_SESSION['user_id'],
            'action' => 'FILM_EDIT',
            'details' => json_encode([
                'film_id' => $filmId,
                'film_title' => $film['title'],
                'trailer_url' => $trailerUrl !== '' ? $trailerUrl : null,
                'updated_fields' => ['trailer_url']
            ]),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        // Log-Fehler nur protokollieren, nicht den Hauptvorgang abbrechen
        error_log('Activity log error: ' . $e->getMessage());
    }
    
    // Erfolgs-Nachricht
    if ($trailerUrl !== '') {
        $_SESSION['film_success'] = "✅ Trailer-URL für \"{$film['title']}\" erfolgreich gespeichert.";
    } else {
        $_SESSION['film_success'] = "✅ Trailer-URL für \"{$film['title']}\" erfolgreich entfernt.";
    }
    
    // Zurück zur Film-Liste
    redirectToFilms();
    exit;
    
} catch (InvalidArgumentException $e) {
    $_SESSION['film_error'] = '❌ ' . $e->getMessage();
    redirectToFilms();
    exit;
    
} catch (RuntimeException $e) {
    $_SESSION['film_error'] = '❌ ' . $e->getMessage();
    error_log('Film edit error: ' . $e->getMessage());
    redirectToFilms();
    exit;
    
} catch (PDOException $e) {
    $_SESSION['film_error'] = '❌ Datenbankfehler beim Speichern.';
    error_log('Database error in edit_film.php: ' . $e->getMessage());
    redirectToFilms();
    exit;
    
} catch (Exception $e) {
    $_SESSION['film_error'] = '❌ Ein unerwarteter Fehler ist aufgetreten.';
    error_log('Unexpected error in edit_film.php: ' . $e->getMessage());
    redirectToFilms();
    exit;
}