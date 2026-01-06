-- SQL für Serien/Staffeln/Episoden Import
-- OHNE Foreign Key Constraints (kompatibler!)

-- Schritt 1: Prüfe dvds Tabelle
SHOW CREATE TABLE dvds;
DESCRIBE dvds;

-- Schritt 2: Erstelle Staffeln-Tabelle
CREATE TABLE IF NOT EXISTS seasons (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    series_id INT(11) NOT NULL,              -- DVD ID (Serie)
    season_number INT(11) NOT NULL,          -- Staffel-Nummer (1, 2, 3...)
    name VARCHAR(255) DEFAULT NULL,          -- Name (z.B. "Season 1")
    overview TEXT DEFAULT NULL,              -- Beschreibung der Staffel
    episode_count INT(11) DEFAULT 0,         -- Anzahl Episoden
    air_date DATE DEFAULT NULL,              -- Erstausstrahlung
    poster_path VARCHAR(255) DEFAULT NULL,   -- TMDb Poster-Pfad
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_season (series_id, season_number),
    INDEX idx_series (series_id),
    INDEX idx_season_number (season_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schritt 3: Erstelle Episoden-Tabelle  
CREATE TABLE IF NOT EXISTS episodes (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    season_id INT(11) NOT NULL,              -- Season ID
    episode_number INT(11) NOT NULL,         -- Episoden-Nummer (1, 2, 3...)
    title VARCHAR(255) NOT NULL,             -- Titel der Episode
    overview TEXT DEFAULT NULL,              -- Beschreibung
    air_date DATE DEFAULT NULL,              -- Erstausstrahlung
    runtime INT(11) DEFAULT NULL,            -- Laufzeit in Minuten
    still_path VARCHAR(255) DEFAULT NULL,    -- TMDb Screenshot-Pfad
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_episode (season_id, episode_number),
    INDEX idx_season (season_id),
    INDEX idx_episode_number (episode_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schritt 4: Prüfen
SHOW TABLES LIKE 'seasons';
SHOW TABLES LIKE 'episodes';

-- Schritt 5: Struktur anzeigen
DESCRIBE seasons;
DESCRIBE episodes;

-- Optional: Foreign Keys NACHTRÄGLICH hinzufügen (nur wenn dvds.id kompatibel ist)
-- ALTER TABLE seasons 
--     ADD CONSTRAINT fk_seasons_series 
--     FOREIGN KEY (series_id) REFERENCES dvds(id) ON DELETE CASCADE;
-- 
-- ALTER TABLE episodes 
--     ADD CONSTRAINT fk_episodes_season 
--     FOREIGN KEY (season_id) REFERENCES seasons(id) ON DELETE CASCADE;

-- Fertig!
SELECT 'Tabellen erfolgreich erstellt!' as Status;