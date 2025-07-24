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
        
        // Benötigte Funktionen definieren falls nicht vorhanden
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
        error_log("Film-Fragment: DVD-Daten: " . json_encode(array_keys($dvd)));
        error_log("Film-Fragment: DVD-ID Feld: " . var_export($dvd['id'] ?? 'NICHT_GESETZT', true));
        
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
    error_log("Film-Fragment Request: " . json_encode($_GET));
    error_log("Film-Fragment Memory: " . memory_get_usage(true) . " bytes");
    
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
            <i class="' . $errorIcon . '"></i>
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
                <button onclick="location.reload()" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Seite neu laden
                </button>
                <button onclick="closeDetail()" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Schließen
                </button>
                <button onclick="goBack()" class="btn btn-outline">
                    <i class="bi bi-arrow-left"></i> Zurück zur Liste
                </button>
            </div>
          </div>';
}

// JavaScript für Enhanced UX mit besserer Fehlerbehandlung
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Film-Fragment JavaScript geladen');
    
    try {
        // Smooth scroll to top of detail panel
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
        
        // Performance monitoring
        const loadTime = performance.now();
        console.log(`Film-Fragment loaded in ${loadTime.toFixed(2)}ms`);
        
        // Memory cleanup für alte Film-Details
        const oldDetails = document.querySelectorAll('.film-detail-content[data-loaded]');
        oldDetails.forEach(detail => {
            const loadedTime = parseInt(detail.dataset.loaded);
            const now = Math.floor(Date.now() / 1000);
            if (now - loadedTime > 300) { // 5 Minuten alt
                detail.remove();
            }
        });
        
    } catch (error) {
        console.error('Film-Fragment JavaScript Error:', error);
    }
});

function closeDetail() {
    try {
        const detailContainer = document.getElementById('detail-container');
        if (detailContainer) {
            // Fade out animation
            detailContainer.style.opacity = '0';
            
            setTimeout(() => {
                detailContainer.innerHTML = `
                    <div class="detail-placeholder">
                        <i class="bi bi-film"></i>
                        <p>Wählen Sie einen Film aus der Liste, um Details anzuzeigen.</p>
                    </div>
                `;
                detailContainer.style.opacity = '1';
            }, 200);
            
            // URL cleanup
            if (history.replaceState) {
                history.replaceState(null, '', window.location.pathname);
            }
        }
    } catch (error) {
        console.error('Error closing detail:', error);
        // Fallback ohne Animation
        location.reload();
    }
}

function goBack() {
    try {
        if (history.length > 1) {
            history.back();
        } else {
            window.location.href = '/';
        }
    } catch (error) {
        console.error('Error going back:', error);
        window.location.href = '/';
    }
}

// Global error handler für AJAX
window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled Promise Rejection:', event.reason);
    event.preventDefault();
});
</script>

<style>
.error-message {
    background: var(--glass-bg-strong);
    border-radius: var(--radius-lg);
    padding: var(--space-xl);
    text-align: center;
    color: var(--text-glass);
    backdrop-filter: blur(10px);
    max-width: 500px;
    margin: var(--space-xl) auto;
    animation: fadeIn 0.3s ease-out;
}

.error-message.server-error {
    border: 1px solid rgba(239, 68, 68, 0.3);
}

.error-message.client-error {
    border: 1px solid rgba(245, 158, 11, 0.3);
}

.error-message i {
    font-size: 3rem;
    margin-bottom: var(--space-lg);
    display: block;
}

.error-message.server-error i {
    color: #ef4444;
}

.error-message.client-error i {
    color: #f59e0b;
}

.error-details {
    margin: var(--space-lg) 0;
    text-align: left;
    background: var(--glass-bg);
    padding: var(--space-md);
    border-radius: var(--radius-md);
    border: 1px solid var(--glass-border);
}

.error-details summary {
    cursor: pointer;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: var(--space-sm);
    user-select: none;
}

.error-details summary:hover {
    color: var(--primary-color);
}

.error-details p {
    margin: var(--space-xs) 0;
    font-size: 0.85rem;
    font-family: 'Courier New', monospace;
    word-break: break-all;
}

.error-actions {
    display: flex;
    gap: var(--space-md);
    justify-content: center;
    margin-top: var(--space-lg);
    flex-wrap: wrap;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 768px) {
    .error-actions {
        flex-direction: column;
    }
    
    .error-message {
        margin: var(--space-md);
        padding: var(--space-lg);
    }
}
</style>