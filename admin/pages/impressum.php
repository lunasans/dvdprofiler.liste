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
                                    Impressum Inhalt (HTML erlaubt)
                                    <i class="bi bi-info-circle" 
                                       data-bs-toggle="tooltip" 
                                       title="Sie können HTML-Formatierung verwenden. Gefährlicher Code wird automatisch entfernt."></i>
                                </label>
                                <textarea class="form-control" 
                                          id="impressum_content" 
                                          name="impressum_content" 
                                          rows="15"><?= htmlspecialchars($impressumContent) ?></textarea>
                                <small class="form-text text-muted">
                                    Erlaubte HTML-Tags: &lt;p&gt;, &lt;br&gt;, &lt;b&gt;, &lt;i&gt;, &lt;u&gt;, &lt;strong&gt;, 
                                    &lt;em&gt;, &lt;ul&gt;, &lt;ol&gt;, &lt;li&gt;, &lt;h2&gt;, &lt;h3&gt;, &lt;a&gt;
                                </small>
                            </div>
                            
                            <!-- HTML Toolbar -->
                            <div class="btn-toolbar mb-3" role="toolbar">
                                <div class="btn-group btn-group-sm me-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('p')">
                                        <i class="bi bi-paragraph"></i> Absatz
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('h2')">
                                        H2
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('h3')">
                                        H3
                                    </button>
                                </div>
                                <div class="btn-group btn-group-sm me-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('b')">
                                        <i class="bi bi-type-bold"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('i')">
                                        <i class="bi bi-type-italic"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertTag('u')">
                                        <i class="bi bi-type-underline"></i>
                                    </button>
                                </div>
                                <div class="btn-group btn-group-sm me-2" role="group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertList('ul')">
                                        <i class="bi bi-list-ul"></i> Liste
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertList('ol')">
                                        <i class="bi bi-list-ol"></i> Nummeriert
                                    </button>
                                </div>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-secondary" onclick="insertLink()">
                                        <i class="bi bi-link-45deg"></i> Link
                                    </button>
                                </div>
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

<script>
// HTML Helper Functions
const textarea = document.getElementById('impressum_content');

function insertTag(tag) {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const selectedText = text.substring(start, end);
    
    const before = text.substring(0, start);
    const after = text.substring(end);
    
    const newText = `<${tag}>${selectedText || 'Text hier'}</${tag}>`;
    textarea.value = before + newText + after;
    
    // Cursor positionieren
    const newPos = start + tag.length + 2 + (selectedText ? selectedText.length : 0);
    textarea.setSelectionRange(newPos, newPos);
    textarea.focus();
}

function insertList(type) {
    const start = textarea.selectionStart;
    const text = textarea.value;
    
    const before = text.substring(0, start);
    const after = text.substring(start);
    
    const list = `<${type}>
    <li>Punkt 1</li>
    <li>Punkt 2</li>
    <li>Punkt 3</li>
</${type}>`;
    
    textarea.value = before + list + after;
    textarea.focus();
}

function insertLink() {
    const start = textarea.selectionStart;
    const end = textarea.selectionEnd;
    const text = textarea.value;
    const selectedText = text.substring(start, end);
    
    const url = prompt('Link URL:', 'https://');
    if (!url) return;
    
    const before = text.substring(0, start);
    const after = text.substring(end);
    
    const link = `<a href="${url}" target="_blank">${selectedText || 'Link-Text'}</a>`;
    textarea.value = before + link + after;
    textarea.focus();
}

// Tooltips initialisieren
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    new bootstrap.Tooltip(el);
});
</script>

<style>
.btn-toolbar {
    background: var(--bs-light);
    padding: 0.5rem;
    border-radius: 0.25rem;
}

#impressum_content {
    font-family: 'Courier New', monospace;
    font-size: 0.9rem;
}
</style>