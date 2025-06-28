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
  <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-update" type="button">🔄 Update</button></li>
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

  <!-- 🔄 System-Update -->
<div class="tab-pane fade" id="tab-update">
  <?php
  // GitHub Repo Info
  $githubRepo = 'lunasans/dvdprofiler.liste';
  $localVersion = $settings['version'] ?? '0.0.0';
  $latestVersion = 'unbekannt';
  $error = '';
  $success = '';

  // Funktion GitHub Release holen
  function getLatestRelease(string $repo): ?array {
      $apiUrl = "https://api.github.com/repos/$repo/releases/latest";
      $opts = ['http' => [
          'method' => 'GET',
          'header' => "User-Agent: dvd-updater"
      ]];
      $ctx = stream_context_create($opts);
      $json = @file_get_contents($apiUrl, false, $ctx);
      return $json ? json_decode($json, true) : null;
  }

  // Release holen
  $latestData = getLatestRelease($githubRepo);
  if ($latestData && !empty($latestData['tag_name'])) {
      $latestVersion = $latestData['tag_name'];
  }

  $isUpdateAvailable = version_compare($latestVersion, $localVersion, '>');

  // Update ausführen
  if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_update'])) {
      $zipUrl = $latestData['zipball_url'] ?? '';
      $repoTag = basename(parse_url($zipUrl, PHP_URL_PATH), '.zip');
      if ($zipUrl) {
          $tmpZip = __DIR__ . '/../update_tmp.zip';
          file_put_contents($tmpZip, file_get_contents($zipUrl));
          $zip = new ZipArchive();
          if ($zip->open($tmpZip) === true) {
              $exclude = ['config/config.php','counter.txt','admin/xml/'];
              for ($i = 0; $i < $zip->numFiles; $i++) {
                  $entry = $zip->getNameIndex($i);
                  $rel = preg_replace("#^{$repoTag}/#", '', $entry);
                  $skip = false;
                  foreach ($exclude as $ex) {
                      if ($rel === $ex || str_starts_with($rel, rtrim($ex, '/').'/')) $skip = true;
                  }
                  if ($skip || $rel === '') continue;
                  $path = dirname(__DIR__).'/'.$rel;
                  if (str_ends_with($rel, '/')) @mkdir($path,0775,true);
                  else {
                      @mkdir(dirname($path),0775,true);
                      file_put_contents($path,$zip->getFromIndex($i));
                  }
              }
              $zip->close();
              unlink($tmpZip);
              if (file_exists(dirname(__DIR__).'/update.sql')) {
                  $pdo->exec(file_get_contents(dirname(__DIR__).'/update.sql'));
                  unlink(dirname(__DIR__).'/update.sql');
              }
              // Version aktualisieren
              $stmt = $pdo->prepare("UPDATE settings SET value = :v WHERE `key` = 'version'");
              $stmt->execute(['v'=>$latestVersion]);
              $success = '✅ Update erfolgreich installiert.';
              $settings['version'] = $latestVersion;
          } else {
              $error = '❌ ZIP konnte nicht geöffnet werden.';
          }
      } else {
          $error = '❌ Keine gültige ZIP-URL gefunden.';
      }
  }
  ?>

  <div class="mt-3">
    <p><strong>Aktuelle Version:</strong> <?= htmlspecialchars($localVersion) ?></p>
    <p><strong>Neueste Version bei GitHub:</strong> <?= htmlspecialchars($latestVersion) ?></p>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= $error ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
  <?php endif; ?>

  <?php if ($isUpdateAvailable): ?>
    <form method="post" class="mt-3">
      <button name="start_update" class="btn btn-primary">⬇️ Update herunterladen & installieren</button>
    </form>
  <?php else: ?>
    <div class="alert alert-info mt-3">✅ Deine Installation ist aktuell.</div>
  <?php endif; ?>
</div>

  <button type="submit" class="btn btn-primary mt-3">💾 Einstellungen speichern</button>
</form>