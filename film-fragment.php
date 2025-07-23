<?php
/**
 * Film-Fragment mit verbesserter Fehlerbehandlung und Sicherheit
 * Fixes: Output Buffer Management, ID-Validierung, Memory-Leaks
 */

// Sicherheitsheader setzen
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

try {
    // Bessere ID-Validierung am Anfang
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        http_response_code(400);
        throw new InvalidArgumentException('Ungültige Film-ID: ' . ($_GET['id'] ?? 'keine'));
    }

    // Memory-optimiertes Output Buffering
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();

    // Database connection mit verbesserter Fehlerbehandlung
    try {
        require_once __DIR__ . '/includes/bootstrap.php';
        
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new Exception('Datenbankverbindung nicht verfügbar');
        }
        
        // Test der Verbindung
        $pdo->query('SELECT 1');
        
    } catch (PDOException $e) {
        throw new Exception('Datenbankfehler: ' . $e->getMessage());
    }

    // Film-Daten laden mit prepared statement - KORRIGIERTER QUERY
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, 
                   u.username as added_by_user,
                   d.genre as genres_list,
                   (SELECT COUNT(*) FROM dvds WHERE boxset_parent = d.id) as boxset_children_count
            FROM dvds d 
            LEFT JOIN users u ON d.user_id = u.id 
            WHERE d.id = ?
        ");
        
        if (!$stmt) {
            throw new Exception('SQL-Statement konnte nicht vorbereitet werden');
        }
        
        $stmt->execute([$id]);
        $dvd = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dvd) {
            http_response_code(404);
            throw new Exception("Film mit ID $id nicht gefunden");
        }
        
        // Debug: Film gefunden
        error_log("Film-Fragment: Film geladen - ID: $id, Titel: " . $dvd['title']);
        
    } catch (PDOException $e) {
        throw new Exception('Fehler beim Laden der Film-Daten: ' . $e->getMessage());
    }

    // BoxSet-Kinder laden falls vorhanden
    $boxsetChildren = [];
    if ($dvd['boxset_children_count'] > 0) {
        try {
            $childStmt = $pdo->prepare("
                SELECT id, title, year, cover_id 
                FROM dvds 
                WHERE boxset_parent = ? 
                ORDER BY title ASC
            ");
            $childStmt->execute([$id]);
            $boxsetChildren = $childStmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Film-Fragment: " . count($boxsetChildren) . " BoxSet-Kinder geladen");
            
        } catch (PDOException $e) {
            error_log("Warnung: BoxSet-Kinder konnten nicht geladen werden: " . $e->getMessage());
            // Nicht kritisch - weiter machen
        }
    }

    // Debug: Vor film-view.php include
    error_log("Film-Fragment: Lade film-view.php für Film: " . $dvd['title']);

    // film-view.php laden - mit Fehlerbehandlung
    $filmViewPath = __DIR__ . '/partials/film-view.php';
    
    if (!file_exists($filmViewPath)) {
        throw new Exception('film-view.php nicht gefunden: ' . $filmViewPath);
    }
    
    if (!is_readable($filmViewPath)) {
        throw new Exception('film-view.php nicht lesbar: ' . $filmViewPath);
    }

    // Neuer Output Buffer für film-view.php
    ob_start();
    
    try {
        // Include mit Variablen-Isolation
        $includeFunction = function($path, $data, $children) {
            extract($data, EXTR_SKIP);
            $boxsetChildren = $children;
            include $path;
        };
        
        $includeFunction($filmViewPath, $dvd, $boxsetChildren);
        $filmViewOutput = ob_get_clean();
        
        if (empty($filmViewOutput)) {
            throw new Exception('film-view.php hat keinen Output produziert');
        }
        
        // XSS-Schutz für Film-ID
        $safeFilmId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        
        // Wrapper für AJAX-Content mit verbesserter Struktur
        echo '<div class="film-detail-content fade-in" data-film-id="' . $safeFilmId . '" data-loaded="' . time() . '">';
        echo $filmViewOutput;
        echo '</div>';
        
    } catch (Throwable $e) {
        ob_end_clean();
        throw new Exception('Fehler beim Laden von film-view.php: ' . $e->getMessage());
    }

    // Debug: Erfolgreich geladen
    error_log("Film-Fragment: Erfolgreich geladen für Film-ID: $id, Output-Größe: " . strlen($filmViewOutput) . " Bytes");

} catch (Throwable $e) {
    // Buffer komplett leeren bei Fehler
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fehler loggen mit mehr Kontext
    error_log("Film-Fragment FATAL ERROR: " . $e->getMessage());
    error_log("Film-Fragment Error Type: " . get_class($e));
    error_log("Film-Fragment File: " . $e->getFile() . ":" . $e->getLine());
    error_log("Film-Fragment Stack: " . $e->getTraceAsString());
    error_log("Film-Fragment Request: " . print_r($_REQUEST, true));
    
    // Benutzerfreundliche Fehlermeldung
    http_response_code(500);
    echo '<div class="alert alert-danger">';
    echo '<h4><i class="bi bi-exclamation-triangle"></i> Fehler beim Laden</h4>';
    echo '<p>Der Film konnte nicht geladen werden. Details wurden protokolliert.</p>';
    if (getSetting('environment', 'production') === 'development') {
        echo '<details><summary>Debug Info</summary>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        echo '</details>';
    }
    echo '</div>';
}
?>