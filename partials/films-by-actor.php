<?php

$name = trim($_GET['name'] ?? '');
if (!$name) {
    echo "<p>Kein Schauspieler angegeben.</p>";
    return;
}

[$first, $last] = explode(' ', $name, 2) + [1 => ''];

$sql = "
    SELECT d.*
      FROM dvds d
      JOIN actors a ON a.dvd_id = d.id
     WHERE a.firstname = :first AND a.lastname = :last
  GROUP BY d.id
  ORDER BY d.title
";
$stmt = $pdo->prepare($sql);
$stmt->execute(['first' => $first, 'last' => $last]);
$dvds = $stmt->fetchAll();
?>

<h2>🎬 Filme mit <?= htmlspecialchars($name) ?></h2>

<?php if (empty($dvds)): ?>
  <p>Keine Filme gefunden.</p>
<?php else: ?>
  <ul class="actor-film-list">
    <?php foreach ($dvds as $dvd): ?>
      <li>
        <a href="?id=<?= $dvd['id'] ?>" class="toggle-detail">
          <?= htmlspecialchars($dvd['title']) ?> (<?= $dvd['year'] ?>)
        </a>
      </li>
    <?php endforeach; ?>
  </ul>
<?php endif; ?>