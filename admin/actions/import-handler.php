<?php
declare(strict_types=1);
session_start();

require_once __DIR__ . '/../../includes/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Nicht angemeldet.');
}

$uploadDir = __DIR__ . '/../xml/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

if (!isset($_FILES['xml_file']) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
    exit('Fehler beim Hochladen der Datei.');
}

$filename = $_FILES['xml_file']['name'];
$tmpPath = $_FILES['xml_file']['tmp_name'];

$xmlContent = '';
if (str_ends_with($filename, '.zip')) {
    $zip = new ZipArchive();
    if ($zip->open($tmpPath) === true) {
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if (str_ends_with(strtolower($entry), '.xml')) {
                $xmlContent = $zip->getFromName($entry);
                break;
            }
        }
        $zip->close();
    }
} else {
    $xmlContent = file_get_contents($tmpPath);
}

if (empty($xmlContent)) {
    exit('Keine XML-Daten gefunden.');
}

libxml_use_internal_errors(true);
$xml = simplexml_load_string($xmlContent);
if ($xml === false) {
    exit('Ungültige XML-Datei.');
}

$savedPath = $uploadDir . 'import_' . date('Ymd_His') . '.xml';
file_put_contents($savedPath, $xmlContent);

$imported = 0;
$skipped = 0;

foreach ($xml->DVD as $dvd) {
    $id           = (int)($dvd->CollectionNumber ?? 0);  // <- ID direkt aus CollectionNumber
    if ($id <= 0) {
        $skipped++;
        continue;
    }

    $title        = trim((string)$dvd->Title);
    $year         = (int)$dvd->ProductionYear;
    $genre        = trim((string)$dvd->Genres->Genre);
    $runtime      = (int)$dvd->RunningTime;

    $rawRatingAge = (string)($dvd->RatingAge ?? '');
    $rating_age   = is_numeric($rawRatingAge) ? (int)$rawRatingAge : null;

    $overview     = trim((string)$dvd->Overview);
    $cover_id     = trim((string)$dvd->ID);
    $collection   = trim((string)$dvd->CollectionType);
    $boxsetParent = isset($dvd->BoxSet->Parent) ? trim((string)$dvd->BoxSet->Parent) : null;
    $trailer      = trim((string)$dvd->trailer_url);
    $userId       = $_SESSION['user_id'];

    $check = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE id = ?");
    $check->execute([$id]);
    if ($check->fetchColumn() > 0) {
        $skipped++;
        continue;
    }

    if (isset($dvd->Actors)) {
    foreach ($dvd->Actors->Actor as $actorXml) {
        $first = trim((string)$actorXml['FirstName']);
        $last  = trim((string)$actorXml['LastName']);
        $birth = (int)($actorXml['BirthYear'] ?? 0);
        $role  = trim((string)$actorXml['Role']);

        // Schauspieler einfügen oder ID finden
        $stmtActor = $pdo->prepare("SELECT id FROM actors WHERE first_name = ? AND last_name = ? AND birth_year = ?");
        $stmtActor->execute([$first, $last, $birth]);
        $actorId = $stmtActor->fetchColumn();

        if (!$actorId) {
            $stmtInsert = $pdo->prepare("INSERT INTO actors (first_name, last_name, birth_year) VALUES (?, ?, ?)");
            $stmtInsert->execute([$first, $last, $birth]);
            $actorId = $pdo->lastInsertId();
        }

        // Verbindung einfügen
        $stmtLink = $pdo->prepare("INSERT IGNORE INTO film_actor (film_id, actor_id, role) VALUES (?, ?, ?)");
        $stmtLink->execute([$id, $actorId, $role]);
    }
}

    $stmt = $pdo->prepare("
        INSERT INTO dvds (
            id, title, year, genre, runtime, rating_age,
            overview, cover_id, collection_type, boxset_parent, trailer_url, user_id
        ) VALUES (
            :id, :title, :year, :genre, :runtime, :rating_age,
            :overview, :cover_id, :collection_type, :boxset_parent, :trailer_url, :user_id
        )
    ");
    $stmt->execute([
        'id'               => $id,
        'title'            => $title,
        'year'             => $year,
        'genre'            => $genre,
        'runtime'          => $runtime,
        'rating_age'       => $rating_age,
        'overview'         => $overview,
        'cover_id'         => $cover_id,
        'collection_type'  => $collection,
        'boxset_parent'    => $boxsetParent ?: null,
        'trailer_url'      => $trailer,
        'user_id'          => $userId
    ]);

    $imported++;
}

$_SESSION['import_result'] = "🎬 Import abgeschlossen:
"
    . "$imported neue Filme importiert
"
    . "$skipped Duplikate übersprungen.
"
    . "Importierte Datei gespeichert unter: admin/xml/" . basename($savedPath);

header('Location: ../index.php?page=import');
exit;