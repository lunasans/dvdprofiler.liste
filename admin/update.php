<?php
declare(strict_types=1);
session_start();
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$githubRepo = 'lunasans/dvdprofiler.liste';
$localVersion = getSetting('version') ?? '1.0.0';
$latestVersion = null;
$updateInfo = '';
$error = '';

function getLatestVersionFromGitHub(string $repo): ?array {
    $apiUrl = "https://api.github.com/repos/$repo/releases/latest";
    $opts = ['http' => [
        'method' => 'GET',
        'header' => "User-Agent: dvd-updater"
    ]];
    $context = stream_context_create($opts);
    $response = @file_get_contents($apiUrl, false, $context);
    return $response ? json_decode($response, true) : null;
}

function downloadAndExtractUpdate(string $zipUrl, string $repoTag): bool {
    $tmpFile = __DIR__ . '/tmp_update.zip';
    $zipData = file_get_contents($zipUrl);
    if (!$zipData) return false;
    file_put_contents($tmpFile, $zipData);

    $zip = new ZipArchive();
    if ($zip->open($tmpFile) !== true) return false;

    $exclude = ['config/config.php', 'counter.txt', 'admin/xml/'];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        $relPath = preg_replace("#^$repoTag/#", '', $entry);
        $skip = false;
        foreach ($exclude as $ex) {
            if ($relPath === $ex || str_starts_with($relPath, rtrim($ex, '/') . '/')) {
                $skip = true;
                break;
            }
        }
        if ($skip || $relPath === '') continue;

        $fullPath = __DIR__ . '/../' . $relPath;
        if (str_ends_with($relPath, '/')) {
            @mkdir($fullPath, 0775, true);
        } else {
            @mkdir(dirname($fullPath), 0775, true);
            file_put_contents($fullPath, $zip->getFromIndex($i));
        }
    }
    $zip->close();
    unlink($tmpFile);

    // Führe optionales SQL aus
    $sqlPath = __DIR__ . '/../update.sql';
    if (file_exists($sqlPath)) {
        global $pdo;
        $pdo->exec(file_get_contents($sqlPath));
        unlink($sqlPath);
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $githubData = getLatestVersionFromGitHub($githubRepo);
    if (!$githubData || empty($githubData['zipball_url']) || empty($githubData['tag_name'])) {
        $error = 'Update-Informationen konnten nicht geladen werden.';
    } else {
        $zipUrl = $githubData['zipball_url'];
        $repoTag = basename(parse_url($zipUrl, PHP_URL_PATH), '.zip');

        if (downloadAndExtractUpdate($zipUrl, $repoTag)) {
            updateSetting('version', $githubData['tag_name']);
            $updateInfo = "✅ Update auf Version <strong>{$githubData['tag_name']}</strong> erfolgreich durchgeführt.";
        } else {
            $error = 'Update fehlgeschlagen. ZIP konnte nicht verarbeitet werden.';
        }
    }
}

$latest = getLatestVersionFromGitHub($githubRepo);
$latestVersion = $latest['tag_name'] ?? 'unbekannt';
$isUpdateAvailable = version_compare($latestVersion, $localVersion, '>');

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Update durchführen</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body class="container">
    <h2 class="mt-4">🧩 System-Update</h2>

    <p>Aktuelle Version: <strong><?= htmlspecialchars($localVersion) ?></strong></p>
    <p>Neueste Version bei GitHub: <strong><?= htmlspecialchars($latestVersion) ?></strong></p>

    <?php if (!empty($updateInfo)): ?>
        <div class="alert alert-success"><?= $updateInfo ?></div>
    <?php elseif (!empty($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <?php if ($isUpdateAvailable): ?>
        <form method="post">
            <button class="btn btn-primary">⬇️ Update jetzt installieren</button>
        </form>
    <?php else: ?>
        <div class="alert alert-info">✅ Deine Installation ist aktuell.</div>
    <?php endif; ?>

    <p><a href="/admin/">Zurück zum Adminbereich</a></p>
</body>
</html>
