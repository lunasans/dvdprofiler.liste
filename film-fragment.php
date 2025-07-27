<?php
/**
 * Film-Fragment - Vollständig migriert auf neues Core-System
 * Version: 1.4.7+ - Core Integration
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

try {
    // Core-System laden
    require_once __DIR__ . '/includes/bootstrap.php';
    
    // Application-Instance abrufen
    $app = \DVDProfiler\Core\Application::getInstance();
    $database = $app->getDatabase();
    $security = $app->getSecurity();
    $validation = new \DVDProfiler\Core\Validation();
    
    // Sicherheitsheader über Core-System setzen
    $security->setSecurityHeaders();
    
    // ID-Validierung mit neuer Validation-Klasse
    $validator = $validation::make($_GET, [
        'id' => 'required|integer|min:1'
    ]);
    
    if ($validator->hasErrors()) {
        http_response_code(400);
        $errorMsg = implode(', ', $validator->getErrors()['id'] ?? ['Ungültige Film-ID']);
        throw new InvalidArgumentException($errorMsg);
    }
    
    $id = $validator->getValidatedData()['id'];
    
    // Rate-Limiting für Film-Details (verhindert Spam)
    $clientIP = $security::getClientIP();
    if (!$app->checkRateLimit("film_detail_{$clientIP}", 60, 60)) {
        http_response_code(429);
        throw new Exception('Zu viele Anfragen. Bitte warten Sie eine Minute.');
    }
    
    // Output Buffer Management
    while (ob_get_level()) {
        ob_end_clean();
    }
    ob_start();
    
    // Legacy-PDO für bestehende Funktionen (Backward Compatibility)
    $pdo = $database->getPDO();
    
} catch (Exception $e) {
    // Fehler-Response direkt ausgeben
    http_response_code($e instanceof InvalidArgumentException ? 400 : 500);
    
    $errorClass = $e instanceof InvalidArgumentException ? 'client-error' : 'server-error';
    $errorIcon = $e instanceof InvalidArgumentException ? 'bi-exclamation-circle' : 'bi-exclamation-triangle';
    $safeErrorMsg = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    
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
            <h2>System-Fehler</h2>
            <p><strong>Fehler:</strong> ' . $safeErrorMsg . '</p>
        </div>
    </div>';
    exit;
}

try {
    // Hilfsfunktionen definieren (falls nicht vorhanden)
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
    
    if (!function_exists('formatRuntime')) {
        function formatRuntime(?int $minutes): string {
            if (!$minutes) return '';
            $h = intdiv($minutes, 60);
            $m = $minutes % 60;
            return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
        }
    }
    
    if (!function_exists('formatDate')) {
        function formatDate(?string $date): string {
            if (!$date) return '';
            return date('d.m.Y', strtotime($date));
        }
    }
    
    if (!function_exists('generateStarRating')) {
        function generateStarRating(float $rating): string {
            $html = '';
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $rating) {
                    $html .= '<i class="bi bi-star-fill"></i>';
                } elseif ($i - 0.5 <= $rating) {
                    $html .= '<i class="bi bi-star-half"></i>';
                } else {
                    $html .= '<i class="bi bi-star"></i>';
                }
            }
            return $html;
        }
    }

    // Film-Daten laden mit Core Database-System
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
        throw new Exception("Film mit ID {$id} nicht gefunden");
    }
    
    // Debug-Logging (nur im Development)
    if ($app->getSettings()->get('environment') === 'development') {
        error_log("[DVDProfiler:INFO] Film-Fragment: Film geladen - ID: {$id}, Titel: " . $dvd['title']);
    }
    
    // Schauspieler laden (Core Database)
    $actors = $database->fetchAll("
        SELECT a.first_name, a.last_name, fa.role 
        FROM film_actor fa 
        JOIN actors a ON fa.actor_id = a.id 
        WHERE fa.film_id = ?
        ORDER BY fa.sort_order ASC, a.last_name ASC
    ", [$id]);
    
    // BoxSet-Kinder laden (Core Database)
    $boxsetChildren = [];
    if ($dvd['boxset_children_count'] > 0) {
        $boxsetChildren = $database->fetchAll("
            SELECT id, title, year, poster_url, cover_id, genre, runtime, rating_age
            FROM dvds 
            WHERE boxset_parent = ? 
            ORDER BY year ASC, title ASC
        ", [$id]);
    }
    
    // BoxSet-Parent laden (falls dieser Film zu einem BoxSet gehört)
    $boxsetParent = null;
    if (!empty($dvd['boxset_parent'])) {
        $boxsetParent = $database->fetchRow("
            SELECT id, title, year 
            FROM dvds 
            WHERE id = ?
        ", [$dvd['boxset_parent']]);
    }
    
    // Bewertungen laden (mit Existenz-Check)
    $averageRating = 0;
    $ratingCount = 0;
    $userRating = 0;
    $userHasRated = false;
    
    if ($database->tableExists('user_ratings')) {
        // Durchschnittsbewertung
        $ratingData = $database->fetchRow("
            SELECT AVG(rating) as avg_rating, COUNT(*) as count 
            FROM user_ratings 
            WHERE film_id = ?
        ", [$id]);
        
        if ($ratingData) {
            $averageRating = round((float)($ratingData['avg_rating'] ?? 0), 1);
            $ratingCount = (int)($ratingData['count'] ?? 0);
        }
        
        // User-spezifische Bewertung (falls eingeloggt)
        if (isset($_SESSION['user_id'])) {
            $userRatingData = $database->fetchRow("
                SELECT rating FROM user_ratings 
                WHERE film_id = ? AND user_id = ?
            ", [$id, $_SESSION['user_id']]);
            
            if ($userRatingData) {
                $userRating = (float)$userRatingData['rating'];
                $userHasRated = true;
            }
        }
    }
    
    // User-Watch-Status laden (falls eingeloggt)
    $isWatched = false;
    if (isset($_SESSION['user_id']) && $database->tableExists('user_watched')) {
        $watchedData = $database->fetchRow("
            SELECT 1 FROM user_watched 
            WHERE user_id = ? AND film_id = ?
        ", [$_SESSION['user_id'], $id]);
        
        $isWatched = (bool)$watchedData;
    }
    
    // Aufrufe erhöhen (asynchron für bessere Performance)
    $database->execute("
        UPDATE dvds 
        SET view_count = COALESCE(view_count, 0) + 1,
            last_viewed = NOW()
        WHERE id = ?
    ", [$id]);
    
    // Film-Statistiken vorbereiten
    $filmStats = [
        'view_count' => (int)($dvd['view_count'] ?? 0) + 1, // +1 für aktuellen Aufruf
        'created_at' => $dvd['created_at'] ?? null,
        'updated_at' => $dvd['updated_at'] ?? null,
        'last_viewed' => date('Y-m-d H:i:s'), // Aktueller Timestamp
    ];
    
    // film-view.php einbinden
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
        // Include film-view.php - alle Variablen sind verfügbar
        include $filmViewPath;
        $filmViewOutput = ob_get_clean();
        
        if (empty($filmViewOutput)) {
            throw new Exception('film-view.php hat keinen Output produziert');
        }
        
        // Sichere Film-ID für HTML
        $safeFilmId = htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8');
        
        // Wrapper für AJAX-Content mit Core-System Timestamps
        echo '<div class="film-detail-content fade-in" 
                   data-film-id="' . $safeFilmId . '" 
                   data-loaded="' . time() . '"
                   data-version="' . \DVDProfiler\Core\Utils::env('DVDPROFILER_VERSION', '1.4.7') . '">';
        echo $filmViewOutput;
        echo '</div>';
        
        // Performance-Logging (Development)
        if ($app->getSettings()->get('environment') === 'development') {
            $loadTime = microtime(true) - ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
            $memoryUsage = memory_get_peak_usage(true);
            error_log(sprintf(
                '[DVDProfiler:INFO] Film-Fragment Performance: %.3fs, %s memory, Film-ID: %d',
                $loadTime,
                \DVDProfiler\Core\Utils::formatBytes($memoryUsage),
                $id
            ));
        }
        
    } catch (Throwable $e) {
        ob_end_clean();
        throw new Exception('Fehler beim Laden von film-view.php: ' . $e->getMessage());
    }

} catch (Throwable $e) {
    // Buffer leeren bei Fehler
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fehler über error_log ausgeben
    error_log("[DVDProfiler:ERROR] Film-Fragment ERROR: " . $e->getMessage());
    if ($app->getSettings()->get('environment') === 'development') {
        error_log("[DVDProfiler:ERROR] Film-Fragment Stack: " . $e->getTraceAsString());
    }
    
    // HTTP Status setzen
    if ($e instanceof InvalidArgumentException) {
        http_response_code(400);
    } elseif (strpos($e->getMessage(), 'nicht gefunden') !== false) {
        http_response_code(404);
    } else {
        http_response_code(500);
    }
    
    // Benutzerfreundliche Fehlermeldung über Core-Utils
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
    console.log('🎬 Film-Fragment (Core-System) geladen');
    
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
        
        // Watch-Status Toggle (falls eingeloggt)
        const watchedToggle = document.querySelector('[data-action="toggle-watched"]');
        if (watchedToggle) {
            watchedToggle.addEventListener('click', function(e) {
                e.preventDefault();
                
                const filmId = this.dataset.filmId;
                if (!filmId) return;
                
                fetch('api/toggle-watched.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({film_id: parseInt(filmId)})
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        throw new Error(data.error);
                    }
                    
                    // UI aktualisieren
                    const icon = this.querySelector('i');
                    const text = this.querySelector('.btn-text');
                    
                    if (data.watched) {
                        icon.className = 'bi bi-check-circle-fill';
                        text.textContent = 'Gesehen';
                        this.classList.add('watched');
                    } else {
                        icon.className = 'bi bi-check-circle';
                        text.textContent = 'Als gesehen markieren';
                        this.classList.remove('watched');
                    }
                    
                    // Toast-Benachrichtigung
                    if (window.showToast) {
                        window.showToast(data.message, 'success');
                    }
                })
                .catch(error => {
                    console.error('Fehler beim Markieren:', error);
                    if (window.showToast) {
                        window.showToast('Fehler beim Markieren des Films', 'error');
                    }
                });
            });
        }
        
    } catch (error) {
        console.error('❌ Film-Fragment JavaScript-Fehler:', error);
    }
});

// Globale Funktionen für Fehlerbehandlung
function closeDetail() {
    const detailPanel = document.getElementById('detail-container');
    if (detailPanel) {
        detailPanel.innerHTML = `
            <div class="detail-placeholder">
                <i class="bi bi-film"></i>
                <p>Wählen Sie einen Film aus der Liste, um Details anzuzeigen.</p>
            </div>
        `;
    }
}

function goBack() {
    if (history.length > 1) {
        history.back();
    } else {
        window.location.href = 'index.php';
    }
}
</script>