<?php
/**
 * Settings AJAX Handler - COMPLETE VERSION
 * Mit korrekter Checkbox-Validierung
 */

// Bootstrap laden (2 Ebenen nach oben!)
require_once __DIR__ . '/../../includes/bootstrap.php';

// Nur AJAX-Requests erlauben
if (!isset($_POST['ajax']) || $_POST['ajax'] !== '1') {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nur AJAX-Requests erlaubt']);
    exit;
}

// Nur für eingeloggte User
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

header('Content-Type: application/json');

// CSRF-Token prüfen
$submittedToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($submittedToken)) {
    echo json_encode(['success' => false, 'message' => '❌ Ungültiger CSRF-Token']);
    exit;
}

// Welcher Tab/Bereich?
$section = $_POST['section'] ?? '';

// Tab-spezifische Settings-Gruppen
$settingsGroups = [
    'general' => [
        'site_title' => ['maxlength' => 255, 'required' => true],
        'site_description' => ['maxlength' => 500],
        'base_url' => ['maxlength' => 500], // URL-Validierung entfernt (zu streng)
        'environment' => ['maxlength' => 20],
        'theme' => ['maxlength' => 50],
        'items_per_page' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 5, 'max_range' => 100]],
        'latest_films_count' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 5, 'max_range' => 50]]
    ],
    'security' => [
        'enable_2fa' => ['type' => 'checkbox'],
        'login_attempts_max' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1, 'max_range' => 10]],
        'login_lockout_time' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 60, 'max_range' => 3600]],
        'session_timeout' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 300, 'max_range' => 86400]]
    ],
    'tmdb' => [
        'tmdb_api_key' => ['maxlength' => 255],
        'tmdb_show_ratings_on_cards' => ['type' => 'checkbox'],
        'tmdb_show_ratings_details' => ['type' => 'checkbox'],
        'tmdb_cache_hours' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1, 'max_range' => 168]],
        'tmdb_show_similar_movies' => ['type' => 'checkbox'],
        'tmdb_auto_download_covers' => ['type' => 'checkbox']
    ],
    'signature' => [
        'signature_enabled' => ['type' => 'checkbox'],
        'signature_film_count' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 5, 'max_range' => 20]],
        'signature_film_source' => ['maxlength' => 50],
        'signature_cache_time' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 1800, 'max_range' => 86400]],
        'signature_enable_type1' => ['type' => 'checkbox'],
        'signature_enable_type2' => ['type' => 'checkbox'],
        'signature_enable_type3' => ['type' => 'checkbox'],
        'signature_show_title' => ['type' => 'checkbox'],
        'signature_show_year' => ['type' => 'checkbox'],
        'signature_show_rating' => ['type' => 'checkbox'],
        'signature_quality' => ['filter' => FILTER_VALIDATE_INT, 'options' => ['min_range' => 0, 'max_range' => 9]]
    ]
];

// Prüfe ob Section existiert
if (!isset($settingsGroups[$section])) {
    echo json_encode(['success' => false, 'message' => '❌ Ungültiger Bereich']);
    exit;
}

$allowedSettings = $settingsGroups[$section];
$savedCount = 0;
$errors = [];

foreach ($allowedSettings as $key => $validation) {
    // Checkbox? (neues System mit 'type' => 'checkbox')
    $isCheckbox = isset($validation['type']) && $validation['type'] === 'checkbox';
    
    if (isset($_POST[$key])) {
        $value = $_POST[$key];
        
        // Checkbox-Handling
        if ($isCheckbox) {
            // Wert ist "1" oder "0" - direkt speichern
            $value = ($value === '1' || $value === 1) ? '1' : '0';
            if (setSetting($key, $value)) {
                $savedCount++;
            }
            continue;
        }
        
        // Normale Felder
        $value = trim($value);
        
        // Validierung
        if (isset($validation['filter'])) {
            $options = $validation['options'] ?? null;
            if (!filter_var($value, $validation['filter'], $options)) {
                $errors[] = "Ungültiger Wert für {$key}";
                continue;
            }
        }
        
        if (isset($validation['maxlength']) && strlen($value) > $validation['maxlength']) {
            $errors[] = "{$key} ist zu lang (max. {$validation['maxlength']} Zeichen)";
            continue;
        }
        
        if (isset($validation['required']) && empty($value)) {
            $errors[] = "{$key} ist erforderlich";
            continue;
        }
        
        if (setSetting($key, $value)) {
            $savedCount++;
        }
    } elseif ($isCheckbox) {
        // Checkbox nicht gecheckt = speichere "0"
        if (setSetting($key, '0')) {
            $savedCount++;
        }
    }
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false, 
        'message' => '❌ ' . implode(', ', $errors)
    ]);
} else {
    echo json_encode([
        'success' => true, 
        'message' => "✅ {$savedCount} Einstellung(en) erfolgreich gespeichert",
        'count' => $savedCount
    ]);
}