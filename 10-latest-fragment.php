<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/functions.php';

$stmt = $pdo->query("SELECT * FROM dvds ORDER BY id DESC LIMIT 15");
$latest = $stmt->fetchAll();

echo "<h2 style='margin-bottom:1rem;'>Neu hinzugefügt</h2>";
echo "<div class='latest-list'>";

foreach ($latest as $dvd) {
    $title    = htmlspecialchars($dvd['title']);
    $year     = (int)$dvd['year'];
    $id       = (int)$dvd['id'];
    $cover    = findCoverImage($dvd['cover_id'], 'f');
    $runtime  = (int)$dvd['runtime'];
    $genres   = htmlspecialchars($dvd['genre']);

    $tooltip = " $genres\n {$runtime} Min";

echo "
<div class='latest-card'>
    <a href='#' class='toggle-detail' data-id='$id'>
        <div class='card-image'>
            <img src='$cover' alt='Cover von $title'>
            <div class='hover-info'>
                 $genres<br> {$runtime} Min
            </div>
        </div>
        <div class='latest-title'>
            $title <span class='year'>($year)</span>
        </div>
    </a>
</div>
"; }