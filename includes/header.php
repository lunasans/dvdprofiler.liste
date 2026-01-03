<?php
// header.php
?>
<header class="smart-header">
  <div class="header-inner">
    <div class="logo"><?= htmlspecialchars($siteTitle) ?></div>
    <nav class="main-nav">
      <a href="index.php" class="route-link">Start</a>
      <a href="?page=trailers" class="route-link">
        <i class="bi bi-play-circle"></i> Trailer
      </a>
      <a href="?page=stats" class="route-link">Statistik</a>
      </nav>
    <form class="search-form" method="get" action="index.php">
      <input type="text" name="q" placeholder="Suche..." autocomplete="off">
      <button type="submit" style="display: none;">Suchen</button>
    </form>
    <button class="burger" onclick="document.querySelector('.main-nav').classList.toggle('show')">☰</button>
  </div>
</header>