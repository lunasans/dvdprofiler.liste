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
$updated = 0;
$skipped = 0;
$boxsetFixed = 0;
$errors = 0;
$totalActorsImported = 0;
$totalActorLinksCreated = 0;

try {
    $pdo->beginTransaction();

    // SOFT DELETE: Markiere alle Filme als gelöscht
    // Während Import werden importierte Filme wieder auf deleted=0 gesetzt
    $pdo->exec("UPDATE dvds SET deleted = 1");
    error_log("Soft Delete: Alle Filme als gelöscht markiert (werden beim Import wiederhergestellt)");

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

        // Prüfung ob DVD existiert
        $check = $pdo->prepare("SELECT COUNT(*) FROM dvds WHERE id = ?");
        $check->execute([$id]);
        $filmExists = $check->fetchColumn() > 0;

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
        $userId = $_SESSION['user_id'];
        
        // Kaufdatum (PurchaseDate) für created_at
        // PurchaseDate ist in PurchaseInfo verschachtelt!
        $purchaseDate = trim((string)($dvd->PurchaseInfo->PurchaseDate ?? ''));
        
        // Debug-Logging
        if (!empty($purchaseDate)) {
            error_log("DVD $id ($title): PurchaseDate aus XML = '$purchaseDate'");
            $createdAt = $purchaseDate;
        } else {
            error_log("DVD $id ($title): Kein PurchaseDate in XML - created_at bleibt NULL");
            $createdAt = null; // Kein Datum = NULL
        }

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
            if ($filmExists) {
                // Film existiert - UPDATE
                $stmt = $pdo->prepare("
                    UPDATE dvds SET
                        title = :title,
                        year = :year,
                        genre = :genre,
                        runtime = :runtime,
                        rating_age = :rating_age,
                        overview = :overview,
                        cover_id = :cover_id,
                        collection_type = :collection_type,
                        boxset_parent = :boxset_parent,
                        created_at = :created_at,
                        deleted = 0,
                        updated_at = NOW()
                    WHERE id = :id
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
                    'created_at' => $createdAt
                ]);
                
                $updated++;
                error_log("DVD $id ($title) erfolgreich aktualisiert (Kaufdatum: $createdAt)");
                
            } else {
                // Film existiert nicht - INSERT (trailer_url wird nicht importiert)
                $stmt = $pdo->prepare("
                    INSERT INTO dvds (
                        id, title, year, genre, runtime, rating_age,
                        overview, cover_id, collection_type, boxset_parent, deleted, user_id, created_at
                    ) VALUES (
                        :id, :title, :year, :genre, :runtime, :rating_age,
                        :overview, :cover_id, :collection_type, :boxset_parent, 0, :user_id, :created_at
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
                    'user_id' => $userId,
                    'created_at' => $createdAt
                ]);

                $imported++;
                error_log("DVD $id ($title) erfolgreich neu importiert (Kaufdatum: $createdAt)");
            }
            
            if ($boxsetParent) {
                error_log("DVD $id ($title) erfolgreich als Kind von Parent $boxsetParent importiert");
            }

            // Bei UPDATE: Alte Schauspieler-Verknüpfungen löschen
            if ($filmExists) {
                $deleteLinks = $pdo->prepare("DELETE FROM film_actor WHERE film_id = ?");
                $deleteLinks->execute([$id]);
                error_log("DVD $id ($title): Alte Schauspieler-Verknüpfungen gelöscht");
            }

            // DANN Schauspieler verarbeiten (nach DVD-Insert/Update!)
            $actorCount = 0;
            if (isset($dvd->Actors) && isset($dvd->Actors->Actor)) {
                error_log("DVD $id ($title): Verarbeite Schauspieler...");
                
                // Falls nur ein Actor, wird es nicht als Array behandelt
                $actorElements = $dvd->Actors->Actor;
                if (!is_array($actorElements) && !($actorElements instanceof Traversable)) {
                    $actorElements = [$actorElements];
                }
                
                foreach ($actorElements as $actorXml) {
                    // Verschiedene XML-Strukturen testen
                    $firstName = trim((string)($actorXml['FirstName'] ?? $actorXml->FirstName ?? ''));
                    $lastName = trim((string)($actorXml['LastName'] ?? $actorXml->LastName ?? ''));
                    $role = trim((string)($actorXml['Role'] ?? $actorXml->Role ?? ''));
                    $birthYear = (int)($actorXml['BirthYear'] ?? $actorXml->BirthYear ?? 0);
                    
                    // Debug: XML-Struktur ausgeben
                    if (empty($firstName) && empty($lastName)) {
                        error_log("DVD $id: Schauspieler-Element gefunden, aber keine Namen: " . 
                                 json_encode([
                                     'attributes' => (array)$actorXml->attributes(),
                                     'children' => array_keys((array)$actorXml),
                                     'content' => (string)$actorXml
                                 ]));
                        continue;
                    }
                    
                    if (!empty($firstName) || !empty($lastName)) {
                        // Prüfen ob Schauspieler bereits existiert (ohne birth_year falls unbekannt)
                        if ($birthYear > 0) {
                            $stmtActor = $pdo->prepare("SELECT id FROM actors WHERE first_name = ? AND last_name = ? AND birth_year = ?");
                            $stmtActor->execute([$firstName, $lastName, $birthYear]);
                        } else {
                            $stmtActor = $pdo->prepare("SELECT id FROM actors WHERE first_name = ? AND last_name = ? AND (birth_year IS NULL OR birth_year = 0)");
                            $stmtActor->execute([$firstName, $lastName]);
                        }
                        
                        $actorId = $stmtActor->fetchColumn();

                        if (!$actorId) {
                            // Neuen Schauspieler anlegen mit allen Spalten
                            $stmtInsert = $pdo->prepare("
                                INSERT INTO actors (first_name, last_name, birth_year, bio, created_at, updated_at) 
                                VALUES (?, ?, ?, '', NOW(), NOW())
                            ");
                            $stmtInsert->execute([$firstName, $lastName, $birthYear > 0 ? $birthYear : null]);
                            $actorId = $pdo->lastInsertId();
                            $totalActorsImported++;
                            
                            error_log("DVD $id: Neuer Schauspieler erstellt: $firstName $lastName (ID: $actorId)");
                        } else {
                            error_log("DVD $id: Bestehender Schauspieler gefunden: $firstName $lastName (ID: $actorId)");
                        }

                        // Verknüpfung Film-Schauspieler anlegen
                        $stmtLink = $pdo->prepare("INSERT IGNORE INTO film_actor (film_id, actor_id, role) VALUES (?, ?, ?)");
                        $stmtLink->execute([$id, $actorId, $role]);
                        
                        $actorCount++;
                        $totalActorLinksCreated++;
                        error_log("DVD $id: Schauspieler verknüpft: $firstName $lastName als '$role'");
                    }
                }
                
                error_log("DVD $id ($title): $actorCount Schauspieler erfolgreich importiert");
            } else {
                error_log("DVD $id ($title): Keine Schauspieler-Daten in XML gefunden");
                
                // Debug: XML-Struktur anzeigen
                $xmlKeys = array_keys((array)$dvd);
                error_log("DVD $id: Verfügbare XML-Elemente: " . implode(', ', $xmlKeys));
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

    // Zähle gelöschte Filme (die nicht mehr in XML sind)
    $deletedCount = $pdo->query("SELECT COUNT(*) FROM dvds WHERE deleted = 1")->fetchColumn();

    $pdo->commit();
    
    // Finale Statistik
    $finalBoxsetChildren = $pdo->query("SELECT COUNT(*) FROM dvds WHERE boxset_parent IS NOT NULL")->fetchColumn();
    $finalBoxsetParents = $pdo->query("SELECT COUNT(DISTINCT boxset_parent) FROM dvds WHERE boxset_parent IS NOT NULL")->fetchColumn();
    $finalActorsTotal = $pdo->query("SELECT COUNT(*) FROM actors")->fetchColumn();
    $finalActorLinksTotal = $pdo->query("SELECT COUNT(*) FROM film_actor")->fetchColumn();
    
    error_log("IMPORT ABGESCHLOSSEN:");
    error_log("- $imported DVDs importiert");
    error_log("- $skipped Duplikate übersprungen");
    error_log("- $errors Fehler");
    error_log("- $totalActorsImported neue Schauspieler erstellt");
    error_log("- $totalActorLinksCreated Schauspieler-Film-Verknüpfungen erstellt");
    error_log("- $finalActorsTotal Schauspieler gesamt in DB");
    error_log("- $finalActorLinksTotal Schauspieler-Verknüpfungen gesamt in DB");
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
    . "$updated Filme aktualisiert\n"
    . "$skipped Duplikate übersprungen\n";

if ($deletedCount > 0) {
    $resultMessage .= "🗑️ $deletedCount Filme als gelöscht markiert (nicht mehr in XML)\n";
}

if ($totalActorsImported > 0) {
    $resultMessage .= "$totalActorsImported neue Schauspieler erstellt\n";
}

if ($totalActorLinksCreated > 0) {
    $resultMessage .= "$totalActorLinksCreated Schauspieler-Film-Verknüpfungen erstellt\n";
}

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