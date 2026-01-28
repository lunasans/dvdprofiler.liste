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

// Banner-Dimensionen (fest 800x120)
$width = 800;
$height = 120;

// Erstelle Bild
$img = imagecreatetruecolor($width, $height);
imagesavealpha($img, true);

// Farben - Heller Glaseffekt mit Gradient
$transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
$glass_bg_top = imagecolorallocatealpha($img, 240, 245, 255, 15);  // Noch transparenter
$glass_bg_bottom = imagecolorallocatealpha($img, 220, 230, 245, 30);  
$glass_border = imagecolorallocatealpha($img, 200, 210, 230, 50);  // Dunklerer Rahmen für bessere Sichtbarkeit
$glass_shadow = imagecolorallocatealpha($img, 0, 0, 0, 40);  // Dezenter Schatten
$cover_shadow = imagecolorallocatealpha($img, 0, 0, 0, 60);  // Schatten für Cover
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
function imagefilledroundedrectangle($img, $x1, $y1, $x2, $y2, $radius, $color) {
    // Hauptrechteck (ohne Ecken)
    imagefilledrectangle($img, $x1 + $radius, $y1, $x2 - $radius, $y2, $color);
    imagefilledrectangle($img, $x1, $y1 + $radius, $x2, $y2 - $radius, $color);
    
    // Vier abgerundete Ecken
    imagefilledellipse($img, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, $color); // Oben links
    imagefilledellipse($img, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, $color); // Oben rechts
    imagefilledellipse($img, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, $color); // Unten links
    imagefilledellipse($img, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, $color); // Unten rechts
}

function drawGlassBackground($img, $x, $y, $w, $h, $bg_top, $bg_bottom, $border, $shadow) {
    $radius = 12; // Abrundungs-Radius
    
    // Gradient von oben nach unten mit abgerundeten Ecken zeichnen
    for ($i = 0; $i < $h; $i++) {
        $ratio = $i / $h;
        // Interpoliere zwischen top und bottom Farbe
        $r = imagecolorsforindex($img, $bg_top)['red'] * (1 - $ratio) + imagecolorsforindex($img, $bg_bottom)['red'] * $ratio;
        $g = imagecolorsforindex($img, $bg_top)['green'] * (1 - $ratio) + imagecolorsforindex($img, $bg_bottom)['green'] * $ratio;
        $b = imagecolorsforindex($img, $bg_top)['blue'] * (1 - $ratio) + imagecolorsforindex($img, $bg_bottom)['blue'] * $ratio;
        $a = imagecolorsforindex($img, $bg_top)['alpha'] * (1 - $ratio) + imagecolorsforindex($img, $bg_bottom)['alpha'] * $ratio;
        
        $color = imagecolorallocatealpha($img, $r, $g, $b, $a);
        
        // Berechne Breite der Linie für abgerundete Ecken
        $lineStart = $x;
        $lineEnd = $x + $w;
        
        // Oben: erste Radius-Pixel
        if ($i < $radius) {
            $offset = $radius - sqrt($radius * $radius - ($radius - $i) * ($radius - $i));
            $lineStart = $x + $offset;
            $lineEnd = $x + $w - $offset;
        }
        // Unten: letzte Radius-Pixel
        elseif ($i > $h - $radius) {
            $offset = $radius - sqrt($radius * $radius - ($i - ($h - $radius)) * ($i - ($h - $radius)));
            $lineStart = $x + $offset;
            $lineEnd = $x + $w - $offset;
        }
        
        imageline($img, $lineStart, $y + $i, $lineEnd, $y + $i, $color);
    }
    
    // Weicher Schatten unten mit leicht abgerundeten Ecken
    for ($i = 0; $i < 3; $i++) {
        $shadowAlpha = 60 + ($i * 20);
        $shadowColor = imagecolorallocatealpha($img, 0, 0, 0, $shadowAlpha);
        $offset = $i * 2;
        imageline($img, $x + $radius + $offset, $y + $h + $i, $x + $w - $radius - $offset, $y + $h + $i, $shadowColor);
    }
    
    // Abgerundeter Rahmen (approximiert durch viele Linien)
    imagesetthickness($img, 1);
    for ($angle = 0; $angle < 360; $angle += 2) {
        $rad = deg2rad($angle);
        
        // Oben links
        if ($angle >= 180 && $angle < 270) {
            $px = $x + $radius + cos($rad) * $radius;
            $py = $y + $radius + sin($rad) * $radius;
            imagesetpixel($img, $px, $py, $border);
        }
        // Oben rechts
        if ($angle >= 270 && $angle < 360) {
            $px = $x + $w - $radius + cos($rad) * $radius;
            $py = $y + $radius + sin($rad) * $radius;
            imagesetpixel($img, $px, $py, $border);
        }
        // Unten links
        if ($angle >= 90 && $angle < 180) {
            $px = $x + $radius + cos($rad) * $radius;
            $py = $y + $h - $radius + sin($rad) * $radius;
            imagesetpixel($img, $px, $py, $border);
        }
        // Unten rechts
        if ($angle >= 0 && $angle < 90) {
            $px = $x + $w - $radius + cos($rad) * $radius;
            $py = $y + $h - $radius + sin($rad) * $radius;
            imagesetpixel($img, $px, $py, $border);
        }
    }
    
    // Gerade Linien zwischen den Ecken
    imageline($img, $x + $radius, $y, $x + $w - $radius, $y, $border); // Oben
    imageline($img, $x + $radius, $y + $h, $x + $w - $radius, $y + $h, $border); // Unten
    imageline($img, $x, $y + $radius, $x, $y + $h - $radius, $border); // Links
    imageline($img, $x + $w, $y + $radius, $x + $w, $y + $h - $radius, $border); // Rechts
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
            
            // Abgerundete Ecken hinzufügen (6px Radius für Cover)
            $radius = 6;
            $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            
            // Ecken maskieren
            for ($x = 0; $x < $radius; $x++) {
                for ($y = 0; $y < $radius; $y++) {
                    // Oben links
                    if (($x - $radius) * ($x - $radius) + ($y - $radius) * ($y - $radius) > $radius * $radius) {
                        imagesetpixel($dst, $x, $y, $transparent);
                    }
                    // Oben rechts
                    if (($targetWidth - 1 - $x - $radius) * ($targetWidth - 1 - $x - $radius) + ($y - $radius) * ($y - $radius) > $radius * $radius) {
                        imagesetpixel($dst, $targetWidth - 1 - $x, $y, $transparent);
                    }
                    // Unten links
                    if (($x - $radius) * ($x - $radius) + ($targetHeight - 1 - $y - $radius) * ($targetHeight - 1 - $y - $radius) > $radius * $radius) {
                        imagesetpixel($dst, $x, $targetHeight - 1 - $y, $transparent);
                    }
                    // Unten rechts
                    if (($targetWidth - 1 - $x - $radius) * ($targetWidth - 1 - $x - $radius) + ($targetHeight - 1 - $y - $radius) * ($targetHeight - 1 - $y - $radius) > $radius * $radius) {
                        imagesetpixel($dst, $targetWidth - 1 - $x, $targetHeight - 1 - $y, $transparent);
                    }
                }
            }
            
            imagedestroy($src);
            return $dst;
        }
    }
    
    return null;
}

// ============================================
// VARIANTE 1: Cover Grid mit Statistik
// ============================================
if ($type === 1) {
    drawGlassBackground($img, 0, 0, $width - 1, $height - 1, $glass_bg_top, $glass_bg_bottom, $glass_border, $glass_shadow);
    
    // Linke Statistik-Box
    $statsBoxWidth = 90;
    $statsBoxHeight = 100;
    $statsBoxX = 15;
    $statsBoxY = 10;
    
    // Statistik-Box Hintergrund (leicht dunkler)
    $statsBoxBg = imagecolorallocatealpha($img, 200, 210, 230, 40);
    imagefilledroundedrectangle($img, $statsBoxX, $statsBoxY, $statsBoxX + $statsBoxWidth, $statsBoxY + $statsBoxHeight, 8, $statsBoxBg);
    
    // Rahmen um Statistik-Box
    $statsBoxBorder = imagecolorallocatealpha($img, 180, 190, 210, 60);
    for ($angle = 0; $angle < 360; $angle += 2) {
        $rad = deg2rad($angle);
        $radius = 8;
        // Approximiere abgerundeten Rahmen
        if ($angle >= 180 && $angle < 270) {
            $px = $statsBoxX + $radius + cos($rad) * $radius;
            $py = $statsBoxY + $radius + sin($rad) * $radius;
            imagesetpixel($img, $px, $py, $statsBoxBorder);
        }
        if ($angle >= 270 && $angle < 360) {
            $px = $statsBoxX + $statsBoxWidth - $radius + cos($rad) * $radius;
            $py = $statsBoxY + $radius + sin($rad) * $radius;
            imagesetpixel($img, $px, $py, $statsBoxBorder);
        }
        if ($angle >= 90 && $angle < 180) {
            $px = $statsBoxX + $radius + cos($rad) * $radius;
            $py = $statsBoxY + $statsBoxHeight - $radius + sin($rad) * $radius;
            imagesetpixel($img, $px, $py, $statsBoxBorder);
        }
        if ($angle >= 0 && $angle < 90) {
            $px = $statsBoxX + $statsBoxWidth - $radius + cos($rad) * $radius;
            $py = $statsBoxY + $statsBoxHeight - $radius + sin($rad) * $radius;
            imagesetpixel($img, $px, $py, $statsBoxBorder);
        }
    }
    imageline($img, $statsBoxX + 8, $statsBoxY, $statsBoxX + $statsBoxWidth - 8, $statsBoxY, $statsBoxBorder);
    imageline($img, $statsBoxX + 8, $statsBoxY + $statsBoxHeight, $statsBoxX + $statsBoxWidth - 8, $statsBoxY + $statsBoxHeight, $statsBoxBorder);
    imageline($img, $statsBoxX, $statsBoxY + 8, $statsBoxX, $statsBoxY + $statsBoxHeight - 8, $statsBoxBorder);
    imageline($img, $statsBoxX + $statsBoxWidth, $statsBoxY + 8, $statsBoxX + $statsBoxWidth, $statsBoxY + $statsBoxHeight - 8, $statsBoxBorder);
    
    // Statistik-Text
    imagestring($img, 3, $statsBoxX + 8, $statsBoxY + 15, "Filme", $text_white);
    imagestring($img, 3, $statsBoxX + 8, $statsBoxY + 30, "gesamt:", $text_muted);
    
    // Große Zahl
    imagestring($img, 5, $statsBoxX + 20, $statsBoxY + 55, (string)$totalFilms, $accent);
    
    // Cover versetzt nach rechts
    $coverWidth = 65;
    $coverHeight = 100;
    $startX = $statsBoxX + $statsBoxWidth + 15;  // Nach der Statistik-Box
    $startY = 10;
    $gap = 4;
    
    foreach ($films as $i => $film) {
        $x = $startX + ($i * ($coverWidth + $gap));
        
        // Prüfe ob noch Platz ist
        if ($x + $coverWidth > $width - 15) break;
        
        $cover = loadCover($film['cover_id'], $coverWidth, $coverHeight);
        
        if ($cover) {
            // Schatten unter dem Cover (für bessere Sichtbarkeit)
            for ($s = 0; $s < 3; $s++) {
                $shadowAlpha = 80 - ($s * 15);
                $shadowCol = imagecolorallocatealpha($img, 0, 0, 0, $shadowAlpha);
                imagerectangle($img, $x + $s, $startY + $s, $x + $coverWidth + $s, $startY + $coverHeight + $s, $shadowCol);
            }
            
            imagecopy($img, $cover, $x, $startY, 0, 0, $coverWidth, $coverHeight);
            imagedestroy($cover);
        } else {
            imagefilledrectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_bg_top);
            imagerectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_border);
        }
    }
}

// ============================================
// VARIANTE 2: Cover + Stats
// ============================================
elseif ($type === 2) {
    drawGlassBackground($img, 0, 0, $width - 1, $height - 1, $glass_bg_top, $glass_bg_bottom, $glass_border, $glass_shadow);
    
    // Header
    imagestring($img, 4, 15, 8, "DVD Profiler Liste", $text_white);
    imagestring($img, 3, 180, 10, "{$totalFilms} Filme", $accent);
    imagestring($img, 3, 290, 10, "{$filmCount} Neueste:", $text_muted);
    
    // Trennlinie
    imageline($img, 15, 28, $width - 15, 28, $glass_border);
    
    // Cover - größer für bessere Sichtbarkeit
    $coverWidth = 60;
    $coverHeight = 85;
    $startX = 20;
    $startY = 32;
    $gap = 5;
    
    foreach ($films as $i => $film) {
        $x = $startX + ($i * ($coverWidth + $gap));
        
        // Prüfe ob noch Platz ist
        if ($x + $coverWidth > $width - 15) break;
        
        $cover = loadCover($film['cover_id'], $coverWidth, $coverHeight);
        
        if ($cover) {
            // Schatten unter dem Cover
            for ($s = 0; $s < 3; $s++) {
                $shadowAlpha = 80 - ($s * 15);
                $shadowCol = imagecolorallocatealpha($img, 0, 0, 0, $shadowAlpha);
                imagerectangle($img, $x + $s, $startY + $s, $x + $coverWidth + $s, $startY + $coverHeight + $s, $shadowCol);
            }
            
            imagecopy($img, $cover, $x, $startY, 0, 0, $coverWidth, $coverHeight);
            imagedestroy($cover);
        } else {
            imagefilledrectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_bg_top);
            imagerectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_border);
        }
    }
}

// ============================================
// VARIANTE 3: Compact Liste
// ============================================
elseif ($type === 3) {
    drawGlassBackground($img, 0, 0, $width - 1, $height - 1, $glass_bg_top, $glass_bg_bottom, $glass_border, $glass_shadow);
    
    $coverWidth = 60;
    $coverHeight = 90;
    $startX = 15;
    $startY = 15;
    $gap = 8;
    
    foreach ($films as $i => $film) {
        $x = $startX + ($i * ($coverWidth + $gap));
        
        // Begrenze auf Breite (bei 10 Filmen würden sonst manche abgeschnitten)
        if ($x + $coverWidth > $width - 15) break;
        
        $cover = loadCover($film['cover_id'], $coverWidth, $coverHeight);
        
        if ($cover) {
            // Schatten unter dem Cover
            for ($s = 0; $s < 3; $s++) {
                $shadowAlpha = 80 - ($s * 15);
                $shadowCol = imagecolorallocatealpha($img, 0, 0, 0, $shadowAlpha);
                imagerectangle($img, $x + $s, $startY + $s, $x + $coverWidth + $s, $startY + $coverHeight + $s, $shadowCol);
            }
            
            imagecopy($img, $cover, $x, $startY, 0, 0, $coverWidth, $coverHeight);
            imagedestroy($cover);
        } else {
            imagefilledrectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_bg_top);
            imagerectangle($img, $x, $startY, $x + $coverWidth, $startY + $coverHeight, $glass_border);
        }
        
        // Optionale Texte unter dem Cover
        if ($showTitle && $showYear) {
            $title = mb_substr($film['title'], 0, 8);
            imagestring($img, 2, $x, $startY + $coverHeight + 2, $title, $text_white);
        } elseif ($showYear) {
            imagestring($img, 2, $x + 15, $startY + $coverHeight + 2, $film['year'], $text_muted);
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