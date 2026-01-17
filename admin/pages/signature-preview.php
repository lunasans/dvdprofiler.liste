<?php
/**
 * Signatur-Banner Vorschau
 * Zeigt alle verfügbaren Banner-Varianten im Admin-Panel
 */

// Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host . dirname(dirname($_SERVER['PHP_SELF']));
$baseUrl = rtrim($baseUrl, '/');
?>

<style>
    .banner-card {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    
    .banner-preview {
        background: #f8f9fa;
        border: 2px dashed #dee2e6;
        border-radius: 8px;
        padding: 1rem;
        text-align: center;
        margin: 1rem 0;
    }
    
    .banner-preview img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
    }
    
    .url-box {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 1rem;
        margin: 1rem 0;
    }
    
    .url-box code {
        display: block;
        padding: 0.5rem;
        background: white;
        border-radius: 4px;
        margin: 0.5rem 0;
        word-break: break-all;
    }
    
    .btn-copy {
        margin-top: 0.5rem;
    }
    
    .badge-status {
        font-size: 0.85rem;
        padding: 0.35rem 0.75rem;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2>
                        <i class="bi bi-image"></i> Signatur-Banner Vorschau
                    </h2>
                    <p class="text-muted mb-0">
                        Alle verfügbaren Banner-Varianten mit aktuellen Einstellungen
                    </p>
                </div>
                <div>
                    <a href="?page=settings#signature" class="btn btn-primary">
                        <i class="bi bi-gear"></i> Einstellungen
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Variante 1 -->
    <?php if (getSetting('signature_enable_type1', '1') == '1'): ?>
    <div class="banner-card">
        <h3 class="mb-3">
            <i class="bi bi-grid-3x3"></i> Variante 1: Cover Grid
            <span class="badge bg-success badge-status">Aktiviert</span>
        </h3>
        <p class="text-muted">
            <?= getSetting('signature_film_count', '10') ?> Film-Cover nebeneinander in kompakter Ansicht
        </p>
        
        <div class="banner-preview">
            <img src="<?= $baseUrl ?>/signature.php?type=1&t=<?= time() ?>" alt="Banner Typ 1">
        </div>
        
        <div class="url-box">
            <strong>Direkt-URL:</strong>
            <code id="url1"><?= $baseUrl ?>/signature.php?type=1</code>
            <button class="btn btn-sm btn-primary btn-copy" onclick="copyUrl('url1')">
                <i class="bi bi-clipboard"></i> Kopieren
            </button>
        </div>
        
        <div class="url-box">
            <strong>BBCode für Foren:</strong>
            <code id="bb1">[img]<?= $baseUrl ?>/signature.php?type=1[/img]</code>
            <button class="btn btn-sm btn-primary btn-copy" onclick="copyUrl('bb1')">
                <i class="bi bi-clipboard"></i> Kopieren
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Variante 2 -->
    <?php if (getSetting('signature_enable_type2', '1') == '1'): ?>
    <div class="banner-card">
        <h3 class="mb-3">
            <i class="bi bi-bar-chart"></i> Variante 2: Cover + Statistiken
            <span class="badge bg-success badge-status">Aktiviert</span>
        </h3>
        <p class="text-muted">
            Header mit Sammlungs-Statistiken + <?= getSetting('signature_film_count', '10') ?> neueste Filme
        </p>
        
        <div class="banner-preview">
            <img src="<?= $baseUrl ?>/signature.php?type=2&t=<?= time() ?>" alt="Banner Typ 2">
        </div>
        
        <div class="url-box">
            <strong>Direkt-URL:</strong>
            <code id="url2"><?= $baseUrl ?>/signature.php?type=2</code>
            <button class="btn btn-sm btn-primary btn-copy" onclick="copyUrl('url2')">
                <i class="bi bi-clipboard"></i> Kopieren
            </button>
        </div>
        
        <div class="url-box">
            <strong>BBCode für Foren:</strong>
            <code id="bb2">[img]<?= $baseUrl ?>/signature.php?type=2[/img]</code>
            <button class="btn btn-sm btn-primary btn-copy" onclick="copyUrl('bb2')">
                <i class="bi bi-clipboard"></i> Kopieren
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Variante 3 -->
    <?php if (getSetting('signature_enable_type3', '1') == '1'): ?>
    <div class="banner-card">
        <h3 class="mb-3">
            <i class="bi bi-view-list"></i> Variante 3: Compact Liste
            <span class="badge bg-success badge-status">Aktiviert</span>
        </h3>
        <p class="text-muted">
            2 Zeilen mit Covern
            <?php if (getSetting('signature_show_title', '1') == '1'): ?>+ Titeln<?php endif; ?>
            <?php if (getSetting('signature_show_year', '1') == '1'): ?>+ Jahren<?php endif; ?>
        </p>
        
        <div class="banner-preview">
            <img src="<?= $baseUrl ?>/signature.php?type=3&t=<?= time() ?>" alt="Banner Typ 3">
        </div>
        
        <div class="url-box">
            <strong>Direkt-URL:</strong>
            <code id="url3"><?= $baseUrl ?>/signature.php?type=3</code>
            <button class="btn btn-sm btn-primary btn-copy" onclick="copyUrl('url3')">
                <i class="bi bi-clipboard"></i> Kopieren
            </button>
        </div>
        
        <div class="url-box">
            <strong>BBCode für Foren:</strong>
            <code id="bb3">[img]<?= $baseUrl ?>/signature.php?type=3[/img]</code>
            <button class="btn btn-sm btn-primary btn-copy" onclick="copyUrl('bb3')">
                <i class="bi bi-clipboard"></i> Kopieren
            </button>
        </div>
    </div>
    <?php endif; ?>

    <!-- Info-Box -->
    <div class="banner-card">
        <h4><i class="bi bi-info-circle"></i> Aktuelle Einstellungen</h4>
        <div class="row">
            <div class="col-md-6">
                <ul class="list-unstyled">
                    <li><strong>Anzahl Filme:</strong> <?= getSetting('signature_film_count', '10') ?></li>
                    <li><strong>Film-Quelle:</strong> <?= getSetting('signature_film_source', 'newest') ?></li>
                    <li><strong>Cache-Zeit:</strong> <?= getSetting('signature_cache_time', '3600') / 60 ?> Minuten</li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="list-unstyled">
                    <li><strong>Bildqualität:</strong> <?= getSetting('signature_quality', '9') ?>/9</li>
                    <li><strong>Titel anzeigen:</strong> <?= getSetting('signature_show_title', '1') == '1' ? 'Ja' : 'Nein' ?></li>
                    <li><strong>Jahr anzeigen:</strong> <?= getSetting('signature_show_year', '1') == '1' ? 'Ja' : 'Nein' ?></li>
                </ul>
            </div>
        </div>
        
        <div class="mt-3">
            <button class="btn btn-warning" onclick="clearCache()">
                <i class="bi bi-trash"></i> Cache leeren
            </button>
            <button class="btn btn-secondary" onclick="location.reload()">
                <i class="bi bi-arrow-clockwise"></i> Neu laden
            </button>
        </div>
    </div>
</div>

<script>
    function copyUrl(elementId) {
        const element = document.getElementById(elementId);
        const text = element.textContent;
        
        navigator.clipboard.writeText(text).then(() => {
            // Visuelles Feedback
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check"></i> Kopiert!';
            button.classList.add('btn-success');
            button.classList.remove('btn-primary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.add('btn-primary');
                button.classList.remove('btn-success');
            }, 2000);
        });
    }
    
    function clearCache() {
        if (!confirm('Cache wirklich leeren?')) return;
        
        fetch('<?= $baseUrl ?>/signature.php?clear_cache=1')
            .then(() => {
                alert('✅ Cache geleert!');
                location.reload();
            })
            .catch(err => alert('❌ Fehler: ' + err));
    }
</script>