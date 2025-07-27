<?php
/**
 * DVD Profiler Liste - Settings Management
 * Zentrales Settings-Management mit Caching und Validierung
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

namespace DVDProfiler\Core;

use Exception;
use PDOException;

/**
 * Settings-Management-Klasse
 * Verwaltet alle Anwendungseinstellungen mit Caching und Validierung
 */
class Settings
{
    private Database $database;
    private array $cache = [];
    private array $defaults = [];
    private bool $cacheLoaded = false;
    private bool $debugMode = false;
    
    /** @var array<string, array> Validierungsregeln für Settings */
    private array $validationRules = [];
    
    /** @var array<string, string> Typ-Mapping für Settings */
    private array $typeMapping = [];
    
    /**
     * Constructor
     */
    public function __construct(Database $database)
    {
        $this->database = $database;
        $this->setupValidationRules();
        $this->setupDefaults();
        $this->loadCache();
    }
    
    /**
     * Validierungsregeln definieren
     */
    private function setupValidationRules(): void
    {
        $this->validationRules = [
            'site_title' => ['type' => 'string', 'max_length' => 100, 'required' => true],
            'base_url' => ['type' => 'url', 'max_length' => 255],
            'language' => ['type' => 'string', 'max_length' => 5, 'pattern' => '/^[a-z]{2}(_[A-Z]{2})?$/'],
            'items_per_page' => ['type' => 'integer', 'min' => 5, 'max' => 100],
            'max_file_size' => ['type' => 'integer', 'min' => 1024, 'max' => 50 * 1024 * 1024],
            'enable_2fa' => ['type' => 'boolean'],
            'smtp_port' => ['type' => 'integer', 'min' => 1, 'max' => 65535],
            'session_timeout' => ['type' => 'integer', 'min' => 300, 'max' => 86400],
            'login_attempts' => ['type' => 'integer', 'min' => 1, 'max' => 20],
            'lock_duration' => ['type' => 'integer', 'min' => 1, 'max' => 1440],
            'backup_retention_days' => ['type' => 'integer', 'min' => 1, 'max' => 365],
            'environment' => ['type' => 'string', 'allowed' => ['development', 'staging', 'production']],
        ];
        
        $this->typeMapping = [
            'boolean' => ['0', '1', 'false', 'true', 'off', 'on'],
            'integer' => 'int',
            'float' => 'float',
            'string' => 'string',
            'url' => 'string',
            'email' => 'string',
            'json' => 'string'
        ];
    }
    
    /**
     * Standard-Werte definieren
     */
    private function setupDefaults(): void
    {
        $this->defaults = [
            'site_title' => 'DVD Profiler Liste',
            'language' => 'de',
            'items_per_page' => '20',
            'max_file_size' => '10485760', // 10MB
            'enable_2fa' => '0',
            'smtp_port' => '587',
            'session_timeout' => '7200',
            'login_attempts' => '5',
            'lock_duration' => '15',
            'backup_retention_days' => '30',
            'environment' => 'production',
            'theme' => 'default',
            'allowed_extensions' => 'jpg,jpeg,png,gif',
            'smtp_encryption' => 'tls',
            'enable_registration' => '0',
            'backup_enabled' => '0',
            'db_version' => '1.4.7'
        ];
    }
    
    /**
     * Cache laden
     */
    private function loadCache(): void
    {
        if ($this->cacheLoaded) {
            return;
        }
        
        try {
            // Prüfe ob Settings-Tabelle existiert
            if (!$this->database->tableExists('settings')) {
                $this->cache = $this->defaults;
                $this->cacheLoaded = true;
                return;
            }
            
            // Lade alle Settings in Cache
            $settings = $this->database->fetchPairs("SELECT `key`, `value` FROM settings");
            
            // Merge mit Defaults für fehlende Werte
            $this->cache = array_merge($this->defaults, $settings);
            $this->cacheLoaded = true;
            
            if ($this->debugMode) {
                error_log('[Settings] Loaded ' . count($this->cache) . ' settings into cache');
            }
            
        } catch (PDOException $e) {
            error_log('[Settings] Failed to load cache: ' . $e->getMessage());
            $this->cache = $this->defaults;
            $this->cacheLoaded = true;
        }
    }
    
    /**
     * Setting-Wert abrufen
     */
    public function get(string $key, string $default = ''): string
    {
        // Key-Validierung
        if (!$this->isValidKey($key)) {
            error_log("[Settings] Invalid key attempted: " . substr($key, 0, 50));
            return $default;
        }
        
        $this->loadCache();
        
        // Aus Cache oder Default zurückgeben
        $value = $this->cache[$key] ?? $this->defaults[$key] ?? $default;
        
        // Typ-Konvertierung falls notwendig
        return $this->convertType($value, $key);
    }
    
    /**
     * Setting-Wert als spezifischen Typ abrufen
     */
    public function getInt(string $key, int $default = 0): int
    {
        return (int) $this->get($key, (string) $default);
    }
    
    public function getBool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default ? '1' : '0');
        return in_array(strtolower($value), ['1', 'true', 'on', 'yes'], true);
    }
    
    public function getFloat(string $key, float $default = 0.0): float
    {
        return (float) $this->get($key, (string) $default);
    }
    
    public function getArray(string $key, array $default = []): array
    {
        $value = $this->get($key, json_encode($default));
        
        // JSON-Decode falls möglich
        if ($decoded = json_decode($value, true)) {
            return $decoded;
        }
        
        // Komma-separierte Liste
        if (str_contains($value, ',')) {
            return array_map('trim', explode(',', $value));
        }
        
        return $default;
    }
    
    /**
     * Setting-Wert setzen
     */
    public function set(string $key, string $value): bool
    {
        // Key-Validierung
        if (!$this->isValidKey($key)) {
            error_log("[Settings] Invalid key attempted: " . substr($key, 0, 50));
            return false;
        }
        
        // Wert-Validierung
        if (!$this->validateValue($key, $value)) {
            error_log("[Settings] Invalid value for key '{$key}': " . substr($value, 0, 100));
            return false;
        }
        
        try {
            // In Datenbank speichern (UPSERT)
            $result = $this->database->execute(
                "INSERT INTO settings (`key`, `value`) VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = NOW()",
                [$key, $value]
            );
            
            if ($result) {
                // Cache aktualisieren
                $this->cache[$key] = $value;
                
                if ($this->debugMode) {
                    error_log("[Settings] Updated '{$key}' = '{$value}'");
                }
            }
            
            return $result->rowCount() > 0;
            
        } catch (PDOException $e) {
            error_log("[Settings] Update failed for key '{$key}': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Multiple Settings auf einmal setzen
     */
    public function setMultiple(array $settings): array
    {
        $results = [];
        
        $this->database->beginTransaction();
        
        try {
            foreach ($settings as $key => $value) {
                $results[$key] = $this->set($key, $value);
            }
            
            $this->database->commit();
            
        } catch (Exception $e) {
            $this->database->rollback();
            error_log("[Settings] Batch update failed: " . $e->getMessage());
        }
        
        return $results;
    }
    
    /**
     * Setting löschen
     */
    public function delete(string $key): bool
    {
        if (!$this->isValidKey($key)) {
            return false;
        }
        
        try {
            $deleted = $this->database->delete('settings', '`key` = ?', [$key]);
            
            if ($deleted > 0) {
                unset($this->cache[$key]);
                
                if ($this->debugMode) {
                    error_log("[Settings] Deleted setting: {$key}");
                }
            }
            
            return $deleted > 0;
            
        } catch (PDOException $e) {
            error_log("[Settings] Delete failed for key '{$key}': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Alle Settings abrufen (für Admin-Panel)
     */
    public function getAll(bool $includePrivate = false): array
    {
        $this->loadCache();
        
        if ($includePrivate) {
            return $this->cache;
        }
        
        // Nur öffentliche Settings zurückgeben
        try {
            return $this->database->fetchPairs(
                "SELECT `key`, `value` FROM settings WHERE is_public = 1"
            );
        } catch (PDOException $e) {
            error_log("[Settings] Failed to load public settings: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Settings nach Prefix abrufen
     */
    public function getByPrefix(string $prefix): array
    {
        $this->loadCache();
        
        $result = [];
        foreach ($this->cache as $key => $value) {
            if (str_starts_with($key, $prefix)) {
                $result[$key] = $value;
            }
        }
        
        return $result;
    }
    
    /**
     * Cache-Statistiken abrufen
     */
    public function getCacheStats(): array
    {
        return [
            'loaded' => $this->cacheLoaded,
            'size' => count($this->cache),
            'memory_usage' => memory_get_usage(true)
        ];
    }
    
    /**
     * Cache manuell neu laden
     */
    public function reloadCache(): void
    {
        $this->cache = [];
        $this->cacheLoaded = false;
        $this->loadCache();
    }
    
    /**
     * Key-Validierung
     */
    private function isValidKey(string $key): bool
    {
        return preg_match('/^[a-zA-Z0-9_.-]+$/', $key) && strlen($key) <= 100;
    }
    
    /**
     * Wert-Validierung basierend auf Regeln
     */
    private function validateValue(string $key, string $value): bool
    {
        // Grundlegende Längen-Validierung
        if (strlen($value) > 10000) {
            return false;
        }
        
        // Spezifische Regeln prüfen
        if (!isset($this->validationRules[$key])) {
            return true; // Keine Regeln = erlaubt
        }
        
        $rules = $this->validationRules[$key];
        
        // Required-Check
        if (($rules['required'] ?? false) && empty($value)) {
            return false;
        }
        
        // Typ-spezifische Validierung
        switch ($rules['type']) {
            case 'integer':
                if (!is_numeric($value)) return false;
                $intValue = (int) $value;
                if (isset($rules['min']) && $intValue < $rules['min']) return false;
                if (isset($rules['max']) && $intValue > $rules['max']) return false;
                break;
                
            case 'boolean':
                if (!in_array($value, ['0', '1', 'false', 'true', 'off', 'on'])) return false;
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) return false;
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) return false;
                break;
                
            case 'string':
                if (isset($rules['max_length']) && strlen($value) > $rules['max_length']) return false;
                if (isset($rules['pattern']) && !preg_match($rules['pattern'], $value)) return false;
                if (isset($rules['allowed']) && !in_array($value, $rules['allowed'])) return false;
                break;
        }
        
        return true;
    }
    
    /**
     * Typ-Konvertierung
     */
    private function convertType(string $value, string $key): string
    {
        // Für die meisten Fälle String zurückgeben
        // Typ-spezifische Getter verwenden bei Bedarf
        return $value;
    }
    
    /**
     * Debug-Modus aktivieren
     */
    public function setDebugMode(bool $enabled): void
    {
        $this->debugMode = $enabled;
    }
}