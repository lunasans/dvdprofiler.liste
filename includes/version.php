<?php
/**
 * DVD Profiler Liste - Versionsverwaltung (GitHub-basiert)
 * Zentrale Stelle für alle Versionsinformationen
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @repository https://github.com/lunasans/dvdprofiler.liste
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

// Repository Information (ZURÜCK AUF GITHUB)
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
    'php_extensions' => [
        'required' => ['mysqli', 'json', 'mbstring'],
        'optional' => ['zip', 'curl', 'gd', 'imagick']
    ],
    'browser_support' => [
        'Chrome' => '90+',
        'Firefox' => '88+',
        'Safari' => '14+',
        'Edge' => '90+',
        'Opera' => '76+'
    ]
]);

// Technologie-Stack Information
define('DVDPROFILER_TECH_STACK', [
    'backend' => 'PHP 7.4+',
    'database' => 'MySQL/MariaDB',
    'frontend' => 'HTML5, CSS3, JavaScript',
    'ui_framework' => 'Bootstrap Icons, Fancybox, Chart.js',
    'architecture' => 'MVC-ähnlich',
    'security' => 'CSRF, XSS Protection, Content Security Policy'
]);

// Version-History (letzten Versionen)
define('DVDPROFILER_CHANGELOG', [
    '1.4.7' => [
        'date' => '2025-07-26',
        'type' => 'minor',
        'changes' => [
            'FIXED: Update system zurück zu GitHub migriert',
            'FIXED: Bootstrap include-Probleme behoben',
            'FIXED: Film-Fragment Fallback-System implementiert', 
            'IMPROVED: Robuste Fehlerbehandlung in allen Kernkomponenten',
            'IMPROVED: Cache-Management für GitHub API',
            'IMPROVED: Backup-System vor Updates',
            'SECURITY: Rate-Limiting für API-Aufrufe',
            'DOCS: Vollständige Update-Dokumentation'
        ]
    ],
    
    '1.4.6' => [
        'date' => '2025-07-25',
        'type' => 'patch',
        'changes' => [
            'Enhanced session management',
            'Performance optimizations for large collections',
            'UI/UX improvements in admin panel',
            'Bug fixes in Charts.js integration'
        ]
    ]
]);

// Helper Functions
function getDVDProfilerVersion(): string
{
    return DVDPROFILER_VERSION;
}

function getDVDProfilerVersionFull(): string
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

function getDVDProfilerBuildInfo(): array
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

function isDVDProfilerFeatureEnabled(string $feature): bool
{
    return DVDPROFILER_FEATURES[$feature] ?? false;
}

function checkDVDProfilerSystemRequirements(): array
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

    return $results;
}

// ═══════════════════════════════════════════════════════════════════════════════
// GITHUB UPDATE SYSTEM
// ═══════════════════════════════════════════════════════════════════════════════

/**
 * GitHub API Konfiguration
 */
function getGitHubUpdateConfig(): array
{
    return [
        'repository' => DVDPROFILER_REPOSITORY,
        'api_url' => 'https://api.github.com/repos/' . DVDPROFILER_REPOSITORY . '/releases/latest',
        'timeout' => 30,
        'user_agent' => 'DVD-Profiler-Updater/' . DVDPROFILER_VERSION,
        'cache_duration' => 3600, // 1 Stunde
        'rate_limit_safe' => true
    ];
}

/**
 * GitHub API Rate-Limit sicher aufrufen
 */
function callGitHubAPISecure(string $url): ?array
{
    $config = getGitHubUpdateConfig();
    
    // Cache prüfen
    $cacheKey = 'github_api_' . md5($url);
    $cached = getSetting($cacheKey . '_data', '');
    $cacheTime = (int)getSetting($cacheKey . '_time', '0');
    
    if ($cached && (time() - $cacheTime < $config['cache_duration'])) {
        error_log("GitHub API: Using cached data for $url");
        return json_decode($cached, true);
    }
    
    // Rate Limit prüfen
    $lastCall = (int)getSetting('github_api_last_call', '0');
    $callsInHour = (int)getSetting('github_api_calls_hour', '0');
    $hourStart = (int)getSetting('github_api_hour_start', '0');
    
    // Reset counter after 1 hour
    if (time() - $hourStart > 3600) {
        $callsInHour = 0;
        $hourStart = time();
        setSetting('github_api_hour_start', (string)$hourStart);
        setSetting('github_api_calls_hour', '0');
    }
    
    // Rate limit check (60 calls per hour for unauthenticated requests)
    if ($callsInHour >= 50) { // Safety margin
        error_log("GitHub API: Rate limit approaching, using cache");
        return $cached ? json_decode($cached, true) : null;
    }
    
    // Minimum delay between calls (1 second)
    if (time() - $lastCall < 1) {
        sleep(1);
    }
    
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: {$config['user_agent']}\r\n",
            'timeout' => $config['timeout'],
            'ignore_errors' => true
        ]
    ]);
    
    try {
        $json = @file_get_contents($url, false, $context);
        
        // Update rate limiting counters
        setSetting('github_api_last_call', (string)time());
        setSetting('github_api_calls_hour', (string)($callsInHour + 1));
        
        if ($json === false) {
            error_log("GitHub API: Request failed for $url");
            return $cached ? json_decode($cached, true) : null;
        }
        
        // Check for GitHub API errors
        $data = json_decode($json, true);
        if (!$data) {
            error_log("GitHub API: Invalid JSON response");
            return null;
        }
        
        if (isset($data['message'])) {
            error_log("GitHub API Error: " . $data['message']);
            
            // If rate limited, use cache if available
            if (strpos($data['message'], 'rate limit') !== false && $cached) {
                return json_decode($cached, true);
            }
            
            return null;
        }
        
        // Cache successful response
        setSetting($cacheKey . '_data', $json);
        setSetting($cacheKey . '_time', (string)time());
        
        error_log("GitHub API: Successfully fetched $url");
        return $data;
        
    } catch (Exception $e) {
        error_log("GitHub API Exception: " . $e->getMessage());
        return $cached ? json_decode($cached, true) : null;
    }
}

/**
 * Neueste Version von GitHub abrufen
 */
function getLatestGitHubRelease(): ?array
{
    $config = getGitHubUpdateConfig();
    $data = callGitHubAPISecure($config['api_url']);
    
    if (!$data || !isset($data['tag_name'])) {
        return null;
    }
    
    return [
        'version' => $data['tag_name'],
        'name' => $data['name'] ?? '',
        'body' => $data['body'] ?? '',
        'published_at' => $data['published_at'] ?? '',
        'zipball_url' => $data['zipball_url'] ?? '',
        'tarball_url' => $data['tarball_url'] ?? '',
        'html_url' => $data['html_url'] ?? ''
    ];
}

/**
 * Prüfen ob Update verfügbar ist
 */
function isGitHubUpdateAvailable(): bool
{
    $latest = getLatestGitHubRelease();
    if (!$latest) {
        return false;
    }
    
    $latestVersion = ltrim($latest['version'], 'v');
    $currentVersion = ltrim(DVDPROFILER_VERSION, 'v');
    
    return version_compare($latestVersion, $currentVersion, '>');
}

/**
 * GitHub Update-URL für Download
 */
function getGitHubDownloadUrl(?string $version = null): string
{
    $repo = DVDPROFILER_REPOSITORY;
    
    if ($version) {
        return "https://github.com/{$repo}/archive/refs/tags/{$version}.zip";
    }
    
    // Neueste Version
    $latest = getLatestGitHubRelease();
    return $latest['zipball_url'] ?? "https://github.com/{$repo}/archive/refs/heads/main.zip";
}

/**
 * Cache für GitHub API leeren
 */
function clearGitHubAPICache(): void
{
    $keys = ['github_api_calls_hour', 'github_api_hour_start', 'github_api_last_call'];
    
    // Alle cached API responses löschen
    global $pdo;
    try {
        $stmt = $pdo->prepare("DELETE FROM settings WHERE `key` LIKE 'github_api_%'");
        $stmt->execute();
        error_log("GitHub API cache cleared");
    } catch (Exception $e) {
        error_log("Failed to clear GitHub API cache: " . $e->getMessage());
    }
}

/**
 * Legacy-Kompatibilität
 * @deprecated Use getLatestGitHubRelease() instead
 */
function getDVDProfilerLatestVersion(): ?array
{
    return getLatestGitHubRelease();
}

/**
 * @deprecated Use isGitHubUpdateAvailable() instead  
 */
function isDVDProfilerUpdateAvailable(): bool
{
    return isGitHubUpdateAvailable();
}