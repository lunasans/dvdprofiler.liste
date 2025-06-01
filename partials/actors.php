<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../functions.php';

$letter = strtoupper($_GET['letter'] ?? '');
$param = $letter ? [$letter . '%'] : [];

$sql = "
    SELECT firstname, lastname, COUNT(*) AS film_count 
      FROM actors 
     WHERE lastname LIKE ? OR ? = ''
  GROUP BY firstname, lastname
  ORDER BY lastname, firstname
";
$stmt = $pdo->prepare($sql);
$stmt->execute($param ?: ['%', '']);
$actors = $stmt->fetchAll();
?>

<h2>Schauspieler<?= $letter ? ' mit "' . htmlspecialchars($letter) . '"' : '' ?></h2>

<div class="actor-filter">
  <?php foreach (range('A', 'Z') as $l): ?>
    <a class="route-link <?= $l === $letter ? 'active' : '' ?>" href="?page=actors&letter=<?= $l ?>"><?= $l ?></a>
  <?php endforeach; ?>
</div>

<ul class="actor-list-full">
  <?php foreach ($actors as $a): ?>
    <li>
      <a href="?page=films-by-actor&name=<?= urlencode($a['firstname'] . ' ' . $a['lastname']) ?>" class="route-link">
        <?= htmlspecialchars($a['firstname'] . ' ' . $a['lastname']) ?> (<?= $a['film_count'] ?>)
      </a>
    </li>
  <?php endforeach; ?>
</ul>