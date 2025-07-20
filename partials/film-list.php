<?php
require_once __DIR__ . '/../includes/bootstrap.php';

ini_set('display_errors', '1');
error_reporting(E_ALL);

$search   = trim($_GET['q'] ?? '');
$type     = trim($_GET['type'] ?? '');
$page     = max(1, (int)($_GET['seite'] ?? 1));
$perPage  = 10;
$offset   = ($page - 1) * $perPage;

// CollectionTypes laden
$types = $pdo->query("SELECT DISTINCT collection_type FROM dvds WHERE collection_type IS NOT NULL ORDER BY collection_type")->fetchAll(PDO::FETCH_COLUMN);

// WHERE-Filter - ORIGINAL OHNE BOXSET FILTER (erstmal)
$where = ['boxset_parent IS NULL']; // Nur Parent-Filme und Einzelfilme
$params = [];

if ($search !== '') {
    $where[] = "title LIKE :search";
    $params['search'] = "%{$search}%";
}
if ($type !== '') {
    $where[] = "collection_type = :type";
    $params['type'] = $type;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Gesamtanzahl
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM dvds $whereSql");
foreach ($params as $k => $v) {
    $countStmt->bindValue($k, $v);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();
$totalPages = (int)ceil($total / $perPage);

// Alle Hauptfilme holen (inkl. Boxsets) - ORIGINAL
$sql = "SELECT * FROM dvds $whereSql ORDER BY title LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue('offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mainFilms = $stmt->fetchAll();

// KEINE buildQuery() Funktion hier - wird schon in bootstrap.php definiert
?>

<!-- Tabs für CollectionType -->
<div class="tabs-wrapper">
  <ul class="tabs">
    <li class="<?= $type === '' ? 'active' : '' ?>">
      <a href="?<?= buildQuery(['type' => '', 'seite' => 1]) ?>">Alle</a>
    </li>
    <?php foreach ($types as $t): ?>
      <li class="<?= $type === $t ? 'active' : '' ?>">
        <a href="?<?= buildQuery(['type' => $t, 'seite' => 1]) ?>"><?= htmlspecialchars($t) ?></a>
      </li>
    <?php endforeach; ?>
  </ul>
  <div class="view-toggle">
    <button onclick="setViewMode('grid')"><i class="bi bi-grid-fill"></i></button>
    <button onclick="setViewMode('list')"><i class="bi bi-list-ol"></i></button>
  </div>
</div>

<!-- Film-Liste -->
<div class="film-list">
  <?php foreach ($mainFilms as $film): ?>
    <?= renderFilmCard($film) ?>
    <?php $children = getChildDvds($pdo, $film['id']); ?>
    <?php if (!empty($children)): ?>
      <div class="boxset-children">
        <?php foreach ($children as $child): ?>
          <?= renderFilmCard($child, true) ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
  <nav class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= buildQuery(['seite' => $page - 1]) ?>">«</a>
    <?php endif; ?>
    <?php if ($page > 3): ?>
      <a href="?<?= buildQuery(['seite' => 1]) ?>">1</a>
      <?php if ($page > 4): ?><span class="dots">…</span><?php endif; ?>
    <?php endif; ?>
    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?<?= buildQuery(['seite' => $i]) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($page < $totalPages - 2): ?>
      <?php if ($page < $totalPages - 3): ?><span class="dots">…</span><?php endif; ?>
      <a href="?<?= buildQuery(['seite' => $totalPages]) ?>"><?= $totalPages ?></a>
    <?php endif; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?<?= buildQuery(['seite' => $page + 1]) ?>">»</a>
    <?php endif; ?>
  </nav>
<?php endif; ?>