<?php
require_once __DIR__ . '/../config/config.php';

session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Adminbereich – DVD Projekt</title>
  <link rel="stylesheet" href="css/admin-style.css">
</head>
<body>
  <header class="smart-header">
    <div class="header-inner">
      <div class="logo">Adminbereich</div>
      <nav class="main-nav">
        <a href="index.php">Dashboard</a>
        <a href="backup.php">Backups</a>
        <a href="../index.php">Zurück zur Website</a>
      </nav>
    </div>
  </header>

  <main>
    <h1>Willkommen im Adminbereich</h1>
    <p>Wähle eine Funktion aus der Navigation.</p>
  </main>

  <footer class="site-footer">
    <div class="footer-left">
      <span class="version">Admin v1.0</span>
      <p>&copy; <?= date('Y') ?> René Neuhaus</p>
    </div>
    <div class="footer-right">
      <a href="../?page=impressum" class="route-link">Impressum</a>
    </div>
  </footer>
</body>
</html>
