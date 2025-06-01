<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain');
ob_implicit_flush(true);
ob_end_flush();

echo "[STEP1] Lade XML...\n";

$xmlFile = __DIR__ . "/xml/Collection.xml";
if (!file_exists($xmlFile)) {
    echo "XML-Datei nicht gefunden\n";
    exit;
}
$xml = simplexml_load_file($xmlFile);
if (!$xml) {
    echo "Fehler beim Laden der XML-Datei\n";
    exit;
}

$entries = $xml->DVD;
$total = count($entries);
$importiert = 0;
$aktualisiert = 0;
$index = 0;

// ID → CollectionNumber Mapping aufbauen
$dvdIdMap = [];
foreach ($entries as $entry) {
    $id = trim((string)$entry->ID);
    $collNum = (int)$entry->CollectionNumber;
    $dvdIdMap[$id] = $collNum;
}

echo "[STEP2] Verarbeite Filme...\n";

foreach ($entries as $dvd) {
    $index++;

    $id        = (int)$dvd->CollectionNumber;
    $ratingAge = (int)$dvd->RatingAge;
    $title     = trim((string)$dvd->Title);
    $year      = (int)$dvd->ProductionYear;
    $coverId   = trim((string)$dvd->ID);
    $collectionType = trim((string)$dvd->CollectionType);
    $runtime   = (int)$dvd->RunningTime ?? 0;
    $overview  = trim((string)$dvd->Overview);
    $genres    = array_map(fn($g) => trim((string)$g), iterator_to_array($dvd->Genres->Genre ?? []));
    $genre     = implode(', ', $genres);
    $rawBoxParent = trim((string)$dvd->BoxSet->Parent ?? '');
    $boxParent = $dvdIdMap[$rawBoxParent] ?? null;

    echo "🔄 [$index/$total] $title (#$id)... ";

    // Existiert schon?
    $stmt = $pdo->prepare("SELECT title, year FROM dvds WHERE id = ?");
    $stmt->execute([$id]);
    $existing = $stmt->fetch();

    if ($existing) {
        if ($existing['title'] !== $title || (int)$existing['year'] !== $year) {
            $stmt = $pdo->prepare("UPDATE dvds SET title= ?, year= ?, genre= ?, cover_id= ?, runtime= ?, overview= ?, rating_age= ?, collection_type = ?, boxset_parent = ? WHERE id = ?");
            $stmt->execute([
                $title,
                $year,
                $genre,
                $coverId,
                $runtime,
                $overview,
                $ratingAge,
                $collectionType,
                $boxParent,
                $id
            ]);
            echo "aktualisiert\n";
            $aktualisiert++;
        } else {
            echo "übersprungen\n";
        }
        continue;
    }

    $stmt = $pdo->prepare("
        INSERT INTO dvds (id, title, year, genre, cover_id, runtime, overview, rating_age, collection_type, boxset_parent)
        VALUES (:id, :title, :year, :genre, :cover_id, :runtime, :overview, :rating_age, :collection_type, :boxset_parent)
    ");
    $stmt->execute([
        'id'             => $id,
        'title'          => $title,
        'year'           => $year,
        'genre'          => $genre,
        'cover_id'       => $coverId,
        'runtime'        => $runtime,
        'overview'       => $overview,
        'rating_age'     => $ratingAge,
        'collection_type'=> $collectionType,
        'boxset_parent'  => $boxParent
    ]);
    $importiert++;

    foreach ($dvd->Actors->Actor ?? [] as $actor) {
        $pdo->prepare("
            INSERT INTO actors (dvd_id, firstname, lastname, role)
            VALUES (?, ?, ?, ?)
        ")->execute([
            $id,
            (string)$actor['FirstName'],
            (string)$actor['LastName'],
            (string)$actor['Role']
        ]);
    }

    echo "importiert\n";

    $percent = (int)($index / $total * 100);
    echo "PROGRESS:$percent%\n";
}

echo "[STEP2DONE]  $aktualisiert aktualisiert, 📥 $importiert importiert\n";
echo "[STEP3] Speichere in Datenbank abgeschlossen\n";
echo "[DONE] Import vollständig\n";
