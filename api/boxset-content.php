<?php
/**
 * API Endpoint für BoxSet-Inhalte
 * Datei: api/boxset-content.php
 */

declare(strict_types=1);

// Bootstrap einbinden
require_once '../includes/bootstrap.php';

// JSON Header setzen
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// CORS für AJAX (optional, falls nötig)
// header('Access-Control-Allow-Origin: *');

// Input validation
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid BoxSet ID provided'
    ]);
    exit;
}

$boxsetId = (int)$_GET['id'];

// Zusätzliche Validierung
if ($boxsetId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'BoxSet ID must be a positive integer'
    ]);
    exit;
}

try {
    // Zuerst prüfen ob der Parent existiert
    $parentCheck = $pdo->prepare("SELECT id, title FROM dvds WHERE id = ?");
    $parentCheck->execute([$boxsetId]);
    $parent = $parentCheck->fetch(PDO::FETCH_ASSOC);
    
    if (!$parent) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'BoxSet not found'
        ]);
        exit;
    }
    
    // BoxSet-Kinder laden
    $stmt = $pdo->prepare("
        SELECT id, title, year, genre, cover_id, runtime, rating_age, overview 
        FROM dvds 
        WHERE boxset_parent = ? 
        ORDER BY year ASC, title ASC
    ");
    $stmt->execute([$boxsetId]);
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Cover-URLs und formatierte Daten hinzufügen
    foreach ($children as &$child) {
        // Cover URL sicher generieren
        $child['cover_url'] = findCoverImage($child['cover_id'] ?? '', 'f');
        
        // Laufzeit formatieren
        $child['formatted_runtime'] = formatRuntime($child['runtime'] ?? null);
        
        // Rating Age sicher ausgeben
        $child['rating_display'] = !empty($child['rating_age']) ? 
            "FSK {$child['rating_age']}" : '';
        
        // Kurze Übersicht für Tooltip/Hover
        $child['short_overview'] = !empty($child['overview']) ? 
            (strlen($child['overview']) > 100 ? 
                substr($child['overview'], 0, 100) . '...' : 
                $child['overview']) : '';
        
        // Sichere HTML-Ausgabe
        $child['title_safe'] = htmlspecialchars($child['title'], ENT_QUOTES, 'UTF-8');
        $child['genre_safe'] = htmlspecialchars($child['genre'] ?? '', ENT_QUOTES, 'UTF-8');
    }
    
    // Erfolgreiche Antwort
    echo json_encode([
        'success' => true,
        'parent' => [
            'id' => $parent['id'],
            'title' => htmlspecialchars($parent['title'], ENT_QUOTES, 'UTF-8')
        ],
        'films' => $children,
        'count' => count($children),
        'total_runtime' => array_sum(array_column($children, 'runtime')),
        'formatted_total_runtime' => formatRuntime(array_sum(array_column($children, 'runtime')))
    ], JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    // Database Fehler loggen
    error_log("BoxSet content API error for ID {$boxsetId}: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
    
} catch (Exception $e) {
    // Allgemeine Fehler loggen
    error_log("BoxSet content API general error for ID {$boxsetId}: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error'
    ]);
}