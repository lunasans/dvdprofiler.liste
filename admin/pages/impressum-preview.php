<?php
/**
 * DVD Profiler Liste - Impressum Vorschau
 * Zeigt Impressum-Vorschau im Admin-Bereich
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

// Lade Impressum-Daten
$impressumName = getSetting('impressum_name', DVDPROFILER_AUTHOR ?? 'Nicht gesetzt');
$impressumEmail = getSetting('impressum_email', 'kontakt@example.com');
$impressumContent = getSetting('impressum_content', '');
$impressumEnabled = getSetting('impressum_enabled', '1');
?>

<div class="container-fluid px-4">
    <!-- Preview Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mt-4">
                <i class="bi bi-eye"></i> Impressum Vorschau
            </h1>
            <ol class="breadcrumb mb-4">
                <li class="breadcrumb-item"><a href="?page=dashboard">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="?page=impressum">Impressum</a></li>
                <li class="breadcrumb-item active">Vorschau</li>
            </ol>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge bg-<?= $impressumEnabled == '1' ? 'success' : 'danger' ?>">
                <?= $impressumEnabled == '1' ? '✓ Aktiviert' : '✗ Deaktiviert' ?>
            </span>
            <a href="?page=impressum" class="btn btn-primary">
                <i class="bi bi-pencil"></i> Bearbeiten
            </a>
        </div>
    </div>

    <!-- Impressum Content -->
    <div class="card">
        <div class="card-body p-5">
            <div class="static-page">
                <header class="page-header">
                    <h1>
                        <i class="bi bi-info-circle"></i>
                        Impressum
                    </h1>
                    <p class="page-subtitle">Rechtliche Informationen zu dieser Website</p>
                </header>

                <section class="content-section">
                    <h2>Angaben gemäß § 5 TMG</h2>
                    <div class="contact-info">
                        <p>
                            <strong><?= htmlspecialchars($impressumName) ?></strong><br>
                            Privatperson<br><br>
                            
                            <strong>Kontakt:</strong><br>
                            E-Mail: <a href="mailto:<?= htmlspecialchars($impressumEmail) ?>"><?= htmlspecialchars($impressumEmail) ?></a><br>
                            <?php if (defined('DVDPROFILER_GITHUB_URL')): ?>
                            GitHub: <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" rel="noopener noreferrer">
                                <?= DVDPROFILER_REPOSITORY ?>
                            </a>
                            <?php endif; ?>
                        </p>
                    </div>
                </section>

                <?php if (!empty($impressumContent)): ?>
                <section class="content-section">
                    <h2>Weitere Informationen</h2>
                    <div class="impressum-content">
                        <?php echo $impressumContent; ?>
                    </div>
                </section>
                <?php else: ?>
                <section class="content-section">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        Keine zusätzlichen Informationen hinterlegt. Fügen Sie im Editor weitere Inhalte hinzu.
                    </div>
                </section>
                <?php endif; ?>

                <section class="content-section">
                    <h2>Projekt-Informationen</h2>
                    <div class="project-info-grid">
                        <div class="info-card">
                            <h3><i class="bi bi-code-square"></i> Software</h3>
                            <ul>
                                <li><strong>Name:</strong> DVD Profiler Liste</li>
                                <?php if (function_exists('getDVDProfilerVersionFull')): ?>
                                <li><strong>Version:</strong> <?= getDVDProfilerVersionFull() ?></li>
                                <?php endif; ?>
                                <?php if (defined('DVDPROFILER_BUILD_DATE')): ?>
                                <li><strong>Build:</strong> <?= DVDPROFILER_BUILD_DATE ?></li>
                                <?php endif; ?>
                            </ul>
                        </div>
                        
                        <div class="info-card">
                            <h3><i class="bi bi-gear"></i> Technologie</h3>
                            <ul>
                                <li><strong>Backend:</strong> PHP <?= PHP_VERSION ?></li>
                                <li><strong>Frontend:</strong> HTML5, CSS3, JavaScript</li>
                                <li><strong>Datenbank:</strong> MySQL/MariaDB</li>
                            </ul>
                        </div>
                        
                        <div class="info-card">
                            <h3><i class="bi bi-shield-check"></i> Datenschutz</h3>
                            <ul>
                                <li><strong>Zweck:</strong> Private Filmsammlung</li>
                                <li><strong>Datenverarbeitung:</strong> Ausschließlich lokal</li>
                                <li><strong>DSGVO:</strong> Vollständig konform</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <section class="content-section">
                    <h2>Rechtliche Hinweise</h2>
                    
                    <h3>Zweck der Website</h3>
                    <p>
                        Diese Website ist ein <strong>rein privates Projekt</strong> und verfolgt keine kommerziellen Interessen. 
                        Sie dient ausschließlich der privaten Dokumentation und Verwaltung einer persönlichen Filmsammlung.
                    </p>
                    
                    <h3>Haftungsausschluss</h3>
                    <p>
                        Die Inhalte dieser Website wurden sorgfältig erstellt. Für die Richtigkeit, Vollständigkeit und 
                        Aktualität der Inhalte kann jedoch keine Gewähr übernommen werden.
                    </p>
                </section>

                <footer class="page-footer mt-5 pt-4 border-top">
                    <div class="text-center">
                        <p>
                            <strong>DVD Profiler Liste</strong> 
                            <?php if (defined('DVDPROFILER_VERSION')): ?>
                            v<?= DVDPROFILER_VERSION ?>
                            <?php endif; ?>
                            <br>
                            © <?= date('Y') ?> <?= htmlspecialchars($impressumName) ?>
                        </p>
                    </div>
                </footer>
            </div>
        </div>
    </div>
</div>

<style>
/* Impressum Preview Styles */
.static-page {
    max-width: 900px;
    margin: 0 auto;
}

.page-header {
    text-align: center;
    margin-bottom: 2rem;
    padding-bottom: 1.5rem;
    border-bottom: 2px solid #dee2e6;
}

.page-header h1 {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    color: #495057;
}

.page-subtitle {
    font-size: 1rem;
    color: #6c757d;
}

.content-section {
    margin-bottom: 2rem;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 0.5rem;
}

.content-section h2 {
    font-size: 1.5rem;
    margin-bottom: 1rem;
    color: #212529;
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 0.5rem;
}

.content-section h3 {
    font-size: 1.2rem;
    margin: 1rem 0 0.75rem 0;
    color: #495057;
}

.project-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.info-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1.25rem;
    transition: transform 0.2s;
}

.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.info-card h3 {
    margin: 0 0 1rem 0;
    font-size: 1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-card li {
    padding: 0.25rem 0;
    border-bottom: 1px solid #e9ecef;
}

.info-card li:last-child {
    border-bottom: none;
}

.contact-info, .impressum-content {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1.25rem;
    line-height: 1.8;
}

.impressum-content a {
    color: #0d6efd;
}

.impressum-content a:hover {
    color: #0a58ca;
}
</style>

        :root {
            --gradient-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --glass-bg: rgba(255, 255, 255, 0.1);
            --glass-bg-strong: rgba(255, 255, 255, 0.15);
            --glass-border: rgba(255, 255, 255, 0.2);
            --text-white: #ffffff;
            --text-glass: rgba(255, 255, 255, 0.9);
            --accent-color: #3498db;
            --radius-lg: 16px;
            --radius-md: 12px;
            --radius-sm: 6px;
            --space-xs: 4px;
            --space-sm: 8px;
            --space-md: 16px;
            --space-lg: 20px;
            --space-xl: 24px;
            --space-2xl: 32px;
            --space-3xl: 48px;
            --transition-fast: 0.3s;
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        body {
            background: var(--gradient-bg);
            background-attachment: fixed;
            color: var(--text-white);
            min-height: 100vh;
            padding: var(--space-lg);
        }
        
        .preview-header {
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .preview-badge {
            background: #ff9800;
            color: white;
            padding: var(--space-xs) var(--space-md);
            border-radius: var(--radius-md);
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .status-badge {
            padding: var(--space-xs) var(--space-md);
            border-radius: var(--radius-sm);
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-active {
            background: #4caf50;
            color: white;
        }
        
        .status-inactive {
            background: #f44336;
            color: white;
        }
        
        /* Impressum Styles */
        .static-page {
            max-width: 1000px;
            margin: 0 auto;
            padding: var(--space-xl);
        }

        .page-header {
            text-align: center;
            margin-bottom: var(--space-3xl);
            padding-bottom: var(--space-xl);
            border-bottom: 2px solid var(--glass-border);
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: var(--space-md);
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.8;
            color: var(--text-glass);
        }

        .content-section {
            margin-bottom: var(--space-3xl);
            background: var(--glass-bg);
            backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
        }

        .content-section h2 {
            color: var(--text-white);
            margin-bottom: var(--space-lg);
            font-size: 1.5rem;
            border-bottom: 1px solid var(--glass-border);
            padding-bottom: var(--space-sm);
        }

        .content-section h3 {
            color: var(--text-glass);
            margin: var(--space-lg) 0 var(--space-md) 0;
        }

        .project-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: var(--space-lg);
            margin-top: var(--space-lg);
        }

        .info-card {
            background: var(--glass-bg-strong);
            border: 1px solid var(--glass-border);
            border-radius: var(--radius-md);
            padding: var(--space-lg);
            transition: all var(--transition-fast);
        }

        .info-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .info-card h3 {
            margin: 0 0 var(--space-md) 0;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .info-card ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .info-card li {
            padding: var(--space-xs) 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-card li:last-child {
            border-bottom: none;
        }

        .contact-info, .impressum-content {
            background: var(--glass-bg-strong);
            border-radius: var(--radius-md);
            padding: var(--space-lg);
            line-height: 1.8;
        }

        .page-footer {
            text-align: center;
            margin-top: var(--space-3xl);
            padding-top: var(--space-xl);
            border-top: 2px solid var(--glass-border);
        }

        .footer-info {
            background: var(--glass-bg);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
        }

        .static-page a {
            color: var(--accent-color);
            text-decoration: none;
            transition: all var(--transition-fast);
        }

        .static-page a:hover {
            color: var(--text-white);
            text-shadow: 0 0 8px var(--accent-color);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--space-3xl);
            opacity: 0.6;
        }
        
        .empty-state i {
            font-size: 3rem;
            margin-bottom: var(--space-md);
        }

        @media (max-width: 768px) {
            .static-page {
                padding: var(--space-lg);
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .project-info-grid {
                grid-template-columns: 1fr;
            }
            
            .preview-header {
                flex-direction: column;
                gap: var(--space-md);
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <!-- Preview Header -->
    <div class="preview-header">
        <div>
            <h5 class="mb-1">
                <i class="bi bi-eye"></i> Impressum Vorschau
            </h5>
            <small class="text-muted">
                So sieht Ihr Impressum auf der Website aus
            </small>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <span class="status-badge <?= $impressumEnabled == '1' ? 'status-active' : 'status-inactive' ?>">
                <?= $impressumEnabled == '1' ? '✓ Aktiviert' : '✗ Deaktiviert' ?>
            </span>
            <a href="?page=impressum" class="btn btn-sm btn-outline-light">
                <i class="bi bi-pencil"></i> Bearbeiten
            </a>
            <button onclick="window.close()" class="btn btn-sm btn-outline-light">
                <i class="bi bi-x-lg"></i> Schließen
            </button>
        </div>
    </div>

    <!-- Impressum Content -->
    <div class="static-page">
        <header class="page-header">
            <h1>
                <i class="bi bi-info-circle"></i>
                Impressum
            </h1>
            <p class="page-subtitle">Rechtliche Informationen zu dieser Website</p>
        </header>

        <section class="content-section">
            <h2>Angaben gemäß § 5 TMG</h2>
            <div class="contact-info">
                <p>
                    <strong><?= htmlspecialchars($impressumName) ?></strong><br>
                    Privatperson<br><br>
                    
                    <strong>Kontakt:</strong><br>
                    E-Mail: <a href="mailto:<?= htmlspecialchars($impressumEmail) ?>"><?= htmlspecialchars($impressumEmail) ?></a><br>
                    <?php if (defined('DVDPROFILER_GITHUB_URL')): ?>
                    GitHub: <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" rel="noopener noreferrer">
                        <?= DVDPROFILER_REPOSITORY ?>
                    </a>
                    <?php endif; ?>
                </p>
            </div>
        </section>

        <?php if (!empty($impressumContent)): ?>
        <section class="content-section">
            <h2>Weitere Informationen</h2>
            <div class="impressum-content">
                <?php
                // HTML wurde bereits beim Speichern bereinigt
                echo $impressumContent;
                ?>
            </div>
        </section>
        <?php else: ?>
        <section class="content-section">
            <div class="empty-state">
                <i class="bi bi-file-earmark-text"></i>
                <p>Keine zusätzlichen Informationen hinterlegt</p>
                <small>Fügen Sie im Editor weitere Inhalte hinzu</small>
            </div>
        </section>
        <?php endif; ?>

        <section class="content-section">
            <h2>Projekt-Informationen</h2>
            <div class="project-info-grid">
                <div class="info-card">
                    <h3><i class="bi bi-code-square"></i> Software</h3>
                    <ul>
                        <li><strong>Name:</strong> DVD Profiler Liste</li>
                        <?php if (function_exists('getDVDProfilerVersionFull')): ?>
                        <li><strong>Version:</strong> <?= getDVDProfilerVersionFull() ?></li>
                        <?php endif; ?>
                        <?php if (defined('DVDPROFILER_BUILD_DATE')): ?>
                        <li><strong>Build:</strong> <?= DVDPROFILER_BUILD_DATE ?></li>
                        <?php endif; ?>
                        <?php if (defined('DVDPROFILER_GITHUB_URL')): ?>
                        <li><strong>Repository:</strong> <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank"><?= DVDPROFILER_REPOSITORY ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <div class="info-card">
                    <h3><i class="bi bi-gear"></i> Technologie</h3>
                    <ul>
                        <li><strong>Backend:</strong> PHP <?= PHP_VERSION ?></li>
                        <li><strong>Frontend:</strong> HTML5, CSS3, JavaScript</li>
                        <li><strong>Datenbank:</strong> MySQL/MariaDB</li>
                    </ul>
                </div>
                
                <div class="info-card">
                    <h3><i class="bi bi-shield-check"></i> Datenschutz</h3>
                    <ul>
                        <li><strong>Zweck:</strong> Private Filmsammlung</li>
                        <li><strong>Datenverarbeitung:</strong> Ausschließlich lokal</li>
                        <li><strong>Externe Services:</strong> Keine Weitergabe</li>
                        <li><strong>DSGVO:</strong> Vollständig konform</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="content-section">
            <h2>Rechtliche Hinweise</h2>
            
            <h3>Zweck der Website</h3>
            <p>
                Diese Website ist ein <strong>rein privates Projekt</strong> und verfolgt keine kommerziellen Interessen. 
                Sie dient ausschließlich der privaten Dokumentation und Verwaltung einer persönlichen Filmsammlung.
            </p>
            
            <h3>Haftungsausschluss</h3>
            <p>
                Die Inhalte dieser Website wurden sorgfältig erstellt. Für die Richtigkeit, Vollständigkeit und 
                Aktualität der Inhalte kann jedoch keine Gewähr übernommen werden.
            </p>
            
            <h3>Urheberrecht</h3>
            <p>
                Die auf dieser Website veröffentlichten Inhalte unterliegen dem deutschen Urheberrecht. 
                Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des 
                Urheberrechtes bedürfen der schriftlichen Zustimmung des Autors.
            </p>
            
            <h3>Externe Links</h3>
            <p>
                Diese Website enthält Links zu externen Websites Dritter (z.B. GitHub, YouTube, TMDb). 
                Auf deren Inhalte haben wir keinen Einfluss. Deshalb können wir für diese fremden Inhalte auch 
                keine Gewähr übernehmen.
            </p>
        </section>

        <footer class="page-footer">
            <div class="footer-info">
                <p>
                    <strong>DVD Profiler Liste</strong> 
                    <?php if (defined('DVDPROFILER_VERSION')): ?>
                    v<?= DVDPROFILER_VERSION ?>
                    <?php endif; ?>
                    <?php if (defined('DVDPROFILER_CODENAME')): ?>
                    "<?= DVDPROFILER_CODENAME ?>"
                    <?php endif; ?>
                    <br>
                    <?php if (defined('DVDPROFILER_BUILD_DATE')): ?>
                    Build <?= DVDPROFILER_BUILD_DATE ?> | 
                    <?php endif; ?>
                    © <?= date('Y') ?> <?= htmlspecialchars($impressumName) ?>
                </p>
            </div>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>