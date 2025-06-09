<?php
declare(strict_types=1);
// Zentrale Initialisierung

$search = trim($_GET['q'] ?? '');
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>DVD Sammlung</title>
  <link rel="stylesheet" href="css/style.css">
  <link href="libs/fancybox/dist/fancybox/fancybox.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
</head>
<body>

<!-- ───────────── Header ───────────── -->
<header class="smart-header">
  <div class="header-inner">
    <div class="logo">DVD-Sammlung</div>

    <nav class="main-nav" id="mainNav">
      <a href="index.php">Home</a>
      <a href="?page=stats" class="route-link">Statistik</a>
    </nav>

    <form class="search-form" method="get">
      <input type="text" name="q" placeholder="Film suchen…" value="<?= htmlspecialchars($search) ?>">
    </form>

    <button class="burger" onclick="toggleNav()">☰</button>
  </div>
</header>

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
    <span class="version">v1.3.0 
    <p>Besucher: <?= $visits ?></p>
    <p>&copy; <?= date('Y') ?> René Neuhaus</p>
    </span>
  </div>
  
  <div class="footer-right">
    <a href="?page=impressum" class="route-link">Impressum</a>
  </div>
</footer>

<script src="js/main.js"></script>
<script src="libs/fancybox/dist/fancybox/fancybox.umd.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js" integrity="sha384-j1CDi7MgGQ12Z7Qab0qlWQ/Qqz24Gc6BM0thvEMVjHnfYGF0rmFCozFSxQBxwHKO" crossorigin="anonymous"></script>
</body>
</html>
