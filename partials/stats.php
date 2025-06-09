<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

// Gesamte Anzahl
$totalFilms = $pdo->query("SELECT COUNT(*) FROM dvds")->fetchColumn();

// Altersfreigabe
$ratingAgeStmt = $pdo->query("
    SELECT rating_age, COUNT(*) AS count 
    FROM dvds 
    WHERE rating_age IS NOT NULL AND rating_age > 0 
    GROUP BY rating_age 
    ORDER BY rating_age ASC
");
$ratingAgeData = $ratingAgeStmt->fetchAll(PDO::FETCH_KEY_PAIR);

//  Durchschnittliche Laufzeit
$avgRuntime = $pdo->query("SELECT ROUND(AVG(runtime)) FROM dvds WHERE runtime > 0")->fetchColumn();

//  Gesamtlaufzeit
$totalRuntime = $pdo->query("SELECT SUM(runtime) FROM dvds WHERE runtime > 0")->fetchColumn();

// Umrechnung:
$minuten = $totalRuntime % 60;
$stunden = floor($totalRuntime / 60);
$tage = floor($totalRuntime / 60 / 24);     // Minuten → Stunden → Tage
$jahre = floor($tage / 365);
$monate = floor(($tage % 365) / 30);

//  Durchschnittliches Erscheinungsjahr
$avgYear = $pdo->query("SELECT ROUND(AVG(year)) FROM dvds WHERE year > 0")->fetchColumn();

//  CollectionType Verteilung
$collectionStmt = $pdo->query("SELECT collection_type, COUNT(*) AS count FROM dvds GROUP BY collection_type ORDER BY count DESC");
$collections = $collectionStmt->fetchAll();

//  Top Genres
$genreStmt = $pdo->query("
    SELECT genre, COUNT(*) AS count
    FROM (
        SELECT SUBSTRING_INDEX(SUBSTRING_INDEX(genre, ',', n.n), ',', -1) AS genre
        FROM dvds
        JOIN (
            SELECT a.N + b.N * 10 + 1 n
            FROM 
              (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
               UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) a
            CROSS JOIN 
              (SELECT 0 AS N UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4
               UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9) b
            ORDER BY n
        ) n
        WHERE n.n <= 1 + LENGTH(genre) - LENGTH(REPLACE(genre, ',', ''))
    ) g
    WHERE genre != ''
    GROUP BY genre
    ORDER BY count DESC
    LIMIT 5
");
$topGenres = $genreStmt->fetchAll();

//  Filme pro Jahr
$yearStmt = $pdo->query("SELECT year, COUNT(*) AS count FROM dvds WHERE year > 0 GROUP BY year ORDER BY year ASC");
$yearData = $yearStmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>


<main style="padding:2rem">
  <h1>Statistik</h1>
<div class="stat-cards">
  <div class="stat-card"><strong>Filme</strong><span><?= $totalFilms ?></span></div>
  <div class="stat-card"><strong>Gesamtlaufzeit</strong><span><?= $stunden ?> h <?= $minuten ?> min</span></div>
  <div class="stat-card"><strong>⌀ Laufzeit</strong><span><?= $avgRuntime ?> Min</span></div>
  <div class="stat-card"><strong>⌀ Jahr</strong><span><?= $avgYear ?></span></div>
</div>


  <h3>Verteilung nach Sammlungstyp</h3>
  <canvas id="collectionChart" height="120"></canvas>

  <h3>Verteilung nach Altersfreigabe</h3>
  <canvas id="ratingChart" height="120"></canvas>

  <h3> Top 5 Genres</h3>
  <canvas id="genreChart" height="120"></canvas>

  <h3> Filme pro Jahr</h3>
  <canvas id="yearChart" height="140"></canvas>
</main>

<script>
( function() {
    const collectionChart = new Chart(document.getElementById('collectionChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($collections, 'collection_type')) ?>,
            datasets: [{
                label: 'Filme',
                data: <?= json_encode(array_column($collections, 'count')) ?>,
                backgroundColor: '#007bff'
            }]
        }
    });

    const genreChart = new Chart(document.getElementById('genreChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($topGenres, 'genre')) ?>,
            datasets: [{
                label: 'Filme',
                data: <?= json_encode(array_column($topGenres, 'count')) ?>,
                backgroundColor: '#28a745'
            }]
        }
    });

    const ratingChart = new Chart(document.getElementById('ratingChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode(array_keys($ratingAgeData)) ?>.map(age => age + '+'),
    datasets: [{
      label: 'Anzahl Filme',
      data: <?= json_encode(array_values($ratingAgeData)) ?>,
      backgroundColor: '#dc3545'
    }]
  },
  options: {
    scales: {
      y: { beginAtZero: true }
    }
  }
});

    const yearChart = new Chart(document.getElementById('yearChart'), {
        type: 'line',
        data: {
            labels: <?= json_encode(array_keys($yearData)) ?>,
            datasets: [{
                label: 'Filme',
                data: <?= json_encode(array_values($yearData)) ?>,
                backgroundColor: 'rgba(255, 193, 7, 0.4)',
                borderColor: '#ffc107',
                fill: true
            }]
        }
    });
})();
</script>
