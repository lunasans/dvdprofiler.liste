<?php
declare(strict_types=1);

/**
 * Generate Backup Codes Action
 * Regeneriert 2FA-Backup-Codes für einen Benutzer
 * Production-ready version mit vollständiger Fehlerbehandlung
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    require_once __DIR__ . '/../../includes/bootstrap.php';
} catch (Exception $e) {
    error_log('Bootstrap error in generate_backup_codes.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'System error']);
    exit;
}

// Security checks
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Nur POST-Requests erlaubt']);
    exit;
}

// CSRF-Schutz
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Ungültiger CSRF-Token']);
    exit;
}

try {
    // Input validation
    $userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    
    if (!$userId || $userId <= 0) {
        throw new InvalidArgumentException('Ungültige Benutzer-ID');
    }
    
    // Permission check - user can only regenerate their own codes or admin can do it for others
    $currentUserId = $_SESSION['user_id'];
    $isAdmin = $_SESSION['is_admin'] ?? false;
    
    if ($userId !== $currentUserId && !$isAdmin) {
        throw new Exception('Keine Berechtigung zum Ändern der Backup-Codes dieses Benutzers');
    }
    
    // Prüfen ob Benutzer existiert und 2FA aktiviert hat
    $stmt = $pdo->prepare("
        SELECT email, is_totp_enabled, totp_secret 
        FROM users 
        WHERE id = ?
    ");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception('Benutzer nicht gefunden');
    }
    
    if (!$user['is_totp_enabled'] || empty($user['totp_secret'])) {
        throw new Exception('2FA ist für diesen Benutzer nicht aktiviert');
    }
    
    // Backup-Codes Konfiguration
    $backupCodesCount = (int)getSetting('2fa_backup_codes_count', '10');
    $backupCodesCount = max(5, min(20, $backupCodesCount)); // Zwischen 5 und 20
    
    // Neue Backup-Codes generieren
    $backupCodes = [];
    for ($i = 0; $i < $backupCodesCount; $i++) {
        // Generiere 8-stellige Backup-Codes (mehr Entropie)
        $backupCodes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    
    // Validierung der generierten Codes
    if (count($backupCodes) !== $backupCodesCount) {
        throw new Exception('Fehler bei der Code-Generierung');
    }
    
    // Alle Codes müssen einzigartig sein
    if (count(array_unique($backupCodes)) !== count($backupCodes)) {
        throw new Exception('Duplicate codes generated, trying again...');
    }
    
    // Datenbankoperation in Transaktion
    $pdo->beginTransaction();
    
    try {
        // Alte Backup-Codes löschen
        $deleteStmt = $pdo->prepare("DELETE FROM user_backup_codes WHERE user_id = ?");
        $deleteResult = $deleteStmt->execute([$userId]);
        
        if (!$deleteResult) {
            throw new Exception('Fehler beim Löschen der alten Backup-Codes');
        }
        
        $deletedCount = $deleteStmt->rowCount();
        
        // Neue Backup-Codes einfügen
        $insertStmt = $pdo->prepare("
            INSERT INTO user_backup_codes (user_id, code, created_at) 
            VALUES (?, ?, NOW())
        ");
        
        $insertedCount = 0;
        foreach ($backupCodes as $code) {
            // Codes werden gehashed gespeichert für Sicherheit
            $hashedCode = password_hash($code, PASSWORD_DEFAULT);
            
            if ($insertStmt->execute([$userId, $hashedCode])) {
                $insertedCount++;
            } else {
                throw new Exception('Fehler beim Speichern des Backup-Codes');
            }
        }
        
        // Prüfen ob alle Codes gespeichert wurden
        if ($insertedCount !== count($backupCodes)) {
            throw new Exception("Nur {$insertedCount} von " . count($backupCodes) . " Backup-Codes gespeichert");
        }
        
        // Activity logging (optional, aber wichtig für Audit)
        try {
            $activityStmt = $pdo->prepare("
                INSERT INTO activity_log (user_id, action, details, ip_address, user_agent, created_at)
                VALUES (?, 'backup_codes_regenerated', ?, ?, ?, NOW())
            ");
            
            $activityDetails = [
                'target_user_id' => $userId,
                'email' => $user['email'],
                'codes_count' => count($backupCodes),
                'old_codes_deleted' => $deletedCount,
                'new_codes_created' => $insertedCount,
                'performed_by' => $currentUserId,
                'timestamp' => time()
            ];
            
            $activityStmt->execute([
                $currentUserId,
                json_encode($activityDetails, JSON_UNESCAPED_UNICODE),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
            
        } catch (Exception $e) {
            // Activity logging ist optional und sollte den Hauptprozess nicht stoppen
            error_log('Activity logging failed in generate_backup_codes.php: ' . $e->getMessage());
        }
        
        // Transaktion committen
        $pdo->commit();
        
        // Erfolgreiche Antwort
        echo json_encode([
            'success' => true,
            'message' => 'Backup-Codes erfolgreich neu generiert',
            'backup_codes' => $backupCodes,
            'codes_count' => count($backupCodes),
            'metadata' => [
                'user_id' => $userId,
                'email' => $user['email'],
                'generated_at' => date('Y-m-d H:i:s'),
                'old_codes_removed' => $deletedCount,
                'new_codes_created' => $insertedCount
            ]
        ], JSON_UNESCAPED_UNICODE);
        
        // Log successful regeneration
        error_log("Backup codes successfully regenerated for user {$userId} ({$user['email']})");
        
    } catch (Exception $e) {
        // Rollback bei Datenbankfehlern
        $pdo->rollBack();
        throw $e;
    }
    
} catch (InvalidArgumentException $e) {
    // Client-side Fehler (400)
    error_log('Invalid input in generate_backup_codes.php: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_type' => 'validation_error'
    ]);
    
} catch (PDOException $e) {
    // Datenbankfehler (500)
    error_log('Database error in generate_backup_codes.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Datenbankfehler beim Generieren der Backup-Codes',
        'error_type' => 'database_error'
    ]);
    
} catch (Exception $e) {
    // Allgemeine Fehler (500)
    error_log('General error in generate_backup_codes.php: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Backup-Code-Generierung fehlgeschlagen: ' . $e->getMessage(),
        'error_type' => 'general_error'
    ]);
}

// Cleanup - Session-Daten bereinigen falls nötig
if (isset($_SESSION['temp_backup_codes'])) {
    unset($_SESSION['temp_backup_codes']);
}
?>