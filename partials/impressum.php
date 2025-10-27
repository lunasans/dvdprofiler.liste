<?php
/**
 * DVD Profiler Liste - Impressum
 *
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.5
 */

// version.php wird bereits in bootstrap.php geladen (von index.php)
?>

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
                <strong><?= DVDPROFILER_AUTHOR ?></strong><br>
                Privatperson<br><br>
                
                <strong>Kontakt:</strong><br>
                E-Mail: <a href="mailto:kontakt@example.com">kontakt@example.com</a><br>
                GitHub: <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" rel="noopener noreferrer">
                    <?= DVDPROFILER_REPOSITORY ?>
                </a>
            </p>
        </div>
    </section>

    <section class="content-section">
        <h2>Projekt-Informationen</h2>
        <div class="project-info-grid">
            <div class="info-card">
                <h3><i class="bi bi-code-square"></i> Software</h3>
                <ul>
                    <li><strong>Name:</strong> DVD Profiler Liste</li>
                    <li><strong>Version:</strong> <?= getDVDProfilerVersionFull() ?></li>
                    <li><strong>Build:</strong> <?= DVDPROFILER_BUILD_DATE ?></li>
                    <li><strong>Repository:</strong> <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank"><?= DVDPROFILER_REPOSITORY ?></a></li>
                </ul>
            </div>
            
            <div class="info-card">
                <h3><i class="bi bi-gear"></i> Technologie</h3>
                <ul>
                    <li><strong>Backend:</strong> PHP <?= PHP_VERSION ?></li>
                    <li><strong>Frontend:</strong> HTML5, CSS3, JavaScript</li>
                    <li><strong>Datenbank:</strong> MySQL/MariaDB</li>
                    <li><strong>Features:</strong> <?= count(array_filter(DVDPROFILER_FEATURES)) ?> aktiv</li>
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
            Aktualität der Inhalte kann jedoch keine Gewähr übernommen werden. Als Diensteanbieter sind wir 
            gemäß § 7 Abs.1 TMG für eigene Inhalte auf diesen Seiten nach den allgemeinen Gesetzen verantwortlich.
        </p>
        
        <h3>Urheberrecht</h3>
        <p>
            Die auf dieser Website veröffentlichten Inhalte unterliegen dem deutschen Urheberrecht. 
            Vervielfältigung, Bearbeitung, Verbreitung und jede Art der Verwertung außerhalb der Grenzen des 
            Urheberrechtes bedürfen der schriftlichen Zustimmung des Autors.
        </p>
        
        <h3>Externe Links</h3>
        <p>
            Diese Website enthält Links zu externen Websites Dritter (z.B. GitHub, YouTube für Trailer). 
            Auf deren Inhalte haben wir keinen Einfluss. Deshalb können wir für diese fremden Inhalte auch 
            keine Gewähr übernehmen. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter 
            oder Betreiber der Seiten verantwortlich.
        </p>
    </section>

    <section class="content-section">
        <h2>Open Source & Lizenzen</h2>
        <p>
            Diese Software nutzt verschiedene Open-Source-Komponenten:
        </p>
        
        <div class="license-info">
            <h3>Verwendete Bibliotheken</h3>
            <ul class="library-list">
                <li><strong>Bootstrap Icons</strong> - MIT License</li>
                <li><strong>Fancybox</strong> - GPLv3 License</li>
                <li><strong>Chart.js</strong> - MIT License</li>
                <li><strong>PHP</strong> - PHP License v3.01</li>
                <li><strong>MySQL</strong> - GPL License</li>
            </ul>
            
            <p class="license-note">
                Alle verwendeten Bibliotheken werden gemäß ihren jeweiligen Lizenzbedingungen eingesetzt.
                Der Quellcode dieses Projekts steht unter einer privaten Lizenz für den persönlichen Gebrauch.
            </p>
        </div>
    </section>

    <section class="content-section">
        <h2>Kontakt & Support</h2>
        <div class="contact-grid">
            <div class="contact-method">
                <h3><i class="bi bi-github"></i> GitHub</h3>
                <p>
                    Für Fragen, Verbesserungsvorschläge oder Bug-Reports nutzen Sie bitte:
                </p>
                <ul>
                    <li><a href="<?= DVDPROFILER_GITHUB_URL ?>/issues" target="_blank">GitHub Issues</a></li>
                    <li><a href="<?= DVDPROFILER_GITHUB_URL ?>/discussions" target="_blank">GitHub Discussions</a></li>
                </ul>
            </div>
            
            <div class="contact-method">
                <h3><i class="bi bi-envelope"></i> E-Mail</h3>
                <p>
                    Für direkte Anfragen können Sie uns per E-Mail erreichen.<br>
                    <strong>Hinweis:</strong> Support wird ausschließlich für private, nicht-kommerzielle Nutzung gewährt.
                </p>
            </div>
        </div>
    </section>

    <footer class="page-footer">
        <div class="footer-info">
            <p>
                <strong>DVD Profiler Liste</strong> v<?= DVDPROFILER_VERSION ?> "<?= DVDPROFILER_CODENAME ?>"<br>
                Build <?= DVDPROFILER_BUILD_DATE ?> | © <?= date('Y') ?> <?= DVDPROFILER_AUTHOR ?>
            </p>
            <p class="build-details">
                <small>
                    Branch: <?= DVDPROFILER_BRANCH ?> | 
                    Commit: <?= DVDPROFILER_COMMIT ?> | 
                    PHP: <?= PHP_VERSION ?>
                </small>
            </p>
        </div>
    </footer>
</div>

<style>
/* Erweiterte Styles für Impressum */
.static-page {
    max-width: 1000px;
    margin: 0 auto;
    padding: var(--space-xl, 24px);
}

.page-header {
    text-align: center;
    margin-bottom: var(--space-3xl, 48px);
    padding-bottom: var(--space-xl, 24px);
    border-bottom: 2px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.page-header h1 {
    font-size: 2.5rem;
    margin-bottom: var(--space-md, 16px);
    background: var(--gradient-primary, linear-gradient(135deg, #667eea 0%, #764ba2 100%));
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.page-subtitle {
    font-size: 1.1rem;
    opacity: 0.8;
    color: var(--text-glass, rgba(255, 255, 255, 0.8));
}

.content-section {
    margin-bottom: var(--space-3xl, 48px);
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    backdrop-filter: blur(10px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: var(--radius-lg, 16px);
    padding: var(--space-xl, 24px);
}

.content-section h2 {
    color: var(--text-white, #ffffff);
    margin-bottom: var(--space-lg, 20px);
    font-size: 1.5rem;
    border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    padding-bottom: var(--space-sm, 8px);
}

.content-section h3 {
    color: var(--text-glass, rgba(255, 255, 255, 0.9));
    margin: var(--space-lg, 20px) 0 var(--space-md, 16px) 0;
}

.project-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-lg, 20px);
    margin-top: var(--space-lg, 20px);
}

.info-card {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    border-radius: var(--radius-md, 12px);
    padding: var(--space-lg, 20px);
    transition: all var(--transition-fast, 0.3s);
}

.info-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg, 0 10px 25px rgba(0, 0, 0, 0.2));
}

.info-card h3 {
    margin: 0 0 var(--space-md, 16px) 0;
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: var(--space-sm, 8px);
}

.info-card ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.info-card li {
    padding: var(--space-xs, 4px) 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.info-card li:last-child {
    border-bottom: none;
}

.contact-info {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border-radius: var(--radius-md, 12px);
    padding: var(--space-lg, 20px);
}

.license-info {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border-radius: var(--radius-md, 12px);
    padding: var(--space-lg, 20px);
    margin-top: var(--space-lg, 20px);
}

.library-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--space-sm, 8px);
    list-style: none;
    padding: 0;
    margin: var(--space-md, 16px) 0;
}

.library-list li {
    padding: var(--space-sm, 8px);
    background: rgba(255, 255, 255, 0.1);
    border-radius: var(--radius-sm, 6px);
    border-left: 3px solid var(--accent-color, #3498db);
}

.license-note {
    font-size: 0.9rem;
    opacity: 0.8;
    font-style: italic;
    margin-top: var(--space-md, 16px);
    padding-top: var(--space-md, 16px);
    border-top: 1px solid rgba(255, 255, 255, 0.1);
}

.contact-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-lg, 20px);
    margin-top: var(--space-lg, 20px);
}

.contact-method {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border-radius: var(--radius-md, 12px);
    padding: var(--space-lg, 20px);
}

.contact-method h3 {
    margin: 0 0 var(--space-md, 16px) 0;
    display: flex;
    align-items: center;
    gap: var(--space-sm, 8px);
}

.page-footer {
    text-align: center;
    margin-top: var(--space-3xl, 48px);
    padding-top: var(--space-xl, 24px);
    border-top: 2px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.footer-info {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-lg, 16px);
    padding: var(--space-lg, 20px);
}

.build-details {
    margin-top: var(--space-sm, 8px);
    opacity: 0.7;
    font-family: monospace;
}

/* Links */
.static-page a {
    color: var(--accent-color, #3498db);
    text-decoration: none;
    transition: all var(--transition-fast, 0.3s);
}

.static-page a:hover {
    color: var(--text-white, #ffffff);
    text-shadow: 0 0 8px var(--accent-color, #3498db);
}

/* Responsive */
@media (max-width: 768px) {
    .static-page {
        padding: var(--space-lg, 20px);
    }
    
    .page-header h1 {
        font-size: 2rem;
    }
    
    .project-info-grid,
    .contact-grid {
        grid-template-columns: 1fr;
    }
    
    .library-list {
        grid-template-columns: 1fr;
    }
}
</style>