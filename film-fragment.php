<?php
/**
 * Film-Fragment mit korrigierter Fehlerbehandlung
 * Fixes für Version 1.4.7
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

    // Film-Daten laden mit prepared statement
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, 
                   u.email as added_by_user,
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
                SELECT id, title, year, poster_url 
                FROM dvds 
                WHERE boxset_parent = ? 
                ORDER BY title ASC
            ");
            $childStmt->execute([$id]);
            $boxsetChildren = $childStmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("Film-Fragment: " . count($boxsetChildren) . " BoxSet-Kinder geladen");
            
        } catch (PDOException $e) {
            error_log("Warnung: BoxSet-Kinder konnten nicht geladen werden: " . $e->getMessage());
        }
    }

    // film-view.php laden - mit Fallback
    $filmViewPath = __DIR__ . '/partials/film-view.php';
    
    if (!file_exists($filmViewPath)) {
        // Fallback: Inline Film-View generieren
        error_log("WARNUNG: partials/film-view.php nicht gefunden, verwende Fallback");
        
        // XSS-Schutz für Film-ID
        $safeFilmId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        
        echo '<div class="film-detail-content fade-in" data-film-id="' . $safeFilmId . '" data-loaded="' . time() . '">';
        include __DIR__ . '/partials/film-view-fallback.php';
        echo '</div>';
        
    } else {
        // Original film-view.php laden
        if (!is_readable($filmViewPath)) {
            throw new Exception('film-view.php nicht lesbar: ' . $filmViewPath);
        }

        // Neuer Output Buffer für film-view.php
        ob_start();
        
        try {
            // Direktes Include - $dvd und $boxsetChildren sind im Scope verfügbar
            include $filmViewPath;
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
    }

    // Debug: Erfolgreich geladen
    error_log("Film-Fragment: Erfolgreich geladen für Film-ID: $id");

} catch (Throwable $e) {
    // Buffer komplett leeren bei Fehler
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fehler loggen mit mehr Kontext
    error_log("Film-Fragment FATAL ERROR: " . $e->getMessage());
    error_log("Film-Fragment Error Type: " . get_class($e));
    error_log("Film-Fragment File: " . $e->getFile() . ":" . $e->getLine());
    
    // HTTP Status setzen basierend auf Fehlertyp
    if ($e instanceof InvalidArgumentException) {
        http_response_code(400);
    } elseif (strpos($e->getMessage(), 'nicht gefunden') !== false) {
        http_response_code(404);
    } else {
        http_response_code(500);
    }
    
    // Benutzerfreundliche Fehlermeldung mit verbesserter UX
    $errorClass = $e instanceof InvalidArgumentException ? 'client-error' : 'server-error';
    $errorIcon = $e instanceof InvalidArgumentException ? 'bi-exclamation-circle' : 'bi-exclamation-triangle';
    $safeErrorMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $safeFilmId = htmlspecialchars($_GET['id'] ?? 'keine', ENT_QUOTES, 'UTF-8');
    $safeIP = htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unbekannt', ENT_QUOTES, 'UTF-8');
    
    echo '<div class="error-message ' . $errorClass . '">
            <div class="error-icon">
                <i class="' . $errorIcon . '"></i>
            </div>
            <div class="error-content">
                <h3>' . ($e instanceof InvalidArgumentException ? 'Ungültige Anfrage' : 'Serverfehler') . '</h3>
                <p>Die Film-Details konnten nicht geladen werden.</p>
                <details class="error-details">
                    <summary>Technische Details (für Entwickler)</summary>
                    <p><strong>Fehlertyp:</strong> ' . htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8') . '</p>
                    <p><strong>Fehler:</strong> ' . $safeErrorMsg . '</p>
                    <p><strong>Film-ID:</strong> ' . $safeFilmId . '</p>
                    <p><strong>Zeit:</strong> ' . date('Y-m-d H:i:s') . '</p>
                    <p><strong>IP:</strong> ' . $safeIP . '</p>
                    <p><strong>Memory:</strong> ' . memory_get_usage(true) . ' bytes</p>
                    <p><strong>User-Agent:</strong> ' . htmlspecialchars($_SERVER['HTTP_USER_AGENT'] ?? 'unbekannt', ENT_QUOTES, 'UTF-8') . '</p>
                </details>
                <div class="error-actions">
                    <button onclick="window.location.reload()" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-arrow-clockwise"></i> Erneut versuchen
                    </button>
                    <button onclick="history.back()" class="btn btn-sm btn-outline-light">
                        <i class="bi bi-arrow-left"></i> Zurück
                    </button>
                </div>
            </div>
          </div>';
}
?>