<?php
/**
 * DVD Profiler Liste - Utility Functions
 * Zentrale Helper-Funktionen für allgemeine Aufgaben
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

namespace DVDProfiler\Core;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * Utility-Klasse
 * Sammlung von statischen Helper-Funktionen
 */
class Utils
{
    /**
     * Byte-Werte in menschenlesbare Formatierung
     */
    public static function formatBytes(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    /**
     * Zeitdauer in menschenlesbarer Form
     */
    public static function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' Sekunden';
        }
        
        if ($seconds < 3600) {
            $minutes = floor($seconds / 60);
            $remainingSeconds = $seconds % 60;
            
            if ($remainingSeconds === 0) {
                return $minutes . ' Minuten';
            }
            
            return $minutes . ' Minuten, ' . $remainingSeconds . ' Sekunden';
        }
        
        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $remainingMinutes = floor(($seconds % 3600) / 60);
            
            if ($remainingMinutes === 0) {
                return $hours . ' Stunden';
            }
            
            return $hours . ' Stunden, ' . $remainingMinutes . ' Minuten';
        }
        
        $days = floor($seconds / 86400);
        $remainingHours = floor(($seconds % 86400) / 3600);
        
        if ($remainingHours === 0) {
            return $days . ' Tage';
        }
        
        return $days . ' Tage, ' . $remainingHours . ' Stunden';
    }
    
    /**
     * Relative Zeit ("vor 5 Minuten")
     */
    public static function timeAgo(int $timestamp): string
    {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'vor ' . $diff . ' Sekunden';
        }
        
        if ($diff < 3600) {
            $minutes = floor($diff / 60);
            return 'vor ' . $minutes . ' Minute' . ($minutes > 1 ? 'n' : '');
        }
        
        if ($diff < 86400) {
            $hours = floor($diff / 3600);
            return 'vor ' . $hours . ' Stunde' . ($hours > 1 ? 'n' : '');
        }
        
        if ($diff < 2592000) { // 30 Tage
            $days = floor($diff / 86400);
            return 'vor ' . $days . ' Tag' . ($days > 1 ? 'en' : '');
        }
        
        if ($diff < 31536000) { // 365 Tage
            $months = floor($diff / 2592000);
            return 'vor ' . $months . ' Monat' . ($months > 1 ? 'en' : '');
        }
        
        $years = floor($diff / 31536000);
        return 'vor ' . $years . ' Jahr' . ($years > 1 ? 'en' : '');
    }
    
    /**
     * URL-slug generieren
     */
    public static function createSlug(string $text, int $maxLength = 100): string
    {
        // Umlaute ersetzen
        $umlauts = [
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
            'ß' => 'ss'
        ];
        
        $text = str_replace(array_keys($umlauts), array_values($umlauts), $text);
        
        // Zu lowercase
        $text = strtolower($text);
        
        // Nur Buchstaben, Zahlen und Bindestriche
        $text = preg_replace('/[^a-z0-9-]/', '-', $text);
        
        // Mehrfache Bindestriche entfernen
        $text = preg_replace('/-+/', '-', $text);
        
        // Führende/abschließende Bindestriche entfernen
        $text = trim($text, '-');
        
        // Länge begrenzen
        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength);
            $text = rtrim($text, '-');
        }
        
        return $text;
    }
    
    /**
     * Zufällige Farbe generieren (Hex)
     */
    public static function generateRandomColor(): string
    {
        return sprintf('#%06X', mt_rand(0, 0xFFFFFF));
    }
    
    /**
     * Array nach Schlüssel sortieren (Unicode-safe)
     */
    public static function sortArrayByKey(array $array, string $key, bool $ascending = true): array
    {
        usort($array, function($a, $b) use ($key, $ascending) {
            $valueA = $a[$key] ?? '';
            $valueB = $b[$key] ?? '';
            
            $result = strcoll($valueA, $valueB);
            
            return $ascending ? $result : -$result;
        });
        
        return $array;
    }
    
    /**
     * Array-Werte plattdrücken
     */
    public static function flattenArray(array $array, string $delimiter = '.'): array
    {
        $result = [];
        
        $flatten = function($arr, $prefix = '') use (&$result, &$flatten, $delimiter) {
            foreach ($arr as $key => $value) {
                $newKey = $prefix === '' ? $key : $prefix . $delimiter . $key;
                
                if (is_array($value)) {
                    $flatten($value, $newKey);
                } else {
                    $result[$newKey] = $value;
                }
            }
        };
        
        $flatten($array);
        return $result;
    }
    
    /**
     * URL-Parameter sicher hinzufügen
     */
    public static function addUrlParams(string $url, array $params): string
    {
        $parsedUrl = parse_url($url);
        
        // Bestehende Query-Parameter parsen
        $queryParams = [];
        if (!empty($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $queryParams);
        }
        
        // Neue Parameter hinzufügen
        $queryParams = array_merge($queryParams, $params);
        
        // URL wieder zusammensetzen
        $newUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
        
        if (!empty($parsedUrl['port'])) {
            $newUrl .= ':' . $parsedUrl['port'];
        }
        
        $newUrl .= $parsedUrl['path'] ?? '/';
        
        if (!empty($queryParams)) {
            $newUrl .= '?' . http_build_query($queryParams);
        }
        
        if (!empty($parsedUrl['fragment'])) {
            $newUrl .= '#' . $parsedUrl['fragment'];
        }
        
        return $newUrl;
    }
    
    /**
     * JSON sicher dekodieren
     */
    public static function jsonDecode(string $json, bool $associative = true): mixed
    {
        $result = json_decode($json, $associative);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON decode error: ' . json_last_error_msg());
        }
        
        return $result;
    }
    
    /**
     * JSON sicher kodieren
     */
    public static function jsonEncode(mixed $data, int $flags = JSON_UNESCAPED_UNICODE): string
    {
        $result = json_encode($data, $flags);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('JSON encode error: ' . json_last_error_msg());
        }
        
        return $result;
    }
    
    /**
     * String kürzen mit Ellipsis
     */
    public static function truncate(string $text, int $length, string $suffix = '...'): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length - mb_strlen($suffix)) . $suffix;
    }
    
    /**
     * Array in Chunks aufteilen
     */
    public static function arrayChunk(array $array, int $size): array
    {
        return array_chunk($array, $size, true);
    }
    
    /**
     * Zufälligen Array-Wert abrufen
     */
    public static function arrayRandom(array $array): mixed
    {
        if (empty($array)) {
            return null;
        }
        
        $keys = array_keys($array);
        $randomKey = $keys[array_rand($keys)];
        
        return $array[$randomKey];
    }
    
    /**
     * Array-Schlüssel umbenennen
     */
    public static function arrayRenameKeys(array $array, array $mapping): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $mapping[$key] ?? $key;
            $result[$newKey] = $value;
        }
        
        return $result;
    }
    
    /**
     * CSV-sichere Escapes
     */
    public static function csvEscape(string $value): string
    {
        // Anführungszeichen escapen
        $value = str_replace('"', '""', $value);
        
        // Wert in Anführungszeichen setzen wenn nötig
        if (str_contains($value, ',') || str_contains($value, '"') || str_contains($value, "\n")) {
            $value = '"' . $value . '"';
        }
        
        return $value;
    }
    
    /**
     * Debug-Ausgabe formatiert
     */
    public static function dump(mixed $data, bool $return = false): ?string
    {
        $output = '<pre>' . print_r($data, true) . '</pre>';
        
        if ($return) {
            return $output;
        }
        
        echo $output;
        return null;
    }
    
    /**
     * Environment-Variable sicher abrufen
     */
    public static function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        
        if ($value === false) {
            return $default;
        }
        
        // Boolean-Werte konvertieren
        switch (strtolower($value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'null':
            case '(null)':
                return null;
            case 'empty':
            case '(empty)':
                return '';
        }
        
        // Anführungszeichen entfernen
        if (strlen($value) > 1 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }
        
        return $value;
    }
    
    /**
     * Directory-Größe berechnen
     */
    public static function getDirectorySize(string $directory): int
    {
        $size = 0;
        
        if (!is_dir($directory)) {
            return 0;
        }
        
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }
        
        return $size;
    }
    
    /**
     * Verzeichnis rekursiv löschen
     */
    public static function deleteDirectory(string $directory): bool
    {
        if (!is_dir($directory)) {
            return false;
        }
        
        $files = array_diff(scandir($directory), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $directory . DIRECTORY_SEPARATOR . $file;
            
            if (is_dir($path)) {
                self::deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($directory);
    }
    
    /**
     * MIME-Type einer Datei ermitteln
     */
    public static function getMimeType(string $filename): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filename);
            finfo_close($finfo);
            
            if ($mimeType !== false) {
                return $mimeType;
            }
        }
        
        // Fallback basierend auf Dateiendung
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'zip' => 'application/zip',
        ];
        
        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
    
    /**
     * Temporäre Datei sicher erstellen
     */
    public static function createTempFile(string $prefix = 'dvdprofiler_', string $suffix = '.tmp'): string
    {
        $tempDir = sys_get_temp_dir();
        $filename = $prefix . uniqid() . $suffix;
        $fullPath = $tempDir . DIRECTORY_SEPARATOR . $filename;
        
        // Datei erstellen mit restriktiven Berechtigungen
        touch($fullPath);
        chmod($fullPath, 0600);
        
        return $fullPath;
    }
}