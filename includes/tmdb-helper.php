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