<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

// Security: Session und Berechtigung prüfen
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// CSRF-Token generieren
$csrfToken = generateCSRFToken();

// Konfiguration
$uploadDir = dirname(__DIR__, 2) . '/admin/xml/';
$maxFileSize = 50 * 1024 * 1024; // 50MB
$allowedExtensions = ['xml', 'zip'];
$allowedMimeTypes = [
    'application/xml',
    'text/xml',
    'application/zip',
    'application/x-zip-compressed'
];

// Upload-Verzeichnis erstellen falls nicht vorhanden
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        die('❌ Upload-Verzeichnis konnte nicht erstellt werden.');
    }
}

class SecureFileUploader
{
    private string $uploadDir;
    private int $maxFileSize;
    private array $allowedExtensions;
    private array $allowedMimeTypes;

    public function __construct(string $uploadDir, int $maxFileSize, array $allowedExtensions, array $allowedMimeTypes)
    {
        $this->uploadDir = rtrim($uploadDir, '/') . '/';
        $this->maxFileSize = $maxFileSize;
        $this->allowedExtensions = $allowedExtensions;
        $this->allowedMimeTypes = $allowedMimeTypes;
    }

    public function validateUpload(array $file): array
    {
        // Basis-Validierung
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['success' => false, 'message' => 'Ungültiger Upload-Parameter.'];
        }

        // Upload-Fehler prüfen
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['success' => false, 'message' => 'Keine Datei ausgewählt.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['success' => false, 'message' => 'Datei zu groß.'];
            default:
                return ['success' => false, 'message' => 'Upload-Fehler aufgetreten.'];
        }

        // Dateigröße prüfen
        if ($file['size'] > $this->maxFileSize) {
            return ['success' => false, 'message' => 'Datei überschreitet die maximale Größe von ' . ($this->maxFileSize / 1024 / 1024) . 'MB.'];
        }

        // Dateiname validieren
        $filename = $file['name'];
        if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
            return ['success' => false, 'message' => 'Ungültiger Dateiname. Nur Buchstaben, Zahlen, Punkte, Unterstriche und Bindestriche erlaubt.'];
        }

        // Extension prüfen
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            return ['success' => false, 'message' => 'Ungültiger Dateityp. Erlaubt: ' . implode(', ', $this->allowedExtensions)];
        }

        // MIME-Type prüfen
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            return ['success' => false, 'message' => 'Ungültiger MIME-Type: ' . $mimeType];
        }

        // ZIP-spezifische Validierung
        if ($extension === 'zip') {
            $validation = $this->validateZipContent($file['tmp_name']);
            if (!$validation['success']) {
                return $validation;
            }
        }

        // XML-spezifische Validierung
        if ($extension === 'xml') {
            $validation = $this->validateXMLContent($file['tmp_name']);
            if (!$validation['success']) {
                return $validation;
            }
        }

        return ['success' => true, 'message' => 'Datei ist gültig.'];
    }

    private function validateZipContent(string $filePath): array
    {
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== TRUE) {
            return ['success' => false, 'message' => 'ZIP-Datei konnte nicht geöffnet werden.'];
        }

        $hasXmlFile = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);

            // Path-Traversal prüfen
            if (str_contains($filename, '..') || str_starts_with($filename, '/')) {
                $zip->close();
                return ['success' => false, 'message' => 'ZIP enthält unsichere Pfade.'];
            }

            // XML-Datei suchen
            if (str_ends_with(strtolower($filename), '.xml')) {
                $hasXmlFile = true;

                // XML-Inhalt validieren
                $xmlContent = $zip->getFromIndex($i);
                if (!$this->isValidXMLContent($xmlContent)) {
                    $zip->close();
                    return ['success' => false, 'message' => "Ungültiger XML-Inhalt in {$filename}."];
                }
            }
        }

        $zip->close();

        if (!$hasXmlFile) {
            return ['success' => false, 'message' => 'ZIP enthält keine XML-Datei.'];
        }

        return ['success' => true, 'message' => 'ZIP-Inhalt ist gültig.'];
    }

    private function validateXMLContent(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if (!$this->isValidXMLContent($content)) {
            return ['success' => false, 'message' => 'Ungültiger XML-Inhalt.'];
        }

        return ['success' => true, 'message' => 'XML-Inhalt ist gültig.'];
    }

    private function isValidXMLContent(string $content): bool
    {
        // XXE-Angriffe verhindern
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);

        $dom = new DOMDocument();
        $dom->validateOnParse = true;

        $isValid = $dom->loadXML($content, LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_DTDATTR);

        // Nach DVD-Profiler-Struktur suchen
        if ($isValid) {
            $dvdElements = $dom->getElementsByTagName('DVD');
            $collectionElements = $dom->getElementsByTagName('Collection');

            if ($dvdElements->length === 0 && $collectionElements->length === 0) {
                return false; // Keine DVD-Daten gefunden
            }
        }

        libxml_use_internal_errors(false);
        return $isValid;
    }

    public function moveUploadedFile(array $file): array
    {
        $validation = $this->validateUpload($file);
        if (!$validation['success']) {
            return $validation;
        }

        // Sicheren Dateinamen generieren
        $originalName = pathinfo($file['name'], PATHINFO_FILENAME);
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);

        // Eindeutigen Dateinamen generieren falls bereits vorhanden
        $counter = 1;
        $finalName = $safeName . '.' . $extension;
        while (file_exists($this->uploadDir . $finalName)) {
            $finalName = $safeName . '_' . $counter . '.' . $extension;
            $counter++;
        }

        $targetPath = $this->uploadDir . $finalName;

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'message' => 'Datei konnte nicht gespeichert werden.'];
        }

        // Dateiberechtigungen setzen
        chmod($targetPath, 0644);

        return [
            'success' => true,
            'message' => "Datei erfolgreich hochgeladen: {$finalName}",
            'filename' => $finalName,
            'path' => $targetPath
        ];
    }

    public function deleteFile(string $filename): array
    {
        // Dateiname validieren
        if (!preg_match('/^[a-zA-Z0-9._-]+\.(xml|zip)$/i', $filename)) {
            return ['success' => false, 'message' => 'Ungültiger Dateiname.'];
        }

        $filePath = $this->uploadDir . $filename;

        // Sicherheitsprüfung: Datei muss im Upload-Verzeichnis sein
        $realPath = realpath($filePath);
        $realUploadDir = realpath($this->uploadDir);

        if (!$realPath || !$realUploadDir || !str_starts_with($realPath, $realUploadDir)) {
            return ['success' => false, 'message' => 'Ungültiger Dateipfad.'];
        }

        if (!file_exists($realPath)) {
            return ['success' => false, 'message' => 'Datei nicht gefunden.'];
        }

        if (!unlink($realPath)) {
            return ['success' => false, 'message' => 'Datei konnte nicht gelöscht werden.'];
        }

        return ['success' => true, 'message' => "Datei '{$filename}' erfolgreich gelöscht."];
    }

    public function listFiles(): array
    {
        $files = [];
        $pattern = $this->uploadDir . '*.{xml,zip}';
        $foundFiles = glob($pattern, GLOB_BRACE);

        foreach ($foundFiles as $file) {
            $filename = basename($file);
            // Zusätzliche Sicherheitsprüfung
            if (preg_match('/^[a-zA-Z0-9._-]+\.(xml|zip)$/i', $filename)) {
                $files[] = [
                    'name' => $filename,
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'extension' => strtolower(pathinfo($filename, PATHINFO_EXTENSION))
                ];
            }
        }

        // Nach Änderungsdatum sortieren (neueste zuerst)
        usort($files, fn($a, $b) => $b['modified'] - $a['modified']);

        return $files;
    }
}

// File-Handler initialisieren
$fileUploader = new SecureFileUploader($uploadDir, $maxFileSize, $allowedExtensions, $allowedMimeTypes);

$message = '';
$error = '';

// POST-Handler mit CSRF-Schutz
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF-Token prüfen
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($submittedToken)) {
        $error = '❌ Ungültiger CSRF-Token. Bitte versuchen Sie es erneut.';
    } else {
        // Datei löschen
        if (isset($_POST['delete_file'])) {
            $result = $fileUploader->deleteFile($_POST['delete_file']);
            if ($result['success']) {
                $message = '✅ ' . $result['message'];
            } else {
                $error = '❌ ' . $result['message'];
            }
        }
    }
}

// Import-Ergebnis aus Session anzeigen
if (!empty($_SESSION['import_result'])) {
    $importResult = $_SESSION['import_result'];
    unset($_SESSION['import_result']);

    // XSS-Schutz für Session-Nachrichten
    $message = '✅ ' . htmlspecialchars($importResult, ENT_QUOTES, 'UTF-8');
}

// Server-Limits abrufen
$maxUploadIni = ini_get('upload_max_filesize');
$maxPostIni = ini_get('post_max_size');
$memoryLimit = ini_get('memory_limit');

// Dateien auflisten
$files = $fileUploader->listFiles();

// Formatierungshelfer
function formatFileSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 1) . ' ' . $units[$i];
}

function getFileIcon(string $extension): string
{
    return match ($extension) {
        'xml' => '📄',
        'zip' => '📦',
        default => '📁'
    };
}
?>

<div class="container-fluid">
    <h3>📥 Datei-Import</h3>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Upload-Formular -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">🔄 Neue Datei hochladen</h5>
        </div>
        <div class="card-body">
            <form action="<?= htmlspecialchars(BASE_URL) ?>/admin/actions/import-handler.php" method="post"
                enctype="multipart/form-data" id="uploadForm">

                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

                <div class="mb-3">
                    <label for="xml_file" class="form-label">Datei auswählen</label>
                    <input type="file" name="xml_file" id="xml_file" class="form-control" required accept=".xml,.zip"
                        data-max-size="<?= $maxFileSize ?>">

                    <div class="form-text">
                        <strong>Erlaubte Dateitypen:</strong> XML, ZIP (mit XML-Inhalt)<br>
                        <strong>Maximale Größe:</strong> <?= formatFileSize($maxFileSize) ?><br>
                        <strong>Server-Limits:</strong> Upload: <?= htmlspecialchars($maxUploadIni) ?>,
                        POST: <?= htmlspecialchars($maxPostIni) ?>,
                        Memory: <?= htmlspecialchars($memoryLimit) ?>
                    </div>
                </div>

                <div class="mb-3">
                    <div class="form-check">
                        <input type="checkbox" id="validate_before_upload" class="form-check-input" checked>
                        <label for="validate_before_upload" class="form-check-label">
                            Datei vor Upload validieren (empfohlen)
                        </label>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary" id="uploadBtn">
                        <span class="spinner-border spinner-border-sm d-none" id="uploadSpinner"></span>
                        📤 Import starten
                    </button>
                    <button type="button" class="btn btn-secondary"
                        onclick="document.getElementById('xml_file').click()">
                        📁 Datei wählen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Datei-Liste -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">📁 Hochgeladene Dateien (<?= count($files) ?>)</h5>
            <small class="text-muted">Verzeichnis: <code><?= htmlspecialchars($uploadDir) ?></code></small>
        </div>
        <div class="card-body">
            <?php if (empty($files)): ?>
                <div class="text-center text-muted py-4">
                    <i class="fs-1">📭</i>
                    <p class="mt-2">Keine Dateien vorhanden</p>
                    <small>Laden Sie XML- oder ZIP-Dateien hoch, um zu beginnen.</small>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="40"></th>
                                <th>Dateiname</th>
                                <th>Größe</th>
                                <th>Hochgeladen</th>
                                <th width="150">Aktionen</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($files as $file): ?>
                                <tr>
                                    <td class="text-center">
                                        <?= getFileIcon($file['extension']) ?>
                                    </td>
                                    <td>
                                        <span class="fw-medium"><?= htmlspecialchars($file['name']) ?></span>
                                        <br>
                                        <small
                                            class="text-muted text-uppercase"><?= htmlspecialchars($file['extension']) ?>-Datei</small>
                                    </td>
                                    <td><?= formatFileSize($file['size']) ?></td>
                                    <td>
                                        <span title="<?= date('d.m.Y H:i:s', $file['modified']) ?>">
                                            <?= date('d.m.Y', $file['modified']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="xml/<?= urlencode($file['name']) ?>" target="_blank"
                                                class="btn btn-outline-primary" title="Datei anzeigen">
                                                👁️ Anzeigen
                                            </a>

                                            <form method="post" style="display:inline;"
                                                onsubmit="return confirm('Datei <?= htmlspecialchars($file['name']) ?> wirklich löschen?')">
                                                <input type="hidden" name="csrf_token"
                                                    value="<?= htmlspecialchars($csrfToken) ?>">
                                                <input type="hidden" name="delete_file"
                                                    value="<?= htmlspecialchars($file['name']) ?>">
                                                <button type="submit" class="btn btn-outline-danger" title="Datei löschen">
                                                    🗑️
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const fileInput = document.getElementById('xml_file');
        const uploadForm = document.getElementById('uploadForm');
        const uploadBtn = document.getElementById('uploadBtn');
        const uploadSpinner = document.getElementById('uploadSpinner');
        const validateCheckbox = document.getElementById('validate_before_upload');
        const maxSize = parseInt(fileInput.dataset.maxSize);

        // Datei-Validierung
        fileInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            // Größe prüfen
            if (file.size > maxSize) {
                alert(`Datei zu groß! Maximum: ${(maxSize / 1024 / 1024).toFixed(1)}MB`);
                this.value = '';
                return;
            }

            // Extension prüfen
            const allowedTypes = ['xml', 'zip'];
            const extension = file.name.split('.').pop().toLowerCase();
            if (!allowedTypes.includes(extension)) {
                alert('Ungültiger Dateityp! Erlaubt: ' + allowedTypes.join(', '));
                this.value = '';
                return;
            }

            // Client-seitige XML-Validierung
            if (validateCheckbox.checked && extension === 'xml') {
                const reader = new FileReader();
                reader.onload = function (e) {
                    try {
                        const parser = new DOMParser();
                        const xmlDoc = parser.parseFromString(e.target.result, 'text/xml');
                        const parseError = xmlDoc.getElementsByTagName('parsererror');

                        if (parseError.length > 0) {
                            alert('Ungültige XML-Datei!');
                            fileInput.value = '';
                            return;
                        }

                        // Nach DVD-Elementen suchen
                        const dvdElements = xmlDoc.getElementsByTagName('DVD');
                        const collectionElements = xmlDoc.getElementsByTagName('Collection');

                        if (dvdElements.length === 0 && collectionElements.length === 0) {
                            if (!confirm('Keine DVD-Daten in der XML-Datei gefunden. Trotzdem hochladen?')) {
                                fileInput.value = '';
                                return;
                            }
                        }

                        console.log(`✅ XML validiert: ${dvdElements.length} DVD(s), ${collectionElements.length} Collection(s)`);
                    } catch (error) {
                        console.error('XML-Validierung fehlgeschlagen:', error);
                        if (!confirm('XML-Validierung fehlgeschlagen. Trotzdem hochladen?')) {
                            fileInput.value = '';
                        }
                    }
                };
                reader.readAsText(file);
            }

            console.log(`📁 Datei ausgewählt: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)}MB)`);
        });

        // Upload-Progress
        uploadForm.addEventListener('submit', function () {
            uploadBtn.disabled = true;
            uploadSpinner.classList.remove('d-none');
            uploadBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Wird hochgeladen...';
        });

        // Auto-refresh nach 30 Sekunden
        setTimeout(() => {
            if (document.hidden) return; // Nur wenn Tab aktiv
            window.location.reload();
        }, 30000);
    });
</script>