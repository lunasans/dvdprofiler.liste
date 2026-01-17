<?php
/**
 * DVD Profiler Signatur-Banner Generator
 * Generiert dynamische Banner mit Film-Covern
 * Liest Einstellungen aus settings-Tabelle
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Fehlerausgabe unterdrücken
error_reporting(0);
ini_set('display_errors', 0);

// Prüfe ob Banner aktiviert
if (getSetting('signature_enabled', '1') != '1') {
    header('HTTP/1.1 403 Forbidden');
    exit('Signatur-Banner sind deaktiviert');
}

// Banner-Typ (1, 2, oder 3)
$type = isset($_GET['type']) ? (int)$_GET['type'] : 1;
$type = max(1, min(3, $type));

// Prüfe ob dieser Typ aktiviert ist
if (getSetting("signature_enable_type{$type}", '1') != '1') {
    header('HTTP/1.1 403 Forbidden');
    exit("Banner-Typ {$type} ist deaktiviert");
}

// Cache-Parameter aus Settings
$cacheTime = (int)getSetting('signature_cache_time', '3600');
$cacheFile = __DIR__ . "/cache/signature_type{$type}.png";

// Cache leeren wenn angefordert
if (isset($_GET['clear_cache'])) {
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
    exit('Cache geleert');
}

// Prüfe Cache
if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTime) {
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=' . $cacheTime);
    readfile($cacheFile);
    exit;
}

// Banner-Dimensionen (fest 600x100)
$width = 600;
$height = 100;

// Erstelle Bild
$img = imagecreatetruecolor($width, $height);
imagesavealpha($img, true);

// Farben
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
$glass_bg = imagecolorallocatealpha($img, 30, 30, 40, 25);
$glass_border = imagecolorallocatealpha($img, 255, 255, 255, 100);
$text_white = imagecolorallocate($img, 228, 228, 231);
$text_muted = imagecolorallocate($img, 161, 161, 170);
$accent = imagecolorallocate($img, 102, 126, 234);

// Hintergrund transparent
imagefill($img, 0, 0, $transparent);

// Einstellungen laden
$filmCount = (int)getSetting('signature_film_count', '10');
$filmSource = getSetting('signature_film_source', 'newest');
$showTitle = getSetting('signature_show_title', '1') == '1';
$showYear = getSetting('signature_show_year', '1') == '1';
$showRating = getSetting('signature_show_rating', '0') == '1';

// SQL-Query basierend auf Film-Quelle
try {
    switch ($filmSource) {
        case 'newest_release':
            $orderBy = 'year DESC, created_at DESC';
            break;
        case 'best_rated':
            // Annahme: es gibt ein rating Feld oder TMDb-Integration
            $orderBy = 'created_at DESC'; // Fallback
            break;
        case 'random':
            $orderBy = 'RAND()';
            break;
        case 'newest':
        default:
            $orderBy = 'created_at DESC';
            break;
    }
    
    $stmt = $pdo->prepare("
        SELECT id, title, year, cover_id, created_at
        FROM dvds 
        WHERE deleted = 0 
        ORDER BY {$orderBy}
        LIMIT ?
    ");
    $stmt->execute([$filmCount]);
    $films = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sammlung-Stats
    $statsStmt = $pdo->query("SELECT COUNT(*) as total FROM dvds WHERE deleted = 0");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    $totalFilms = $stats['total'] ?? 0;
    
} catch (PDOException $e) {
    $films = [];
    $totalFilms = 0;
}

// Hilfsfunktionen
function drawGlassBackground($img, $x, $y, $w, $h, $bg, $border) {
    imagefilledrectangle($img, $x + 2, $y, $x + $w - 2, $y + $h, $bg);
    imagefilledrectangle($img, $x, $y + 2, $x + $w, $y + $h - 2, $bg);
    imagerectangle($img, $x, $y, $x + $w, $y + $h, $border);
}

function loadCover($coverId, $targetWidth, $targetHeight) {
    if (empty($coverId)) return null;
    
    $extensions = ['.jpg', '.jpeg', '.png'];
    foreach ($extensions as $ext) {
        $file = __DIR__ . "/cover/{$coverId}f{$ext}";
        if (file_exists($file)) {
            $info = getimagesize($file);
            if (!$info) continue;
            
            switch ($info[2]) {
                case IMAGETYPE_JPEG:
                    $src = imagecreatefromjpeg($file);
                    break;
                case IMAGETYPE_PNG:
                    $src = imagecreatefrompng($file);
                    break;
                default:
                    continue 2;
            }
            
            if (!$src) continue;
            
            $dst = imagecreatetruecolor($targetWidth, $targetHeight);
            imagesavealpha($dst, true);
            imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
            
            imagecopyresampled(
                $dst, $src,
                0, 0, 0, 0,
                $targetWidth, $targetHeight,
                imagesx($src), imagesy($src)
            );
            
            imagedestroy($src);
            return $dst;
        }
    }
    
    return null;
}

// ============================================
// VARIANTE 1: Cover Grid
// ============================================
if ($type === 1) {
    drawGlassBackground($img, 0, 0, $width - 1, $height - 1, $glass_bg, $glass_border);
    
    $coverWidth = 55;
    $coverHeight = 80;
    $startX = 10;
    $startY = 10;
    $gap = 2;
    
    foreach ($films as $i => $film) {
        $x = $startX + ($i * ($coverWidth + $gap));
        
        $cover = loadCover($film['cover_id'], $coverWidth, $coverHeight);
        
        if ($cover) {
            imagecopy($img, $cover, $x, $startY, 0, 0, $coverWidth, $coverHeight);
            imagedestroy($cover);
        } else {
            imagefilledrectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_bg);
            imagerectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_border);
        }
    }
}

// ============================================
// VARIANTE 2: Cover + Stats
// ============================================
elseif ($type === 2) {
    drawGlassBackground($img, 0, 0, $width - 1, $height - 1, $glass_bg, $glass_border);
    
    // Header
    imagestring($img, 4, 10, 8, "DVD Profiler Liste", $text_white);
    imagestring($img, 3, 150, 10, "{$totalFilms} Filme", $accent);
    imagestring($img, 3, 250, 10, "{$filmCount} Neueste:", $text_muted);
    
    // Trennlinie
    imageline($img, 10, 25, $width - 10, 25, $glass_border);
    
    // Cover
    $coverWidth = 50;
    $coverHeight = 65;
    $startX = 15;
    $startY = 32;
    $gap = 3;
    
    foreach ($films as $i => $film) {
        $x = $startX + ($i * ($coverWidth + $gap));
        
        $cover = loadCover($film['cover_id'], $coverWidth, $coverHeight);
        
        if ($cover) {
            imagecopy($img, $cover, $x, $startY, 0, 0, $coverWidth, $coverHeight);
            imagedestroy($cover);
        } else {
            imagefilledrectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_bg);
            imagerectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_border);
        }
    }
}

// ============================================
// VARIANTE 3: Compact Liste
// ============================================
elseif ($type === 3) {
    drawGlassBackground($img, 0, 0, $width - 1, $height - 1, $glass_bg, $glass_border);
    
    $coverWidth = 55;
    $coverHeight = 40;
    $startX = 10;
    $gap = 5;
    
    foreach ($films as $i => $film) {
        $row = floor($i / 5);
        $col = $i % 5;
        
        $x = $startX + ($col * ($coverWidth + $gap + 50));
        $y = 10 + ($row * ($coverHeight + 5));
        
        $cover = loadCover($film['cover_id'], $coverWidth, $coverHeight);
        
        if ($cover) {
            imagecopy($img, $cover, $x, $y, 0, 0, $coverWidth, $coverHeight);
            imagedestroy($cover);
        } else {
            imagefilledrectangle($img, $x, $y, $x + $coverWidth, $y + $coverHeight, $glass_bg);
            imagerectangle($img, $x, $y, $x + $coverWidth, $y + $coverHeight, $glass_border);
        }
        
        // Optionale Texte
        if ($showTitle) {
            $title = mb_substr($film['title'], 0, 10) . '...';
            imagestring($img, 2, $x + $coverWidth + 3, $y + 5, $title, $text_muted);
        }
        
        if ($showYear) {
            imagestring($img, 2, $x + $coverWidth + 3, $y + 20, $film['year'], $text_muted);
        }
    }
}

// Speichere Cache
if (!is_dir(__DIR__ . '/cache')) {
    mkdir(__DIR__ . '/cache', 0755, true);
}

$quality = (int)getSetting('signature_quality', '9');
imagepng($img, $cacheFile, $quality);

// Ausgabe
header('Content-Type: image/png');
header('Cache-Control: public, max-age=' . $cacheTime);
imagepng($img, null, $quality);

// Cleanup
imagedestroy($img);