<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$id = $_POST['id'] ?? null;
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$id || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo "❌ Ungültige Eingabe.";
    exit;
}

// E-Mail aktualisieren
$stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
$stmt->execute([$email, $id]);

// Passwort nur aktualisieren, wenn angegeben
if (!empty($password)) {
    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->execute([$hashed, $id]);
}

echo "✅ Benutzer wurde aktualisiert.";
