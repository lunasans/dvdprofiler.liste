<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_GET['id'])) {
    http_response_code(400);
    echo "❌ Ungültige Anfrage";
    exit;
}

$id = (int) $_GET['id'];
$stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
$stmt->execute([$id]);

echo "✅ Benutzer wurde gelöscht.";