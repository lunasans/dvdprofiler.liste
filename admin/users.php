<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// Wenn keine AJAX-Anfrage => Seite mit HTML und JS laden
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SERVER['HTTP_X_REQUESTED_WITH'])):
?>
<h2>Benutzerverwaltung</h2>
<table class="table table-striped" id="userTable">
  <thead>
    <tr>
      <th>E-Mail</th>
      <th>Erstellt</th>
      <th>Aktion</th>
    </tr>
  </thead>
  <tbody>
    <tr><td colspan="3">Lade Benutzer...</td></tr>
  </tbody>
</table>

<?php exit; endif;

// AJAX-API
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT id, email, created_at FROM users ORDER BY id");
    echo json_encode($stmt->fetchAll());
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);

    if ($action === 'update' && $id > 0 && !empty($_POST['email'])) {
        $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->execute([trim($_POST['email']), $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'delete' && $id > 0) {
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['success' => true]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Ungültige Anfrage']);
}
?>
