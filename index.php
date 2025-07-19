<?php
declare(strict_types=1);
// Zentrale Initialisierung
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/counter.php';

$search = trim($_GET['q'] ?? '');
$siteTitle = getSetting('site_title', 'Meine DVD-Verwaltung');
$baseUrl = rtrim((isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']), '/') . '/';
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($siteTitle) ?></title>
  <link rel="stylesheet" href="css/style.css">
  <link href="libs/fancybox/dist/fancybox/fancybox.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>

<!-- ───────────── Header ───────────── -->
<?php include 'partials/header.php'; ?>

<!-- ───────────── Hauptlayout ───────────── -->
<div class="layout">
  <!--  Linke Film-Liste + Tabs + Pagination -->
  <div class="film-list-area">
    <?php include __DIR__ . '/partials/film-list.php'; ?>
  </div>

  <!--  Rechte Detailansicht -->
  <div class="detail-panel" id="detail-container">
    <!-- wird dynamisch per JS gefüllt -->
  </div>
</div>

<!-- ───────────── Footer ───────────── -->
<footer class="site-footer">
  <div class="footer-left">
  </div>

  <div class="footer-center">
    <div class="version">
      <div>Version <a href="https://github.com/lunasans/dvdprofiler.liste" target="_blank"><?= htmlspecialchars($config['version']) ?> <i class="bi bi-github"></i></a></div>
      <div>Besucher: <?= $visits ?></div>
      <div>&copy; <?= date('Y') ?> René Neuhaus</div>
    </div>
  </div>

  <ul class="footer-right">
  <li><a href="?page=impressum">Impressum</a></li>
  <?php if (isset($_SESSION['user_id'])): ?>
    <li><a href="<?= $baseUrl ?>admin/">Admin-Panel</a></li>
    <li><a href="<?= $baseUrl ?>admin/logout.php">Logout</a></li>
  <?php else: ?>
    <li><a href="<?= $baseUrl ?>admin/login.php">Login</a></li>
  <?php endif; ?>
</ul>


</footer>

<script src="js/main.js"></script>
<script src="libs/fancybox/dist/fancybox/fancybox.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
