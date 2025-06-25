<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/bootstrap.php';

$uploadDir = __DIR__ . '/../../admin/xml/';

// Datei löschen
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_file'])) {
    $fileToDelete = basename($_POST['delete_file']);
    $filePath = $uploadDir . $fileToDelete;
    if (file_exists($filePath)) {
        unlink($filePath);
        $message = "Datei <strong>{$fileToDelete}</strong> wurde gelöscht.";
    } else {
        $error = "Datei <strong>{$fileToDelete}</strong> nicht gefunden.";
    }
}

// Maximale Uploadgrößen
$maxUpload = ini_get('upload_max_filesize');
$maxPost = ini_get('post_max_size');

// XML-Dateien auflisten
$files = glob($uploadDir . '*.xml');
?>

<h3>Dateiimport</h3>

<?php if (!empty($_SESSION['import_result'])): ?>
  <div class="alert alert-success" style="white-space: pre-line">
    <?= htmlspecialchars($_SESSION['import_result']) ?>
  </div>
  <?php unset($_SESSION['import_result']); ?>
<?php endif; ?>

<?php if (!empty($message)): ?>
  <div class="alert alert-success"><?= $message ?></div>
<?php elseif (!empty($error)): ?>
  <div class="alert alert-danger"><?= $error ?></div>
<?php endif; ?>

<form action="<?= BASE_URL ?>/admin/actions/import-handler.php" method="post" enctype="multipart/form-data" class="border rounded p-4 bg-light">
  <div class="mb-3">
    <label for="xml_file" class="form-label">XML-Datei auswählen</label>
    <input type="file" name="xml_file" id="xml_file" class="form-control" required accept=".xml,.zip">
    <small class="text-muted">Erlaubt: XML oder ZIP (mit .xml im Inneren)</small><br>
    <small class="text-muted">Maximale Dateigröße laut Server: <strong><?= $maxUpload ?></strong></small><br>
    <small class="text-muted">Max. POST-Größe: <strong><?= $maxPost ?></strong></small><br>
  </div>
  <button type="submit" class="btn btn-primary">Import starten</button>
</form>

<hr>

<h4>📁 XML-Dateien im Verzeichnis <code>/admin/xml/</code></h4>

<?php if (empty($files)): ?>
  <p class="text-muted">Keine XML-Dateien gefunden.</p>
<?php else: ?>
  <table class="table table-bordered table-sm align-middle">
    <thead class="table-light">
      <tr>
        <th>Dateiname</th>
        <th>Größe</th>
        <th>Aktionen</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($files as $file):
        $filename = basename($file);
        $size = round(filesize($file) / 1024, 1);
      ?>
      <tr>
        <td><?= htmlspecialchars($filename) ?></td>
        <td><?= $size ?> KB</td>
        <td>
          <a href="xml/<?= urlencode($filename) ?>" target="_blank" class="btn btn-sm btn-outline-secondary">🔍 Öffnen</a>
          <form method="post" action="" style="display:inline;">
            <input type="hidden" name="delete_file" value="<?= htmlspecialchars($filename) ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Datei wirklich löschen?')">🗑️ Löschen</button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
