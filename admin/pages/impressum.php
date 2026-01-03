<?php
/**
 * DVD Profiler Liste - Impressum Verwaltung
 * 
 * @package    dvdprofiler.liste
 * @version    1.4.8
 */

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Settings Helper laden (falls nicht in bootstrap.php)
if (!function_exists('getSetting')) {
    require_once __DIR__ . '/../../includes/settings-helper.php';
}

// CSRF-Token generieren
$csrfToken = generateCSRFToken();

// Success/Error Messages
$success = '';
$error = '';

if (isset($_SESSION['impressum_success'])) {
    $success = $_SESSION['impressum_success'];
    unset($_SESSION['impressum_success']);
}

if (isset($_SESSION['impressum_error'])) {
    $error = $_SESSION['impressum_error'];
    unset($_SESSION['impressum_error']);
}

// Lade aktuelles Impressum
$impressumContent = getSetting('impressum_content', '');
$impressumName = getSetting('impressum_name', DVDPROFILER_AUTHOR);
$impressumEmail = getSetting('impressum_email', 'kontakt@example.com');
$impressumEnabled = getSetting('impressum_enabled', '1');
?>

<div class="container-fluid px-4">
    <h1 class="mt-4">
        <i class="bi bi-info-circle"></i> Impressum verwalten
    </h1>
    <ol class="breadcrumb mb-4">
        <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
        <li class="breadcrumb-item active">Impressum</li>
    </ol>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-xl-8">
            <!-- Haupt-Content -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-file-text"></i> Impressum Inhalt
                </div>
                <div class="card-body">
                    <form method="post" action="actions/save-impressum.php" id="impressumForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        
                        <!-- Kontakt-Informationen -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-3">Kontaktdaten</h5>
                            
                            <div class="mb-3">
                                <label for="impressum_name" class="form-label">Name / Firma</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="impressum_name" 
                                       name="impressum_name" 
                                       value="<?= htmlspecialchars($impressumName) ?>"
                                       required>
                                <small class="form-text text-muted">Ihr vollständiger Name oder Firmenname</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="impressum_email" class="form-label">E-Mail</label>
                                <input type="email" 
                                       class="form-control" 
                                       id="impressum_email" 
                                       name="impressum_email" 
                                       value="<?= htmlspecialchars($impressumEmail) ?>"
                                       required>
                                <small class="form-text text-muted">Kontakt-E-Mail-Adresse</small>
                            </div>
                        </div>
                        
                        <!-- Freitext-Content -->
                        <div class="mb-4">
                            <h5 class="border-bottom pb-2 mb-3">Zusätzliche Informationen</h5>
                            
                            <div class="mb-3">
                                <label for="impressum_content" class="form-label">
                                    Impressum Inhalt
                                    <i class="bi bi-info-circle" 
                                       data-bs-toggle="tooltip" 
                                       title="Verwenden Sie den Editor um Ihren Text zu formatieren. Gefährlicher Code wird automatisch entfernt."></i>
                                </label>
                                
                                <!-- Quill Editor -->
                                <div id="quill-editor" style="height: 400px;"></div>
                                
                                <!-- Hidden Textarea für Form Submit -->
                                <textarea id="impressum_content" 
                                          name="impressum_content" 
                                          style="display:none;"></textarea>
                                
                                <small class="form-text text-muted mt-2 d-block">
                                    Nutzen Sie die Toolbar um Text zu formatieren, Listen zu erstellen und Links einzufügen.
                                </small>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save"></i> Speichern
                            </button>
                            <a href="?page=impressum-preview" class="btn btn-outline-secondary" target="_blank">
                                <i class="bi bi-eye"></i> Vorschau
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-xl-4">
            <!-- Sidebar -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-gear"></i> Einstellungen
                </div>
                <div class="card-body">
                    <form method="post" action="actions/save-impressum.php">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="settings_only" value="1">
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="impressum_enabled" 
                                       name="impressum_enabled" 
                                       value="1"
                                       <?= $impressumEnabled == '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="impressum_enabled">
                                    Impressum aktivieren
                                </label>
                            </div>
                            <small class="form-text text-muted">
                                Impressum im Footer anzeigen
                            </small>
                        </div>
                        
                        <button type="submit" class="btn btn-sm btn-primary">
                            <i class="bi bi-check-lg"></i> Speichern
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-header">
                    <i class="bi bi-lightbulb"></i> Tipps
                </div>
                <div class="card-body">
                    <h6>HTML-Formatierung</h6>
                    <p class="small">
                        Sie können HTML-Tags verwenden um Ihren Text zu formatieren. 
                        Gefährlicher Code (JavaScript, etc.) wird automatisch entfernt.
                    </p>
                    
                    <h6>Rechtliche Hinweise</h6>
                    <p class="small">
                        Stellen Sie sicher dass Ihr Impressum alle rechtlich erforderlichen 
                        Informationen gemäß TMG § 5 enthält.
                    </p>
                    
                    <h6>Vorschau</h6>
                    <p class="small">
                        Nutzen Sie die Vorschau-Funktion um zu sehen wie das Impressum 
                        auf der Website aussieht.
                    </p>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <i class="bi bi-link-45deg"></i> Links
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <a href="?page=impressum-preview" target="_blank">
                                <i class="bi bi-box-arrow-up-right"></i> Impressum anzeigen
                            </a>
                        </li>
                        <li class="mb-2">
                            <a href="https://www.e-recht24.de/impressum-generator.html" target="_blank" rel="noopener">
                                <i class="bi bi-box-arrow-up-right"></i> Impressum Generator
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quill CSS -->
<link href="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.snow.css" rel="stylesheet">

<!-- Quill JS -->
<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>

<script>
// Warte bis Quill geladen ist
window.addEventListener('load', function() {
    // Quill Editor initialisieren
    const quill = new Quill('#quill-editor', {
        theme: 'snow',
        modules: {
            toolbar: [
                [{ 'header': [2, 3, false] }],
                ['bold', 'italic', 'underline'],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link'],
                ['clean']
            ]
        },
        placeholder: 'Geben Sie hier zusätzliche Informationen für Ihr Impressum ein...'
    });

    // Existierenden Content laden
    try {
        const existingContent = <?= json_encode($impressumContent, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        if (existingContent && existingContent.trim() !== '') {
            console.log('Loading content:', existingContent);
            quill.clipboard.dangerouslyPasteHTML(existingContent);
        } else {
            console.log('No content to load');
        }
    } catch (e) {
        console.error('Error loading content:', e);
    }

    // Form Submit: Quill HTML in Textarea kopieren
    document.getElementById('impressumForm').addEventListener('submit', function(e) {
        const html = quill.root.innerHTML;
        // Leere Quill-Defaults nicht speichern
        if (html === '<p><br></p>') {
            document.getElementById('impressum_content').value = '';
        } else {
            document.getElementById('impressum_content').value = html;
        }
    });

    // Tooltips initialisieren
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
        new bootstrap.Tooltip(el);
    });
});
</script>

<style>
.ql-editor {
    min-height: 300px;
    max-height: 500px;
    overflow-y: auto;
    font-size: 1rem;
}

.ql-toolbar.ql-snow {
    border-radius: 0.375rem 0.375rem 0 0;
    background: #f8f9fa;
}

.ql-container.ql-snow {
    border-radius: 0 0 0.375rem 0.375rem;
    font-family: inherit;
}

#quill-editor {
    background: white;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
}
</style>