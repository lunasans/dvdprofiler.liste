# Lokale Umgebung Setup - DVD Profiler Liste

## Korrekte Installation

Die Anwendung verfügt über einen vollständigen Installationsassistenten. Dieser erstellt automatisch:
- Die Datenbank-Tabellen (9 Tabellen)
- Die config/config.php mit Ihren Einstellungen
- Die install.lock zur Installationssperre
- Einen Admin-Benutzer
- Alle notwendigen Standardeinstellungen

## Installationsschritte

### 1. Datenbank vorbereiten

Erstellen Sie eine leere MySQL/MariaDB Datenbank:

```sql
CREATE DATABASE dvdprofiler CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**Optional:** Erstellen Sie einen dedizierten Datenbankbenutzer:

```sql
CREATE USER 'dvdprofiler'@'localhost' IDENTIFIED BY 'IhrSicheresPasswort';
GRANT ALL PRIVILEGES ON dvdprofiler.* TO 'dvdprofiler'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Webserver starten

#### Option A: PHP Built-in Server (Entwicklung)

```bash
cd /home/user/dvdprofiler.liste
php -S localhost:8000
```

#### Option B: Apache/Nginx

Konfigurieren Sie einen VirtualHost für das Projekt.

### 3. Installation durchführen

Öffnen Sie im Browser:

```
http://localhost:8000/install/index.php
```

Der Installationsassistent führt Sie durch folgende Schritte:

**a) Systemanforderungen-Check**
- PHP-Version ≥ 8.0.0
- PDO MySQL Extension
- OpenSSL Extension
- JSON Extension
- mbstring Extension
- Schreibrechte für config-Verzeichnis

**b) Installationsformular ausfüllen:**

- **Website-Titel**: z.B. "Meine DVD-Sammlung"
- **Basis-URL**: z.B. "http://localhost:8000/"
- **Administrator E-Mail**: Ihre E-Mail
- **Administrator Passwort**: Min. 8 Zeichen mit Groß-, Kleinbuchstaben und Zahlen
- **DB-Host**: localhost
- **DB-Name**: dvdprofiler
- **DB-Benutzer**: root (oder Ihr DB-User)
- **DB-Passwort**: Ihr Datenbankpasswort

**c) Installation ausführen**

Der Assistent erstellt automatisch:
- ✅ Alle 9 Datenbank-Tabellen (users, dvds, actors, film_actor, settings, etc.)
- ✅ config/config.php mit Ihren Einstellungen
- ✅ install.lock zur Sperrung der Neuinstallation
- ✅ Admin-Benutzer mit Ihrem Passwort
- ✅ Standard-Einstellungen (2FA, Session, SMTP, etc.)
- ✅ Audit-Log Einträge

### 4. Nach der Installation

Nach erfolgreicher Installation werden Sie zum Admin-Login weitergeleitet:

```
http://localhost:8000/admin/login.php
```

Melden Sie sich mit der E-Mail und dem Passwort an, die Sie während der Installation angegeben haben

## Troubleshooting

### Problem: "Datenbankverbindung fehlgeschlagen"

- Prüfen Sie die Zugangsdaten in `config/config.php`
- Stellen Sie sicher, dass MySQL läuft
- Prüfen Sie, ob die Datenbank existiert

### Problem: "Settings-Tabelle nicht gefunden"

- Importieren Sie das SQL-Schema erneut
- Oder führen Sie die Installation neu durch

### Problem: "Keine Filme angezeigt"

- Die Datenbank ist leer nach der Installation
- Importieren Sie Ihre collection.xml über das Admin-Panel
- Oder fügen Sie manuell Testdaten ein

## Admin-Panel

Nach dem Setup können Sie sich im Admin-Panel anmelden:

```
http://localhost:8000/admin/login.php
```

Standard-Login (falls Sie den SQL-Befehl oben verwendet haben):
- Email: admin@localhost
- Passwort: admin123

**WICHTIG:** Ändern Sie das Passwort sofort nach dem ersten Login!

## Weitere Hilfe

Bei Problemen siehe:
- README.md für allgemeine Informationen
- GitHub Issues: https://github.com/lunasans/dvdprofiler.liste/issues
