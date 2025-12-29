<?php
/**
 * DVD Profiler Liste - Visitor Counter (Database Version)
 * Zählt eindeutige Besucher pro Tag mittels Cookie und Datenbank
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.8
 */

// Session starten – aber nur wenn noch nicht aktiv
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialisiere Besucherzahl
$visits = 0;
$dailyVisits = 0;

try {
    // Prüfe ob PDO-Verbindung existiert
    if (!isset($pdo) || !($pdo instanceof PDO)) {
        throw new Exception('Keine Datenbankverbindung verfügbar');
    }
    
    // Prüfe ob counter Tabelle existiert
    $stmt = $pdo->query("SHOW TABLES LIKE 'counter'");
    $tableExists = $stmt->fetch() !== false;
    
    if (!$tableExists) {
        // Fallback: Erstelle Tabelle automatisch
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS counter (
                id INT PRIMARY KEY DEFAULT 1,
                visits BIGINT UNSIGNED NOT NULL DEFAULT 0,
                last_visit_date DATE NULL,
                daily_visits BIGINT UNSIGNED NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Initialen Eintrag erstellen
        $pdo->exec("INSERT IGNORE INTO counter (id, visits, last_visit_date, daily_visits) VALUES (1, 0, CURDATE(), 0)");
        
        error_log('Counter: Tabelle wurde automatisch erstellt');
    }
    
    // Hole aktuellen Counter-Stand
    $stmt = $pdo->query("SELECT visits, daily_visits, last_visit_date FROM counter WHERE id = 1");
    $counter = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$counter) {
        // Falls kein Eintrag existiert, erstelle einen
        $pdo->exec("INSERT INTO counter (id, visits, last_visit_date, daily_visits) VALUES (1, 0, CURDATE(), 0)");
        $visits = 0;
        $dailyVisits = 0;
    } else {
        $visits = (int)$counter['visits'];
        $dailyVisits = (int)$counter['daily_visits'];
        $lastVisitDate = $counter['last_visit_date'];
        
        // Prüfe ob ein neuer Tag begonnen hat
        $today = date('Y-m-d');
        if ($lastVisitDate !== $today) {
            // Neuer Tag: Setze daily_visits zurück
            $pdo->exec("UPDATE counter SET daily_visits = 0, last_visit_date = CURDATE() WHERE id = 1");
            $dailyVisits = 0;
            error_log("Counter: Neuer Tag erkannt, täglicher Counter wurde zurückgesetzt");
        }
    }
    
    // Prüfe ob dieser Besucher schon gezählt wurde (Cookie-basiert)
    $cookieName = 'visitor_counted';
    $cookieExpire = time() + 86400; // 24 Stunden
    
    if (!isset($_COOKIE[$cookieName])) {
        // Neuer Besucher (oder Cookie abgelaufen)
        try {
            // Erhöhe beide Counter in einer Transaktion
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE counter 
                SET visits = visits + 1,
                    daily_visits = daily_visits + 1,
                    updated_at = NOW()
                WHERE id = 1
            ");
            $stmt->execute();
            
            $pdo->commit();
            
            // Setze Cookie
            setcookie($cookieName, '1', $cookieExpire, '/', '', true, true); // secure & httponly
            
            // Aktualisiere lokale Variablen
            $visits++;
            $dailyVisits++;
            
            // Optional: Logge den Besuch (nur für Debugging)
            // error_log("Counter: Neuer Besucher gezählt. Gesamt: $visits, Heute: $dailyVisits");
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Counter: Fehler beim Inkrementieren - " . $e->getMessage());
        }
    }
    
} catch (PDOException $e) {
    error_log('Counter: Datenbank-Fehler - ' . $e->getMessage());
    
    // Fallback auf Textdatei wenn DB nicht verfügbar
    $counterFile = __DIR__ . '/../counter.txt';
    if (file_exists($counterFile)) {
        $visits = (int)file_get_contents($counterFile);
    }
    
} catch (Exception $e) {
    error_log('Counter: Allgemeiner Fehler - ' . $e->getMessage());
}

// Stelle sicher dass $visits mindestens 0 ist
$visits = max(0, $visits);
$dailyVisits = max(0, $dailyVisits);

// Mache Variablen global verfügbar
$GLOBALS['total_visits'] = $visits;
$GLOBALS['daily_visits'] = $dailyVisits;