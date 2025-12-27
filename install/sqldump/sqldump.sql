SET FOREIGN_KEY_CHECKS=0;

-- Struktur für Tabelle `actors`
CREATE TABLE `actors` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `birth_year` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Struktur für Tabelle `dvds`
CREATE TABLE `dvds` (
  `id` bigint(20) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `year` int(11) DEFAULT NULL,
  `genre` varchar(100) DEFAULT NULL,
  `cover_id` varchar(50) DEFAULT NULL,
  `collection_type` varchar(100) DEFAULT NULL,
  `runtime` int(11) DEFAULT NULL,
  `rating_age` int(11) DEFAULT NULL,
  `overview` text,
  `trailer_url` varchar(255) DEFAULT NULL,
  `boxset_parent` bigint(20) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `dvds_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Struktur für Tabelle `film_actor`
CREATE TABLE `film_actor` (
  `film_id` bigint(20) NOT NULL,
  `actor_id` bigint(20) NOT NULL,
  `role` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`film_id`,`actor_id`),
  KEY `actor_id` (`actor_id`),
  CONSTRAINT `film_actor_ibfk_1` FOREIGN KEY (`film_id`) REFERENCES `dvds` (`id`) ON DELETE CASCADE,
  CONSTRAINT `film_actor_ibfk_2` FOREIGN KEY (`actor_id`) REFERENCES `actors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Struktur für Tabelle `settings`
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key` varchar(64) NOT NULL,
  `value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Struktur für Tabelle `users`
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ================================================================
-- DVD Profiler Liste - Counter Tabelle
-- Erstellt eine persistente Tabelle für den Besucherzähler
-- ================================================================

-- Counter Tabelle erstellen
CREATE TABLE IF NOT EXISTS counter (
    id INT PRIMARY KEY DEFAULT 1,
    visits BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_visit_date DATE NULL,
    daily_visits BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Initialen Eintrag erstellen (falls nicht vorhanden)
-- Hinweis: Verwende immer id=1 für den einzigen Counter-Eintrag
INSERT IGNORE INTO counter (id, visits, last_visit_date, daily_visits) 
VALUES (1, 0, CURDATE(), 0);

-- Index für Performance
CREATE INDEX idx_last_visit ON counter(last_visit_date);


SET FOREIGN_KEY_CHECKS=1;
