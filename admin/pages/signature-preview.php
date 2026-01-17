<?php
/**
 * Signatur-Banner Vorschau
 * Zeigt alle verfügbaren Banner-Varianten
 */

require_once __DIR__ . '/../bootstrap.php';

// Nur für eingeloggte User
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Base URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$baseUrl = $protocol . $host . dirname(dirname($_SERVER['PHP_SELF']));
$baseUrl = rtrim($baseUrl, '/');

$pageTitle = 'Signatur-Banner Vorschau';
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 2rem 0;
        }
        
        .container {
            max-width: 1000px;
        }
        
        .header {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
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
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">
                        <i class="bi bi-image"></i> Signatur-Banner Vorschau
                    </h1>
                    <p class="text-muted mb-0">
                        Alle verfügbaren Banner-Varianten mit aktuellen Einstellungen
                    </p>
                </div>
                <div>
                    <a href="settings.php#signature" class="btn btn-primary">
                        <i class="bi bi-gear"></i> Einstellungen
                    </a>
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
            <h4><i class="bi bi-info-circle"></i> Einstellungen</h4>
            <ul class="list-unstyled mb-0">
                <li><strong>Anzahl Filme:</strong> <?= getSetting('signature_film_count', '10') ?></li>
                <li><strong>Film-Quelle:</strong> <?= getSetting('signature_film_source', 'newest') ?></li>
                <li><strong>Cache-Zeit:</strong> <?= getSetting('signature_cache_time', '3600') / 60 ?> Minuten</li>
                <li><strong>Bildqualität:</strong> <?= getSetting('signature_quality', '9') ?>/9</li>
            </ul>
            
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
                alert('✅ In Zwischenablage kopiert!');
            });
        }
        
        function clearCache() {
            if (!confirm('Cache wirklich leeren?')) return;
            
            fetch('../signature.php?clear_cache=1')
                .then(() => {
                    alert('✅ Cache geleert!');
                    location.reload();
                })
                .catch(err => alert('❌ Fehler: ' + err));
        }
    </script>
</body>
</html>