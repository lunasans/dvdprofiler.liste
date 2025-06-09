<?php

function findCoverImage(string $coverId, string $suffix = 'f', string $folder = 'cover', string $fallback = 'cover/placeholder.png'): string
{
    $extensions = ['.jpg', '.jpeg', '.png'];
    foreach ($extensions as $ext) {
        $file = "{$folder}/{$coverId}{$suffix}{$ext}";
        if (file_exists($file)) {
            return $file;
        }
    }
    return $fallback;
}

function getActorsByDvdId(PDO $pdo, int $dvdId): array
{
    $stmt = $pdo->prepare("SELECT firstname, lastname, role FROM actors WHERE dvd_id = ?");
    $stmt->execute([$dvdId]);
    return $stmt->fetchAll();
}

function formatRuntime(?int $minutes): string
{
    if (!$minutes) return '';
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
}

function getChildDvds(PDO $pdo, string $parentId): array
{
    $stmt = $pdo->prepare("SELECT * FROM dvds WHERE boxset_parent = ? ORDER BY title");
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

function renderFilmCard(array $dvd, bool $isChild = false): string
{
    $cover = htmlspecialchars(findCoverImage($dvd['cover_id'], 'f'));
    $title = htmlspecialchars($dvd['title']);
    $year = (int)$dvd['year'];
    $genre = htmlspecialchars($dvd['genre'] ?? '');
    $id = (int)$dvd['id'];

    $hasChildren = !$isChild && !empty(getChildDvds($GLOBALS['pdo'], $id));

    return '
    <div class="dvd' . ($isChild ? ' child-dvd' : '') . '" data-dvd-id="' . $id . '">
      <div class="cover-area">
        <img src="' . $cover . '" alt="Cover">
      </div>
      <div class="dvd-details">
        <h2><a href="#" class="toggle-detail" data-id="' . $id . '">' . $title . ' (' . $year . ')</a></h2>
        <p><strong>Genre:</strong> ' . $genre . '</p>'
        . ($hasChildren ? '<button class="boxset-toggle">► Box-Inhalte anzeigen</button>' : '') .
      '</div>
    </div>';
}
