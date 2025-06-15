<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/bootstrap.php';

// Login-Prüfung
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <title>Admin Center</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/admin.css"> <!-- Optional -->
</head>
<body class="bg-light">

  <div class="admin-wrapper d-flex">
    
    <?php include 'sidebar.php'; ?>

    <div id="admin-content" class="flex-grow-1 p-4">
      <h2>Willkommen im Admin-Center</h2>
      <p>Wähle eine Funktion aus der linken Navigation aus.</p>
    </div>

  </div>

  <script src="js/admin.js"></script>
</body>
</html>
