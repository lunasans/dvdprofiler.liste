<?php
    
declare(strict_types=1);

// Debug-Modus für Fehlersuche
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('log_errors', '1');


// Output Buffer für saubere Fehlerbehandlung
ob_start();

try {
    // Security Headers für AJAX-Requests
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');

    // Bootstrap laden - mit Fehlerbehandlung
    if (!file_exists(__DIR__ . '/includes/bootstrap.php')) {
        throw new Exception('Bootstrap-Datei nicht gefunden');
    }
    
    require_once __DIR__ . '/includes/bootstrap.php';

    // Verbindung zur Datenbank prüfen
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new Exception('Datenbankverbindung nicht verfügbar');
    }

    // ID aus GET-Parameter holen und validieren
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
        'options' => [
            'min_range' => 1,
            'max_range' => PHP_INT_MAX
        ]
    ]);

    // Debug: ID-Validierung
    if ($id === false || $id === null) {
        error_log("Film-Fragment: Ungültige ID empfangen: " . ($_GET['id'] ?? 'NULL'));
        http_response_code(400);
        echo '<div class="error-message">
                <i class="bi bi-exclamation-triangle"></i>
                <p>Ungültige Film-ID: ' . htmlspecialchars($_GET['id'] ?? 'keine ID') . '</p>
              </div>';
        exit;
    }

    // Debug: Film-ID loggen
    error_log("Film-Fragment: Lade Film mit ID: $id");

    // Film aus Datenbank laden - mit erweiterten Infos
    try {
        $stmt = $pdo->prepare("
            SELECT d.*, u.email as added_by_user 
            FROM dvds d 
            LEFT JOIN users u ON d.user_id = u.id 
            WHERE d.id = ? 
            LIMIT 1
        ");
        
        if (!$stmt) {
            throw new Exception('SQL Statement konnte nicht vorbereitet werden: ' . implode(', ', $pdo->errorInfo()));
        }
        
        $executeResult = $stmt->execute([$id]);
        
        if (!$executeResult) {
            throw new Exception('SQL Execute fehlgeschlagen: ' . implode(', ', $stmt->errorInfo()));
        }
        
        $dvd = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Ergebnis loggen
        if ($dvd) {
            error_log("Film-Fragment: Film gefunden: " . $dvd['title']);
        } else {
            error_log("Film-Fragment: Kein Film mit ID $id gefunden");
        }
        
    } catch (PDOException $e) {
        error_log("Film-Fragment: SQL Fehler: " . $e->getMessage());
        throw new Exception("Datenbankfehler beim Laden des Films: " . $e->getMessage());
    }

    // Film nicht gefunden
    if (!$dvd) {
        error_log("Film-Fragment: Film mit ID $id nicht in Datenbank");
        http_response_code(404);
        echo '<div class="error-message film-not-found">
                <i class="bi bi-film"></i>
                <h3>Film nicht gefunden</h3>
                <p>Der Film mit ID <strong>' . htmlspecialchars((string)$id) . '</strong> wurde nicht gefunden.</p>
                <div class="error-actions">
                    <button onclick="closeDetail()" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Zurück zur Liste
                    </button>
                </div>
              </div>';
        exit;
    }

    // View Counter erhöhen - mit Fehlerbehandlung
    try {
        $updateViews = $pdo->prepare("UPDATE dvds SET view_count = COALESCE(view_count, 0) + 1 WHERE id = ?");
        $updateViews->execute([$id]);
    } catch (PDOException $e) {
        error_log("Film-Fragment: View-Count Update fehlgeschlagen: " . $e->getMessage());
        // Nicht kritisch - weiter machen
    }

    // Last viewed für eingeloggte User - mit Fehlerbehandlung
    if (isset($_SESSION['user_id'])) {
        try {
            // Prüfen ob Tabelle existiert
            $checkTable = $pdo->query("SHOW TABLES LIKE 'user_film_views'");
            if ($checkTable->rowCount() > 0) {
                $updateLastViewed = $pdo->prepare("
                    INSERT INTO user_film_views (user_id, film_id, last_viewed) 
                    VALUES (?, ?, NOW()) 
                    ON DUPLICATE KEY UPDATE last_viewed = NOW()
                ");
                $updateLastViewed->execute([$_SESSION['user_id'], $id]);
            }
        } catch (PDOException $e) {
            error_log("Film-Fragment: Last-Viewed Update fehlgeschlagen: " . $e->getMessage());
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

    // Output Buffer für film-view.php
    ob_start();
    
    try {
        include $filmViewPath;
        $filmViewOutput = ob_get_clean();
        
        if (empty($filmViewOutput)) {
            throw new Exception('film-view.php hat keinen Output produziert');
        }
        
        // Wrapper für AJAX-Content
        echo '<div class="film-detail-content fade-in" data-film-id="' . $id . '">';
        echo $filmViewOutput;
        echo '</div>';
        
    } catch (Exception $e) {
        ob_end_clean();
        throw new Exception('Fehler beim Laden von film-view.php: ' . $e->getMessage());
    }

    // Debug: Erfolgreich geladen
    error_log("Film-Fragment: Erfolgreich geladen für Film-ID: $id");

} catch (Exception $e) {
    // Buffer leeren bei Fehler
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Fehler loggen
    error_log("Film-Fragment FATAL ERROR: " . $e->getMessage());
    error_log("Film-Fragment Stack: " . $e->getTraceAsString());
    
    // HTTP Status setzen
    http_response_code(500);
    
    // Benutzerfreundliche Fehlermeldung
    echo '<div class="error-message server-error">
            <i class="bi bi-exclamation-triangle"></i>
            <h3>Serverfehler</h3>
            <p>Die Film-Details konnten nicht geladen werden.</p>
            <details class="error-details">
                <summary>Technische Details (für Entwickler)</summary>
                <p><strong>Fehler:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
                <p><strong>Film-ID:</strong> ' . htmlspecialchars($_GET['id'] ?? 'keine') . '</p>
                <p><strong>Zeit:</strong> ' . date('Y-m-d H:i:s') . '</p>
                <p><strong>IP:</strong> ' . ($_SERVER['REMOTE_ADDR'] ?? 'unbekannt') . '</p>
            </details>
            <div class="error-actions">
                <button onclick="location.reload()" class="btn btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> Seite neu laden
                </button>
                <button onclick="closeDetail()" class="btn btn-secondary">
                    <i class="bi bi-x"></i> Schließen
                </button>
            </div>
          </div>';
} finally {
    // Output Buffer beenden falls noch aktiv
    if (ob_get_level()) {
        ob_end_flush();
    }
}

// JavaScript für Enhanced UX
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('Film-Fragment JavaScript geladen');
    
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
});

function closeDetail() {
    const detailContainer = document.getElementById('detail-container');
    if (detailContainer) {
        detailContainer.innerHTML = `
            <div class="detail-placeholder">
                <i class="bi bi-film"></i>
                <p>Wählen Sie einen Film aus der Liste, um Details anzuzeigen.</p>
            </div>
        `;
        
        if (history.replaceState) {
            history.replaceState(null, '', window.location.pathname);
        }
    }
}
</script>

<style>
.error-message.server-error {
    background: var(--glass-bg-strong);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: var(--radius-lg);
    padding: var(--space-xl);
    text-align: center;
    color: var(--text-glass);
    backdrop-filter: blur(10px);
    max-width: 500px;
    margin: var(--space-xl) auto;
}

.error-message.server-error i {
    font-size: 3rem;
    color: #ef4444;
    margin-bottom: var(--space-lg);
    display: block;
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
}

.error-details p {
    margin: var(--space-xs) 0;
    font-size: 0.9rem;
    font-family: monospace;
}

.error-actions {
    display: flex;
    gap: var(--space-md);
    justify-content: center;
    margin-top: var(--space-lg);
}

@media (max-width: 768px) {
    .error-actions {
        flex-direction: column;
    }
}
</style>