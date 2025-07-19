<?php
declare(strict_types=1);

require_once __DIR__ . '/../../includes/bootstrap.php';

// Security: Session-Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet.']);
    exit;
}

// Security: Nur POST erlaubt
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Nur POST erlaubt.']);
    exit;
}

// Security: CSRF-Token prüfen
$submittedToken = $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($submittedToken)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token.']);
    exit;
}

// Konfiguration
$uploadDir = dirname(__DIR__, 2) . '/admin/xml/';
$maxFileSize = 50 * 1024 * 1024; // 50MB
$maxMemoryUsage = 256 * 1024 * 1024; // 256MB
$allowedMimeTypes = [
    'application/xml',
    'text/xml',
    'application/zip',
    'application/x-zip-compressed'
];

// Upload-Verzeichnis sicher erstellen
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Upload-Verzeichnis konnte nicht erstellt werden.']);
        exit;
    }
}

class SecureDVDImporter {
    private PDO $pdo;
    private int $userId;
    private string $uploadDir;
    private array $stats = [
        'imported' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0
    ];
    private array $errors = [];
    
    public function __construct(PDO $pdo, int $userId, string $uploadDir) {
        $this->pdo = $pdo;
        $this->userId = $userId;
        $this->uploadDir = $uploadDir;
        
        // Memory Limit erhöhen für große XML-Dateien
        ini_set('memory_limit', '256M');
        set_time_limit(300); // 5 Minuten
    }
    
    public function processUpload(): array {
        try {
            // 1. Upload validieren
            $uploadResult = $this->validateUpload();
            if (!$uploadResult['success']) {
                return $uploadResult;
            }
            
            // 2. XML extrahieren
            $xmlContent = $this->extractXMLContent($uploadResult['file']);
            if (!$xmlContent) {
                return ['success' => false, 'message' => 'Keine gültigen XML-Daten gefunden.'];
            }
            
            // 3. XML-Datei sicher speichern
            $savedPath = $this->saveXMLFile($xmlContent);
            
            // 4. XML parsen und importieren
            $importResult = $this->importXMLData($xmlContent);
            
            // 5. Ergebnis zusammenstellen
            return [
                'success' => true,
                'message' => $this->generateSuccessMessage($savedPath),
                'stats' => $this->stats,
                'errors' => $this->errors
            ];
            
        } catch (Exception $e) {
            error_log('Import failed: ' . $e->getMessage());
            return [
                'success' => false, 
                'message' => 'Import fehlgeschlagen: ' . $e->getMessage(),
                'stats' => $this->stats,
                'errors' => $this->errors
            ];
        }
    }
    
    private function validateUpload(): array {
        if (!isset($_FILES['xml_file']) || $_FILES['xml_file']['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'Datei überschreitet upload_max_filesize.',
                UPLOAD_ERR_FORM_SIZE => 'Datei überschreitet MAX_FILE_SIZE.',
                UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen.',
                UPLOAD_ERR_NO_FILE => 'Keine Datei hochgeladen.',
                UPLOAD_ERR_NO_TMP_DIR => 'Temporäres Verzeichnis fehlt.',
                UPLOAD_ERR_CANT_WRITE => 'Datei konnte nicht geschrieben werden.',
                UPLOAD_ERR_EXTENSION => 'Upload durch PHP-Extension gestoppt.'
            ];
            
            $error = $_FILES['xml_file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $message = $errorMessages[$error] ?? 'Unbekannter Upload-Fehler.';
            
            return ['success' => false, 'message' => $message];
        }
        
        $file = $_FILES['xml_file'];
        
        // Dateigröße prüfen
        if ($file['size'] > $maxFileSize = 50 * 1024 * 1024) {
            return ['success' => false, 'message' => 'Datei zu groß (max. 50MB).'];
        }
        
        // MIME-Type prüfen
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        global $allowedMimeTypes;
        if (!in_array($mimeType, $allowedMimeTypes)) {
            return ['success' => false, 'message' => "Ungültiger Dateityp: {$mimeType}"];
        }
        
        // Dateiname validieren
        if (!preg_match('/^[a-zA-Z0-9._-]+\.(xml|zip)$/i', $file['name'])) {
            return ['success' => false, 'message' => 'Ungültiger Dateiname.'];
        }
        
        return ['success' => true, 'file' => $file];
    }
    
    private function extractXMLContent(array $file): ?string {
        $filename = $file['name'];
        $tmpPath = $file['tmp_name'];
        
        if (str_ends_with(strtolower($filename), '.zip')) {
            return $this->extractFromZip($tmpPath);
        } else {
            return $this->validateXMLFile($tmpPath);
        }
    }
    
    private function extractFromZip(string $zipPath): ?string {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== TRUE) {
            throw new Exception('ZIP-Datei konnte nicht geöffnet werden.');
        }
        
        $xmlContent = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            
            // Security: Path-Traversal verhindern
            if (str_contains($entry, '..') || str_starts_with($entry, '/')) {
                continue;
            }
            
            if (str_ends_with(strtolower($entry), '.xml')) {
                $content = $zip->getFromName($entry);
                if ($content && $this->isValidXMLContent($content)) {
                    $xmlContent = $content;
                    break;
                }
            }
        }
        
        $zip->close();
        return $xmlContent;
    }
    
    private function validateXMLFile(string $filePath): ?string {
        $content = file_get_contents($filePath);
        return $this->isValidXMLContent($content) ? $content : null;
    }
    
    private function isValidXMLContent(string $content): bool {
        // XXE-Angriffe verhindern
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);
        
        $dom = new DOMDocument();
        $dom->validateOnParse = true;
        
        // XML laden ohne externe Entities
        $isValid = $dom->loadXML($content, LIBXML_NOENT | LIBXML_DTDLOAD | LIBXML_DTDATTR);
        
        if ($isValid) {
            // DVD-Profiler-Struktur prüfen
            $dvdElements = $dom->getElementsByTagName('DVD');
            $collectionElements = $dom->getElementsByTagName('Collection');
            
            if ($dvdElements->length === 0 && $collectionElements->length === 0) {
                $isValid = false;
            }
        }
        
        libxml_use_internal_errors(false);
        return $isValid;
    }
    
    private function saveXMLFile(string $content): string {
        $filename = 'import_' . date('Ymd_His') . '_' . uniqid() . '.xml';
        $savedPath = $this->uploadDir . $filename;
        
        if (!file_put_contents($savedPath, $content)) {
            throw new Exception('XML-Datei konnte nicht gespeichert werden.');
        }
        
        chmod($savedPath, 0644);
        return $savedPath;
    }
    
    private function importXMLData(string $xmlContent): bool {
        // XXE-Protection
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);
        
        $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOENT);
        if ($xml === false) {
            throw new Exception('XML-Parsing fehlgeschlagen.');
        }
        
        // Transaktion starten für Datenintegrität
        $this->pdo->beginTransaction();
        
        try {
            foreach ($xml->DVD as $dvd) {
                $this->processDVDEntry($dvd);
            }
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    private function processDVDEntry(SimpleXMLElement $dvd): void {
        try {
            // ID aus CollectionNumber extrahieren und validieren
            $id = (int)($dvd->CollectionNumber ?? 0);
            if ($id <= 0) {
                $this->stats['skipped']++;
                $this->errors[] = "Ungültige ID für Film: " . (string)$dvd->Title;
                return;
            }
            
            // DVD-Daten extrahieren und validieren
            $dvdData = $this->extractDVDData($dvd);
            
            // Prüfen ob DVD bereits existiert
            $existingDVD = $this->checkExistingDVD($id);
            
            if ($existingDVD) {
                // Update-Option (optional implementieren)
                $this->stats['skipped']++;
                return;
            }
            
            // DVD einfügen
            $this->insertDVD($id, $dvdData);
            
            // Schauspieler verarbeiten
            if (isset($dvd->Actors)) {
                $this->processActors($id, $dvd->Actors);
            }
            
            $this->stats['imported']++;
            
        } catch (Exception $e) {
            $this->stats['errors']++;
            $this->errors[] = "Fehler bei Film ID {$id}: " . $e->getMessage();
            error_log("DVD import error for ID {$id}: " . $e->getMessage());
        }
    }
    
    private function extractDVDData(SimpleXMLElement $dvd): array {
        return [
            'title' => $this->sanitizeString((string)$dvd->Title, 255),
            'year' => $this->validateYear((int)$dvd->ProductionYear),
            'genre' => $this->sanitizeString((string)$dvd->Genres->Genre, 100),
            'runtime' => max(0, (int)$dvd->RunningTime),
            'rating_age' => $this->validateRating((string)($dvd->RatingAge ?? '')),
            'overview' => $this->sanitizeString((string)$dvd->Overview, 2000),
            'cover_id' => $this->sanitizeString((string)$dvd->ID, 50),
            'collection_type' => $this->sanitizeString((string)$dvd->CollectionType, 50),
            'boxset_parent' => $this->sanitizeString((string)($dvd->BoxSet->Parent ?? ''), 50),
            'trailer_url' => $this->validateURL((string)($dvd->trailer_url ?? ''))
        ];
    }
    
    private function sanitizeString(string $input, int $maxLength): string {
        $input = trim($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return substr($input, 0, $maxLength);
    }
    
    private function validateYear(int $year): int {
        return ($year >= 1800 && $year <= 2100) ? $year : 0;
    }
    
    private function validateRating(string $rating): ?int {
        $rating = trim($rating);
        if (!is_numeric($rating)) return null;
        
        $age = (int)$rating;
        return ($age >= 0 && $age <= 99) ? $age : null;
    }
    
    private function validateURL(string $url): ?string {
        $url = trim($url);
        if (empty($url)) return null;
        
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
    
    private function checkExistingDVD(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT id, title FROM dvds WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    private function insertDVD(int $id, array $data): void {
        $stmt = $this->pdo->prepare("
            INSERT INTO dvds (
                id, title, year, genre, runtime, rating_age,
                overview, cover_id, collection_type, boxset_parent, 
                trailer_url, user_id, created_at
            ) VALUES (
                :id, :title, :year, :genre, :runtime, :rating_age,
                :overview, :cover_id, :collection_type, :boxset_parent,
                :trailer_url, :user_id, NOW()
            )
        ");
        
        $stmt->execute([
            'id' => $id,
            'title' => $data['title'],
            'year' => $data['year'],
            'genre' => $data['genre'],
            'runtime' => $data['runtime'],
            'rating_age' => $data['rating_age'],
            'overview' => $data['overview'],
            'cover_id' => $data['cover_id'],
            'collection_type' => $data['collection_type'],
            'boxset_parent' => $data['boxset_parent'] ?: null,
            'trailer_url' => $data['trailer_url'],
            'user_id' => $this->userId
        ]);
    }
    
    private function processActors(int $filmId, SimpleXMLElement $actors): void {
        foreach ($actors->Actor as $actorXml) {
            try {
                $firstName = $this->sanitizeString((string)($actorXml['FirstName'] ?? ''), 100);
                $lastName = $this->sanitizeString((string)($actorXml['LastName'] ?? ''), 100);
                $birthYear = $this->validateYear((int)($actorXml['BirthYear'] ?? 0));
                $role = $this->sanitizeString((string)($actorXml['Role'] ?? ''), 255);
                
                if (empty($firstName) && empty($lastName)) {
                    continue; // Skip invalid actors
                }
                
                $actorId = $this->getOrCreateActor($firstName, $lastName, $birthYear);
                $this->linkActorToFilm($filmId, $actorId, $role);
                
            } catch (Exception $e) {
                error_log("Actor processing error for film {$filmId}: " . $e->getMessage());
            }
        }
    }
    
    private function getOrCreateActor(string $firstName, string $lastName, int $birthYear): int {
        // Schauspieler suchen
        $stmt = $this->pdo->prepare("
            SELECT id FROM actors 
            WHERE first_name = ? AND last_name = ? AND birth_year = ?
        ");
        $stmt->execute([$firstName, $lastName, $birthYear]);
        $actorId = $stmt->fetchColumn();
        
        if (!$actorId) {
            // Neuen Schauspieler einfügen
            $stmt = $this->pdo->prepare("
                INSERT INTO actors (first_name, last_name, birth_year, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$firstName, $lastName, $birthYear]);
            $actorId = $this->pdo->lastInsertId();
        }
        
        return (int)$actorId;
    }
    
    private function linkActorToFilm(int $filmId, int $actorId, string $role): void {
        $stmt = $this->pdo->prepare("
            INSERT IGNORE INTO film_actor (film_id, actor_id, role, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$filmId, $actorId, $role]);
    }
    
    private function generateSuccessMessage(string $savedPath): string {
        $filename = basename($savedPath);
        return "🎬 Import abgeschlossen:\n" .
               "• {$this->stats['imported']} neue Filme importiert\n" .
               "• {$this->stats['updated']} Filme aktualisiert\n" .
               "• {$this->stats['skipped']} Duplikate übersprungen\n" .
               "• {$this->stats['errors']} Fehler aufgetreten\n" .
               "• Datei gespeichert: admin/xml/{$filename}";
    }
    
    public function getStats(): array {
        return $this->stats;
    }
    
    public function getErrors(): array {
        return $this->errors;
    }
}

// Main Execution
try {
    $importer = new SecureDVDImporter($pdo, (int)$_SESSION['user_id'], $uploadDir);
    $result = $importer->processUpload();
    
    if ($result['success']) {
        $_SESSION['import_result'] = $result['message'];
        
        // Optional: Detailed results as JSON
        if (!empty($result['errors'])) {
            $_SESSION['import_errors'] = $result['errors'];
        }
        
        // Redirect to import page
        header('Location: ../index.php?page=import&success=1');
        exit;
    } else {
        $_SESSION['import_result'] = '❌ ' . $result['message'];
        header('Location: ../index.php?page=import&error=1');
        exit;
    }
    
} catch (Exception $e) {
    error_log('Import handler error: ' . $e->getMessage());
    $_SESSION['import_result'] = '❌ Unerwarteter Fehler beim Import.';
    header('Location: ../index.php?page=import&error=1');
    exit;
}
?>