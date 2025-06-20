<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../vendor/autoload.php';

use RobThree\Auth\TwoFactorAuth;

session_start();
$userId = $_SESSION['user_id'] ?? 0;
if (!$userId) exit('unauth');

$secret = $_POST['secret'] ?? '';
$token  = $_POST['token'] ?? '';

$tfa = new TwoFactorAuth();
if ($tfa->verifyCode($secret, $token)) {
    $stmt = $pdo->prepare("UPDATE users SET twofa_secret = ?, twofa_enabled = 1 WHERE id = ?");
    $stmt->execute([$secret, $userId]);
    echo 'ok';
} else {
    echo 'fail';
}
