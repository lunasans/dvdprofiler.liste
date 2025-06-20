<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/bootstrap.php';

session_start();

// Nur eingeloggte Admins erlauben
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Nicht autorisiert');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($id <= 0 || $email === '') {
        $_SESSION['error'] = 'Ungültige Eingaben.';
        header('Location: ../index.php?page=users');
        exit;
    }

    try {
        if ($password !== '') {
            // Passwort aktualisieren
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
            $stmt->execute([$email, $hashed, $id]);
        } else {
            // Nur E-Mail aktualisieren
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $id]);
        }

        $_SESSION['success'] = 'Benutzer erfolgreich aktualisiert.';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Fehler beim Speichern: ' . $e->getMessage();
    }

    header('Location: ../index.php?page=users');
    exit;
}
?>
