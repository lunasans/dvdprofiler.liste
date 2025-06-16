<h2>Benutzerverwaltung</h2>
<table>
  <tr><th>ID</th><th>E-Mail</th></tr>
  <?php
  $stmt = $pdo->query("SELECT id, email FROM users");
  while ($user = $stmt->fetch()):
  ?>
    <tr>
      <td><?= $user['id'] ?></td>
      <td><?= htmlspecialchars($user['email']) ?></td>
    </tr>
  <?php endwhile; ?>
</table>
