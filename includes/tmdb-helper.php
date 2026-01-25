<?php
/**
 * includes/tmdb-helper.php
 * TMDb API Integration für Film-Ratings
 * 
 * API Key kostenlos bei: https://www.themoviedb.org/settings/api
 */

class TMDbHelper {
    private $apiKey;
    private $baseUrl = 'https://api.themoviedb.org/3';
    private $cacheDir;
    
    public function __construct($apiKey) {
        $this->apiKey = $apiKey;
        $this->cacheDir = __DIR__ . '/../cache/tmdb';
        
        // Cache-Verzeichnis erstellen falls nicht vorhanden
        if (!file_exists($this->cacheDir)) {
            if (!@mkdir($this->cacheDir, 0755, true)) {
                error_log('TMDb: Failed to create cache directory: ' . $this->cacheDir);
                // Fallback: Nutze system temp
                $this->cacheDir = sys_get_temp_dir() . '/dvdprofiler_tmdb';
                if (!file_exists($this->cacheDir)) {
                    @mkdir($this->cacheDir, 0755, true);
                }
            }
        }
        
        // Prüfe ob Verzeichnis beschreibbar ist
        if (!is_writable($this->cacheDir)) {
            error_log('TMDb: Cache directory not writable: ' . $this->cacheDir);
        }
    }
    
    /**
     * Suche Film und hole Ratings
     * 
     * @param string $title Film-Titel
     * @param int $year Jahr (optional, verbessert Genauigkeit)
     * @return array|null ['tmdb_rating' => 8.3, 'tmdb_votes' => 15000, 'imdb_rating' => 8.5, 'imdb_id' => 'tt1375666']
     */
    public function getFilmRatings($title, $year = null) {
        // Cache-Key generieren
        $cacheKey = md5($title . ($year ?? ''));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';
        
        // Cache-Dauer aus Settings (Standard: 24 Stunden)
        $cacheHours = (int)getSetting('tmdb_cache_hours', '24');
        $cacheSeconds = $cacheHours * 3600;
        
        // Cache prüfen
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheSeconds) {
            $cached = file_get_contents($cacheFile);
            return json_decode($cached, true);
        }
        
        try {
            // 1. Film suchen
            $searchUrl = $this->baseUrl . '/search/movie';
            $searchParams = [
                'api_key' => $this->apiKey,
                'query' => $title,
                'language' => 'de-DE'
            ];
            
            if ($year) {
                $searchParams['year'] = $year;
            }
            
            $searchResult = $this->makeRequest($searchUrl, $searchParams);
            
            if (!$searchResult || empty($searchResult['results'])) {
                return null;
            }
            
            // Ersten Treffer nehmen (beste Übereinstimmung)
            $movie = $searchResult['results'][0];
            $movieId = $movie['id'];
            
            // 2. Film-Details mit External IDs holen
            $detailsUrl = $this->baseUrl . '/movie/' . $movieId;
            $detailsParams = [
                'api_key' => $this->apiKey,
                'append_to_response' => 'external_ids',
                'language' => 'de-DE'
            ];
            
            $details = $this->makeRequest($detailsUrl, $detailsParams);
            
            if (!$details) {
                return null;
            }
            
            // Ratings zusammenstellen
            $ratings = [
                'tmdb_rating' => round($details['vote_average'] ?? 0, 1),
                'tmdb_votes' => $details['vote_count'] ?? 0,
                'tmdb_popularity' => round($details['popularity'] ?? 0),
                'imdb_id' => $details['external_ids']['imdb_id'] ?? null,
                'imdb_rating' => null, // TMDb hat kein IMDb Rating direkt
                'poster_path' => $details['poster_path'] ?? null,
                'backdrop_path' => $details['backdrop_path'] ?? null
            ];
            
            // Cache speichern
            file_put_contents($cacheFile, json_encode($ratings));
            
            return $ratings;
            
        } catch (Exception $e) {
            error_log('TMDb API Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Lade Poster von TMDb und speichere es lokal
     * 
     * @param string $title Film-Titel
     * @param int $year Jahr
     * @param string $coverId Cover-ID (z.B. "13geister")
     * @return bool Success
     */
    
    /**
     * Suche Filme auf TMDb - gibt ALLE Ergebnisse zurück
     * 
     * @param string $title Film-Titel
     * @param int $year Jahr (optional)
     * @param int $limit Max. Anzahl Ergebnisse (Standard: 20)
     * @return array|null Array mit Film-Ergebnissen
     */
    public function searchMovies($title, $year = null, $limit = 20) {
        try {
            $searchUrl = $this->baseUrl . '/search/movie';
            $searchParams = [
                'api_key' => $this->apiKey,
                'query' => $title,
                'language' => 'de-DE',
                'page' => 1
            ];
            
            if ($year) {
                $searchParams['year'] = $year;
            }
            
            $searchResult = $this->makeRequest($searchUrl, $searchParams);
            
            if (!$searchResult || empty($searchResult['results'])) {
                return [];
            }
            
            // Alle Ergebnisse formatieren
            $movies = array_slice($searchResult['results'], 0, $limit);
            
            $formatted = array_map(function($movie) {
                // Genre IDs zu Namen konvertieren
                $genreNames = $this->getGenreNames($movie['genre_ids'] ?? []);
                
                return [
                    'tmdb_id' => $movie['id'],
                    'title' => $movie['title'] ?? 'Unbekannt',
                    'original_title' => $movie['original_title'] ?? '',
                    'year' => isset($movie['release_date']) ? substr($movie['release_date'], 0, 4) : null,
                    'release_date' => $movie['release_date'] ?? null,
                    'overview' => $movie['overview'] ?? '',
                    'poster_path' => $movie['poster_path'] ?? null,
                    'backdrop_path' => $movie['backdrop_path'] ?? null,
                    'rating' => round($movie['vote_average'] ?? 0, 1),
                    'votes' => $movie['vote_count'] ?? 0,
                    'popularity' => round($movie['popularity'] ?? 0, 1),
                    'genre' => implode(', ', $genreNames),
                    'genre_ids' => $movie['genre_ids'] ?? []
                ];
            }, $movies);
            
            return $formatted;
            
        } catch (Exception $e) {
            error_log('TMDb searchMovies error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Konvertiere Genre-IDs zu Namen
     */
    private function getGenreNames($genreIds) {
        // TMDb Genre Map (häufigste Genres)
        $genreMap = [
            28 => 'Action',
            12 => 'Abenteuer',
            16 => 'Animation',
            35 => 'Komödie',
            80 => 'Krimi',
            99 => 'Dokumentarfilm',
            18 => 'Drama',
            10751 => 'Familie',
            14 => 'Fantasy',
            36 => 'Historie',
            27 => 'Horror',
            10402 => 'Musik',
            9648 => 'Mystery',
            10749 => 'Liebesfilm',
            878 => 'Science Fiction',
            10770 => 'TV-Film',
            53 => 'Thriller',
            10752 => 'Kriegsfilm',
            37 => 'Western'
        ];
        
        $names = [];
        foreach ($genreIds as $id) {
            if (isset($genreMap[$id])) {
                $names[] = $genreMap[$id];
            }
        }
        
        return array_slice($names, 0, 3); // Max 3 Genres
    }
    public function downloadPoster($title, $year, $coverId) {
        $ratings = $this->getFilmRatings($title, $year);
        
        if (!$ratings || empty($ratings['poster_path'])) {
            return false;
        }
        
        $posterPath = $ratings['poster_path'];
        $imageUrl = 'https://image.tmdb.org/t/p/w500' . $posterPath;
        
        try {
            // Cover-Verzeichnis
            $coverDir = dirname(__DIR__) . '/cover';
            if (!file_exists($coverDir)) {
                mkdir($coverDir, 0755, true);
            }
            
            // Bild herunterladen
            $imageData = file_get_contents($imageUrl);
            if ($imageData === false) {
                return false;
            }
            
            // Als JPG speichern (Front-Cover)
            $targetFile = $coverDir . '/' . $coverId . 'f.jpg';
            $success = file_put_contents($targetFile, $imageData);
            
            if ($success) {
                error_log("TMDb Cover downloaded: {$title} -> {$targetFile}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('TMDb Cover Download Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Lade Backdrop von TMDb
     * 
     * @param string $title Film-Titel
     * @param int $year Jahr
     * @param string $coverId Cover-ID
     * @return bool Success
     */
    public function downloadBackdrop($title, $year, $coverId) {
        $ratings = $this->getFilmRatings($title, $year);
        
        if (!$ratings || empty($ratings['backdrop_path'])) {
            return false;
        }
        
        $backdropPath = $ratings['backdrop_path'];
        $imageUrl = 'https://image.tmdb.org/t/p/w1280' . $backdropPath;
        
        try {
            $coverDir = dirname(__DIR__) . '/cover';
            if (!file_exists($coverDir)) {
                mkdir($coverDir, 0755, true);
            }
            
            $imageData = file_get_contents($imageUrl);
            if ($imageData === false) {
                return false;
            }
            
            // Als JPG speichern (Back-Cover)
            $targetFile = $coverDir . '/' . $coverId . 'b.jpg';
            $success = file_put_contents($targetFile, $imageData);
            
            if ($success) {
                error_log("TMDb Backdrop downloaded: {$title} -> {$targetFile}");
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            error_log('TMDb Backdrop Download Error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Hole ähnliche Filme von TMDb (kombiniert similar + recommendations)
     * 
     * @param string $title Film-Titel
     * @param int $year Jahr
     * @param int $limit Anzahl der Ergebnisse (Standard: 10)
     * @return array|null Array mit ähnlichen Filmen
     */
    public function getSimilarMovies($title, $year = null, $limit = 10) {
        // Cache-Key
        $cacheKey = md5('similar_' . $title . ($year ?? ''));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';
        
        // Cache-Dauer
        $cacheHours = (int)getSetting('tmdb_cache_hours', '24');
        $cacheSeconds = $cacheHours * 3600;
        
        // Cache prüfen
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheSeconds) {
            $cached = file_get_contents($cacheFile);
            return json_decode($cached, true);
        }
        
        try {
            // 1. Film-ID finden
            $searchUrl = $this->baseUrl . '/search/movie';
            $searchParams = [
                'api_key' => $this->apiKey,
                'query' => $title,
                'language' => 'de-DE'
            ];
            
            if ($year) {
                $searchParams['year'] = $year;
            }
            
            $searchResult = $this->makeRequest($searchUrl, $searchParams);
            
            if (!$searchResult || empty($searchResult['results'])) {
                return null;
            }
            
            $movieId = $searchResult['results'][0]['id'];
            
            // 2. Beide Endpoints kombinieren (similar + recommendations)
            $allMovies = [];
            
            // Similar Movies
            $similarUrl = $this->baseUrl . '/movie/' . $movieId . '/similar';
            $similarParams = [
                'api_key' => $this->apiKey,
                'language' => 'de-DE',
                'page' => 1
            ];
            
            $similarResult = $this->makeRequest($similarUrl, $similarParams);
            if ($similarResult && !empty($similarResult['results'])) {
                $allMovies = array_merge($allMovies, $similarResult['results']);
            }
            
            // Recommendations (oft bessere Ergebnisse!)
            $recoUrl = $this->baseUrl . '/movie/' . $movieId . '/recommendations';
            $recoParams = [
                'api_key' => $this->apiKey,
                'language' => 'de-DE',
                'page' => 1
            ];
            
            $recoResult = $this->makeRequest($recoUrl, $recoParams);
            if ($recoResult && !empty($recoResult['results'])) {
                $allMovies = array_merge($allMovies, $recoResult['results']);
            }
            
            if (empty($allMovies)) {
                return null;
            }
            
            // 3. Qualitäts-Filter anwenden
            $filtered = array_filter($allMovies, function($movie) {
                // Mindestens Rating 6.0
                $rating = $movie['vote_average'] ?? 0;
                if ($rating < 6.0) return false;
                
                // Mindestens 100 Votes (sonst könnte es Schrott sein)
                $votes = $movie['vote_count'] ?? 0;
                if ($votes < 100) return false;
                
                // Muss Poster haben
                if (empty($movie['poster_path'])) return false;
                
                return true;
            });
            
            // 4. Nach Popularität + Rating sortieren
            usort($filtered, function($a, $b) {
                $scoreA = ($a['vote_average'] ?? 0) * log($a['vote_count'] ?? 1);
                $scoreB = ($b['vote_average'] ?? 0) * log($b['vote_count'] ?? 1);
                return $scoreB <=> $scoreA; // Höchster Score zuerst
            });
            
            // 5. Duplikate entfernen (nach ID)
            $unique = [];
            $seenIds = [];
            foreach ($filtered as $movie) {
                $id = $movie['id'];
                if (!in_array($id, $seenIds)) {
                    $unique[] = $movie;
                    $seenIds[] = $id;
                }
            }
            
            // 6. Limitieren und formatieren
            $movies = array_slice($unique, 0, $limit);
            
            $formatted = array_map(function($movie) {
                return [
                    'title' => $movie['title'] ?? 'Unbekannt',
                    'original_title' => $movie['original_title'] ?? '',
                    'year' => isset($movie['release_date']) ? substr($movie['release_date'], 0, 4) : null,
                    'rating' => round($movie['vote_average'] ?? 0, 1),
                    'votes' => $movie['vote_count'] ?? 0,
                    'poster_path' => $movie['poster_path'] ?? null,
                    'overview' => $movie['overview'] ?? '',
                    'tmdb_id' => $movie['id'] ?? null
                ];
            }, $movies);
            
            // Cache speichern
            file_put_contents($cacheFile, json_encode($formatted));
            
            return $formatted;
            
        } catch (Exception $e) {
            error_log('TMDb Similar Movies Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Hole Film-Crew (Director, Writer, Composer, etc.)
     * 
     * @param string $title Film-Titel
     * @param int $year Jahr (optional)
     * @return array|null ['director' => 'Name', 'writers' => ['Name1', 'Name2'], 'composer' => 'Name', ...]
     */
    public function getFilmCrew($title, $year = null) {
        // Cache-Key generieren
        $cacheKey = md5('crew_' . $title . ($year ?? ''));
        $cacheFile = $this->cacheDir . '/' . $cacheKey . '.json';
        
        // Cache-Dauer aus Settings (Standard: 24 Stunden)
        $cacheHours = (int)getSetting('tmdb_cache_hours', '24');
        $cacheSeconds = $cacheHours * 3600;
        
        // Cache prüfen
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $cacheSeconds) {
            $cached = file_get_contents($cacheFile);
            return json_decode($cached, true);
        }
        
        try {
            // 1. Film suchen
            $searchUrl = $this->baseUrl . '/search/movie';
            $searchParams = [
                'api_key' => $this->apiKey,
                'query' => $title,
                'language' => 'de-DE'
            ];
            
            if ($year) {
                $searchParams['year'] = $year;
            }
            
            $searchResult = $this->makeRequest($searchUrl, $searchParams);
            
            if (!$searchResult || empty($searchResult['results'])) {
                return null;
            }
            
            $movieId = $searchResult['results'][0]['id'];
            
            // 2. Film-Details mit Credits holen
            $detailsUrl = $this->baseUrl . '/movie/' . $movieId;
            $detailsParams = [
                'api_key' => $this->apiKey,
                'append_to_response' => 'credits',
                'language' => 'de-DE'
            ];
            
            $details = $this->makeRequest($detailsUrl, $detailsParams);
            
            if (!$details || empty($details['credits']['crew'])) {
                return null;
            }
            
            $crew = $details['credits']['crew'];
            
            // 3. Crew-Mitglieder nach Job filtern
            $crewData = [
                'director' => null,
                'writers' => [],
                'composer' => null,
                'cinematographer' => null,
                'producer' => null
            ];
            
            foreach ($crew as $member) {
                $name = $member['name'] ?? '';
                $job = $member['job'] ?? '';
                
                if ($job === 'Director' && !$crewData['director']) {
                    $crewData['director'] = $name;
                }
                
                if (in_array($job, ['Screenplay', 'Writer', 'Story'])) {
                    if (!in_array($name, $crewData['writers'])) {
                        $crewData['writers'][] = $name;
                    }
                }
                
                if ($job === 'Original Music Composer' && !$crewData['composer']) {
                    $crewData['composer'] = $name;
                }
                
                if ($job === 'Director of Photography' && !$crewData['cinematographer']) {
                    $crewData['cinematographer'] = $name;
                }
                
                if ($job === 'Producer' && !$crewData['producer']) {
                    $crewData['producer'] = $name;
                }
            }
            
            // Limitiere Writers auf maximal 3
            $crewData['writers'] = array_slice($crewData['writers'], 0, 3);
            
            // Cache speichern
            file_put_contents($cacheFile, json_encode($crewData));
            
            return $crewData;
            
        } catch (Exception $e) {
            error_log('TMDb Crew API Error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Hole komplette Film-Details von TMDb
     * 
     * @param int $tmdbId TMDb Movie ID
     * @return array|null Film-Daten inkl. Videos, Credits, etc.
     */
    public function getMovieDetails($tmdbId) {
        try {
            // Hole Film-Details
            $url = $this->baseUrl . '/movie/' . $tmdbId;
            $params = [
                'api_key' => $this->apiKey,
                'language' => 'de-DE',
                'append_to_response' => 'videos,credits,keywords,release_dates'
            ];
            
            $data = $this->makeRequest($url, $params);
            
            if (!$data || !isset($data['id'])) {
                return null;
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log('TMDb getMovieDetails error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Hole komplette TV Show Details von TMDb
     * 
     * @param int $tmdbId TMDb TV Show ID
     * @return array|null TV Show Daten inkl. Seasons & Episodes
     */
    public function getTVShowDetails($tmdbId) {
        try {
            // Hole TV Show Details
            $url = $this->baseUrl . '/tv/' . $tmdbId;
            $params = [
                'api_key' => $this->apiKey,
                'language' => 'de-DE',
                'append_to_response' => 'videos,credits,keywords,content_ratings'
            ];
            
            $data = $this->makeRequest($url, $params);
            
            if (!$data || !isset($data['id'])) {
                return null;
            }
            
            // Hole alle Staffeln mit Episoden
            $data['seasons_detailed'] = [];
            if (!empty($data['seasons'])) {
                foreach ($data['seasons'] as $season) {
                    $seasonNumber = $season['season_number'];
                    
                    // Hole Staffel-Details inkl. Episoden
                    $seasonUrl = $this->baseUrl . '/tv/' . $tmdbId . '/season/' . $seasonNumber;
                    $seasonData = $this->makeRequest($seasonUrl, [
                        'api_key' => $this->apiKey,
                        'language' => 'de-DE'
                    ]);
                    
                    if ($seasonData) {
                        $data['seasons_detailed'][] = $seasonData;
                    }
                }
            }
            
            return $data;
            
        } catch (Exception $e) {
            error_log('TMDb getTVShowDetails error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * HTTP Request an TMDb API
     */
    private function makeRequest($url, $params = []) {
        $queryString = http_build_query($params);
        $fullUrl = $url . '?' . $queryString;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'DVD Profiler Liste/1.0');
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() ist seit PHP 8.0 nicht mehr nötig (automatisch)
        
        if ($httpCode !== 200) {
            return null;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Formatiere Rating für Anzeige
     */
    public static function formatRating($rating, $votes = null) {
        if (!$rating || $rating == 0) {
            return 'N/A';
        }
        
        $formatted = number_format($rating, 1);
        
        if ($votes) {
            $votesFormatted = self::formatVotes($votes);
            return $formatted . ' (' . $votesFormatted . ')';
        }
        
        return $formatted;
    }
    
    /**
     * Formatiere Vote-Anzahl
     */
    private static function formatVotes($votes) {
        if ($votes >= 1000000) {
            return round($votes / 1000000, 1) . 'M';
        } elseif ($votes >= 1000) {
            return round($votes / 1000, 1) . 'K';
        }
        return $votes;
    }
    
    /**
     * Hole Rating-Farbe basierend auf Score
     */
    public static function getRatingColor($rating) {
        if ($rating >= 8.0) return '#4caf50'; // Grün - Sehr gut
        if ($rating >= 7.0) return '#8bc34a'; // Hellgrün - Gut
        if ($rating >= 6.0) return '#ffc107'; // Gelb - OK
        if ($rating >= 5.0) return '#ff9800'; // Orange - Mäßig
        return '#f44336'; // Rot - Schlecht
    }
}

/**
 * Helper-Funktion: Hole Ratings für einen Film
 */
function getFilmRatings($title, $year = null) {
    static $tmdb = null;
    
    // TMDb API Key aus Settings laden
    $apiKey = getSetting('tmdb_api_key', '');
    
    if (empty($apiKey)) {
        return null; // Kein API Key konfiguriert
    }
    
    if ($tmdb === null) {
        $tmdb = new TMDbHelper($apiKey);
    }
    
    return $tmdb->getFilmRatings($title, $year);
}

/**
 * Helper-Funktion: Hole Crew-Mitglieder für einen Film
 */
function getFilmCrew($title, $year = null) {
    static $tmdb = null;
    
    // TMDb API Key aus Settings laden
    $apiKey = getSetting('tmdb_api_key', '');
    
    if (empty($apiKey)) {
        return null; // Kein API Key konfiguriert
    }
    
    if ($tmdb === null) {
        $tmdb = new TMDbHelper($apiKey);
    }
    
    return $tmdb->getFilmCrew($title, $year);
}

/**
 * Helper-Funktion: Hole komplette Film-Details von TMDb
 */
function getTMDbMovie($tmdbId) {
    static $tmdb = null;
    
    // TMDb API Key aus Settings laden
    $apiKey = getSetting('tmdb_api_key', '');
    
    if (empty($apiKey)) {
        error_log('TMDb: No API key configured');
        return null;
    }
    
    if ($tmdb === null) {
        $tmdb = new TMDbHelper($apiKey);
    }
    
    return $tmdb->getMovieDetails($tmdbId);
}

/**
 * Helper-Funktion: Hole komplette TV Show Details von TMDb
 */
function getTMDbTVShow($tmdbId) {
    static $tmdb = null;
    
    // TMDb API Key aus Settings laden
    $apiKey = getSetting('tmdb_api_key', '');
    
    if (empty($apiKey)) {
        error_log('TMDb: No API key configured');
        return null;
    }
    
    if ($tmdb === null) {
        $tmdb = new TMDbHelper($apiKey);
    }
    
    return $tmdb->getTVShowDetails($tmdbId);
}