<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// Einstellungen laden
$stmt = $pdo->query("SELECT `key`, `value` FROM settings");
$settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// GitHub-Repo
$githubRepo = 'lunasans/dvdprofiler.liste';
$localVersion = $settings['version'] ?? '0.0.0';
$latestVersion = 'unbekannt';
$error = '';
$success = '';
$changelog = '';

// Release-Infos holen
function getLatestReleaseFull(string $repo): ?array {
    $apiUrl = "https://api.github.com/repos/$repo/releases/latest";
    $opts = ['http' => [
        'method' => 'GET',
        'header' => "User-Agent: dvd-updater"
    ]];
    $ctx = stream_context_create($opts);
    $json = @file_get_contents($apiUrl, false, $ctx);
    return $json ? json_decode($json, true) : null;
}

$latestData = getLatestReleaseFull($githubRepo);
if ($latestData && !empty($latestData['tag_name'])) {
    $latestVersion = $latestData['tag_name'];
    $changelog = $latestData['body'] ?? '';
}

$isUpdateAvailable = version_compare($latestVersion, $localVersion, '>');

// Einstellungen speichern
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    foreach ($_POST as $k => $v) {
        if (in_array($k, ['site_title','base_url','language','enable_2fa','login_attempts','smtp_host','smtp_sender'])) {
            updateSetting($k, trim($v));
        }
    }
    header('Location: ?page=settings&saved=1');
    exit;
}

// Update durchführen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_update'])) {
    $zipUrl = $latestData['zipball_url'] ?? '';
    $repoTag = basename(parse_url($zipUrl, PHP_URL_PATH), '.zip');
    if ($zipUrl) {
        // Backup-Verzeichnis
        $backupDir = dirname(__DIR__) . '/backups/';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }
        $backupName = $backupDir . 'backup_' . date('Ymd_His') . '.zip';

        $zipBackup = new ZipArchive();
        if ($zipBackup->open($backupName, ZipArchive::CREATE) === true) {
            // Config laden
            $config = require __DIR__ . '/../../config/config.php';
            $dbHost = $config['db_host'];
            $dbName = $config['db_name'];
            $dbUser = $config['db_user'];
            $dbPass = $config['db_pass'];

            // DB-Dump
            $dumpFile = sys_get_temp_dir() . '/db_backup_' . date('Ymd_His') . '.sql';
            $command = sprintf(
                'mysqldump -h%s -u%s -p%s %s > %s',
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($dumpFile)
            );
            exec($command);

            // Dateien ins ZIP
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(dirname(__DIR__), RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($files as $file) {
                $path = str_replace(dirname(__DIR__) . '/', '', $file->getPathname());
                if (
                    str_starts_with($path, 'admin/xml/') ||
                    str_starts_with($path, 'cover/') ||
                    $path === 'config/config.php' ||
                    $path === 'counter.txt'
                ) continue;
                if ($file->isDir()) {
                    $zipBackup->addEmptyDir($path);
                } else {
                    $zipBackup->addFile($file->getPathname(), $path);
                }
            }

            // DB-Dump ins ZIP
            if (file_exists($dumpFile)) {
                $zipBackup->addFile($dumpFile, 'db_backup.sql');
                unlink($dumpFile);
            }

            $zipBackup->close();
        }

        // Update herunterladen und entpacken
        $tmpZip = __DIR__ . '/../../update_tmp.zip';
        file_put_contents($tmpZip, file_get_contents($zipUrl));
        $zip = new ZipArchive();
        if ($zip->open($tmpZip) === true) {
            $exclude = ['config/config.php','counter.txt','admin/xml/','cover/'];
            for ($i=0; $i<$zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                $rel = preg_replace("#^{$repoTag}/#", '', $entry);
                $skip = false;
                foreach ($exclude as $ex) {
                    if ($rel === $ex || str_starts_with($rel, rtrim($ex, '/').'/')) $skip = true;
                }
                if ($skip || $rel === '') continue;
                $path = dirname(__DIR__,2) . '/' . $rel;
                if (str_ends_with($rel, '/')) @mkdir($path,0775,true);
                else {
                    @mkdir(dirname($path),0775,true);
                    file_put_contents($path, $zip->getFromIndex($i));
                }
            }
            $zip->close();
            unlink($tmpZip);
            if (file_exists(dirname(__DIR__,2).'/update.sql')) {
                $pdo->exec(file_get_contents(dirname(__DIR__,2).'/update.sql'));
                unlink(dirname(__DIR__,2).'/update.sql');
            }
            $stmt = $pdo->prepare("UPDATE settings SET value = :v WHERE `key` = 'version'");
            $stmt->execute(['v' => $latestVersion]);
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

<div class="container mt-4">
  <h2>Einstellungen</h2>

  <?php if (isset($_GET['saved'])): ?>
    <div class="alert alert-success">✅ Einstellungen gespeichert.</div>
  <?php endif; ?>

  <ul class="nav nav-tabs mt-3" role="tablist">
    <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-main">🏠 Allgemein</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-security">🔒 Sicherheit</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-mail">✉️ Mail</button></li>
    <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-update">🔄 Update</button></li>
  </ul>

  <div class="tab-content">
    <div class="tab-pane fade show active p-3" id="tab-main">
      <form method="post">
        <div class="mb-2">
          <label>Seitentitel</label>
          <input name="site_title" value="<?= htmlspecialchars($settings['site_title'] ?? '') ?>" class="form-control">
        </div>
        <div class="mb-2">
          <label>Basis-URL</label>
          <input name="base_url" value="<?= htmlspecialchars($settings['base_url'] ?? '') ?>" class="form-control">
        </div>
        <div class="mb-2">
          <label>Sprache</label>
          <input name="language" value="<?= htmlspecialchars($settings['language'] ?? '') ?>" class="form-control">
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary mt-3">💾 Speichern</button>
      </form>
    </div>

    <div class="tab-pane fade p-3" id="tab-security">
      <form method="post">
        <div class="mb-2">
          <label>2FA aktivieren</label>
          <select name="enable_2fa" class="form-select">
            <option value="0" <?= ($settings['enable_2fa'] ?? '0')==='0'?'selected':'' ?>>Nein</option>
            <option value="1" <?= ($settings['enable_2fa'] ?? '0')==='1'?'selected':'' ?>>Ja</option>
          </select>
        </div>
        <div class="mb-2">
          <label>Max. Login-Versuche</label>
          <input name="login_attempts" value="<?= htmlspecialchars($settings['login_attempts'] ?? '5') ?>" class="form-control">
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary mt-3">💾 Speichern</button>
      </form>
    </div>

    <div class="tab-pane fade p-3" id="tab-mail">
      <form method="post">
        <div class="mb-2">
          <label>SMTP-Host</label>
          <input name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? '') ?>" class="form-control">
        </div>
        <div class="mb-2">
          <label>Absender-Adresse</label>
          <input name="smtp_sender" value="<?= htmlspecialchars($settings['smtp_sender'] ?? '') ?>" class="form-control">
        </div>
        <button type="submit" name="save_settings" class="btn btn-primary mt-3">💾 Speichern</button>
      </form>
    </div>

    <div class="tab-pane fade p-3" id="tab-update">
      <p><strong>Aktuelle Version:</strong> <?= htmlspecialchars($localVersion) ?></p>
      <p><strong>Neueste Version bei GitHub:</strong> <?= htmlspecialchars($latestVersion) ?></p>
      <?php if ($changelog): ?>
        <div class="card mb-3">
          <div class="card-header">📋 Changelog</div>
          <div class="card-body">
            <pre><?= htmlspecialchars($changelog) ?></pre>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
      <?php endif; ?>
      <?php if ($isUpdateAvailable): ?>
        <form method="post">
          <button name="start_update" class="btn btn-primary">⬇️ Update installieren</button>
        </form>
      <?php else: ?>
        <div class="alert alert-info mt-3">✅ Deine Installation ist aktuell.</div>
      <?php endif; ?>
    </div>
  </div>
</div>