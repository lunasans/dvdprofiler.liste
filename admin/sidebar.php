<?php
// Aktuelle Version aus DB
$currentVersion = getSetting('version') ?? '0.0.0';

// GitHub-Version abfragen
function getLatestGitHubVersion(): string {
    $repo = 'lunasans/dvdprofiler.liste';
    $apiUrl = "https://api.github.com/repos/$repo/releases/latest";
    $opts = ['http' => [
        'method' => 'GET',
        'header' => "User-Agent: dvd-updater"
    ]];
    $ctx = stream_context_create($opts);
    $json = @file_get_contents($apiUrl, false, $ctx);
    if (!$json) return '0.0.0';
    $data = json_decode($json, true);
    return $data['tag_name'] ?? '0.0.0';
}

$latestVersion = getLatestGitHubVersion();
$isUpdateAvailable = version_compare($latestVersion, $currentVersion, '>');
?>
<aside class="sidebar bg-dark text-white p-3" style="width: 220px; min-height: 100vh;">
  <h4 class="text-white mb-4">Admin-Panel</h4>
  <ul class="nav flex-column">
    <li><a href="?page=dashboard">Dashboard</a></li>
    <li><a href="?page=users">Benutzer</a></li>
    <li><a href="?page=settings">Einstellungen<?php if ($isUpdateAvailable): ?><span class="badge rounded-pill bg-secondary">Update!</span><?php endif; ?></a></li>
    <li><a href="?page=import">Film Import</a></li>
    ____________________
    <li><a href="logout.php">Logout</a></li>
   </ul>
</aside>