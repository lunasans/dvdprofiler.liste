<?php
/**
 * DVD Profiler Liste - Impressum speichern
 * 
 * @package    dvdprofiler.liste
 * @version    1.4.8
 */

// Error Reporting für Debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/bootstrap.php';

// Settings Helper laden (falls saveSetting nicht in bootstrap.php existiert)
if (!function_exists('saveSetting')) {
    require_once __DIR__ . '/../../includes/settings-helper.php';
}

// HTML Purifier laden (mit Fallback)
$purifierExists = file_exists(__DIR__ . '/../../includes/html-purifier.php');
if ($purifierExists) {
    require_once __DIR__ . '/../../includes/html-purifier.php';
}

// Sicherheitscheck
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// CSRF-Check
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    $_SESSION['impressum_error'] = 'Ungültiges CSRF-Token';
    header('Location: ../index.php?page=impressum');
    exit;
}

try {
    // Nur Settings speichern?
    if (isset($_POST['settings_only'])) {
        $impressumEnabled = isset($_POST['impressum_enabled']) ? '1' : '0';
        
        // Prüfe ob saveSetting Funktion existiert
        if (!function_exists('saveSetting')) {
            throw new Exception('saveSetting Funktion nicht gefunden');
        }
        
        saveSetting('impressum_enabled', $impressumEnabled);
        
        $_SESSION['impressum_success'] = 'Einstellungen gespeichert!';
        header('Location: ../index.php?page=impressum');
        exit;
    }
    
    // Vollständiges Impressum speichern
    $impressumName = trim($_POST['impressum_name'] ?? '');
    $impressumEmail = trim($_POST['impressum_email'] ?? '');
    $impressumContent = $_POST['impressum_content'] ?? '';
    
    // Validierung
    if (empty($impressumName)) {
        throw new Exception('Bitte geben Sie einen Namen an');
    }
    
    if (empty($impressumEmail) || !filter_var($impressumEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Bitte geben Sie eine gültige E-Mail-Adresse an');
    }
    
    // HTML bereinigen
    if ($purifierExists && function_exists('purifyHTML')) {
        // Mit HTML Purifier
        $impressumContent = purifyHTML($impressumContent, true);
    } else {
        // Fallback: Einfaches strip_tags
        $allowedTags = '<p><br><b><i><u><strong><em><ul><ol><li><h2><h3><h4><a><blockquote><span><div>';
        $impressumContent = strip_tags($impressumContent, $allowedTags);
        
        // Gefährliche Patterns entfernen
        $impressumContent = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i', '', $impressumContent);
        $impressumContent = preg_replace('/on\w+\s*=\s*["\']?[^"\']*["\']?/i', '', $impressumContent);
        $impressumContent = preg_replace('/javascript:/i', '', $impressumContent);
    }
    
    // Prüfe ob saveSetting Funktion existiert
    if (!function_exists('saveSetting')) {
        throw new Exception('saveSetting Funktion nicht gefunden - bitte bootstrap.php prüfen');
    }
    
    // Speichern
    saveSetting('impressum_name', $impressumName);
    saveSetting('impressum_email', $impressumEmail);
    saveSetting('impressum_content', $impressumContent);
    
    $_SESSION['impressum_success'] = 'Impressum erfolgreich gespeichert!';
    header('Location: ../index.php?page=impressum');
    exit;
    
} catch (Exception $e) {
    // Detaillierter Fehler für Debugging
    error_log('Impressum Save Error: ' . $e->getMessage());
    error_log('Stack Trace: ' . $e->getTraceAsString());
    
    $_SESSION['impressum_error'] = 'Fehler beim Speichern: ' . $e->getMessage();
    header('Location: ../index.php?page=impressum');
    exit;
}