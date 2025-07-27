<?php
/**
 * Film-Fragment - Fixed für neues Core-System
 * Kompatibel mit der neuen Database-Klasse
 */

// Sicherheitsheader setzen
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

try {
    // ID-Validierung
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        http_response_code(400);
        throw new InvalidArgumentException('Ungültige Film-ID: ' . ($_GET['id'] ?? 'keine'));
    }

    // Output Buffer Management
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();

    // Core-System laden
    try {
        require_once __DIR__ . '/includes/bootstrap.php';
        
        // Application-Instance abrufen
        $app = \DVDProfiler\Core\Application::getInstance();
        $database = $app->getDatabase();
        
        // Legacy-Kompatibilität: $pdo für bestehende Funktionen
        $pdo = $database->getPDO();
        
        // Test der Verbindung
        $database->query('SELECT 1');
        
    } catch (Exception $e) {
        throw new Exception('Core-System Fehler: ' . $e->getMessage());
    }

    // Hilfsfunktionen definieren (falls nicht vorhanden)
    if (!function_exists('findCoverImage')) {
        function findCoverImage(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string {
            if (empty($coverId)) return $fallback;
            $extensions = ['.jpg', '.jpeg', '.png'];
            foreach ($extensions as $ext) {
                $file = "{$folder}/{$coverId}{$suffix}{$ext}";
                if (file_exists($file)) {
                    return $file;
                }
            }
            return $fallback;
        }
    }
    
    if (!function_exists('getActorsByDvdId')) {
        function getActorsByDvdId(PDO $pdo, int $dvdId): array {
            try {
                $stmt = $pdo->prepare("SELECT first_name, last_name, role FROM film_actor fa JOIN actors a ON fa.actor_id = a.id WHERE fa.film_id = ?");
                $stmt->execute([$dvdId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Actor query error: " . $e->getMessage());
                return [];
            }
        }
    }
    
    if (!function_exists('formatRuntime')) {
        function formatRuntime(?int $minutes): string {
            if (!$minutes) return '';
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
        }
    }

    // Film-Daten laden mit dem neuen Database-System
    try {
        // Verwende die neue Database-Klasse für bessere Performance
        $dvd = $database->fetchRow("
            SELECT d.*, 
                   u.email as added_by_user,
                   d.genre as genres_list,
                   (SELECT COUNT(*) FROM dvds WHERE boxset_parent = d.id) as boxset_children_count
            FROM dvds d 
            LEFT JOIN users u ON d.user_id = u.id 
            WHERE d.id = ?
        ", [$id]);
        
        if (!$dvd) {
            http_response_code(404);
            throw new Exception("Film mit ID $id nicht gefunden");
        }
        
        // Debug-Logging (nur im Development)
        if ($app->getSettings()->get('environment') === 'development') {
            error_log("Film-Fragment: Film geladen - ID: $id, Titel: " . $dvd['title']);
        }
        
    } catch (Exception $e) {
        throw new Exception('Fehler beim Laden der Film-Daten: ' . $e->getMessage());
    }

    // BoxSet-Kinder laden falls vorhanden
    $boxsetChildren = [];
    if ($dvd['boxset_children_count'] > 0) {
        try {
            $boxsetChildren = $database->fetchAll("
                SELECT id, title, year, poster_url 
                FROM dvds 
                WHERE boxset_parent = ? 
                ORDER BY title ASC
            ", [$id]);
            
        } catch (Exception $e) {
            error_log("Warnung: BoxSet-Kinder konnten nicht geladen werden: " . $e->getMessage());
            // Nicht kritisch - weiter machen
        }
    }

    // film-view.php laden
    $filmViewPath = __DIR__ . '/partials/film-view.php';
    
    if (!file_exists($filmViewPath)) {
        throw new Exception('film-view.php nicht gefunden: ' . $filmViewPath);
    }
    
    if (!is_readable($filmViewPath)) {
        throw new Exception('film-view.php nicht lesbar: ' . $filmViewPath);
    }

    // Output Buffer für film-view.php
    ob_start();
    
    try {
        // Include film-view.php - $dvd und $boxsetChildren sind verfügbar
        include $filmViewPath;
        $filmViewOutput = ob_get_clean();
        
        if (empty($filmViewOutput)) {
            throw new Exception('film-view.php hat keinen Output produziert');
        }
        
        // Sichere Film-ID für HTML
        $safeFilmId = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
        
        // Wrapper für AJAX-Content
        echo '<div class="film-detail-content fade-in" data-film-id="' . $safeFilmId . '" data-loaded="' . time() . '">';
        echo $filmViewOutput;
        echo '</div>';
        
    } catch (Throwable $e) {
        ob_end_clean();
        throw new Exception('Fehler beim Laden von film-view.php: ' . $e->getMessage());
    }

} catch (Throwable $e) {
    // Buffer leeren bei Fehler
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fehler loggen
    error_log("Film-Fragment ERROR: " . $e->getMessage());
    error_log("Film-Fragment Stack: " . $e->getTraceAsString());
    
    // HTTP Status setzen
    if ($e instanceof InvalidArgumentException) {
        http_response_code(400);
    } elseif (strpos($e->getMessage(), 'nicht gefunden') !== false) {
        http_response_code(404);
    } else {
        http_response_code(500);
    }
    
    // Benutzerfreundliche Fehlermeldung
    $errorClass = $e instanceof InvalidArgumentException ? 'client-error' : 'server-error';
    $errorIcon = $e instanceof InvalidArgumentException ? 'bi-exclamation-circle' : 'bi-exclamation-triangle';
    $safeErrorMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    $safeFilmId = htmlspecialchars($_GET['id'] ?? 'keine', ENT_QUOTES, 'UTF-8');
    
    echo '<div class="error-container ' . $errorClass . '" style="
        padding: 2rem;
        text-align: center;
        background: var(--glass-bg);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        color: var(--text-glass);
    ">
        <div class="error-content">
            <i class="' . $errorIcon . '" style="font-size: 3rem; margin-bottom: 1rem; color: #dc3545;"></i>
            <h2>Film konnte nicht geladen werden</h2>
            <p><strong>Fehler:</strong> ' . $safeErrorMsg . '</p>
            <p><strong>Film-ID:</strong> ' . $safeFilmId . '</p>
            
            <div class="error-actions" style="margin-top: 2rem; display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                <button onclick="location.reload()" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Seite neu laden
                </button>
                <button onclick="closeDetail()" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Schließen
                </button>
                <button onclick="history.back()" class="btn btn-outline">
                    <i class="bi bi-arrow-left"></i> Zurück
                </button>
            </div>
        </div>
    </div>';
}
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('🎬 Film-Fragment geladen');
    
    try {
        // Smooth scroll to top
        const detailPanel = document.getElementById('detail-container');
        if (detailPanel) {
            detailPanel.scrollTop = 0;
        }
        
        // Focus management für Accessibility
        const firstHeading = document.querySelector('.film-detail-content h2');
        if (firstHeading) {
            firstHeading.setAttribute('tabindex', '-1');
            firstHeading.focus();
        }
        
        // Performance-Info (Development)
        if (console && performance) {
            const loadTime = performance.now();
            console.log(`📊 Film-Fragment geladen in ${loadTime.toFixed(2)}ms`);
        }
        
        // Memory cleanup für alte Details (verhindert Memory-Leaks)
        const oldDetails = document.querySelectorAll('.film-detail-content[data-loaded]');
        const now = Math.floor(Date.now() / 1000);
        
        oldDetails.forEach(detail => {
            const loadedTime = parseInt(detail.dataset.loaded);
            // Entferne Details älter als 5 Minuten
            if (now - loadedTime > 300) {
                detail.remove();
            }
        });
        
    } catch (error) {
        console.error('❌ Film-Fragment JavaScript-Fehler:', error);
    }
});

// Globale Funktionen für Fehlerbehandlung
function goBack() {
    if (history.length > 1) {
        history.back();
    } else {
        window.location.href = 'index.php';
    }
}
</script>