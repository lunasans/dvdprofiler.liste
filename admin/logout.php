<?php
declare(strict_types=1);

// Bootstrap lädt $pdo, BASE_URL usw. und startet bereits die Session
require_once __DIR__ . '/../includes/bootstrap.php';

// Session zerstören (Session ist bereits gestartet)
session_destroy();

// Redirect zur Login-Page
$loginUrl = (defined('BASE_URL') && BASE_URL !== '') 
    ? BASE_URL . '/admin/login.php'
    : 'login.php';

header("Location: {$loginUrl}");
exit;