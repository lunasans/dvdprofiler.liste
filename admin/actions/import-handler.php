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
    
    error_log("Import gestartet: " . count($dvdElements) . " DVDs gefunden");
    
    // PHASE 1: ID-Mapping und BoxSet-Relationen analysieren
    $idMapping = [];
    $parentChildRelations = [];
    
    foreach ($dvdElements as $dvd) {
        $collectionNumber = (int)($dvd->CollectionNumber ?? 0);
        $originalId = trim((string)$dvd->ID);
        
        // ID-Mapping erstellen: Original-ID → CollectionNumber
        if ($collectionNumber > 0 && !empty($originalId)) {
            $idMapping[$originalId] = $collectionNumber;
        }
        
        // BoxSet-Inhalte analysieren
        if (isset($dvd->BoxSet) && isset($dvd->BoxSet->Contents)) {
            $parentOriginalId = $originalId; // Das ist der Parent
            $children = [];
            
            // Contents->Content Elemente durchgehen
            if (isset($dvd->BoxSet->Contents->Content)) {
                $contentElements = $dvd->BoxSet->Contents->Content;
                
                // Falls nur ein Content, wird es nicht als Array behandelt
                if (!is_array($contentElements) && !($contentElements instanceof Traversable)) {
                    $contentElements = [$contentElements];
                }
                
                foreach ($contentElements as $content) {
                    $childOriginalId = trim((string)$content);
                    if (!empty($childOriginalId)) {
                        $children[] = $childOriginalId;
                    }
                }
            }
            
            if (!empty($children)) {
                $parentChildRelations[$parentOriginalId] = $children;
                error_log("BoxSet gefunden: Parent '$parentOriginalId' (CollectionNumber: $collectionNumber) hat " . count($children) . " Kinder");
            }
        }
    }
    
    error_log("ID-Mapping erstellt: " . count($idMapping) . " Einträge");
    error_log("BoxSet-Relationen gefunden: " . count($parentChildRelations) . " Parents");
    
    // PHASE 2: Alle DVDs importieren
    foreach ($dvdElements as $dvd) {
        $id = (int)($dvd->CollectionNumber ?? 0);
        if ($id <= 0) {
            error_log("DVD übersprungen: Keine gültige CollectionNumber");
            continue;
        }

        // Prüfung auf existierende DVD
        $check = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() > 0) {
            $skipped++;
            continue;
        }

        // Standard-Felder extrahieren
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

        // BoxSet-Parent ermitteln
        $boxsetParent = null;
        $originalId = trim((string)$dvd->ID);
        
        // Prüfen ob diese DVD ein Kind in irgendeinem BoxSet ist
        foreach ($parentChildRelations as $parentOrigId => $children) {
            if (in_array($originalId, $children)) {
                // Diese DVD ist ein Kind - finde die Parent-CollectionNumber
                if (isset($idMapping[$parentOrigId])) {
                    $parentCollectionNumber = $idMapping[$parentOrigId];
                    
                    // Prüfen ob Parent bereits in DB existiert oder wird später importiert
                    $parentCheck = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE id = ?");
                    $parentCheck->execute([$parentCollectionNumber]);
                    
                    // Parent wird auf jeden Fall gesetzt, auch wenn er noch nicht in DB ist
                    // (er wird später in derselben Transaktion importiert)
                    $boxsetParent = $parentCollectionNumber;
                    error_log("DVD $id ($title) wird Kind von Parent $parentCollectionNumber");
                } else {
                    error_log("DVD $id ($title): Parent '$parentOrigId' nicht im ID-Mapping gefunden");
                }
                break; // Ein Parent gefunden, fertig
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
                        // Prüfen ob Schauspieler bereits existiert
                        $stmtActor = $pdo->prepare("SELECT id FROM actors WHERE first_name = ? AND last_name = ? AND birth_year = ?");
                        $stmtActor->execute([$firstName, $lastName, $birthYear]);
                        $actorId = $stmtActor->fetchColumn();

                        if (!$actorId) {
                            // Neuen Schauspieler anlegen
                            $stmtInsert = $pdo->prepare("INSERT INTO actors (first_name, last_name, birth_year) VALUES (?, ?, ?)");
                            $stmtInsert->execute([$firstName, $lastName, $birthYear]);
                            $actorId = $pdo->lastInsertId();
                        }

                        // Verknüpfung Film-Schauspieler anlegen
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
            
            if ($boxsetParent) {
                error_log("DVD $id ($title) erfolgreich als Kind von Parent $boxsetParent importiert");
            }

        } catch (Exception $e) {
            $errors++;
            error_log("Fehler beim Importieren von DVD {$id} ({$title}): " . $e->getMessage());
        }
    }

    // Foreign Key Constraints temporär prüfen
    try {
        // Prüfe auf invalide BoxSet-Parents
        $invalidParents = $pdo->query("
            SELECT DISTINCT d1.boxset_parent, COUNT(*) as count
            FROM dvds d1 
            LEFT JOIN dvds d2 ON d1.boxset_parent = d2.id 
            WHERE d1.boxset_parent IS NOT NULL AND d2.id IS NULL
            GROUP BY d1.boxset_parent
        ")->fetchAll();
        
        if (!empty($invalidParents)) {
            foreach ($invalidParents as $invalid) {
                error_log("WARNUNG: {$invalid['count']} DVDs referenzieren nicht-existierenden Parent {$invalid['boxset_parent']}");
                $boxsetFixed += $invalid['count'];
                
                // Invalide Parent-Referenzen auf NULL setzen
                $fixStmt = $pdo->prepare("UPDATE dvds SET boxset_parent = NULL WHERE boxset_parent = ?");
                $fixStmt->execute([$invalid['boxset_parent']]);
            }
        }
    } catch (Exception $e) {
        error_log("Fehler bei BoxSet-Validierung: " . $e->getMessage());
    }

    $pdo->commit();
    
    // Finale Statistik
    $finalBoxsetChildren = $pdo->query("SELECT COUNT(*) FROM dvds WHERE boxset_parent IS NOT NULL")->fetchColumn();
    $finalBoxsetParents = $pdo->query("SELECT COUNT(DISTINCT boxset_parent) FROM dvds WHERE boxset_parent IS NOT NULL")->fetchColumn();
    
    error_log("IMPORT ABGESCHLOSSEN:");
    error_log("- $imported DVDs importiert");
    error_log("- $skipped Duplikate übersprungen");
    error_log("- $errors Fehler");
    error_log("- $finalBoxsetChildren BoxSet-Kinder");
    error_log("- $finalBoxsetParents BoxSet-Parents");

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("IMPORT FEHLGESCHLAGEN: " . $e->getMessage());
    exit('Fehler beim Import: ' . $e->getMessage());
}

// Erfolgsmeldung zusammenstellen
$resultMessage = "🎬 Import abgeschlossen:\n"
    . "$imported neue Filme importiert\n"
    . "$skipped Duplikate übersprungen\n";

if ($finalBoxsetChildren > 0) {
    $resultMessage .= "$finalBoxsetChildren BoxSet-Kinder importiert\n";
    $resultMessage .= "$finalBoxsetParents BoxSet-Collections gefunden\n";
}

if ($boxsetFixed > 0) {
    $resultMessage .= "$boxsetFixed BoxSet-Zuordnungen korrigiert\n";
}

if ($errors > 0) {
    $resultMessage .= "⚠️ $errors Fehler beim Import\n";
}

$resultMessage .= "\nImportierte Datei gespeichert unter: admin/xml/" . basename($savedPath);

$_SESSION['import_result'] = $resultMessage;

header('Location: ../index.php?page=import');
exit;