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
$xml = simplexml_load_string(
    $xmlContent,
    'SimpleXMLElement',
    LIBXML_NOCDATA | LIBXML_NOENT | LIBXML_NONET
);

if ($xml === false) {
    $errors = libxml_get_errors();
    $errorMsg = 'Ungültige XML-Datei.';
    if (!empty($errors)) {
        $errorMsg .= ' Fehler: ' . $errors[0]->message;
    }
    exit($errorMsg);
}

$savedPath = $uploadDir . 'import_' . date('Ymd_His') . '.xml';
file_put_contents($savedPath, $xmlContent);

$imported = 0;
$skipped = 0;
$boxsetFixed = 0;
$errors = 0;

try {
    $pdo->beginTransaction();

    $dvdElements = $xml->DVD ?? [];
    
    if (empty($dvdElements)) {
        throw new Exception('Keine DVD-Elemente in der XML-Datei gefunden.');
    }
    
    // PHASE 1: Erstelle ID-Mapping für BoxSet-Collections
    // Original-ID (z.B. "4010232024787.5") → CollectionNumber (z.B. 420)
    $idMapping = [];
    
    foreach ($dvdElements as $dvd) {
        $collectionNumber = (int)($dvd->CollectionNumber ?? 0);
        $originalId = trim((string)$dvd->ID);
        
        if ($collectionNumber > 0 && !empty($originalId)) {
            $idMapping[$originalId] = $collectionNumber;
        }
    }
    
    // PHASE 2: Erst alle BoxSet-Collections (Parents) importieren
    foreach ($dvdElements as $dvd) {
        $id = (int)($dvd->CollectionNumber ?? 0);
        if ($id <= 0) continue;

        // Prüfung auf existierende DVD
        $check = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $skipped++;
            continue;
        }

        // Ist das eine BoxSet-Collection? (hat Contents aber keinen Parent)
        $isBoxSetCollection = false;
        if (isset($dvd->BoxSet)) {
            $hasContents = isset($dvd->BoxSet->Contents) && count($dvd->BoxSet->Contents->children()) > 0;
            $hasParent = isset($dvd->BoxSet->Parent) && !empty(trim((string)$dvd->BoxSet->Parent));
            
            if ($hasContents && !$hasParent) {
                $isBoxSetCollection = true;
            }
        }

        // Nur BoxSet-Collections in Phase 2
        if (!$isBoxSetCollection) {
            continue;
        }

        // Standard-Felder
        $title = trim((string)$dvd->Title);
        $year = (int)($dvd->ProductionYear ?? 0);
        $genre = trim((string)($dvd->Genres->Genre ?? ''));
        $runtime = (int)($dvd->RunningTime ?? 0);
        $rawRatingAge = (string)($dvd->RatingAge ?? '');
        $rating_age = is_numeric($rawRatingAge) ? (int)$rawRatingAge : null;
        $overview = trim((string)($dvd->Overview ?? ''));
        $cover_id = trim((string)$dvd->ID);
        $collection = trim((string)($dvd->CollectionType ?? ''));
        $trailer = trim((string)($dvd->trailer_url ?? ''));
        $userId = $_SESSION['user_id'];

        try {
            // BoxSet-Collection einfügen (ohne Parent)
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
                'id' => $id,
                'title' => $title,
                'year' => $year,
                'genre' => $genre,
                'runtime' => $runtime,
                'rating_age' => $rating_age,
                'overview' => $overview,
                'cover_id' => $cover_id,
                'collection_type' => $collection,
                'boxset_parent' => null, // Collections haben keinen Parent
                'trailer_url' => $trailer,
                'user_id' => $userId
            ]);

            $imported++;

        } catch (Exception $e) {
            $errors++;
            error_log("Fehler beim Importieren der BoxSet-Collection {$id} ({$title}): " . $e->getMessage());
        }
    }

    // PHASE 3: Jetzt alle einzelnen DVDs importieren
    foreach ($dvdElements as $dvd) {
        $id = (int)($dvd->CollectionNumber ?? 0);
        if ($id <= 0) continue;

        // Prüfung auf existierende DVD
        $check = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $skipped++;
            continue;
        }

        // Ist das eine BoxSet-Collection? Dann überspringen (schon importiert)
        $isBoxSetCollection = false;
        if (isset($dvd->BoxSet)) {
            $hasContents = isset($dvd->BoxSet->Contents) && count($dvd->BoxSet->Contents->children()) > 0;
            $hasParent = isset($dvd->BoxSet->Parent) && !empty(trim((string)$dvd->BoxSet->Parent));
            
            if ($hasContents && !$hasParent) {
                $isBoxSetCollection = true;
            }
        }

        if ($isBoxSetCollection) {
            continue; // Schon in Phase 2 importiert
        }

        // Standard-Felder
        $title = trim((string)$dvd->Title);
        $year = (int)($dvd->ProductionYear ?? 0);
        $genre = trim((string)($dvd->Genres->Genre ?? ''));
        $runtime = (int)($dvd->RunningTime ?? 0);
        $rawRatingAge = (string)($dvd->RatingAge ?? '');
        $rating_age = is_numeric($rawRatingAge) ? (int)$rawRatingAge : null;
        $overview = trim((string)($dvd->Overview ?? ''));
        $cover_id = trim((string)$dvd->ID);
        $collection = trim((string)($dvd->CollectionType ?? ''));
        $trailer = trim((string)($dvd->trailer_url ?? ''));
        $userId = $_SESSION['user_id'];

        // BoxSet-Parent behandeln mit ID-Mapping
        $boxsetParent = null;
        if (isset($dvd->BoxSet) && isset($dvd->BoxSet->Parent)) {
            $rawParent = trim((string)$dvd->BoxSet->Parent);
            if (!empty($rawParent)) {
                if (is_numeric($rawParent)) {
                    $parentId = (int)$rawParent;
                    if ($parentId === 2147483647) {
                        // DVD Profiler Platzhalter - ignorieren
                        $boxsetParent = null;
                        $boxsetFixed++;
                    } else {
                        // Numerischer Parent - prüfe ob in DB
                        $parentCheck = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE id = ?");
                        $parentCheck->execute([$parentId]);
                        if ($parentCheck->fetchColumn() > 0) {
                            $boxsetParent = $parentId;
                        } else {
                            $boxsetParent = null;
                            $boxsetFixed++;
                        }
                    }
                } else {
                    // String-Parent - verwende ID-Mapping
                    if (isset($idMapping[$rawParent])) {
                        $mappedParentId = $idMapping[$rawParent];
                        // Prüfe ob Parent-Collection bereits importiert wurde
                        $parentCheck = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE id = ?");
                        $parentCheck->execute([$mappedParentId]);
                        if ($parentCheck->fetchColumn() > 0) {
                            $boxsetParent = $mappedParentId;
                        } else {
                            $boxsetParent = null;
                            $boxsetFixed++;
                        }
                    } else {
                        // Parent nicht in XML gefunden
                        $boxsetParent = null;
                        $boxsetFixed++;
                    }
                }
            }
        }

        try {
            // Schauspieler verarbeiten
            if (isset($dvd->Actors) && isset($dvd->Actors->Actor)) {
                foreach ($dvd->Actors->Actor as $actorXml) {
                    $firstName = trim((string)($actorXml['FirstName'] ?? ''));
                    $lastName = trim((string)($actorXml['LastName'] ?? ''));
                    $role = trim((string)($actorXml['Role'] ?? ''));
                    $birthYear = (int)($actorXml['BirthYear'] ?? 0);
                    
                    if (!empty($firstName) || !empty($lastName)) {
                        $stmtActor = $pdo->prepare("SELECT id FROM actors WHERE first_name = ? AND last_name = ? AND birth_year = ?");
                        $stmtActor->execute([$firstName, $lastName, $birthYear]);
                        $actorId = $stmtActor->fetchColumn();

                        if (!$actorId) {
                            $stmtInsert = $pdo->prepare("INSERT INTO actors (first_name, last_name, birth_year) VALUES (?, ?, ?)");
                            $stmtInsert->execute([$firstName, $lastName, $birthYear]);
                            $actorId = $pdo->lastInsertId();
                        }

                        $stmtLink = $pdo->prepare("INSERT IGNORE INTO film_actor (film_id, actor_id, role) VALUES (?, ?, ?)");
                        $stmtLink->execute([$id, $actorId, $role]);
                    }
                }
            }

            // DVD einfügen
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
                'id' => $id,
                'title' => $title,
                'year' => $year,
                'genre' => $genre,
                'runtime' => $runtime,
                'rating_age' => $rating_age,
                'overview' => $overview,
                'cover_id' => $cover_id,
                'collection_type' => $collection,
                'boxset_parent' => $boxsetParent,
                'trailer_url' => $trailer,
                'user_id' => $userId
            ]);

            $imported++;

        } catch (Exception $e) {
            $errors++;
            error_log("Fehler beim Importieren von DVD {$id} ({$title}): " . $e->getMessage());
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    $pdo->rollBack();
    exit('Fehler beim Import: ' . $e->getMessage());
}

$resultMessage = "🎬 Import abgeschlossen:\n"
    . "$imported neue Filme importiert\n"
    . "$skipped Duplikate übersprungen\n";

if ($boxsetFixed > 0) {
    $resultMessage .= "$boxsetFixed BoxSet-Zuordnungen korrigiert\n";
}

if ($errors > 0) {
    $resultMessage .= "⚠️ $errors Fehler beim Import\n";
}

$resultMessage .= "Importierte Datei gespeichert unter: admin/xml/" . basename($savedPath);

$_SESSION['import_result'] = $resultMessage;

header('Location: ../index.php?page=import');
exit;