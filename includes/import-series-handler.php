<?php
/**
 * Import Serien mit Staffeln & Episoden
 * 
 * Diese Funktion wird von import-from-tmdb.php aufgerufen
 */

function importSeries($pdo, $seriesId, $seasonsData, $selectedSeasons) {
    if (empty($seasonsData) || empty($selectedSeasons)) {
        return;
    }
    
    // Prüfe ob Tabellen existieren
    $stmt = $pdo->query("SHOW TABLES LIKE 'seasons'");
    if (!$stmt->fetch()) {
        error_log("TMDb Import - seasons table does not exist");
        return;
    }
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'episodes'");
    if (!$stmt->fetch()) {
        error_log("TMDb Import - episodes table does not exist");
        return;
    }
    
    foreach ($seasonsData as $seasonNumber => $seasonInfo) {
        // Nur ausgewählte Staffeln importieren
        if (!in_array($seasonNumber, $selectedSeasons)) {
            continue;
        }
        
        $name = $seasonInfo['name'] ?? "Season $seasonNumber";
        $overview = $seasonInfo['overview'] ?? '';
        $airDate = !empty($seasonInfo['air_date']) ? $seasonInfo['air_date'] : null;
        $posterPath = $seasonInfo['poster_path'] ?? '';
        $episodes = $seasonInfo['episodes'] ?? [];
        
        try {
            // Staffel speichern
            $stmt = $pdo->prepare("
                INSERT INTO seasons (
                    series_id, season_number, name, overview, 
                    episode_count, air_date, poster_path, created_at, updated_at
                ) VALUES (
                    :series_id, :season_number, :name, :overview,
                    :episode_count, :air_date, :poster_path, NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    overview = VALUES(overview),
                    episode_count = VALUES(episode_count),
                    air_date = VALUES(air_date),
                    poster_path = VALUES(poster_path),
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                'series_id' => $seriesId,
                'season_number' => $seasonNumber,
                'name' => $name,
                'overview' => $overview,
                'episode_count' => count($episodes),
                'air_date' => $airDate,
                'poster_path' => $posterPath
            ]);
            
            $seasonId = $pdo->lastInsertId();
            if (!$seasonId) {
                // Season existiert bereits, hole ID
                $stmt = $pdo->prepare("SELECT id FROM seasons WHERE series_id = ? AND season_number = ?");
                $stmt->execute([$seriesId, $seasonNumber]);
                $seasonId = $stmt->fetchColumn();
            }
            
            error_log("TMDb Import - Imported season $seasonNumber (ID: $seasonId)");
            
            // Episoden importieren
            foreach ($episodes as $episodeNumber => $episode) {
                $episodeTitle = $episode['title'] ?? "Episode $episodeNumber";
                $episodeOverview = $episode['overview'] ?? '';
                $episodeAirDate = !empty($episode['air_date']) ? $episode['air_date'] : null;
                $episodeRuntime = !empty($episode['runtime']) ? (int)$episode['runtime'] : null;
                $stillPath = $episode['still_path'] ?? '';
                
                $stmt = $pdo->prepare("
                    INSERT INTO episodes (
                        season_id, episode_number, title, overview,
                        air_date, runtime, still_path, created_at, updated_at
                    ) VALUES (
                        :season_id, :episode_number, :title, :overview,
                        :air_date, :runtime, :still_path, NOW(), NOW()
                    )
                    ON DUPLICATE KEY UPDATE
                        title = VALUES(title),
                        overview = VALUES(overview),
                        air_date = VALUES(air_date),
                        runtime = VALUES(runtime),
                        still_path = VALUES(still_path),
                        updated_at = NOW()
                ");
                
                $stmt->execute([
                    'season_id' => $seasonId,
                    'episode_number' => $episodeNumber,
                    'title' => $episodeTitle,
                    'overview' => $episodeOverview,
                    'air_date' => $episodeAirDate,
                    'runtime' => $episodeRuntime,
                    'still_path' => $stillPath
                ]);
            }
            
            error_log("TMDb Import - Imported " . count($episodes) . " episodes for season $seasonNumber");
            
        } catch (PDOException $e) {
            error_log("TMDb Import - Failed to import season $seasonNumber: " . $e->getMessage());
        }
    }
}