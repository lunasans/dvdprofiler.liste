<?php
/**
 * Settings Helper für Impressum
 * Falls saveSetting() Funktion nicht in bootstrap.php existiert
 */

if (!function_exists('saveSetting')) {
    /**
     * Speichert eine Einstellung in der settings Tabelle
     * 
     * @param string $key Setting Key
     * @param string $value Setting Value
     * @return bool Success
     */
    function saveSetting($key, $value) {
        global $pdo;
        
        if (!$pdo) {
            throw new Exception('Keine Datenbankverbindung');
        }
        
        // Prüfe ob Setting existiert
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $exists = $stmt->fetchColumn() > 0;
        
        if ($exists) {
            // UPDATE
            $stmt = $pdo->prepare("UPDATE settings SET `value` = ?, updated_at = NOW() WHERE `key` = ?");
            return $stmt->execute([$value, $key]);
        } else {
            // INSERT
            $stmt = $pdo->prepare("INSERT INTO settings (`key`, `value`, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
            return $stmt->execute([$key, $value]);
        }
    }
}

if (!function_exists('getSetting')) {
    /**
     * Holt eine Einstellung aus der settings Tabelle
     * 
     * @param string $key Setting Key
     * @param mixed $default Default Value
     * @return mixed Setting Value oder Default
     */
    function getSetting($key, $default = null) {
        global $pdo;
        
        if (!$pdo) {
            return $default;
        }
        
        $stmt = $pdo->prepare("SELECT `value` FROM settings WHERE `key` = ?");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        
        return $value !== false ? $value : $default;
    }
}