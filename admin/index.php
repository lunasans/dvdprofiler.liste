<?php
declare(strict_types=1);
session_start();

// Bootstrap
require_once __DIR__ . '/../includes/bootstrap.php';

// Zugriffsschutz
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Erlaubte Seiten
$allowedPages = ['dashboard', 'users', 'settings', 'logs'];
$page = $_GET['page'] ?? 'dashboard';

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard'; // Fallback
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Admin Center</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
  <div class="admin-layout">
    
    <!-- Sidebar -->
    <aside class="sidebar bg-dark text-white p-3" style="width: 220px; min-height: 100vh;">
      <?php include 'sidebar.php'; ?>
    </aside>

    <!-- Hauptinhalt -->
    <main class="admin-content flex-grow-1 px-4 py-4">
      <?php
        if (in_array($page, $allowedPages) && file_exists(__DIR__ . "/pages/{$page}.php")) {
            include __DIR__ . "/pages/{$page}.php";
        } else {
            echo "<p>❌ Seite nicht gefunden.</p>";
        }
      ?>
    </main>

  </div>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/admin.js"></script>
</body>
</html>
