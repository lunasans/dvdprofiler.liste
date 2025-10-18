<?php
/**
 * DVD Profiler Liste - Versionsverwaltung
 * Zentrale Stelle für alle Versionsinformationen
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @repository https://update.neuhaus.or.at/dvdprofiler-liste (eigenes Update-System)
 */

// Version Information
define('DVDPROFILER_VERSION', '1.4.7');
define('DVDPROFILER_CODENAME', 'Cinephile');
define('DVDPROFILER_BUILD_DATE', '2025.07.25');
define('DVDPROFILER_RELEASE_DATE', '25. Juli 2025');

// Build Information
define('DVDPROFILER_BUILD_TYPE', 'Release'); // Release, Beta, Alpha, Development
define('DVDPROFILER_BRANCH', 'main');
define('DVDPROFILER_COMMIT', '207ece9'); // Git commit hash (ersten 7 Zeichen)

// Repository Information (GitHub-basiert)
define('DVDPROFILER_REPOSITORY', 'lunasans/dvdprofiler.liste');
define('DVDPROFILER_GITHUB_URL', 'https://github.com/lunasans/dvdprofiler.liste');
define('DVDPROFILER_AUTHOR', 'René Neuhaus');

// Feature Flags
define('DVDPROFILER_FEATURES', [
    // Core Features
    'xml_import' => true,
    'database_management' => true,
    'boxset_support' => true,
    'responsive_design' => true,
    'glass_morphism' => true,
    
    // Search & Navigation
    'advanced_search' => true,
    'film_details' => true,
    'list_grid_view' => true,
    'pagination' => true,
    'trailer_integration' => true,
    
    // UI/UX Features
    'bootstrap_icons' => true,
    'fancybox_lightbox' => true,
    'chart_statistics' => true,
    'visitor_counter' => true,
    'dark_mode' => true,
    
    // Admin Features
    'admin_panel' => true,
    'user_authentication' => true,
    'system_updates' => true,
    'batch_import' => true,
    'maintenance_tools' => true,
    
    // Security & Privacy
    'gdpr_compliance' => true,
    'content_security_policy' => true,
    'xss_protection' => true,
    'data_encryption' => true,
    
    // Future Features
    'multi_language' => false,
    'api_interface' => false,
    'mobile_app' => false,
    'cloud_sync' => false,
    'social_features' => false,
    'user_ratings' => false,
    'watchlist' => false,
    'recommendations' => false
]);

// System Requirements
define('DVDPROFILER_REQUIREMENTS', [
    'php_min' => '7.4.0',
    'php_recommended' => '8.0.0',
    'mysql_min' => '5.7.0',
    'mysql_recommended' => '8.0.0',
    'apache_min' => '2.4.0',
    'nginx_min' => '1.18.0',
    'browser_support' => [
        'Chrome' => '90+',
        'Firefox' => '88+',
        'Safari' => '14+',
        'Edge' => '90+'
    ],
    'php_extensions' => [
        'required' => ['pdo', 'pdo_mysql', 'mbstring', 'json', 'openssl'],
        'optional' => ['zip', 'curl', 'gd', 'exif']
    ]
]);

// Technology Stack Information
define('DVDPROFILER_TECH_STACK', [
    'backend' => [
        'language' => 'PHP',
        'version' => '7.4+',
        'database' => 'MySQL/MariaDB',
        'server' => 'Apache/Nginx'
    ],
    'frontend' => [
        'html' => 'HTML5',
        'css' => 'CSS3 + Bootstrap Icons',
        'javascript' => 'Vanilla JS + Libraries',
        'design' => 'Glass Morphism'
    ],
    'libraries' => [
        'fancybox' => '5.0.36',
        'chart_js' => '4.x',
        'bootstrap_icons' => '1.x'
    ]
]);

// Changelog (letzte 5 Versionen)
define('DVDPROFILER_CHANGELOG', [
    '1.4.6' => [
        'date' => '2025-07-20',
        'type' => 'minor',
        'changes' => [
            'Enhanced session management',
            'Performance optimizations for large collections',
            'UI/UX improvements in admin panel',
            'Updated documentation and code comments',
            'Bug fixes in Charts.js integration',
            'Improved error handling in database operations',
        ]
    ],

    '1.4.7' => [
        'date' => '2025-07-25',
        'type'=> 'minor',
        'changes' => [
            'Fixed critical bug in isDVDProfilerFeatureEnabled function',
            'Reverted to GitHub-based update system',
            'Improved error handling in version management',
            'Enhanced compatibility with existing codebase',
            'Fixed syntax errors in core functions',
            'Restored GitHub API integration',
            'Improved code documentation',
            'Better fallback handling for update checks',
        ]
    ],
]);

// Helper Functions
function getDVDProfilerVersion()
{
    return DVDPROFILER_VERSION;
}

function getDVDProfilerVersionFull()
{
    $version = DVDPROFILER_VERSION;
    $codename = DVDPROFILER_CODENAME;
    $buildType = DVDPROFILER_BUILD_TYPE;
    $buildDate = DVDPROFILER_BUILD_DATE;

    if ($buildType !== 'Release') {
        return "{$version}-{$buildType} \"{$codename}\" (Build {$buildDate})";
    }

    return "{$version} \"{$codename}\"";
}

function getDVDProfilerBuildInfo()
{
    return [
        'version' => DVDPROFILER_VERSION,
        'codename' => DVDPROFILER_CODENAME,
        'build_date' => DVDPROFILER_BUILD_DATE,
        'build_type' => DVDPROFILER_BUILD_TYPE,
        'branch' => DVDPROFILER_BRANCH,
        'commit' => DVDPROFILER_COMMIT,
        'repository' => DVDPROFILER_REPOSITORY,
        'github_url' => DVDPROFILER_GITHUB_URL,
        'author' => DVDPROFILER_AUTHOR,
        'php_version' => PHP_VERSION,
        'features' => DVDPROFILER_FEATURES,
        'tech_stack' => DVDPROFILER_TECH_STACK
    ];
}

// FIXED: Vollständige Funktion mit Default-Wert
function isDVDProfilerFeatureEnabled($feature)
{
    return DVDPROFILER_FEATURES[$feature] ?? false;
}

function getDVDProfilerLatestChangelog($limit = 5)
{
    return array_slice(DVDPROFILER_CHANGELOG, 0, $limit, true);
}

function checkDVDProfilerSystemRequirements()
{
    $requirements = DVDPROFILER_REQUIREMENTS;
    $results = [
        'php' => version_compare(PHP_VERSION, $requirements['php_min'], '>='),
        'php_recommended' => version_compare(PHP_VERSION, $requirements['php_recommended'], '>='),
        'extensions' => [],
        'overall' => true
    ];

    // Check PHP extensions
    foreach ($requirements['php_extensions']['required'] as $ext) {
        $results['extensions'][$ext] = extension_loaded($ext);
        if (!$results['extensions'][$ext]) {
            $results['overall'] = false;
        }
    }

    foreach ($requirements['php_extensions']['optional'] as $ext) {
        $results['extensions'][$ext] = extension_loaded($ext);
    }

    $results['mysql'] = true; // Wird zur Laufzeit geprüft
    $results['overall'] = $results['php'] && $results['mysql'] && 
                         !in_array(false, array_slice($results['extensions'], 0, count($requirements['php_extensions']['required'])));

    return $results;
}

// Update-System Functions (GitHub-basiert)

/**
 * GitHub API Update-Konfiguration
 */
function getDVDProfilerUpdateConfig(): array
{
    return [
        'github_api_url' => 'https://api.github.com/repos/' . DVDPROFILER_REPOSITORY . '/releases/latest',
        'github_releases_url' => 'https://api.github.com/repos/' . DVDPROFILER_REPOSITORY . '/releases',
        'timeout' => 30,
        'user_agent' => 'DVD-Profiler-Updater/' . DVDPROFILER_VERSION,
        'verify_ssl' => true
    ];
}

/**
 * GitHub Release Download-URL generieren
 */
function getDVDProfilerUpdateUrl(string $version): string
{
    return 'https://github.com/' . DVDPROFILER_REPOSITORY . '/archive/refs/tags/v' . $version . '.zip';
}

/**
 * GitHub API: Neueste Version abrufen
 */
function getDVDProfilerLatestVersion(): ?array
{
    $config = getDVDProfilerUpdateConfig();
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: {$config['user_agent']}",
            'timeout' => $config['timeout'],
            'ignore_errors' => true
        ]
    ]);
    
    try {
        $json = @file_get_contents($config['github_api_url'], false, $context);
        
        if ($json === false) {
            $error = error_get_last();
            error_log('GitHub API failed: ' . ($error['message'] ?? 'Unknown error'));
            return null;
        }
        
        $data = json_decode($json, true);
        if (!$data || !isset($data['tag_name'])) {
            error_log('Invalid GitHub API response. Raw response: ' . substr($json, 0, 200));
            return null;
        }
        
        return [
            'version' => ltrim($data['tag_name'] ?? '', 'v'),
            'name' => $data['name'] ?? $data['tag_name'] ?? null,
            'description' => $data['body'] ?? null,
            'published_at' => $data['published_at'] ?? null,
            'download_url' => $data['zipball_url'] ?? null,
            'html_url' => $data['html_url'] ?? null
        ];
        
    } catch (Exception $e) {
        error_log('GitHub API exception: ' . $e->getMessage());
        return null;
    }
}

/**
 * ZENTRALISIERTE UPDATE-LOGIK - Alle Teile verwenden diese Funktionen
 */

/**
 * Zentrale Funktion: Ist Update verfügbar?
 * Verwendet immer DVDPROFILER_VERSION als Basis
 */
function isDVDProfilerUpdateAvailable(): bool
{
    $latestVersion = getDVDProfilerLatestVersion();
    
    if (!$latestVersion || empty($latestVersion['version'])) {
        return false; // Keine Server-Antwort = kein Update
    }
    
    $latest = ltrim($latestVersion['version'], 'v');
    $current = ltrim(DVDPROFILER_VERSION, 'v');
    
    return version_compare($latest, $current, '>');
}

/**
 * Zentrale Funktion: Update-Informationen für UI
 * Alle UI-Teile verwenden diese Funktion
 */
function getDVDProfilerUpdateInfo(): array
{
    $current = DVDPROFILER_VERSION;
    $latestData = getDVDProfilerLatestVersion();
    $latest = $latestData['version'] ?? null;
    
    $isAvailable = false;
    if ($latestData && $latest) {
        $isAvailable = version_compare(ltrim($latest, 'v'), ltrim($current, 'v'), '>');
    }
    
    return [
        'current_version' => $current,
        'latest_version' => $latest,
        'is_update_available' => $isAvailable,
        'latest_data' => $latestData,
        'server_reachable' => $latestData !== null
    ];
}

/**
 * Zentrale Funktion: Update-Status für Sidebar/Dashboard
 */
function getDVDProfilerUpdateStatus(): array
{
    $updateInfo = getDVDProfilerUpdateInfo();
    
    return [
        'version' => DVDPROFILER_VERSION,
        'codename' => DVDPROFILER_CODENAME,
        'build_date' => DVDPROFILER_BUILD_DATE,
        'build_type' => DVDPROFILER_BUILD_TYPE,
        'has_update' => $updateInfo['is_update_available'],
        'latest_version' => $updateInfo['latest_version'],
        'update_message' => $updateInfo['is_update_available'] 
            ? "Update auf {$updateInfo['latest_version']} verfügbar"
            : "Aktuelle Version"
    ];
}

// Legacy Support und Kompatibilität

/**
 * GitHub Integration für Kompatibilität
 */
function getDVDProfilerLatestGitHubVersion()
{
    return getDVDProfilerLatestVersion();
}

?>