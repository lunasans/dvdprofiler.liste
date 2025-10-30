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
define('DVDPROFILER_BUILD_DATE', '2025.07.26');
define('DVDPROFILER_RELEASE_DATE', '26. July 2025');

// Build Information
define('DVDPROFILER_BUILD_TYPE', 'Release'); // Release, Beta, Alpha, Development
define('DVDPROFILER_BRANCH', 'main');
define('DVDPROFILER_COMMIT', '207ece9'); // Git commit hash (ersten 7 Zeichen)

// Repository Information (GEÄNDERT für eigenes Update-System)
define('DVDPROFILER_REPOSITORY', 'update.neuhaus.or.at/dvdprofiler-liste');
define('DVDPROFILER_GITHUB_URL', 'https://update.neuhaus.or.at/dvdprofiler-liste'); // Ihre Projekt-URL
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
        'Edge' => '90+',
        'Opera' => '76+'
    ],
    'php_extensions' => [
        'required' => ['pdo', 'pdo_mysql', 'json', 'mbstring'],
        'optional' => ['zip', 'curl', 'gd', 'xml']
    ]
]);

// Technology Stack
define('DVDPROFILER_TECH_STACK', [
    'backend' => [
        'language' => 'PHP',
        'database' => 'MySQL/MariaDB',
        'architecture' => 'MVC Pattern'
    ],
    'frontend' => [
        'html' => 'HTML5',
        'css' => 'CSS3 (Glass-Morphism)',
        'javascript' => 'Vanilla JavaScript',
        'icons' => 'Bootstrap Icons',
        'libraries' => ['Fancybox', 'Chart.js']
    ],
    'security' => [
        'authentication' => 'Session-based',
        'encryption' => 'PHP password_hash',
        'protection' => 'CSP, XSS Protection',
        'compliance' => 'DSGVO/GDPR'
    ]
]);

// Changelog (letzten 10 Versionen)
define('DVDPROFILER_CHANGELOG', [
    '1.3.0' => [
        'date' => '2025-06-15',
        'type' => 'major',
        'changes' => [
            'Initial release with modern web interface',
            'XML import functionality for DVD Profiler collection.xml',
            'Database-driven film management',
            'Responsive grid and list views',
            'Basic search functionality',
            'Bootstrap Icons integration',
            'Glass-Morphism design implementation'
        ]
    ],

    '1.3.1' => [
        'date' => '2025-06-22',
        'type' => 'minor',
        'changes' => [
            'Enhanced BoxSet support with expandable sub-films',
            'Improved XML import with better error handling',
            'Database optimization for large collections',
            'Mobile responsiveness improvements',
            'Added trailer integration functionality',
            'Security enhancements and input validation'
        ]
    ],

    '1.3.2' => [
        'date' => '2025-07-01',
        'type' => 'minor',
        'changes' => [
            'Added comprehensive film detail view',
            'Fancybox lightbox integration for covers',
            'Enhanced search with filter options',
            'Visitor counter implementation',
            'Performance optimizations',
            'Bug fixes in pagination system'
        ]
    ],

    '1.3.3' => [
        'date' => '2025-07-08',
        'type' => 'patch',
        'changes' => [
            'Chart.js integration for statistics page',
            'Improved admin panel functionality',
            'GDPR compliance features',
            'Content Security Policy implementation',
            'Enhanced error handling and logging',
            'UI/UX improvements across all pages'
        ]
    ],

    '1.3.4' => [
        'date' => '2025-07-15',
        'type' => 'minor',
        'changes' => [
            'Advanced admin panel with system management',
            'Automatic update system implementation',
            'User authentication and session management',
            'Database maintenance tools',
            'Backup and restore functionality',
            'Improved security measures'
        ]
    ],

    '1.3.5' => [
        'date' => '2025-07-20',
        'type' => 'minor',
        'changes' => [
            'Enhanced update system with GitHub integration',
            'Improved backup functionality',
            'Advanced user management',
            'System health monitoring',
            'Performance optimizations',
            'Bug fixes and stability improvements',
            'Enhanced admin dashboard',
            'Better error reporting'
        ]
    ],

    '1.4.5' => [
        'date' => '2025-07-23',
        'type' => 'minor',
        'changes' => [
            'Comprehensive version management system',
            'Enhanced footer with extended functionality',
            'System statistics and monitoring',
            'Feature flags implementation',
            'Technology stack documentation',
            'Improved changelog management',
            'Build information tracking',
            'System requirements validation',
            'Enhanced GitHub integration',
            'Performance monitoring tools'
        ]
    ],

    '1.4.6' => [
        'date' => '2025-07-24',
        'type'=> 'patch',
        'changes' => [
            'Bug fixes in 2FA implementation',
            'Improved security measures for user authentication',
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
            'Migration from GitHub to custom update infrastructure',
            'Enhanced independence from external services',
            'Improved update performance and reliability',
            'Simplified update configuration',
            'Enhanced monitoring and logging capabilities',
            'Reduced dependency on external rate limits',
            'Improved backup and restore functionality',
            'Better error handling in update process',
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

// Update-System Functions (EINFACH und zukunftssicher)

/**
 * Update-Konfiguration abrufen
 * Zukunftssicher: Kann später um API-Keys erweitert werden
 */
function getDVDProfilerUpdateConfig(): array
{
    return [
        'api_url' => getSetting('update_api_url', 'https://update.neuhaus.or.at/update-api.php'),
        'base_url' => getSetting('update_base_url', 'https://update.neuhaus.or.at/packages/'),
        'timeout' => 30,
        'user_agent' => 'DVD-Profiler-Updater/' . DVDPROFILER_VERSION,
        'verify_ssl' => true
    ];
}

/**
 * Update-URL für bestimmte Version generieren
 */
function getDVDProfilerUpdateUrl(string $version): string
{
    $config = getDVDProfilerUpdateConfig();
    return $config['base_url'] . "dvdprofiler-liste-v{$version}.zip";
}

/**
 * Prüfen ob Update-System verfügbar ist
 */
function isDVDProfilerUpdateAvailable(): bool
{
    $config = getDVDProfilerUpdateConfig();
    
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'header' => "User-Agent: {$config['user_agent']}",
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);
    
    $headers = @get_headers($config['api_url'], 1, $context);
    return $headers && strpos($headers[0], '200') !== false;
}

/**
 * Eigene Update-API aufrufen (ersetzt GitHub API)
 * EINFACH - ohne API-Key Komplexität
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
        $json = @file_get_contents($config['api_url'], false, $context);
        if (!$json) {
            error_log('Update API request failed');
            return null;
        }
        
        $data = json_decode($json, true);
        if (!$data || !isset($data['tag_name'])) {
            error_log('Invalid update API response');
            return null;
        }
        
        return [
            'version' => $data['tag_name'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['body'] ?? null,
            'published_at' => $data['published_at'] ?? null,
            'download_url' => $data['zipball_url'] ?? null
        ];
    } catch (Exception $e) {
        error_log('Update API error: ' . $e->getMessage());
        return null;
    }
}

// Legacy GitHub Integration Functions (für Kompatibilität beibehalten)

/**
 * @deprecated Verwenden Sie getDVDProfilerLatestVersion() stattdessen
 */
function getDVDProfilerLatestGitHubVersion()
{
    error_log('Warning: getDVDProfilerLatestGitHubVersion() is deprecated. Use getDVDProfilerLatestVersion() instead.');
    return getDVDProfilerLatestVersion();
}

// Statistics Functions
function getDVDProfilerStatistics(): array
{
    global $pdo;
    
    $stats = [
        'total_films' => 0,
        'total_boxsets' => 0,
        'total_genres' => 0,
        'total_actors' => 0,
        'newest_film' => null,
        'storage_size' => 0,
        'last_updated' => date('Y-m-d H:i:s')
    ];
    
    try {
        if (isset($pdo)) {
            // Total films
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM dvds");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_films'] = (int)($result['count'] ?? 0);
            
            // Total boxsets
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM dvds WHERE is_boxset = 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_boxsets'] = (int)($result['count'] ?? 0);
            
            // Unique genres
            $stmt = $pdo->query("SELECT COUNT(DISTINCT genre) as count FROM dvds WHERE genre IS NOT NULL AND genre != ''");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_genres'] = (int)($result['count'] ?? 0);
            
            // Unique actors
            $stmt = $pdo->query("SELECT COUNT(DISTINCT actors) as count FROM dvds WHERE actors IS NOT NULL AND actors != ''");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_actors'] = (int)($result['count'] ?? 0);
            
            // Newest film
            $stmt = $pdo->query("SELECT title, created_at FROM dvds ORDER BY created_at DESC LIMIT 1");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['newest_film'] = $result;
            
            // Storage size calculation (estimated)
            $stats['storage_size'] = $stats['total_films'] * 4.7; // GB (average DVD size)
        }
    } catch (Exception $e) {
        error_log('DVDProfiler Statistics error: ' . $e->getMessage());
    }
    
    return $stats;
}

function getDVDProfilerSystemInfo()
{
    return [
        'php_version' => PHP_VERSION,
        'php_sapi' => PHP_SAPI,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? '',
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => ini_get('max_execution_time'),
        'post_max_size' => ini_get('post_max_size'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'timezone' => date_default_timezone_get(),
        'extensions' => get_loaded_extensions()
    ];
}

// Export important variables for templates
$version = DVDPROFILER_VERSION;
$buildDate = DVDPROFILER_BUILD_DATE;
$codename = DVDPROFILER_CODENAME;
$buildType = DVDPROFILER_BUILD_TYPE;
$fullVersion = getDVDProfilerVersionFull();
$buildInfo = getDVDProfilerBuildInfo();
$dvdProfilerStats = getDVDProfilerStatistics();
$systemInfo = getDVDProfilerSystemInfo();

// Legacy compatibility
$dvdStats = $dvdProfilerStats; // For backward compatibility with existing templates
?>