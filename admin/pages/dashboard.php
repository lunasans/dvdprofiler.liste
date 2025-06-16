<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// Statistiken abrufen
$stmtFilms = $pdo->query("SELECT COUNT(*) FROM dvds");
$stmtActors = $pdo->query("SELECT COUNT(*) FROM actors");
$stmtUsers = $pdo->query("SELECT COUNT(*) FROM users");

// Letzte Filme
$latestFilms = $pdo->query("
    SELECT title, year 
    FROM dvds 
    ORDER BY created_at DESC 
    LIMIT 5
")->fetchAll();
?>

<div class="w-100 py-4 px-4">
    <h2 class="mb-4">📊 Dashboard</h2>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card border-primary">
                <div class="card-body">
                    <h5 class="card-title">🎬 Filme</h5>
                    <p class="card-text fs-3"><?= $stmtFilms->fetchColumn() ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-secondary">
                <div class="card-body">
                    <h5 class="card-title">🧑‍🎤 Schauspieler</h5>
                    <p class="card-text fs-3"><?= $stmtActors->fetchColumn() ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body">
                    <h5 class="card-title">👤 Benutzer</h5>
                    <p class="card-text fs-3"><?= $stmtUsers->fetchColumn() ?></p>
                </div>
            </div>
        </div>
    </div>

    <h4>🆕 Neuste Filme</h4>
    <ul class="list-group">
        <?php foreach ($latestFilms as $film): ?>
            <li class="list-group-item">
                <?= htmlspecialchars($film['title']) ?> (<?= $film['year'] ?>)
            </li>
        <?php endforeach; ?>
    </ul>
</div>
