# DVD Profiler Liste

Ein modernes, webbasiertes Tool zur Verwaltung Ihrer privaten Filmsammlung mit eleganter Benutzeroberfläche und umfangreichen Funktionen.

## 🎬 Übersicht

DVD Profiler Liste ist eine vollständige Webanwendung zur Verwaltung, Durchsuchung und Präsentation Ihrer DVD/Blu-ray-Sammlung. Das System bietet eine intuitive Benutzeroberfläche mit Glass-Morphism-Design und umfangreiche Funktionen für Film-Enthusiasten.

## ✨ Hauptfunktionen

### 📥 Import & Datenmanagement
- **XML-Import** aus collection.xml (kompatibel mit DVD Profiler)
- **Automatischer Datenbankabgleich** mit Update- und Einfügefunktionen
- **BoxSet-Erkennung** mit gruppierten, aufklappbaren Unterfilmen

### 🎭 Film-Details & Präsentation
- **Umfassende Film-Informationen** mit Schauspielern, Cover und Übersicht
- **Trailer-Integration** für erweiterte Filminformationen
- **Responsive Design** für alle Bildschirmgrößen
- **Listen- und Kachelansicht** mit nahtlosem Umschalten

### 📊 Erweiterte Features
- **Statistikseite** mit interaktiven Diagrammen (Chart.js)
- **Admin-Panel** (ab Version 1.3.5) mit Update-Funktionen
- **Besucherzähler** für Nutzungsstatistiken
- **DSGVO-konformes Design** mit Impressum und Datenschutz

## 🛠️ Technische Details

### Systemanforderungen
- PHP 7.4+ 
- MySQL/MariaDB
- Webserver (Apache/Nginx)
- Modern Browser mit JavaScript-Unterstützung

### Verwendete Technologien
- **Backend**: PHP mit PDO
- **Frontend**: HTML5, CSS3 (Glass-Morphism), JavaScript
- **UI-Bibliotheken**: 
  - Bootstrap Icons
  - Fancybox für Lightbox-Funktionen
  - Chart.js für Statistiken
- **Datenbank**: MySQL/MariaDB

## 📁 Projektstruktur

```
dvdprofiler.liste/
├── admin/                  # Admin-Panel und Verwaltung
├── css/                    # Stylesheets und Themes
│   ├── style.css          # Haupt-Stylesheet
│   └── themes/            # Theme-Varianten
├── js/                     # JavaScript-Dateien
│   └── main.js            # Haupt-JavaScript
├── libs/                   # Externe Bibliotheken
│   └── fancybox/          # Fancybox Library
├── partials/              # Template-Teile
│   ├── header.php         # Header-Template
│   ├── film-list.php      # Film-Listen-Template
│   ├── impressum.php      # Impressum
│   └── datenschutz.php    # Datenschutzerklärung
├── index.php              # Hauptdatei
└── README.md              # Diese Datei
```

## 🚀 Installation

### 1. Repository klonen
```bash
git clone https://github.com/lunasans/dvdprofiler.liste.git
cd dvdprofiler.liste
```

### 2. Datenbank einrichten
- Erstellen Sie eine MySQL/MariaDB-Datenbank
- Importieren Sie das mitgelieferte SQL-Schema
- Konfigurieren Sie die Datenbankverbindung

### 3. Konfiguration
- Passen Sie die Konfigurationsdateien an Ihre Umgebung an
- Setzen Sie die entsprechenden Dateiberechtigungen
- Konfigurieren Sie Ihren Webserver

### 4. XML-Import
- Exportieren Sie Ihre Sammlung aus DVD Profiler als collection.xml
- Nutzen Sie die Import-Funktion im Admin-Panel

## 🎨 Features im Detail

### Glass-Morphism Design
Das moderne Interface nutzt Glasmorphismus-Effekte für eine elegante und zeitgemäße Benutzeroberfläche mit:
- Transparente Hintergründe mit Blur-Effekten
- Smooth Animationen und Hover-Effekte
- Responsive Grid-Layout
- Dunkler Modus verfügbar

### Erweiterte Suchfunktionen
- Volltext-Suche durch alle Film-Metadaten
- Filter nach Genre, Jahr, Bewertung
- Sortierung nach verschiedenen Kriterien
- Schnelle Navigation durch große Sammlungen

### Admin-Funktionen
- Benutzer-Authentifizierung
- Batch-Import von XML-Dateien
- Datenbank-Wartungstools
- Statistik-Dashboard
- System-Updates

## 📊 Screenshots

Die Anwendung bietet eine moderne, benutzerfreundliche Oberfläche:
- **Hauptansicht**: Übersichtliche Film-Grid mit Cover-Bildern
- **Detail-Panel**: Ausführliche Informationen zu jedem Film
- **Statistiken**: Interaktive Diagramme Ihrer Sammlung
- **Admin-Panel**: Verwaltungstools für Power-User

## 🔒 Datenschutz & Sicherheit

- **DSGVO-konform**: Vollständige Datenschutzerklärung und Impressum
- **Keine externe Datenübertragung**: Alle Daten bleiben auf Ihrem Server
- **Sichere Authentifizierung**: Verschlüsselte Login-Funktionen
- **Content Security Policy**: Schutz vor XSS-Angriffen

## 🤝 Mitwirken

Beiträge sind willkommen! Bitte:
1. Forken Sie das Repository
2. Erstellen Sie einen Feature-Branch
3. Committen Sie Ihre Änderungen
4. Erstellen Sie einen Pull Request

## 📝 Lizenz

Dieses Projekt ist für den privaten Gebrauch konzipiert. Weitere Details finden Sie in der LICENSE-Datei.

## 👤 Autor

**René Neuhaus**  
GitHub: [@lunasans](https://github.com/lunasans)

## 🐛 Support & Feedback

Bei Fragen, Problemen oder Verbesserungsvorschlägen:
- Erstellen Sie ein [GitHub Issue](https://github.com/lunasans/dvdprofiler.liste/issues)
- Nutzen Sie die Diskussionsfunktion im Repository

## 📈 Roadmap

- [ ] Multi-Language Support
- [ ] API-Schnittstelle
- [ ] Mobile App
- [ ] Cloud-Synchronisation
- [ ] Erweiterte Statistiken
- [ ] Social Features

---

**Version**: 1.3.5+  
**Letztes Update**: Juli 2025  
**Status**: Aktiv entwickelt

*Verwalten Sie Ihre Filmsammlung mit Stil und Effizienz!* 🎬✨