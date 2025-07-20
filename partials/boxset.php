<?php

// Alle Hauptfilme, die BoxSet-Parent anderer Filme sind
$boxParents = $pdo->query("
  SELECT * FROM dvds 
   WHERE id IN (SELECT DISTINCT boxset_parent FROM dvds WHERE boxset_parent IS NOT NULL)
   ORDER BY title
")->fetchAll();

?>

<div class="static-page">
  <h2>BoxSets</h2>

  <?php if (empty($boxParents)): ?>
    <p>Keine BoxSets gefunden.</p>
  <?php else: ?>
    <?php foreach ($boxParents as $parent): ?>
      <div class="boxset-overview">
        <h3><?= htmlspecialchars($parent['title']) ?> (<?= $parent['year'] ?>)</h3>
        <div class="boxset-children">
          <ul>
            <?php foreach (getChildDvds($pdo, $parent['id']) as $child): ?>
              <li>
                <a href="#" class="toggle-detail" data-id="<?= $child['id'] ?>">
                  <?= htmlspecialchars($child['title']) ?> (<?= $child['year'] ?>)
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
