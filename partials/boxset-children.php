<?php
// partials/boxset-children.php - AJAX Endpoint für BoxSet Children

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/tmdb-helper.php';

$parentId = (int)($_GET['parent_id'] ?? 0);

if ($parentId <= 0) {
    echo json_encode(['error' => 'Invalid parent ID']);
    exit;
}

try {
    // Parent Film laden
    $parentStmt = $pdo->prepare("SELECT title, year FROM dvds WHERE id = ?");
    $parentStmt->execute([$parentId]);
    $parent = $parentStmt->fetch();
    
    if (!$parent) {
        echo json_encode(['error' => 'Parent not found']);
        exit;
    }
    
    // Children laden
    $childrenStmt = $pdo->prepare("SELECT * FROM dvds WHERE boxset_parent = ? ORDER BY title");
    $childrenStmt->execute([$parentId]);
    $children = $childrenStmt->fetchAll();
    
    // Cover-Pfade für Children generieren
    foreach ($children as &$child) {
        $child['cover'] = 'cover/placeholder.png';
        
        if (!empty($child['cover_id'])) {
            $extensions = ['.jpg', '.jpeg', '.png'];
            foreach ($extensions as $ext) {
                $file = __DIR__ . "/../cover/{$child['cover_id']}f{$ext}";
                if (file_exists($file)) {
                    $child['cover'] = "cover/{$child['cover_id']}f{$ext}";
                    break;
                }
            }
        }
        
        // TMDb Rating aus Cache laden
        try {
            $apiKey = getSetting('tmdb_api_key', '');
            if (!empty($apiKey)) {
                $tmdb = new TMDbHelper($apiKey);
                $ratings = $tmdb->getFilmRatings($child['title'], $child['year']);
                
                if ($ratings) {
                    $child['tmdb_rating'] = $ratings['tmdb_rating'];
                    $child['tmdb_votes'] = $ratings['tmdb_votes'];
                } else {
                    $child['tmdb_rating'] = null;
                    $child['tmdb_votes'] = null;
                }
            } else {
                $child['tmdb_rating'] = null;
                $child['tmdb_votes'] = null;
            }
        } catch (Exception $e) {
            error_log('Rating load error for film ' . $child['id'] . ': ' . $e->getMessage());
            $child['tmdb_rating'] = null;
            $child['tmdb_votes'] = null;
        }
    }
    
    echo json_encode([
        'parent_title' => $parent['title'] . ' (' . $parent['year'] . ')',
        'children' => $children
    ]);
    
} catch (Exception $e) {
    error_log('BoxSet children error: ' . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}