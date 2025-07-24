<?php
/**
 * DVD Profiler Liste - Versionsverwaltung
 * Zentrale Stelle für alle Versionsinformationen
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @repository https://github.com/lunasans/dvdprofiler.liste
 */

// Version Information
define('DVDPROFILER_VERSION', '1.4.6');
define('DVDPROFILER_CODENAME', 'Cinephile');
define('DVDPROFILER_BUILD_DATE', '2025.07.24');
define('DVDPROFILER_RELEASE_DATE', '24. Juli 2025');

// Build Information
define('DVDPROFILER_BUILD_TYPE', 'Release'); // Release, Beta, Alpha, Development
define('DVDPROFILER_BRANCH', 'main');
define('DVDPROFILER_COMMIT', 'c8d9e1f'); // Git commit hash (ersten 7 Zeichen)

// Repository Information
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
            'Updated documentation and code comments'
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

// GitHub Integration Functions
function getDVDProfilerLatestGitHubVersion()
{
    $repo = DVDPROFILER_REPOSITORY;
    $apiUrl = "https://api.github.com/repos/$repo/releases/latest";
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: DVDProfiler-Updater/1.0\r\n",
            'timeout' => 10
        ]
    ];
    $context = stream_context_create($opts);
    
    try {
        $json = @file_get_contents($apiUrl, false, $context);
        if (!$json) return null;
        
        $data = json_decode($json, true);
        return [
            'version' => $data['tag_name'] ?? null,
            'name' => $data['name'] ?? null,
            'description' => $data['body'] ?? null,
            'published_at' => $data['published_at'] ?? null,
            'download_url' => $data['zipball_url'] ?? null
        ];
    } catch (Exception $e) {
        error_log('GitHub API error: ' . $e->getMessage());
        return null;
    }
}

function isDVDProfilerUpdateAvailable()
{
    $latestRelease = getDVDProfilerLatestGitHubVersion();
    if (!$latestRelease || !$latestRelease['version']) {
        return false;
    }
    
    return version_compare($latestRelease['version'], DVDPROFILER_VERSION, '>');
}

// Statistics Functions
function getDVDProfilerStatistics()
{
    global $pdo;
    
    $stats = [
        'total_films' => 0,
        'total_boxsets' => 0,
        'total_visits' => 0,
        'total_genres' => 0,
        'total_actors' => 0,
        'storage_size' => 0,
        'avg_rating' => 0,
        'newest_film' => null,
        'most_viewed' => null
    ];
    
    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            // Total films
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM dvds");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_films'] = (int)($result['count'] ?? 0);
            
            // BoxSets
            $stmt = $pdo->query("SELECT COUNT(DISTINCT boxset_name) as count FROM dvds WHERE boxset_name IS NOT NULL AND boxset_name != ''");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $stats['total_boxsets'] = (int)($result['count'] ?? 0);
            
            // Visitors (if counter table exists)
            $stmt = $pdo->query("SHOW TABLES LIKE 'counter'");
            if ($stmt->fetch()) {
                $stmt = $pdo->query("SELECT visits FROM counter WHERE id = 1");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $stats['total_visits'] = (int)($result['visits'] ?? 0);
            }
            
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