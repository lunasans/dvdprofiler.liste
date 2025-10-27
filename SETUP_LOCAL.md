# Lokale Umgebung Setup - DVD Profiler Liste

## Problem behoben!

Die fehlenden Konfigurationsdateien wurden erstellt. Sie können jetzt Ihre lokale Umgebung einrichten.

## Schnellstart

### 1. Datenbank einrichten

Erstellen Sie eine MySQL/MariaDB Datenbank:

```sql
CREATE DATABASE dvdprofiler CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 2. Datenbank-Schema importieren

Importieren Sie das SQL-Schema:

```bash
mysql -u root -p dvdprofiler < install/sqldump/sqldump.sql
```

**ODER** die komplette Installation mit allen Tabellen aus install/index.php durchführen.

### 3. Konfiguration anpassen

Bearbeiten Sie die Datei `config/config.php` und passen Sie Ihre Datenbankzugangsdaten an:

```php
return [
    'db_host' => 'localhost',        // Ihr MySQL Host
    'db_name' => 'dvdprofiler',      // Ihr Datenbankname
    'db_user' => 'root',             // Ihr Datenbankbenutzer
    'db_pass' => 'IhrPasswort',      // Ihr Datenbankpasswort
    'db_charset' => 'utf8mb4',
    'version' => '1.4.6',
    'environment' => 'development',
];
```

### 4. Admin-Benutzer erstellen (falls noch nicht vorhanden)

Erstellen Sie einen Admin-Benutzer in der Datenbank:

```sql
INSERT INTO users (email, password, is_active)
VALUES ('admin@localhost', '$2y$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMQJqhN8/LewY5aeUjRLKzNtQ6', 1);
-- Passwort ist: admin123 (BITTE ÄNDERN!)
```

**WICHTIG:** Ändern Sie das Passwort nach dem ersten Login!

### 5. Webserver konfigurieren

#### Option A: PHP Built-in Server (Entwicklung)

```bash
cd /home/user/dvdprofiler.liste
php -S localhost:8000
```

Dann öffnen Sie: http://localhost:8000

#### Option B: Apache/Nginx

Konfigurieren Sie einen VirtualHost für das Projekt.

### 6. Installation neu durchführen (Alternative)

Wenn Sie die Installation komplett neu durchführen möchten:

1. Löschen Sie die install.lock Datei:
   ```bash
   rm install/install.lock
   ```

2. Löschen Sie die config/config.php (optional):
   ```bash
   rm config/config.php
   ```

3. Rufen Sie die Installation auf:
   ```
   http://localhost:8000/install/index.php
   ```

4. Folgen Sie den Anweisungen im Installationsassistenten

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
