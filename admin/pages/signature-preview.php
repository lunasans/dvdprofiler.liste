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

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2 class="mb-1">
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

<!-- Variante 1 -->
<?php if (getSetting('signature_enable_type1', '1') == '1'): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-grid-3x3"></i> Variante 1: Cover Grid + Statistik
            <span class="badge bg-success ms-2">Aktiviert</span>
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            Statistik-Box links + <?= getSetting('signature_film_count', '10') ?> Film-Cover in kompakter Ansicht
        </p>
        
        <!-- Banner Preview -->
        <div class="p-3 bg-light border rounded text-center mb-3">
            <img src="<?= $baseUrl ?>/signature.php?type=1&t=<?= time() ?>" 
                 alt="Banner Typ 1" 
                 class="img-fluid rounded"
                 style="max-width: 100%; height: auto;">
        </div>
        
        <!-- URLs -->
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-link-45deg"></i> Direkt-URL
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               value="<?= $baseUrl ?>/signature.php?type=1" 
                               id="url1" 
                               readonly>
                        <button class="btn btn-outline-primary" 
                                type="button" 
                                onclick="copyUrl('url1')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-code-square"></i> BBCode für Foren
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               value="[img]<?= $baseUrl ?>/signature.php?type=1[/img]" 
                               id="bb1" 
                               readonly>
                        <button class="btn btn-outline-primary" 
                                type="button" 
                                onclick="copyUrl('bb1')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Variante 2 -->
<?php if (getSetting('signature_enable_type2', '1') == '1'): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-bar-chart"></i> Variante 2: Cover + Statistiken
            <span class="badge bg-success ms-2">Aktiviert</span>
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            Header mit Sammlungs-Statistiken + <?= getSetting('signature_film_count', '10') ?> neueste Filme
        </p>
        
        <!-- Banner Preview -->
        <div class="p-3 bg-light border rounded text-center mb-3">
            <img src="<?= $baseUrl ?>/signature.php?type=2&t=<?= time() ?>" 
                 alt="Banner Typ 2" 
                 class="img-fluid rounded"
                 style="max-width: 100%; height: auto;">
        </div>
        
        <!-- URLs -->
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-link-45deg"></i> Direkt-URL
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               value="<?= $baseUrl ?>/signature.php?type=2" 
                               id="url2" 
                               readonly>
                        <button class="btn btn-outline-primary" 
                                type="button" 
                                onclick="copyUrl('url2')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-code-square"></i> BBCode für Foren
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               value="[img]<?= $baseUrl ?>/signature.php?type=2[/img]" 
                               id="bb2" 
                               readonly>
                        <button class="btn btn-outline-primary" 
                                type="button" 
                                onclick="copyUrl('bb2')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Variante 3 -->
<?php if (getSetting('signature_enable_type3', '1') == '1'): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-view-list"></i> Variante 3: Compact Liste
            <span class="badge bg-success ms-2">Aktiviert</span>
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            Eine Zeile mit Covern
            <?php if (getSetting('signature_show_title', '1') == '1'): ?>+ Titeln<?php endif; ?>
            <?php if (getSetting('signature_show_year', '1') == '1'): ?>+ Jahren<?php endif; ?>
        </p>
        
        <!-- Banner Preview -->
        <div class="p-3 bg-light border rounded text-center mb-3">
            <img src="<?= $baseUrl ?>/signature.php?type=3&t=<?= time() ?>" 
                 alt="Banner Typ 3" 
                 class="img-fluid rounded"
                 style="max-width: 100%; height: auto;">
        </div>
        
        <!-- URLs -->
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-link-45deg"></i> Direkt-URL
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               value="<?= $baseUrl ?>/signature.php?type=3" 
                               id="url3" 
                               readonly>
                        <button class="btn btn-outline-primary" 
                                type="button" 
                                onclick="copyUrl('url3')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-code-square"></i> BBCode für Foren
                    </label>
                    <div class="input-group">
                        <input type="text" 
                               class="form-control" 
                               value="[img]<?= $baseUrl ?>/signature.php?type=3[/img]" 
                               id="bb3" 
                               readonly>
                        <button class="btn btn-outline-primary" 
                                type="button" 
                                onclick="copyUrl('bb3')">
                            <i class="bi bi-clipboard"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Info-Card -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-info-circle"></i> Aktuelle Einstellungen
        </h5>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <ul class="list-unstyled mb-3" style="color: var(--bs-body-color);">
                    <li class="mb-2">
                        <i class="bi bi-collection text-primary"></i>
                        <strong>Anzahl Filme:</strong> <?= getSetting('signature_film_count', '10') ?>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-funnel text-primary"></i>
                        <strong>Film-Quelle:</strong> 
                        <?php 
                        $sources = [
                            'newest' => 'Neueste',
                            'newest_release' => 'Neueste Veröffentlichungen',
                            'best_rated' => 'Bestbewertet',
                            'random' => 'Zufällig'
                        ];
                        echo $sources[getSetting('signature_film_source', 'newest')] ?? 'Neueste';
                        ?>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-clock text-primary"></i>
                        <strong>Cache-Zeit:</strong> <?= getSetting('signature_cache_time', '3600') / 60 ?> Minuten
                    </li>
                </ul>
            </div>
            <div class="col-md-6">
                <ul class="list-unstyled mb-3" style="color: var(--bs-body-color);">
                    <li class="mb-2">
                        <i class="bi bi-image text-primary"></i>
                        <strong>Bildqualität:</strong> <?= getSetting('signature_quality', '9') ?>/9
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-tag text-primary"></i>
                        <strong>Titel anzeigen:</strong> 
                        <?= getSetting('signature_show_title', '1') == '1' ? '<span class="badge bg-success">Ja</span>' : '<span class="badge bg-secondary">Nein</span>' ?>
                    </li>
                    <li class="mb-2">
                        <i class="bi bi-calendar text-primary"></i>
                        <strong>Jahr anzeigen:</strong> 
                        <?= getSetting('signature_show_year', '1') == '1' ? '<span class="badge bg-success">Ja</span>' : '<span class="badge bg-secondary">Nein</span>' ?>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="d-flex gap-2 mt-3">
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
        const text = element.value;
        
        navigator.clipboard.writeText(text).then(() => {
            // Visuelles Feedback
            const button = event.target.closest('button');
            const originalHTML = button.innerHTML;
            button.innerHTML = '<i class="bi bi-check"></i> Kopiert!';
            button.classList.add('btn-success');
            button.classList.remove('btn-outline-primary');
            
            setTimeout(() => {
                button.innerHTML = originalHTML;
                button.classList.add('btn-outline-primary');
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