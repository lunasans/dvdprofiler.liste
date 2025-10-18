<?php
/**
 * Film-Fragment mit robuster Fehlerbehandlung und Memory-Management
 * PRODUCTION VERSION - Ohne Debug-Code, mit verbesserter Stabilität
 */

// Sicherheitsheader setzen
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Cache-Control: no-cache, must-revalidate');

try {
    // ID-Validierung mit strikter Prüfung
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

    // Database connection mit Fehlerbehandlung
    try {
        require_once __DIR__ . '/includes/bootstrap.php';
        
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            throw new Exception('Datenbankverbindung nicht verfügbar');
        }
        
        // Verbindung testen
        $pdo->query('SELECT 1');
        
        // Helper-Funktionen definieren falls nicht vorhanden
        if (!function_exists('findCoverImage')) {
            function findCoverImage(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string {
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
        }
        
        if (!function_exists('getActorsByDvdId')) {
            function getActorsByDvdId(PDO $pdo, int $dvdId): array {
                try {
                    $stmt = $pdo->prepare("
                        SELECT first_name, last_name, role 
                        FROM film_actor fa 
                        JOIN actors a ON fa.actor_id = a.id 
                        WHERE fa.film_id = ? 
                        ORDER BY a.last_name, a.first_name
                    ");
                    $stmt->execute([$dvdId]);
                    return $stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log("Actor query error for film {$dvdId}: " . $e->getMessage());
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
        
        if (!function_exists('formatFileSize')) {
            function formatFileSize(?int $bytes): string {
                if (!$bytes) return '';
                $units = ['B', 'KB', 'MB', 'GB'];
                $factor = floor((strlen((string)$bytes) - 1) / 3);
                return sprintf("%.1f", $bytes / pow(1024, $factor)) . ' ' . $units[$factor];
            }
        }
        
    } catch (PDOException $e) {
        throw new Exception('Datenbankfehler: ' . $e->getMessage());
    }

    // Film-Daten laden mit optimierter Query
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, 
                   u.email as added_by_user,
                   d.genre as genres_list,
                   (SELECT COUNT(*) FROM dvds WHERE boxset_parent = d.id) as boxset_children_count,
                   COALESCE(d.view_count, 0) as view_count
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
        
    } catch (PDOException $e) {
        throw new Exception('Fehler beim Laden der Film-Daten: ' . $e->getMessage());
    }

    // BoxSet-Kinder laden falls vorhanden
    $boxsetChildren = [];
    if ($dvd['boxset_children_count'] > 0) {
        try {
            $childStmt = $pdo->prepare("
                SELECT id, title, year, cover_id, runtime, rating_age
                FROM dvds 
                WHERE boxset_parent = ? 
                ORDER BY year ASC, title ASC
            ");
            $childStmt->execute([$id]);
            $boxsetChildren = $childStmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Warnung: BoxSet-Kinder konnten nicht geladen werden: " . $e->getMessage());
            // Nicht kritisch - weiter machen
        }
    }

    // film-view.php laden mit verbesserter Fehlerbehandlung
    $filmViewPath = __DIR__ . '/partials/film-view.php';
    
    if (!file_exists($filmViewPath)) {
        throw new Exception('Film-Detail-Template nicht gefunden');
    }
    
    if (!is_readable($filmViewPath)) {
        throw new Exception('Film-Detail-Template nicht lesbar');
    }

    // Output Buffer für film-view.php
    $filmViewOutput = '';
    $bufferLevel = ob_get_level();
    
    try {
        ob_start();
        
        // Include mit allen Variablen im Scope
        include $filmViewPath;
        
        $filmViewOutput = ob_get_clean();
        
        if (empty($filmViewOutput)) {
            throw new Exception('Film-Detail-Template hat keinen Output produziert');
        }
        
    } catch (Throwable $e) {
        // Buffer aufräumen bei Fehler
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }
        throw new Exception('Fehler beim Laden des Film-Detail-Templates: ' . $e->getMessage());
    }

    // View-Count erhöhen (non-blocking)
    try {
        $updateViewStmt = $pdo->prepare("UPDATE dvds SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?");
        $updateViewStmt->execute([$id]);
    } catch (PDOException $e) {
        // View-Count-Update ist nicht kritisch
        error_log("View count update error for film {$id}: " . $e->getMessage());
    }

    // Sichere HTML-Ausgabe
    $safeFilmId = htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8');
    $safeTitle = htmlspecialchars($dvd['title'] ?? 'Unbekannter Film', ENT_QUOTES, 'UTF-8');
    
    // Wrapper für AJAX-Content
    echo '<div class="film-detail-content fade-in" data-film-id="' . $safeFilmId . '" data-loaded="' . time() . '" aria-label="Film-Details für ' . $safeTitle . '">';
    echo $filmViewOutput;
    echo '</div>';

} catch (Throwable $e) {
    // Komplette Buffer-Bereinigung bei Fehler
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fehler loggen mit Kontext
    error_log("Film-Fragment ERROR: " . $e->getMessage());
    error_log("Film-Fragment Context: " . json_encode([
        'type' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'film_id' => $_GET['id'] ?? 'keine',
        'memory_usage' => memory_get_usage(true),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]));
    
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
    $isDevelopment = defined('DEBUG') && DEBUG;
    
    echo '<div class="error-message ' . $errorClass . '">';
    echo '<i class="' . $errorIcon . '"></i>';
    echo '<h3>' . ($e instanceof InvalidArgumentException ? 'Ungültige Anfrage' : 'Serverfehler') . '</h3>';
    echo '<p>Die Film-Details konnten nicht geladen werden.</p>';
    
    if ($isDevelopment) {
        echo '<details class="error-details">';
        echo '<summary>Technische Details (Development-Mode)</summary>';
        echo '<p><strong>Fehlertyp:</strong> ' . htmlspecialchars(get_class($e), ENT_QUOTES, 'UTF-8') . '</p>';
        echo '<p><strong>Fehler:</strong> ' . $safeErrorMsg . '</p>';
        echo '<p><strong>Film-ID:</strong> ' . $safeFilmId . '</p>';
        echo '<p><strong>Zeit:</strong> ' . date('Y-m-d H:i:s') . '</p>';
        echo '<p><strong>Memory:</strong> ' . memory_get_usage(true) . ' bytes</p>';
        echo '</details>';
    }
    
    echo '<div class="error-actions">';
    echo '<button onclick="location.reload()" class="btn btn-primary">';
    echo '<i class="bi bi-arrow-clockwise"></i> Seite neu laden';
    echo '</button>';
    echo '<button onclick="closeDetail()" class="btn btn-secondary">';
    echo '<i class="bi bi-x"></i> Schließen';
    echo '</button>';
    echo '<button onclick="goBack()" class="btn btn-outline">';
    echo '<i class="bi bi-arrow-left"></i> Zurück zur Liste';
    echo '</button>';
    echo '</div>';
    echo '</div>';
}

// JavaScript für Enhanced UX (außerhalb try-catch)
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        // Smooth scroll to top of detail panel
        const detailPanel = document.getElementById('detail-container');
        if (detailPanel) {
            detailPanel.scrollTop = 0;
        }
        
        // Focus management für Accessibility
        const firstHeading = document.querySelector('.film-detail-content h2, .film-detail-content h1');
        if (firstHeading) {
            firstHeading.setAttribute('tabindex', '-1');
            firstHeading.focus();
        }
        
        // Memory cleanup für alte Film-Details
        const oldDetails = document.querySelectorAll('.film-detail-content[data-loaded]');
        const currentTime = Math.floor(Date.now() / 1000);
        
        oldDetails.forEach(detail => {
            const loadedTime = parseInt(detail.dataset.loaded || '0');
            // Entferne Details die älter als 5 Minuten sind
            if (currentTime - loadedTime > 300) {
                detail.remove();
            }
        });
        
        // Error handling für AJAX-Calls in film-view.php
        window.addEventListener('unhandledrejection', function(event) {
            console.error('Unhandled promise rejection in film-fragment:', event.reason);
        });
        
    } catch (error) {
        console.error('Film-Fragment JavaScript error:', error);
    }
});

// Helper functions für error actions
function closeDetail() {
    try {
        const detailContainer = document.getElementById('detail-container');
        if (detailContainer) {
            detailContainer.innerHTML = '';
            detailContainer.style.display = 'none';
        }
        
        // Focus zurück zur Liste
        const filmGrid = document.querySelector('.film-grid, .film-list');
        if (filmGrid) {
            filmGrid.focus();
        }
    } catch (error) {
        console.error('Error closing detail:', error);
        // Fallback: Seite neu laden
        location.reload();
    }
}

function goBack() {
    try {
        if (window.history.length > 1) {
            window.history.back();
        } else {
            // Fallback: Zu Hauptseite
            window.location.href = '/';
        }
    } catch (error) {
        console.error('Error going back:', error);
        window.location.href = '/';
    }
}
</script>

<style>
/* Error Message Styles */
.error-message {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border-radius: var(--radius-lg, 16px);
    padding: var(--space-xl, 24px);
    text-align: center;
    color: var(--text-glass, rgba(255, 255, 255, 0.9));
    backdrop-filter: blur(10px);
    max-width: 500px;
    margin: var(--space-xl, 24px) auto;
    animation: fadeIn 0.3s ease-out;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.error-message.server-error {
    border-color: rgba(239, 68, 68, 0.3);
}

.error-message.client-error {
    border-color: rgba(245, 158, 11, 0.3);
}

.error-message i {
    font-size: 3rem;
    margin-bottom: var(--space-lg, 16px);
    display: block;
}

.error-message.server-error i {
    color: #ef4444;
}

.error-message.client-error i {
    color: #f59e0b;
}

.error-message h3 {
    margin: 0 0 var(--space-md, 12px) 0;
    font-size: 1.5rem;
}

.error-message p {
    margin: 0 0 var(--space-lg, 16px) 0;
    opacity: 0.8;
}

.error-details {
    margin: var(--space-lg, 16px) 0;
    text-align: left;
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    padding: var(--space-md, 12px);
    border-radius: var(--radius-md, 8px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.error-details summary {
    cursor: pointer;
    font-weight: 600;
    color: var(--text-white, #ffffff);
    margin-bottom: var(--space-sm, 8px);
    user-select: none;
}

.error-details summary:hover {
    color: var(--primary-color, #3b82f6);
}

.error-details p {
    margin: var(--space-xs, 4px) 0;
    font-size: 0.85rem;
    font-family: 'Courier New', monospace;
    word-break: break-all;
    opacity: 0.9;
}

.error-actions {
    display: flex;
    gap: var(--space-md, 12px);
    justify-content: center;
    margin-top: var(--space-lg, 16px);
    flex-wrap: wrap;
}

.error-actions .btn {
    padding: var(--space-sm, 8px) var(--space-md, 12px);
    border-radius: var(--radius-md, 8px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    color: var(--text-white, #ffffff);
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 0.9rem;
}

.error-actions .btn:hover {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.2));
    transform: translateY(-1px);
}

.error-actions .btn-primary {
    background: var(--primary-color, #3b82f6);
    border-color: var(--primary-color, #3b82f6);
}

.error-actions .btn i {
    font-size: 1rem;
    margin-right: var(--space-xs, 4px);
}

@keyframes fadeIn {
    from { 
        opacity: 0; 
        transform: translateY(20px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0); 
    }
}

@media (max-width: 768px) {
    .error-actions {
        flex-direction: column;
    }
    
    .error-message {
        margin: var(--space-md, 12px);
        padding: var(--space-lg, 16px);
    }
    
    .error-message i {
        font-size: 2.5rem;
    }
}

/* Film Detail Content Animations */
.film-detail-content.fade-in {
    animation: slideInFromRight 0.3s ease-out;
}

@keyframes slideInFromRight {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}
</style>