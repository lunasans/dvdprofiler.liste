<?php
declare(strict_types=1);

// Bootstrap.php ist bereits durch admin/index.php geladen - NICHT nochmal laden!

// Security: Session und Berechtigung prüfen
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// CSRF-Token generieren (Funktion ist bereits durch bootstrap.php verfügbar)
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
            return ['success' => false, 'message' => 'Ungültiger MIME-Type. Erlaubt: ' . implode(', ', $this->allowedMimeTypes)];
        }

        return ['success' => true, 'message' => 'Datei ist gültig.'];
    }

    public function moveUploadedFile(array $file, string $targetFilename): array
    {
        $validation = $this->validateUpload($file);
        if (!$validation['success']) {
            return $validation;
        }

        // Sichere Ziel-Datei generieren
        $targetPath = $this->uploadDir . basename($targetFilename);
        
        // Datei verschieben
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => false, 'message' => 'Datei konnte nicht gespeichert werden.'];
        }

        return [
            'success' => true, 
            'message' => 'Datei erfolgreich hochgeladen.',
            'file_path' => $targetPath
        ];
    }
}

// Upload-Handler
$uploadResult = null;
$importResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // CSRF-Token validieren
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('Ungültiges CSRF-Token.');
        }

        if (isset($_FILES['xml_file'])) {
            $uploader = new SecureFileUploader($uploadDir, $maxFileSize, $allowedExtensions, $allowedMimeTypes);
            
            // Eindeutigen Dateinamen generieren
            $timestamp = date('Ymd_His');
            $randomId = bin2hex(random_bytes(6));
            $extension = strtolower(pathinfo($_FILES['xml_file']['name'], PATHINFO_EXTENSION));
            $targetFilename = "import_{$timestamp}_{$randomId}.{$extension}";
            
            $uploadResult = $uploader->moveUploadedFile($_FILES['xml_file'], $targetFilename);
            
            if ($uploadResult['success']) {
                // Nach erfolgreichem Upload: Import starten
                $xmlFile = $uploadResult['file_path'];
                
                // Import-Funktionen laden
                require_once dirname(__DIR__, 2) . '/includes/functions.php';
                
                if (function_exists('importDvdCollection')) {
                    try {
                        $importResult = importDvdCollection($xmlFile, $pdo);
                    } catch (Exception $e) {
                        $importResult = [
                            'success' => false,
                            'message' => 'Import-Fehler: ' . $e->getMessage(),
                            'details' => []
                        ];
                    }
                } else {
                    $importResult = [
                        'success' => false,
                        'message' => 'Import-Funktion nicht verfügbar.',
                        'details' => []
                    ];
                }
            }
        }
    } catch (Exception $e) {
        $uploadResult = [
            'success' => false,
            'message' => 'Fehler: ' . $e->getMessage()
        ];
    }
}

// Vorhandene XML-Dateien auflisten
$xmlFiles = [];
if (is_dir($uploadDir)) {
    $files = scandir($uploadDir);
    foreach ($files as $file) {
        if (preg_match('/\.xml$/i', $file)) {
            $filePath = $uploadDir . $file;
            $xmlFiles[] = [
                'name' => $file,
                'size' => filesize($filePath),
                'modified' => filemtime($filePath)
            ];
        }
    }
    // Nach Änderungsdatum sortieren (neuste zuerst)
    usort($xmlFiles, fn($a, $b) => $b['modified'] <=> $a['modified']);
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <!-- Page Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">📥 DVD Collection Import</h1>
                    <p class="text-muted">XML-Dateien von DVD Profiler oder anderen Quellen importieren</p>
                </div>
                <div class="btn-group">
                    <a href="?page=dashboard" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Zurück
                    </a>
                </div>
            </div>

            <!-- Status-Meldungen -->
            <?php if ($uploadResult): ?>
                <div class="alert alert-<?= $uploadResult['success'] ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $uploadResult['success'] ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <strong><?= $uploadResult['success'] ? 'Upload erfolgreich:' : 'Upload fehlgeschlagen:' ?></strong>
                    <?= htmlspecialchars($uploadResult['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($importResult): ?>
                <div class="alert alert-<?= $importResult['success'] ? 'success' : 'danger' ?> alert-dismissible fade show">
                    <i class="bi bi-<?= $importResult['success'] ? 'check-circle' : 'exclamation-triangle' ?>"></i>
                    <strong><?= $importResult['success'] ? 'Import erfolgreich:' : 'Import fehlgeschlagen:' ?></strong>
                    <?= htmlspecialchars($importResult['message']) ?>
                    
                    <?php if (isset($importResult['details']) && !empty($importResult['details'])): ?>
                        <div class="mt-2">
                            <details>
                                <summary>Import-Details anzeigen</summary>
                                <ul class="mt-2 mb-0">
                                    <?php foreach ($importResult['details'] as $detail): ?>
                                        <li><?= htmlspecialchars($detail) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </details>
                        </div>
                    <?php endif; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="row">
                <!-- Upload-Bereich -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-upload"></i>
                                Neue XML-Datei hochladen
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data" class="upload-form">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                
                                <div class="mb-3">
                                    <label for="xml_file" class="form-label">XML-Datei auswählen</label>
                                    <input type="file" 
                                           class="form-control" 
                                           id="xml_file" 
                                           name="xml_file" 
                                           accept=".xml,.zip"
                                           required>
                                    <div class="form-text">
                                        Erlaubte Formate: XML, ZIP | Max. Größe: <?= $maxFileSize / 1024 / 1024 ?>MB
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-upload"></i>
                                        Datei hochladen und importieren
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Upload-Hinweise -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-info-circle"></i>
                                Import-Hinweise
                            </h6>
                        </div>
                        <div class="card-body">
                            <ul class="mb-0">
                                <li><strong>DVD Profiler:</strong> Exportieren Sie Ihre Collection als XML</li>
                                <li><strong>Format:</strong> Standard Collection.xml wird unterstützt</li>
                                <li><strong>BoxSets:</strong> Werden automatisch erkannt und gruppiert</li>
                                <li><strong>Updates:</strong> Existierende Filme werden aktualisiert</li>
                                <li><strong>Sicherheit:</strong> Dateien werden validiert und sicher gespeichert</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Vorhandene Dateien -->
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-files"></i>
                                Vorhandene XML-Dateien
                            </h5>
                            <span class="badge bg-secondary"><?= count($xmlFiles) ?> Dateien</span>
                        </div>
                        <div class="card-body">
                            <?php if (empty($xmlFiles)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-file-earmark-x display-4"></i>
                                    <p class="mt-2">Noch keine XML-Dateien vorhanden</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($xmlFiles as $file): ?>
                                        <div class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?= htmlspecialchars($file['name']) ?></h6>
                                                <small class="text-muted">
                                                    <?= formatBytes($file['size']) ?> • 
                                                    <?= date('d.m.Y H:i', $file['modified']) ?>
                                                </small>
                                            </div>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm">
                                                    <i class="bi bi-download"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-sm">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.upload-form {
    border: 2px dashed var(--bs-border-color);
    border-radius: var(--bs-border-radius);
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
}

.upload-form:hover {
    border-color: var(--bs-primary);
    background-color: var(--bs-light);
}

.upload-form.dragover {
    border-color: var(--bs-success);
    background-color: var(--bs-success-bg-subtle);
}

.list-group-item {
    border-left: none;
    border-right: none;
}

.list-group-item:first-child {
    border-top: none;
}

.list-group-item:last-child {
    border-bottom: none;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const uploadForm = document.querySelector('.upload-form');
    const fileInput = document.getElementById('xml_file');
    
    // Drag & Drop functionality
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        uploadForm.addEventListener(eventName, preventDefaults, false);
    });
    
    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }
    
    ['dragenter', 'dragover'].forEach(eventName => {
        uploadForm.addEventListener(eventName, highlight, false);
    });
    
    ['dragleave', 'drop'].forEach(eventName => {
        uploadForm.addEventListener(eventName, unhighlight, false);
    });
    
    function highlight(e) {
        uploadForm.classList.add('dragover');
    }
    
    function unhighlight(e) {
        uploadForm.classList.remove('dragover');
    }
    
    uploadForm.addEventListener('drop', handleDrop, false);
    
    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        
        if (files.length > 0) {
            fileInput.files = files;
        }
    }
});
</script>