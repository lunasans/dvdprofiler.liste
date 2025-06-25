<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// Einstellungen aus der DB laden
$stmt = $pdo->query("SELECT `key`, `value` FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Bei Formularübermittlung speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($_POST as $key => $value) {
        $stmt = $pdo->prepare("UPDATE settings SET value = :value WHERE `key` = :key");
        $stmt->execute(['key' => $key, 'value' => $value]);
    }
    echo '<div class="alert alert-success">Einstellungen wurden gespeichert.</div>';
    // Werte neu laden
    $stmt = $pdo->query("SELECT `key`, `value` FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
?>

<h2>⚙️ Seiteneinstellungen</h2>

<ul class="nav nav-tabs mt-3" role="tablist">
  <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-basic" type="button">🏠 Haupt</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-security" type="button">🔒 Sicherheit</button></li>
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mail" type="button">✉️ E-Mail</button></li>
</ul>

<form method="post" class="tab-content border rounded-bottom p-4 bg-white">
  <!-- 🏠 Grundkonfiguration -->
  <div class="tab-pane fade show active" id="tab-basic">
    <div class="mb-3">
      <label for="site_title" class="form-label">Seitentitel</label>
      <input type="text" name="site_title" id="site_title" class="form-control" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label for="base_url" class="form-label">Basis-URL</label>
      <input type="url" name="base_url" id="base_url" class="form-control" value="<?= htmlspecialchars($settings['base_url'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label for="language" class="form-label">Sprache</label>
      <select name="language" id="language" class="form-select">
        <option value="de" <?= ($settings['language'] ?? '') === 'de' ? 'selected' : '' ?>>Deutsch</option>
        <option value="en" <?= ($settings['language'] ?? '') === 'en' ? 'selected' : '' ?>>Englisch</option>
      </select>
    </div>
  </div>

  <!-- 🔒 Sicherheit -->
  <div class="tab-pane fade" id="tab-security">
    <div class="mb-3">
      <label class="form-label">2FA global aktivieren</label>
      <select name="enable_2fa" class="form-select">
        <option value="1" <?= ($settings['enable_2fa'] ?? '') == '1' ? 'selected' : '' ?>>Ja</option>
        <option value="0" <?= ($settings['enable_2fa'] ?? '') == '0' ? 'selected' : '' ?>>Nein</option>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Maximale Login-Versuche</label>
      <input type="number" name="login_attempts" class="form-control" value="<?= htmlspecialchars($settings['login_attempts'] ?? '5') ?>">
    </div>
  </div>

  <!-- ✉️ Mail -->
  <div class="tab-pane fade" id="tab-mail">
    <div class="mb-3">
      <label class="form-label">SMTP-Host</label>
      <input type="text" name="smtp_host" class="form-control" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">SMTP-Absender</label>
      <input type="email" name="smtp_sender" class="form-control" value="<?= htmlspecialchars($settings['smtp_sender'] ?? '') ?>">
    </div>
  </div>

  <button type="submit" class="btn btn-primary mt-3">💾 Einstellungen speichern</button>
</form>