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
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
        <div class="flex-grow-1 p-4">
            <?php
                $pageFile = __DIR__ . '/pages/' . $page . '.php';
                if (file_exists($pageFile)) {
                    include $pageFile;
                } else {
                    echo '<div class="alert alert-danger">Seite nicht gefunden.</div>';
                }
            ?>
        </div>
    </div>
</body>
</html>
