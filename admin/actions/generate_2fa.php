<?php
declare(strict_types=1);
header('Content-Type: application/json');

require_once '/../../../vendor/autoload.php'; // ggf. anpassen

use RobThree\Auth\TwoFactorAuth;
use RobThree\Auth\Providers\Qr\EndroidQrCodeProvider;
use RobThree\Auth\Algorithm;

$qrProvider = new EndroidQrCodeProvider();
$twofa = new TwoFactorAuth($qrProvider, 'DVD Verwaltung', 6, 30, Algorithm::SHA1);

$secret = $twofa->createSecret();
$uri = $twofa->getQRCodeImageAsDataUri('DVD Verwaltung', $secret);

// Beispielhafte Backup-Codes
$codes = [];
for ($i = 0; $i < 10; $i++) {
    $codes[] = bin2hex(random_bytes(4));
}

echo json_encode([
    'qrcode' => $uri,
    'secret' => $secret,
    'backup_codes' => $codes
]);
