<?php
// Minimale, sichere Version von partials/film-list.php

// Bootstrap ist bereits geladen durch index.php, nur sicherstellen dass functions da ist
if (!function_exists('renderFilmCard')) {
    // Temporäre renderFilmCard Funktion falls functions.php nicht geladen
    function renderFilmCard(array $dvd, bool $isChild = false): string {
        $title = htmlspecialchars($dvd['title'] ?? 'Unbekannt');
        $year = (int)($dvd['year'] ?? 0);
        $genre = htmlspecialchars($dvd['genre'] ?? 'Unbekannt');
        $id = (int)($dvd['id'] ?? 0);
        $cover = 'cover/placeholder.png'; // Fallback cover
        
        // Versuch Cover zu finden
        if (!empty($dvd['cover_id'])) {
            $extensions = ['.jpg', '.jpeg', '.png'];
            foreach ($extensions as $ext) {
                $file = "cover/{$dvd['cover_id']}f{$ext}";
                if (file_exists($file)) {
                    $cover = $file;
                    break;
                }
            }
        }

        return '
        <div class="dvd" data-dvd-id="' . $id . '">
          <div class="cover-area">
            <img src="' . htmlspecialchars($cover) . '" alt="Cover">
          </div>
          <div class="dvd-details">
            <h2><a href="#" class="toggle-detail" data-id="' . $id . '">' . $title . ' (' . $year . ')</a></h2>
            <p><strong>Genre:</strong> ' . $genre . '</p>
          </div>
        </div>';
    }
}

// BuildQuery-Funktion für Pagination
function buildQuery($params = []) {
    $currentParams = $_GET;
    foreach ($params as $key => $value) {
        if ($value === '') {
            unset($currentParams[$key]);
        } else {
            $currentParams[$key] = $value;
        }
    }
    return http_build_query($currentParams);
}

// Einfache, sichere Datenabfrage
$search = trim($_GET['q'] ?? '');
$type = trim($_GET['type'] ?? '');
$page = max(1, (int)($_GET['seite'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

try {
    // Collection Types laden
    $typesStmt = $pdo->query("SELECT DISTINCT collection_type FROM dvds WHERE collection_type IS NOT NULL ORDER BY collection_type");
    $types = $typesStmt ? $typesStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    
    // WHERE-Filter aufbauen
    $where = ['1=1']; // Immer wahr
    $params = [];
    
    if ($search !== '') {
        $where[] = "title LIKE :search";
        $params['search'] = "%{$search}%";
    }
    if ($type !== '') {
        $where[] = "collection_type = :type";
        $params['type'] = $type;
    }
    
    $whereSql = 'WHERE ' . implode(' AND ', $where);
    
    // Gesamtanzahl
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM dvds $whereSql");
    if ($countStmt) {
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($total / $perPage);
    } else {
        $total = 0;
        $totalPages = 0;
    }
    
    // Filme laden
    $sql = "SELECT * FROM dvds $whereSql ORDER BY title LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    if ($stmt) {
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $films = $stmt->fetchAll();
    } else {
        $films = [];
    }
    
} catch (Exception $e) {
    error_log('Film-list error: ' . $e->getMessage());
    $types = [];
    $films = [];
    $total = 0;
    $totalPages = 0;
}
?>

<!-- Tabs für Collection Types -->
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
</div>

<!-- Film-Liste -->
<div class="film-list">
  <?php if (empty($films)): ?>
    <div class="empty-state">
      <i class="bi bi-film"></i>
      <h3>Keine Filme gefunden</h3>
      <p>
        <?php if (!empty($search)): ?>
          Keine Filme gefunden für "<?= htmlspecialchars($search) ?>".
        <?php elseif (!empty($type)): ?>
          Keine Filme im Genre "<?= htmlspecialchars($type) ?>" gefunden.
        <?php else: ?>
          Noch keine Filme in der Sammlung vorhanden.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <?php foreach ($films as $film): ?>
      <?= renderFilmCard($film) ?>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<!-- Einfache Pagination -->
<?php if ($totalPages > 1): ?>
  <nav class="pagination">
    <?php if ($page > 1): ?>
      <a href="?<?= buildQuery(['seite' => $page - 1]) ?>">« Zurück</a>
    <?php endif; ?>
    
    <?php for ($i = 1; $i <= min(10, $totalPages); $i++): ?>
      <?php if ($i === $page): ?>
        <span class="current"><?= $i ?></span>
      <?php else: ?>
        <a href="?<?= buildQuery(['seite' => $i]) ?>"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($totalPages > 10): ?>
      <span>...</span>
      <a href="?<?= buildQuery(['seite' => $totalPages]) ?>"><?= $totalPages ?></a>
    <?php endif; ?>
    
    <?php if ($page < $totalPages): ?>
      <a href="?<?= buildQuery(['seite' => $page + 1]) ?>">Weiter »</a>
    <?php endif; ?>
  </nav>
  
  <div class="pagination-info">
    Seite <?= $page ?> von <?= $totalPages ?> (<?= $total ?> Filme insgesamt)
  </div>
<?php endif; ?>