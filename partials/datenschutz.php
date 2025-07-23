<?php
/**
 * DVD Profiler Liste - Datenschutzerklärung
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.5
 */

// Versionsinformationen laden
require_once __DIR__ . '/../includes/version.php';
?>

<div class="static-page">
    <header class="page-header">
        <h1>
            <i class="bi bi-shield-check"></i>
            Datenschutzerklärung
        </h1>
        <p class="page-subtitle">Informationen zum Datenschutz und zur DSGVO-Konformität</p>
        <div class="last-updated">
            Letzte Aktualisierung: 23. Juli 2025 | Version <?= DVDPROFILER_VERSION ?>
        </div>
    </header>

    <section class="content-section">
        <h2 id="m4158">Präambel</h2>
        <p>
            Mit der folgenden Datenschutzerklärung möchten wir Sie darüber aufklären, welche Arten Ihrer 
            personenbezogenen Daten (nachfolgend auch kurz als "Daten" bezeichnet) wir zu welchen Zwecken 
            und in welchem Umfang im Rahmen der Bereitstellung unserer Applikation verarbeiten.
        </p>
        <p>
            Die verwendeten Begriffe sind nicht geschlechtsspezifisch.
        </p>
        
        <div class="privacy-summary">
            <h3>Zusammenfassung</h3>
            <div class="summary-grid">
                <div class="summary-item">
                    <i class="bi bi-house-check"></i>
                    <strong>Lokale Datenverarbeitung</strong>
                    <p>Alle Daten verbleiben auf Ihrem Server</p>
                </div>
                <div class="summary-item">
                    <i class="bi bi-shield-slash"></i>
                    <strong>Keine Tracking-Tools</strong>
                    <p>Keine externen Analytics oder Cookies</p>
                </div>
                <div class="summary-item">
                    <i class="bi bi-lock"></i>
                    <strong>Private Nutzung</strong>
                    <p>Ausschließlich für persönliche Filmsammlung</p>
                </div>
                <div class="summary-item">
                    <i class="bi bi-check-circle"></i>
                    <strong>DSGVO-konform</strong>
                    <p>Vollständige Einhaltung der Datenschutzrichtlinien</p>
                </div>
            </div>
        </div>
    </section>

    <nav class="content-section">
        <h2>Inhaltsübersicht</h2>
        <ul class="index">
            <li><a class="index-link" href="#m4158">Präambel</a></li>
            <li><a class="index-link" href="#m3">Verantwortlicher</a></li>
            <li><a class="index-link" href="#mOverview">Übersicht der Verarbeitungen</a></li>
            <li><a class="index-link" href="#m2427">Maßgebliche Rechtsgrundlagen</a></li>
            <li><a class="index-link" href="#m27">Sicherheitsmaßnahmen</a></li>
            <li><a class="index-link" href="#m225">Bereitstellung des Onlineangebots</a></li>
            <li><a class="index-link" href="#m134">Einsatz von Cookies</a></li>
            <li><a class="index-link" href="#m328">Externe Inhalte und Dienste</a></li>
            <li><a class="index-link" href="#m12">Datenspeicherung und Löschung</a></li>
            <li><a class="index-link" href="#m15">Änderungen dieser Datenschutzerklärung</a></li>
            <li><a class="index-link" href="#m42">Begriffsdefinitionen</a></li>
        </ul>
    </nav>

    <section class="content-section">
        <h2 id="m3">Verantwortlicher</h2>
        <div class="contact-info">
            <p>
                <strong><?= DVDPROFILER_AUTHOR ?></strong><br>
                Privatperson<br><br>
                
                E-Mail-Adresse: <a href="mailto:kontakt@example.com">kontakt@example.com</a><br>
                GitHub: <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" rel="noopener noreferrer">
                    <?= DVDPROFILER_REPOSITORY ?>
                </a>
            </p>
        </div>
    </section>

    <section class="content-section">
        <h2 id="mOverview">Übersicht der Verarbeitungen</h2>
        <p>
            Die nachfolgende Übersicht fasst die Arten der verarbeiteten Daten und die Zwecke ihrer 
            Verarbeitung zusammen und verweist auf die betroffenen Personen.
        </p>
        
        <div class="data-overview-grid">
            <div class="data-category">
                <h3>Arten der verarbeiteten Daten</h3>
                <ul>
                    <li>Zugriffsdaten (IP-Adresse, Browserinformationen)</li>
                    <li>Session-Daten (temporäre Anmeldeinformationen)</li>
                    <li>Film-Sammlung-Daten (persönliche DVD-Liste)</li>
                    <li>Nutzungsstatistiken (Besucherzähler)</li>
                </ul>
            </div>
            
            <div class="data-category">
                <h3>Kategorien betroffener Personen</h3>
                <ul>
                    <li>Website-Besucher</li>
                    <li>Admin-Benutzer</li>
                    <li>Nutzer der privaten Filmsammlung</li>
                </ul>
            </div>
            
            <div class="data-category">
                <h3>Zwecke der Verarbeitung</h3>
                <ul>
                    <li>Bereitstellung der Webanwendung</li>
                    <li>Verwaltung der persönlichen Filmsammlung</li>
                    <li>Sicherheitsmaßnahmen</li>
                    <li>Technische Administration</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="content-section">
        <h2 id="m2427">Maßgebliche Rechtsgrundlagen</h2>
        <p>
            <strong>Rechtsgrundlagen nach der DSGVO:</strong> Im Folgenden erhalten Sie eine Übersicht der 
            Rechtsgrundlagen der DSGVO, auf deren Basis wir personenbezogene Daten verarbeiten.
        </p>
        
        <div class="legal-basis">
            <ul>
                <li>
                    <strong>Berechtigte Interessen (Art. 6 Abs. 1 S. 1 lit. f) DSGVO):</strong> 
                    Verarbeitung zur Wahrung der berechtigten Interessen des Verantwortlichen oder eines Dritten, 
                    sofern nicht die Interessen oder Grundrechte und Grundfreiheiten der betroffenen Person überwiegen.
                </li>
                <li>
                    <strong>Einwilligung (Art. 6 Abs. 1 S. 1 lit. a) DSGVO):</strong> 
                    Die betroffene Person hat ihre Einwilligung zu der Verarbeitung der sie betreffenden 
                    personenbezogenen Daten für einen oder mehrere bestimmte Zwecke gegeben.
                </li>
            </ul>
        </div>
    </section>

    <section class="content-section">
        <h2 id="m27">Sicherheitsmaßnahmen</h2>
        <p>
            Wir treffen nach Maßgabe der gesetzlichen Vorgaben unter Berücksichtigung des Stands der Technik, 
            der Implementierungskosten und der Art, des Umfangs, der Umstände und der Zwecke der Verarbeitung 
            sowie der unterschiedlichen Eintrittswahrscheinlichkeiten und des Ausmaßes der Bedrohung der Rechte 
            und Freiheiten natürlicher Personen geeignete technische und organisatorische Maßnahmen.
        </p>
        
        <div class="security-measures">
            <h3>Implementierte Sicherheitsmaßnahmen</h3>
            <div class="measures-grid">
                <div class="measure-item">
                    <i class="bi bi-shield-lock"></i>
                    <strong>Verschlüsselung</strong>
                    <p>HTTPS/TLS-Verschlüsselung für alle Datenübertragungen</p>
                </div>
                <div class="measure-item">
                    <i class="bi bi-key"></i>
                    <strong>Passwort-Sicherheit</strong>
                    <p>Argon2ID-Hashing für Admin-Passwörter</p>
                </div>
                <div class="measure-item">
                    <i class="bi bi-bug"></i>
                    <strong>XSS-Schutz</strong>
                    <p>Content Security Policy und Input-Validierung</p>
                </div>
                <div class="measure-item">
                    <i class="bi bi-database-lock"></i>
                    <strong>SQL-Injection-Schutz</strong>
                    <p>Prepared Statements und Parameterisierung</p>
                </div>
                <div class="measure-item">
                    <i class="bi bi-clock-history"></i>
                    <strong>Session-Management</strong>
                    <p>Sichere Session-Konfiguration mit Timeout</p>
                </div>
                <div class="measure-item">
                    <i class="bi bi-shield-check"></i>
                    <strong>CSRF-Schutz</strong>
                    <p>Token-basierter Schutz vor Cross-Site-Request-Forgery</p>
                </div>
            </div>
        </div>
    </section>

    <section class="content-section">
        <h2 id="m225">Bereitstellung des Onlineangebots und Webhosting</h2>
        <p>
            Zur Bereitstellung unseres Onlineangebots verarbeiten wir die IP-Adresse der Nutzer, damit 
            wir die Inhalte und Funktionen unseres Onlineangebots an deren Browser übermitteln können.
        </p>
        
        <div class="hosting-info">
            <h3>Verarbeitete Datenarten</h3>
            <ul>
                <li><strong>Zugriffsdaten:</strong> IP-Adresse, Browser-Typ und Version, Betriebssystem</li>
                <li><strong>Zeitdaten:</strong> Datum und Uhrzeit des Zugriffs</li>
                <li><strong>Referrer-Daten:</strong> Zuvor besuchte Website (falls vorhanden)</li>
                <li><strong>Nutzungsdaten:</strong> Aufgerufene Seiten und Funktionen</li>
            </ul>
            
            <h3>Zwecke der Verarbeitung</h3>
            <ul>
                <li>Bereitstellung der Webanwendung und ihrer Funktionen</li>
                <li>Gewährleistung der Systemsicherheit und -stabilität</li>
                <li>Optimierung der Anwendungsleistung</li>
                <li>Fehlerdiagnose und -behebung</li>
            </ul>
            
            <div class="data-retention">
                <h3>Speicherdauer</h3>
                <p>
                    <strong>Server-Logs:</strong> Die Daten werden für maximal 30 Tage gespeichert und 
                    anschließend automatisch gelöscht.<br>
                    <strong>Session-Daten:</strong> Werden nach Ende der Browser-Session oder nach 
                    <?= getSetting('session_timeout', '3600') / 3600 ?> Stunden Inaktivität gelöscht.
                </p>
            </div>
        </div>
    </section>

    <section class="content-section">
        <h2 id="m134">Einsatz von Cookies</h2>
        <p>
            Cookies sind kleine Textdateien, die von Ihrem Browser auf Ihrem Gerät gespeichert werden. 
            Diese Website verwendet Cookies nur für technisch notwendige Funktionen.
        </p>
        
        <div class="cookie-info">
            <h3>Verwendete Cookie-Kategorien</h3>
            
            <div class="cookie-category">
                <h4><i class="bi bi-gear"></i> Technisch notwendige Cookies</h4>
                <p>
                    Diese Cookies sind für die grundlegende Funktionalität der Website erforderlich 
                    und können nicht deaktiviert werden.
                </p>
                <ul>
                    <li><strong>Session-Cookie:</strong> Zur Aufrechterhaltung der Admin-Anmeldung</li>
                    <li><strong>CSRF-Token:</strong> Schutz vor Sicherheitsangriffen</li>
                    <li><strong>Theme-Präferenz:</strong> Speicherung der gewählten Farbgebung</li>
                </ul>
                <p><strong>Speicherdauer:</strong> Session-Ende oder maximal 24 Stunden</p>
            </div>
            
            <div class="cookie-category">
                <h4><i class="bi bi-x-circle"></i> Nicht verwendete Cookies</h4>
                <p>Diese Website verwendet <strong>KEINE</strong>:</p>
                <ul>
                    <li>Analytics-Cookies (Google Analytics, etc.)</li>
                    <li>Marketing-Cookies</li>
                    <li>Social Media Tracking-Cookies</li>
                    <li>Drittanbieter-Tracking-Cookies</li>
                </ul>
            </div>
        </div>
    </section>

    <section class="content-section">
        <h2 id="m328">Externe Inhalte und Dienste</h2>
        <p>
            Wir binden Inhalte oder Funktionen von Drittanbietern in unser Onlineangebot ein. 
            Dies erfordert, dass die jeweiligen Anbieter die IP-Adresse der Nutzer verarbeiten.
        </p>
        
        <div class="external-services">
            <h3>Verwendete externe Dienste</h3>
            
            <div class="service-item">
                <h4><i class="bi bi-github"></i> GitHub</h4>
                <p>
                    <strong>Zweck:</strong> Hosting des Quellcodes und Update-Informationen<br>
                    <strong>Anbieter:</strong> GitHub, Inc., 88 Colin P Kelly Jr St, San Francisco, CA 94107, USA<br>
                    <strong>Datenschutz:</strong> <a href="https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement" target="_blank">GitHub Privacy Statement</a><br>
                    <strong>Datenübertragung:</strong> Nur bei Klick auf GitHub-Links
                </p>
            </div>
            
            <div class="service-item">
                <h4><i class="bi bi-fonts"></i> Bootstrap Icons</h4>
                <p>
                    <strong>Zweck:</strong> Darstellung von Icons in der Benutzeroberfläche<br>
                    <strong>Anbieter:</strong> CDN-Service (jsDelivr)<br>
                    <strong>Lokale Alternative:</strong> Icons können lokal gehostet werden<br>
                    <strong>Datenübertragung:</strong> IP-Adresse beim Laden der Icon-Fonts
                </p>
            </div>
            
            <div class="service-item">
                <h4><i class="bi bi-play-circle"></i> YouTube (Optional)</h4>
                <p>
                    <strong>Zweck:</strong> Einbindung von Film-Trailern (falls aktiviert)<br>
                    <strong>Anbieter:</strong> Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland<br>
                    <strong>Datenschutz:</strong> <a href="https://policies.google.com/privacy" target="_blank">Google Privacy Policy</a><br>
                    <strong>Hinweis:</strong> Trailer werden nur bei explizitem Nutzer-Wunsch geladen
                </p>
            </div>
        </div>
    </section>

    <section class="content-section">
        <h2 id="m12">Allgemeine Informationen zur Datenspeicherung und Löschung</h2>
        <p>
            Wir löschen personenbezogene Daten, sobald diese für die Erfüllung des Zwecks ihrer Verarbeitung 
            nicht mehr erforderlich sind und der Löschung keine gesetzlichen Aufbewahrungspflichten entgegenstehen.
        </p>
        
        <div class="data-retention-table">
            <h3>Speicherfristen im Überblick</h3>
            <table>
                <thead>
                    <tr>
                        <th>Datentyp</th>
                        <th>Speicherdauer</th>
                        <th>Löschung</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Server-Access-Logs</td>
                        <td>30 Tage</td>
                        <td>Automatisch</td>
                    </tr>
                    <tr>
                        <td>Session-Daten</td>
                        <td>1-24 Stunden</td>
                        <td>Bei Session-Ende</td>
                    </tr>
                    <tr>
                        <td>Admin-Account-Daten</td>
                        <td>Bis zur Deaktivierung</td>
                        <td>Manuell oder automatisch</td>
                    </tr>
                    <tr>
                        <td>Film-Sammlung-Daten</td>
                        <td>Dauerhaft (Zweck der Anwendung)</td>
                        <td>Bei Löschung der Sammlung</td>
                    </tr>
                    <tr>
                        <td>Backup-Daten</td>
                        <td><?= getSetting('backup_retention_days', '30') ?> Tage</td>
                        <td>Automatisch</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="content-section">
        <h2>Ihre Rechte als betroffene Person</h2>
        <p>
            Sie haben verschiedene Rechte bezüglich Ihrer personenbezogenen Daten. Diese können Sie 
            jederzeit per E-Mail an die oben genannte Kontaktadresse geltend machen.
        </p>
        
        <div class="rights-grid">
            <div class="right-item">
                <i class="bi bi-info-circle"></i>
                <h4>Auskunftsrecht (Art. 15 DSGVO)</h4>
                <p>Sie haben das Recht zu erfahren, ob und welche Daten wir über Sie verarbeiten.</p>
            </div>
            
            <div class="right-item">
                <i class="bi bi-pencil"></i>
                <h4>Berichtigungsrecht (Art. 16 DSGVO)</h4>
                <p>Sie können die Berichtigung unrichtiger oder unvollständiger Daten verlangen.</p>
            </div>
            
            <div class="right-item">
                <i class="bi bi-trash"></i>
                <h4>Löschungsrecht (Art. 17 DSGVO)</h4>
                <p>Sie können unter bestimmten Umständen die Löschung Ihrer Daten verlangen.</p>
            </div>
            
            <div class="right-item">
                <i class="bi bi-pause-circle"></i>
                <h4>Einschränkung der Verarbeitung (Art. 18 DSGVO)</h4>
                <p>Sie können die Einschränkung der Verarbeitung Ihrer Daten verlangen.</p>
            </div>
            
            <div class="right-item">
                <i class="bi bi-download"></i>
                <h4>Datenübertragbarkeit (Art. 20 DSGVO)</h4>
                <p>Sie können Ihre Daten in einem maschinenlesbaren Format erhalten.</p>
            </div>
            
            <div class="right-item">
                <i class="bi bi-x-octagon"></i>
                <h4>Widerspruchsrecht (Art. 21 DSGVO)</h4>
                <p>Sie können der Verarbeitung Ihrer Daten widersprechen.</p>
            </div>
        </div>
    </section>

    <section class="content-section">
        <h2 id="m15">Änderung und Aktualisierung der Datenschutzerklärung</h2>
        <p>
            Wir bitten Sie, sich regelmäßig über den Inhalt unserer Datenschutzerklärung zu informieren. 
            Wir passen die Datenschutzerklärung an, sobald die Änderungen der von uns durchgeführten 
            Datenverarbeitungen dies erforderlich machen.
        </p>
        
        <div class="version-history">
            <h3>Versionshistorie</h3>
            <ul>
                <li><strong>Version 1.3.6 (23.07.2025):</strong> Aktualisierung für erweiterte Versionsverwaltung</li>
                <li><strong>Version 1.3.5 (20.07.2025):</strong> Ergänzung um GitHub-Integration und Update-System</li>
                <li><strong>Version 1.3.0 (15.06.2025):</strong> Erste vollständige DSGVO-konforme Version</li>
            </ul>
        </div>
    </section>

    <footer class="page-footer">
        <div class="footer-info">
            <p>
                <strong>DVD Profiler Liste</strong> v<?= DVDPROFILER_VERSION ?> "<?= DVDPROFILER_CODENAME ?>"<br>
                Datenschutzerklärung | Build <?= DVDPROFILER_BUILD_DATE ?> | © <?= date('Y') ?> <?= DVDPROFILER_AUTHOR ?>
            </p>
            <p class="build-details">
                <small>
                    Diese Datenschutzerklärung wurde automatisch mit der Software-Version aktualisiert.<br>
                    Branch: <?= DVDPROFILER_BRANCH ?> | Commit: <?= DVDPROFILER_COMMIT ?> | 
                    Features: <?= count(array_filter(DVDPROFILER_FEATURES)) ?> aktiv
                </small>
            </p>
        </div>
    </footer>
</div>

<style>
/* Erweiterte Styles für Datenschutzerklärung */
.last-updated {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    padding: var(--space-sm, 8px) var(--space-md, 16px);
    border-radius: var(--radius-md, 12px);
    font-size: 0.9rem;
    margin-top: var(--space-md, 16px);
    border-left: 3px solid var(--accent-color, #3498db);
}

.privacy-summary {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border-radius: var(--radius-lg, 16px);
    padding: var(--space-xl, 24px);
    margin-top: var(--space-lg, 20px);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--space-lg, 20px);
    margin-top: var(--space-lg, 20px);
}

.summary-item {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    padding: var(--space-md, 16px);
    border-radius: var(--radius-md, 12px);
    text-align: center;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    transition: all var(--transition-fast, 0.3s);
}

.summary-item:hover {
    transform: translateY(-4px);
    box-shadow: var(--shadow-lg, 0 10px 25px rgba(0, 0, 0, 0.2));
}

.summary-item i {
    font-size: 2rem;
    margin-bottom: var(--space-sm, 8px);
    color: var(--accent-color, #3498db);
}

.data-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-lg, 20px);
    margin-top: var(--space-lg, 20px);
}

.data-category {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    padding: var(--space-lg, 20px);
    border-radius: var(--radius-md, 12px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.legal-basis {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    padding: var(--space-lg, 20px);
    border-radius: var(--radius-md, 12px);
    margin-top: var(--space-lg, 20px);
}

.security-measures {
    margin-top: var(--space-lg, 20px);
}

.measures-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: var(--space-lg, 20px);
    margin-top: var(--space-lg, 20px);
}

.measure-item {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    padding: var(--space-lg, 20px);
    border-radius: var(--radius-md, 12px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    text-align: center;
    transition: all var(--transition-fast, 0.3s);
}

.measure-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md, 0 4px 12px rgba(0, 0, 0, 0.15));
}

.measure-item i {
    font-size: 1.5rem;
    color: var(--accent-color, #3498db);
    margin-bottom: var(--space-sm, 8px);
}

.hosting-info,
.cookie-info {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    padding: var(--space-lg, 20px);
    border-radius: var(--radius-md, 12px);
    margin-top: var(--space-lg, 20px);
}

.data-retention {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    padding: var(--space-md, 16px);
    border-radius: var(--radius-sm, 8px);
    margin-top: var(--space-lg, 20px);
    border-left: 3px solid var(--accent-color, #3498db);
}

.cookie-category {
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    padding: var(--space-lg, 20px);
    border-radius: var(--radius-md, 12px);
    margin: var(--space-lg, 20px) 0;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.cookie-category h4 {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 8px);
    margin-bottom: var(--space-md, 16px);
}

.external-services {
    margin-top: var(--space-lg, 20px);
}

.service-item {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    padding: var(--space-lg, 20px);
    border-radius: var(--radius-md, 12px);
    margin: var(--space-lg, 20px) 0;
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.service-item h4 {
    display: flex;
    align-items: center;
    gap: var(--space-sm, 8px);
    margin-bottom: var(--space-md, 16px);
    color: var(--accent-color, #3498db);
}

.data-retention-table {
    margin-top: var(--space-lg, 20px);
}

.data-retention-table table {
    width: 100%;
    border-collapse: collapse;
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    border-radius: var(--radius-md, 12px);
    overflow: hidden;
}

.data-retention-table th,
.data-retention-table td {
    padding: var(--space-md, 16px);
    text-align: left;
    border-bottom: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
}

.data-retention-table th {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.2));
    font-weight: 600;
}

.rights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: var(--space-lg, 20px);
    margin-top: var(--space-lg, 20px);
}

.right-item {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    padding: var(--space-lg, 20px);
    border-radius: var(--radius-md, 12px);
    border: 1px solid var(--glass-border, rgba(255, 255, 255, 0.2));
    transition: all var(--transition-fast, 0.3s);
}

.right-item:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-md, 0 4px 12px rgba(0, 0, 0, 0.15));
}

.right-item i {
    font-size: 1.5rem;
    color: var(--accent-color, #3498db);
    margin-bottom: var(--space-sm, 8px);
}

.right-item h4 {
    margin: var(--space-sm, 8px) 0;
    font-size: 1rem;
}

.version-history {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.15));
    padding: var(--space-lg, 20px);
    border-radius: var(--radius-md, 12px);
    margin-top: var(--space-lg, 20px);
}

.index {
    list-style: none;
    padding: 0;
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--space-sm, 8px);
}

.index-link {
    display: block;
    padding: var(--space-sm, 8px);
    background: var(--glass-bg, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-sm, 6px);
    transition: all var(--transition-fast, 0.3s);
}

.index-link:hover {
    background: var(--glass-bg-strong, rgba(255, 255, 255, 0.2));
    transform: translateX(4px);
}

/* Responsive */
@media (max-width: 768px) {
    .summary-grid,
    .data-overview-grid,
    .measures-grid,
    .rights-grid {
        grid-template-columns: 1fr;
    }
    
    .data-retention-table {
        overflow-x: auto;
    }
    
    .index {
        grid-template-columns: 1fr;
    }
}
</style>