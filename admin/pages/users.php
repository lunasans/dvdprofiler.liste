<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

$columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
print_r($columns);

$stmt = $pdo->query("SELECT id, email, created_at, twofa_enabled FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll();
?>

<div class="container">
  <h2>Benutzerverwaltung</h2>

  <table class="table table-bordered">
    <thead>
      <tr>
        <th>ID</th>
        <th>E-Mail</th>
        <th>Erstellt am</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td><?= $user['id'] ?></td>
          <td><?= htmlspecialchars($user['email']) ?></td>
          <td><?= date('d.m.Y H:i', strtotime($user['created_at'])) ?></td>
          <td>
            <button class="btn btn-sm btn-primary edit-user-btn"
              data-id="<?= $user['id'] ?>"
              data-email="<?= htmlspecialchars($user['email']) ?>">
              Bearbeiten
            </button>
            <!--- <?php if (!$user['twofa_enabled']): ?>
            <!--- <button class="btn btn-sm btn-primary" onclick="open2FAModal(<?= $user['id'] ?>)">2FA aktivieren</button> --->
            <?php else: ?> --->
            <span class="badge bg-success">2FA aktiv</span>
            <?php endif; ?> --->
        </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Modal (Bootstrap) -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="post" action="actions/update_user.php">
      <div class="modal-header">
        <h5 class="modal-title" id="editUserModalLabel">Benutzer bearbeiten</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="edit-user-id">
        <div class="mb-3">
          <label for="edit-email" class="form-label">E-Mail</label>
          <input type="email" class="form-control" id="edit-email" name="email" required>
        </div>
        <div class="mb-3">
          <label for="edit-password" class="form-label">Neues Passwort (optional)</label>
          <input type="password" class="form-control" id="edit-password" name="password">
        </div>
      </div>
      <div class="modal-footer">
        <button type="submit" class="btn btn-success">Speichern</button>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
      </div>
    </form>
  </div>
</div>

<!-- 2FA Modal -->
<div class="modal fade" id="enable2faModal" tabindex="-1" aria-labelledby="enable2faLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="enable2faForm">
        <div class="modal-header">
          <h5 class="modal-title" id="enable2faLabel">2FA aktivieren</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
        </div>
        <div class="modal-body text-center">
          <p>Scanne den QR-Code mit deiner Authenticator-App:</p>
          <img id="qrcode" src="" alt="QR-Code">
          <div class="mt-3">
            <label for="token" class="form-label">Bestätigungscode</label>
            <input type="text" class="form-control" id="token" name="token" required>
          </div>
          <input type="hidden" id="secret" name="secret">
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">2FA aktivieren</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.querySelectorAll('.edit-user-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    document.getElementById('edit-user-id').value = btn.dataset.id;
    document.getElementById('edit-email').value = btn.dataset.email;
    const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
    modal.show();
  });
});
</script>
