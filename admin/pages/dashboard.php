<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$stmtFilms = $pdo->query("SELECT COUNT(*) FROM dvds")->fetchColumn();
$stmtActors = $pdo->query("SELECT COUNT(*) FROM actors");
$stmtUsers = $pdo->query("SELECT COUNT(*) FROM users");
$latestFilms = $pdo->query("SELECT title, year FROM dvds ORDER BY created_at DESC LIMIT 5")->fetchAll();
?>

<h2 class="mb-4">Dashboard</h2>

<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card text-bg-primary">
            <div class="card-body">
                <h5 class="card-title">🎬 Filme</h5>
                <p class="card-text fs-4"><?= $stmtFilms ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-bg-secondary">
            <div class="card-body">
                <h5 class="card-title">🎭 Schauspieler</h5>
                <p class="card-text fs-4"><?= $stmtActors->fetchColumn() ?></p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-bg-success">
            <div class="card-body">
                <h5 class="card-title">👤 Benutzer</h5>
                <p class="card-text fs-4"><?= $stmtUsers->fetchColumn() ?></p>
            </div>
        </div>
    </div>
</div>

<h4>🆕 Neuste Filme</h4>
<ul class="list-group">
    <?php foreach ($latestFilms as $film): ?>
        <li class="list-group-item"><?= htmlspecialchars($film['title']) ?> (<?= $film['year'] ?>)</li>
    <?php endforeach; ?>
</ul>
