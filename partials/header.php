<?php
// header.php
?>
<header class="smart-header">
  <div class="header-inner">
    <div class="logo">🎬 DVD Liste</div>
    <nav class="main-nav">
      <a href="?page=home" class="route-link">Start</a>
      <a href="?page=films" class="route-link">Filme</a>
      <a href="?page=stats" class="route-link">Statistik</a>
      <a href="?page=impressum" class="route-link">Impressum</a>
    </nav>
    <form class="search-form" method="get" action="index.php">
      <input type="text" name="search" placeholder="Suche...">
    </form>
    <button class="burger" onclick="document.querySelector('.main-nav').classList.toggle('show')">☰</button>
  </div>
</header>