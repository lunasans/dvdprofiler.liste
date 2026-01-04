<?php
/**
 * Delete Film Action (Soft Delete)
 */

require_once __DIR__ . '/../../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

if (!isset($_SESSION['user_id'])) {
    $_SESSION['films_error'] = 'Sie müssen eingeloggt sein.';
    header('Location: ../index.php?page=films');
    exit;
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    $_SESSION['films_error'] = 'Ungültiger CSRF-Token.';
    header('Location: ../index.php?page=films');
    exit;
}

try {
    $filmId = (int)($_POST['film_id'] ?? 0);
    
    if ($filmId === 0) {
        throw new Exception('Ungültige Film-ID.');
    }
    
    // Get film title for message
    $stmt = $pdo->prepare("SELECT title FROM dvds WHERE id = ? AND deleted = 0");
    $stmt->execute([$filmId]);
    $film = $stmt->fetch();
    
    if (!$film) {
        throw new Exception('Film nicht gefunden.');
    }
    
    // Soft Delete
    $stmt = $pdo->prepare("UPDATE dvds SET deleted = 1, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$filmId]);
    
    $_SESSION['films_success'] = "Film \"{$film['title']}\" wurde gelöscht.";
    header('Location: ../index.php?page=films');
    exit;
    
} catch (Exception $e) {
    $_SESSION['films_error'] = 'Fehler beim Löschen: ' . $e->getMessage();
    error_log('Delete Film Error: ' . $e->getMessage());
    header('Location: ../index.php?page=films');
    exit;
}