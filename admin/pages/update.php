<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

// Nur für eingeloggte Nutzer
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// GitHub Repo-Info
$githubRepo = 'lunasans/dvdprofiler.liste';
$localVersion = htmlspecialchars($config['version'] ?? '0.0.0');
$latestVersion = null;
$error = '';
$success = '';

function getLatestRelease(string $repo): ?array {
    $apiUrl = "https://api.github.com/repos/$repo/releases/latest";
    $opts = ['http' => [
        'method' => 'GET',
        'header' => "User-Agent: dvd-updater"
    ]];
    $context = stream_context_create($opts);
    $json = @file_get_contents($apiUrl, false, $context);
    return $json ? json_decode($json, true) : null;
}

function downloadAndUpdate(string $zipUrl, string $repoTag): bool {
    $tmpZip = __DIR__ . '/update_tmp.zip';
    $data = file_get_contents($zipUrl);
    if (!$data) return false;
    file_put_contents($tmpZip, $data);

    $zip = new ZipArchive();
    if ($zip->open($tmpZip) !== true) return false;

    $exclude = [
        'config/config.php',
        'counter.txt',
        'admin/xml/'
    ];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        $relPath = preg_replace("#^{$repoTag}/#", '', $entry);

        $skip = false;
        foreach ($exclude as $ex) {
            if ($relPath === $ex || str_starts_with($relPath, rtrim($ex, '/') . '/')) {
                $skip = true;
                break;
            }
        }
        if ($skip || $relPath === '') continue;

        $targetPath = dirname(__DIR__) . '/' . $relPath;

        if (str_ends_with($relPath, '/')) {
            @mkdir($targetPath, 0775, true);
        } else {
            @mkdir(dirname($targetPath), 0775, true);
            file_put_contents($targetPath, $zip->getFromIndex($i));
        }
    }
    $zip->close();
    unlink($tmpZip);

    // update.sql ausführen
    $sqlFile = dirname(__DIR__) . '/update.sql';
    if (file_exists($sqlFile)) {
        global $pdo;
        $pdo->exec(file_get_contents($sqlFile));
        unlink($sqlFile);
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $release = getLatestRelease($githubRepo);
    if (!$release || empty($release['zipball_url']) || empty($release['tag_name'])) {
        $error = 'Update-Informationen konnten nicht geladen werden.';
    } else {
        $zipUrl = $release['zipball_url'];
        $repoTag = basename(parse_url($zipUrl, PHP_URL_PATH), '.zip');

        if (downloadAndUpdate($zipUrl, $repoTag)) {
            updateSetting('version', $release['tag_name']);
            $success = '✅ Update erfolgreich installiert. Version: ' . htmlspecialchars($release['tag_name']);
        } else {
            $error = '❌ Update fehlgeschlagen.';
        }
    }
}

// Neueste Version holen
$latestData = getLatestRelease($githubRepo);
$latestVersion = $latestData['tag_name'] ?? 'unbekannt';
$isUpdateAvailable = version_compare($latestVersion, $localVersion, '>');

?>

    <h2 class="mt-4">🔄 System-Update</h2>

    <p>Aktuelle Version: <strong><?= htmlspecialchars($localVersion) ?></strong></p>
    <p>Neueste Version bei GitHub: <strong><?= htmlspecialchars($latestVersion) ?></strong></p>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($isUpdateAvailable): ?>
        <form method="post">
            <button class="btn btn-primary">⬇️ Update herunterladen und installieren</button>
        </form>
    <?php else: ?>
        <div class="alert alert-info">✅ Deine Installation ist aktuell.</div>
    <?php endif; ?>

