<?php
/**
 * Film-Fragment mit ultra-robuster Fehlerbehandlung
 * Prüft jede Abhängigkeit schrittweise
 */

// Sicherheitsheader setzen
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Globale Variablen für Debugging
$debugSteps = [];

try {
    $debugSteps[] = "Start - ID Validierung";
    
    // Bessere ID-Validierung am Anfang
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        http_response_code(400);
        throw new InvalidArgumentException('Ungültige Film-ID: ' . ($_GET['id'] ?? 'keine'));
    }
    
    $debugSteps[] = "ID validiert: $id";

    // Memory-optimiertes Output Buffering
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();

    $debugSteps[] = "Output buffering initialisiert";

    // Database connection mit verbesserter Fehlerbehandlung
    try {
        $debugSteps[] = "Lade bootstrap.php";
        require_once __DIR__ . '/includes/bootstrap.php';
        
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new Exception('Datenbankverbindung nicht verfügbar');
        }
        
        // Test der Verbindung
        $pdo->query('SELECT 1');
        $debugSteps[] = "Datenbankverbindung erfolgreich";
        
    } catch (PDOException $e) {
        $debugSteps[] = "Datenbankfehler: " . $e->getMessage();
        throw new Exception('Datenbankfehler: ' . $e->getMessage());
    }

    // Prüfe ob dvds Tabelle existiert
    try {
        $pdo->query("DESCRIBE dvds");
        $debugSteps[] = "dvds Tabelle existiert";
    } catch (PDOException $e) {
        throw new Exception('dvds Tabelle nicht gefunden');
    }

    // Prüfe ob users Tabelle existiert (optional)
    $usersTableExists = false;
    try {
        $pdo->query("DESCRIBE users");
        $usersTableExists = true;
        $debugSteps[] = "users Tabelle gefunden";
    } catch (PDOException $e) {
        $debugSteps[] = "users Tabelle nicht gefunden (ok)";
    }

    // Film-Daten laden - mit oder ohne users JOIN je nach Verfügbarkeit
    try {
        if ($usersTableExists) {
            $sql = "
                SELECT d.*, 
                       u.email as added_by_user,
                       d.genre as genres_list,
                       (SELECT COUNT(*) FROM dvds WHERE boxset_parent = d.id) as boxset_children_count
                FROM dvds d 
                LEFT JOIN users u ON d.user_id = u.id 
                WHERE d.id = ?
            ";
        } else {
            $sql = "
                SELECT d.*, 
                       d.genre as genres_list,
                       (SELECT COUNT(*) FROM dvds WHERE boxset_parent = d.id) as boxset_children_count
                FROM dvds d 
                WHERE d.id = ?
            ";
        }
        
        $debugSteps[] = "SQL Query vorbereitet";
        
        $stmt = $pdo->prepare($sql);
        if (!$stmt) {
            throw new Exception('SQL-Statement konnte nicht vorbereitet werden');
        }
        
        $stmt->execute([$id]);
        $dvd = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$dvd) {
            http_response_code(404);
            throw new Exception("Film mit ID $id nicht gefunden");
        }
        
        // Falls users Tabelle nicht existiert, setze Fallback
        if (!$usersTableExists) {
            $dvd['added_by_user'] = null;
        }
        
        $debugSteps[] = "Film geladen: " . $dvd['title'];
        
    } catch (PDOException $e) {
        $debugSteps[] = "SQL Fehler: " . $e->getMessage();
        throw new Exception('Fehler beim Laden der Film-Daten: ' . $e->getMessage());
    }

    // BoxSet-Kinder laden falls vorhanden
    $boxsetChildren = [];
    if (isset($dvd['boxset_children_count']) && $dvd['boxset_children_count'] > 0) {
        try {
            $childStmt = $pdo->prepare("
                SELECT id, title, year, cover_id 
                FROM dvds 
                WHERE boxset_parent = ? 
                ORDER BY title ASC
            ");
            $childStmt->execute([$id]);
            $boxsetChildren = $childStmt->fetchAll(PDO::FETCH_ASSOC);
            
            $debugSteps[] = "BoxSet-Kinder geladen: " . count($boxsetChildren);
            
        } catch (PDOException $e) {
            $debugSteps[] = "Warnung: BoxSet-Kinder Fehler: " . $e->getMessage();
            // Nicht kritisch - weiter machen
        }
    }

    // film-view.php laden - mit Fehlerbehandlung
    $filmViewPath = __DIR__ . '/partials/film-view.php';
    
    if (!file_exists($filmViewPath)) {
        throw new Exception('film-view.php nicht gefunden: ' . $filmViewPath);
    }
    
    if (!is_readable($filmViewPath)) {
        throw new Exception('film-view.php nicht lesbar: ' . $filmViewPath);
    }

    $debugSteps[] = "film-view.php gefunden und lesbar";

    // Benötigte Funktionen definieren falls sie nicht existieren
    if (!function_exists('findCoverImage')) {
        function findCoverImage(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string
        {
            if (empty($coverId)) return $fallback;
            
            $extensions = ['.jpg', '.jpeg', '.png', '.webp'];
            foreach ($extensions as $ext) {
                $file = "{$folder}/{$coverId}{$suffix}{$ext}";
                if (file_exists($file)) {
                    return $file;
                }
            }
            return $fallback;
        }
        $debugSteps[] = "findCoverImage() Funktion definiert";
    }

    if (!function_exists('getActorsByDvdId')) {
        function getActorsByDvdId(PDO $pdo, int $dvdId): array
        {
            try {
                $stmt = $pdo->prepare("
                    SELECT a.first_name, a.last_name, fa.role 
                    FROM actors a 
                    JOIN film_actor fa ON a.id = fa.actor_id 
                    WHERE fa.film_id = ? 
                    ORDER BY fa.sort_order ASC, a.last_name ASC, a.first_name ASC
                ");
                $stmt->execute([$dvdId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("getActorsByDvdId error: " . $e->getMessage());
                return [];
            }
        }
        $debugSteps[] = "getActorsByDvdId() Funktion definiert";
    }

    if (!function_exists('formatRuntime')) {
        function formatRuntime(?int $minutes): string
        {
            if (!$minutes || $minutes <= 0) return '';
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
        }
        $debugSteps[] = "formatRuntime() Funktion definiert";
    }

    // Neuer Output Buffer für film-view.php
    ob_start();
    
    try {
        // Sichere Variablen für include setzen
        $film_id = (int)$dvd['id'];
        $film_title = $dvd['title'] ?? 'Unbekannt';
        
        // Include - einfacher Ansatz
        include $filmViewPath;
        
        $filmViewOutput = ob_get_clean();
        
        if (empty($filmViewOutput)) {
            throw new Exception('film-view.php hat keinen Output produziert');
        }
        
        $debugSteps[] = "film-view.php erfolgreich geladen (" . strlen($filmViewOutput) . " Bytes)";
        
        // XSS-Schutz für Film-ID
        $safeFilmId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        
        // Wrapper für AJAX-Content mit verbesserter Struktur
        echo '<div class="film-detail-content fade-in" data-film-id="' . $safeFilmId . '" data-loaded="' . time() . '">';
        echo $filmViewOutput;
        echo '</div>';
        
    } catch (Throwable $e) {
        ob_end_clean();
        $debugSteps[] = "film-view.php Fehler: " . $e->getMessage();
        throw new Exception('Fehler beim Laden von film-view.php: ' . $e->getMessage());
    }

    $debugSteps[] = "Erfolgreich abgeschlossen";

} catch (Throwable $e) {
    // Buffer komplett leeren bei Fehler
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fehler loggen mit allen Debug-Schritten
    error_log("=== FILM-FRAGMENT FATAL ERROR ===");
    error_log("Error: " . $e->getMessage());
    error_log("Type: " . get_class($e));
    error_log("File: " . $e->getFile() . ":" . $e->getLine());
    error_log("Debug Steps: " . implode(" -> ", $debugSteps));
    error_log("Request: " . print_r($_REQUEST, true));
    error_log("===================================");
    
    // Benutzerfreundliche Fehlermeldung
    http_response_code(500);
    echo '<div class="alert alert-danger">';
    echo '<h4><i class="bi bi-exclamation-triangle"></i> Fehler beim Laden</h4>';
    echo '<p>Der Film konnte nicht geladen werden. Details wurden protokolliert.</p>';
    
    // Zeige Debug-Informationen wenn möglich
    echo '<details><summary>Debug-Schritte</summary>';
    echo '<ol>';
    foreach ($debugSteps as $step) {
        echo '<li>' . htmlspecialchars($step) . '</li>';
    }
    echo '</ol>';
    echo '<p><strong>Fehler:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '</details>';
    echo '</div>';
}
?>