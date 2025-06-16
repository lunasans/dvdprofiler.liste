<?php
declare(strict_types=1);

// Bootstrap lädt $pdo, BASE_URL usw.
require_once __DIR__ . '/../includes/bootstrap.php';

session_start();
session_destroy();
header("Location: " . BASE_URL . "/admin/login.php");
exit;