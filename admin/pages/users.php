<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

// Benutzer mit 2FA-Status laden
$stmt = $pdo->query("
    SELECT u.id, u.email, u.created_at, u.last_login, u.twofa_enabled, u.twofa_activated_at,
           COUNT(bc.id) as backup_codes_remaining
    FROM users u
    LEFT JOIN user_backup_codes bc ON u.id = bc.user_id AND bc.used_at IS NULL
    GROUP BY u.id
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

// Session-Messages
$success = $_SESSION['success'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);
?>


<style>
/* ============================================
   DARK THEME für User-Tabelle
   ============================================ */

/* Card & Container */
.card {
    background: var(--clr-card) !important;
    border-color: var(--clr-border) !important;
}

.card-header {
    background: rgba(255, 255, 255, 0.05) !important;
    border-bottom-color: var(--clr-border) !important;
    color: var(--clr-text) !important;
}

.card-body {
    background: var(--clr-card) !important;
}

/* Table */
.table-responsive {
    background: var(--clr-card) !important;
}

.table {
    background: var(--clr-card) !important;
    color: var(--clr-text) !important;
}

.table thead {
    background: rgba(255, 255, 255, 0.1) !important;
}

.table thead th {
    background: transparent !important;
    color: var(--clr-text) !important;
    border-bottom-color: var(--clr-border) !important;
}

.table tbody {
    background: var(--clr-card) !important;
}

.table tbody tr {
    background: var(--clr-card) !important;
    border-bottom-color: var(--clr-border) !important;
}

.table tbody tr:hover {
    background: rgba(255, 255, 255, 0.05) !important;
}

.table tbody td {
    background: transparent !important;
    color: var(--clr-text) !important;
    border-bottom-color: var(--clr-border) !important;
}

/* Text Colors */
.fw-medium {
    color: var(--clr-text) !important;
}

.text-muted,
.table .text-muted {
    color: var(--clr-text-muted) !important;
}

small.text-muted {
    color: var(--clr-text-muted) !important;
}

/* Badges */
.badge.bg-success {
    background: #2ecc71 !important;
    color: #ffffff !important;
}

.badge.bg-secondary {
    background: #6c757d !important;
    color: #ffffff !important;
}

.badge.bg-warning {
    background: #f39c12 !important;
    color: #ffffff !important;
}

.badge.bg-danger {
    background: #e74c3c !important;
    color: #ffffff !important;
}

.badge.bg-info {
    background: #3498db !important;
    color: #ffffff !important;
}

/* Text Warning (für "Keine Backup-Codes") */
small.text-warning {
    color: #f39c12 !important;
}

/* Buttons */
.btn-group .btn {
    border-color: var(--clr-border) !important;
}

.btn-outline-primary {
    color: var(--clr-accent) !important;
    border-color: var(--clr-accent) !important;
}

.btn-outline-primary:hover {
    background: var(--clr-accent) !important;
    color: #ffffff !important;
}

.btn-outline-success {
    color: #2ecc71 !important;
    border-color: #2ecc71 !important;
}

.btn-outline-success:hover {
    background: #2ecc71 !important;
    color: #ffffff !important;
}

.btn-outline-warning {
    color: #f39c12 !important;
    border-color: #f39c12 !important;
}

.btn-outline-warning:hover {
    background: #f39c12 !important;
    color: #ffffff !important;
}

.btn-outline-danger {
    color: #e74c3c !important;
    border-color: #e74c3c !important;
}

.btn-outline-danger:hover {
    background: #e74c3c !important;
    color: #ffffff !important;
}

/* Alerts */
.alert-success {
    background: rgba(46, 204, 113, 0.1) !important;
    border-color: #2ecc71 !important;
    color: #2ecc71 !important;
}

.alert-danger {
    background: rgba(231, 76, 60, 0.1) !important;
    border-color: #e74c3c !important;
    color: #e74c3c !important;
}

/* Modal - Dunkles Theme */
.modal-content {
    background: var(--clr-card) !important;
    color: var(--clr-text) !important;
    border-color: var(--clr-border) !important;
}

.modal-header {
    background: rgba(255, 255, 255, 0.05) !important;
    border-bottom-color: var(--clr-border) !important;
}

.modal-title {
    color: var(--clr-text) !important;
}

.modal-body {
    color: var(--clr-text) !important;
}

.modal-footer {
    background: rgba(255, 255, 255, 0.05) !important;
    border-top-color: var(--clr-border) !important;
}

/* Form Elements in Modal */
.modal-content .form-label {
    color: var(--clr-text) !important;
    font-weight: 500;
}

.modal-content .form-text {
    color: var(--clr-text-muted) !important;
}

.modal-content .form-control {
    background: rgba(255, 255, 255, 0.1) !important;
    color: var(--clr-text) !important;
    border-color: var(--clr-border) !important;
}

.modal-content .form-control:focus {
    background: rgba(255, 255, 255, 0.15) !important;
    color: var(--clr-text) !important;
    border-color: var(--clr-accent) !important;
    box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25) !important;
}

.modal-content .form-control::placeholder {
    color: var(--clr-text-muted) !important;
}

/* Close Button */
.btn-close {
    filter: invert(1);
    opacity: 0.5;
}

.btn-close:hover {
    opacity: 1;
}

/* QR Code Container */
#qrcode-container {
    background: rgba(255, 255, 255, 0.1) !important;
}

/* Backup Codes */
.backup-code {
    background: rgba(255, 255, 255, 0.1) !important;
    color: var(--clr-text) !important;
    border-color: var(--clr-border) !important;
}

/* Avatar */
.avatar div {
    background: var(--clr-accent) !important;
}
</style>

<div class="container-fluid">
    

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Registrierte Benutzer (<?= count($users) ?>)</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus"></i> Neuer Benutzer
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Benutzer</th>
                            <th>2FA-Status</th>
                            <th>Letzter Login</th>
                            <th>Erstellt</th>
                            <th width="200">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-3">
                                            <div
                                                style="width: 40px; height: 40px; background: var(--clr-accent); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                                <?= strtoupper(substr($user['email'], 0, 1)) ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?= htmlspecialchars($user['email']) ?></div>
                                            <small class="text-muted">ID: <?= $user['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($user['twofa_enabled']): ?>
                                        <span class="badge bg-success d-flex align-items-center" style="width: fit-content;">
                                            <i class="bi bi-shield-check me-1"></i>
                                            2FA Aktiv
                                        </span>
                                        <?php if ($user['backup_codes_remaining'] > 0): ?>
                                            <small class="text-muted d-block mt-1">
                                                <?= $user['backup_codes_remaining'] ?> Backup-Codes übrig
                                            </small>
                                        <?php else: ?>
                                            <small class="text-warning d-block mt-1">
                                                <i class="bi bi-exclamation-triangle"></i> Keine Backup-Codes
                                            </small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-shield-x me-1"></i>
                                            Nicht aktiviert
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['last_login']): ?>
                                        <span title="<?= date('d.m.Y H:i:s', strtotime($user['last_login'])) ?>">
                                            <?= date('d.m.Y H:i', strtotime($user['last_login'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Nie</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span title="<?= date('d.m.Y H:i:s', strtotime($user['created_at'])) ?>">
                                        <?= date('d.m.Y', strtotime($user['created_at'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary edit-user-btn" data-id="<?= $user['id'] ?>"
                                            data-email="<?= htmlspecialchars($user['email']) ?>"
                                            title="Benutzer bearbeiten">
                                            <i class="bi bi-pencil"></i>
                                        </button>

                                        <?php if (!$user['twofa_enabled']): ?>
                                            <button class="btn btn-outline-success setup-2fa-btn" data-id="<?= $user['id'] ?>"
                                                title="2FA aktivieren">
                                                <i class="bi bi-shield-plus"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-outline-warning regenerate-backup-btn"
                                                data-id="<?= $user['id'] ?>" title="Backup-Codes neu generieren">
                                                <i class="bi bi-arrow-clockwise"></i>
                                            </button>
                                            <button class="btn btn-outline-danger disable-2fa-btn" data-id="<?= $user['id'] ?>"
                                                title="2FA deaktivieren">
                                                <i class="bi bi-shield-minus"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                            <button class="btn btn-outline-danger delete-user-btn" data-id="<?= $user['id'] ?>"
                                                data-email="<?= htmlspecialchars($user['email']) ?>" title="Benutzer löschen">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="actions/update_user.php">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-gear"></i> Benutzer bearbeiten
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit-user-id">
                    <div class="mb-3">
                        <label for="edit-email" class="form-label">E-Mail-Adresse</label>
                        <input type="email" class="form-control" id="edit-email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-password" class="form-label">Neues Passwort</label>
                        <input type="password" class="form-control" id="edit-password" name="password">
                        <div class="form-text">Leer lassen, um Passwort nicht zu ändern</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check"></i> Speichern
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" action="actions/create_user.php">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> Neuer Benutzer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="new-email" class="form-label">E-Mail-Adresse</label>
                        <input type="email" class="form-control" id="new-email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label for="new-password" class="form-label">Passwort</label>
                        <input type="password" class="form-control" id="new-password" name="password" required
                            minlength="8">
                        <div class="form-text">Mindestens 8 Zeichen</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-person-plus"></i> Erstellen
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Abbrechen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 2FA Setup Modal -->
<div class="modal fade" id="setup2faModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-shield-plus"></i> 2FA aktivieren
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="setup-step-1">
                    <div class="text-center">
                        <p class="mb-4">Klicken Sie auf "QR-Code generieren" um zu beginnen:</p>
                        <button type="button" class="btn btn-primary" id="generate2faBtn">
                            <i class="bi bi-qr-code"></i> QR-Code generieren
                        </button>
                    </div>
                </div>

                <div id="setup-step-2" style="display: none;">
                    <div class="row">
                        <div class="col-md-6 text-center">
                            <h6>1. QR-Code scannen</h6>
                            <div class="mb-3">
                                <img id="qrcode-image" src="" alt="QR-Code" class="img-fluid"
                                    style="max-width: 200px; border: 1px solid var(--clr-border); border-radius: 8px;">
                            </div>
                            <p class="small text-muted">
                                Scannen Sie den QR-Code mit Ihrer Authenticator-App
                                (Google Authenticator, Authy, etc.)
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6>2. Code eingeben</h6>
                            <form id="verify2faSetupForm">
                                <div class="mb-3">
                                    <label for="setup-token" class="form-label">6-stelliger Code</label>
                                    <input type="text" class="form-control text-center" id="setup-token" name="token"
                                        required pattern="[0-9]{6}" maxlength="6"
                                        style="font-size: 1.2rem; letter-spacing: 0.3em;">
                                </div>
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="bi bi-check-circle"></i> 2FA aktivieren
                                </button>
                            </form>

                            <hr>

                            <h6>Alternative: Manueller Eintrag</h6>
                            <div class="small">
                                <strong>Anbieter:</strong> <span id="manual-issuer"></span><br>
                                <strong>Konto:</strong> <span id="manual-account"></span><br>
                                <strong>Secret:</strong> <code id="manual-secret" style="word-break: break-all;"></code>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="setup-step-3" style="display: none;">
                    <div class="text-center">
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <strong>2FA erfolgreich aktiviert!</strong>
                        </div>

                        <h6>Backup-Codes</h6>
                        <p class="text-muted">Speichern Sie diese Codes sicher. Sie können jeden Code nur einmal
                            verwenden.</p>

                        <div class="backup-codes" id="backup-codes-display">
                            <!-- Wird durch JavaScript gefüllt -->
                        </div>

                        <button type="button" class="btn btn-outline-primary mt-3" id="downloadBackupCodes">
                            <i class="bi bi-download"></i> Codes herunterladen
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Schließen</button>
            </div>
        </div>
    </div>
</div>

<style>
    .backup-codes {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 0.5rem;
        max-width: 500px;
        margin: 0 auto;
    }

    .backup-code {
        background: var(--clr-card);
        border: 1px solid var(--clr-border);
        border-radius: 4px;
        padding: 0.5rem;
        font-family: monospace;
        font-size: 0.9rem;
        text-align: center;
    }

    .avatar {
        flex-shrink: 0;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        let currentUserId = null; // Variable richtig initialisieren

        // Edit User Modal
        document.querySelectorAll('.edit-user-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                document.getElementById('edit-user-id').value = this.dataset.id;
                document.getElementById('edit-email').value = this.dataset.email;
                document.getElementById('edit-password').value = '';
                new bootstrap.Modal(document.getElementById('editUserModal')).show();
            });
        });

        // Setup 2FA
        document.querySelectorAll('.setup-2fa-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                currentUserId = this.dataset.id; // Hier wird die Variable gesetzt
                console.log('Setting currentUserId to:', currentUserId);

                // Reset modal
                document.getElementById('setup-step-1').style.display = 'block';
                document.getElementById('setup-step-2').style.display = 'none';
                document.getElementById('setup-step-3').style.display = 'none';

                // QR-Code Image zurücksetzen
                document.getElementById('qrcode-image').src = '';
                document.getElementById('manual-secret').textContent = '';

                new bootstrap.Modal(document.getElementById('setup2faModal')).show();
            });
        });

        // Generate 2FA QR Code
        document.getElementById('generate2faBtn').addEventListener('click', function () {
            console.log('Generate 2FA button clicked');
            console.log('Current User ID:', currentUserId);

            // Prüfe ob currentUserId gesetzt ist
            if (!currentUserId) {
                alert('Fehler: Keine Benutzer-ID gefunden. Bitte schließe das Modal und versuche es erneut.');
                return;
            }

            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generierung...';

            const requestData = {
                'user_id': currentUserId,
                'action': 'generate'
            };

            console.log('Request data:', requestData);

            fetch('actions/generate_2fa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams(requestData)
            })
                .then(response => {
                    console.log('Response received');
                    console.log('Status:', response.status);
                    console.log('Status text:', response.statusText);

                    // Erst den rohen Text holen
                    return response.text();
                })
                .then(text => {
                    console.log('Raw response text:');
                    console.log(text);
                    console.log('Text length:', text.length);

                    // Prüfe ob es HTML ist (Fehlerseite)
                    if (text.trim().startsWith('<')) {
                        console.error('Server returned HTML instead of JSON');

                        // Extrahiere Titel falls vorhanden
                        const titleMatch = text.match(/<title>(.*?)<\/title>/i);
                        const title = titleMatch ? titleMatch[1] : 'Unbekannter Fehler';

                        alert(`Server-Fehler: ${title}\n\nDer Server hat eine HTML-Seite statt JSON zurückgegeben. Prüfe die PHP-Datei auf Syntax-Fehler.`);
                        return;
                    }

                    // Versuche JSON zu parsen
                    let data;
                    try {
                        data = JSON.parse(text);
                        console.log('Parsed JSON:', data);
                    } catch (parseError) {
                        console.error('JSON Parse Error:', parseError);
                        console.error('Response was not valid JSON');

                        // Zeige die ersten 500 Zeichen der Antwort
                        const preview = text.substring(0, 500);
                        alert(`JSON-Parse-Fehler:\n\n${preview}${text.length > 500 ? '\n\n... (gekürzt)' : ''}`);
                        return;
                    }

                    if (data.success) {
                        console.log('Generation successful');

                        // QR-Code anzeigen
                        if (data.qrcode) {
                            console.log('Setting QR code image:', data.qrcode);
                            document.getElementById('qrcode-image').src = data.qrcode;

                            // Test ob QR-Code lädt
                            document.getElementById('qrcode-image').onload = function () {
                                console.log('QR code image loaded successfully');
                            };
                            document.getElementById('qrcode-image').onerror = function () {
                                console.error('QR code image failed to load');
                                // Versuche alternativen QR-Code Provider
                                if (data.debug && data.debug.qr_providers) {
                                    console.log('Trying alternative QR providers...');
                                    const providers = Object.values(data.debug.qr_providers);
                                    let providerIndex = 1; // Starte mit dem zweiten Provider

                                    const tryNextProvider = () => {
                                        if (providerIndex < providers.length) {
                                            console.log(`Trying provider ${providerIndex + 1}:`, providers[providerIndex]);
                                            this.src = providers[providerIndex];
                                            providerIndex++;
                                        } else {
                                            alert('QR-Code konnte nicht geladen werden. Verwende die manuelle Eingabe.');
                                        }
                                    };

                                    this.onerror = tryNextProvider;
                                    tryNextProvider();
                                }
                            };
                        }

                        // Manuelle Eingabe-Daten
                        if (data.manual_entry) {
                            document.getElementById('manual-issuer').textContent = data.manual_entry.issuer;
                            document.getElementById('manual-account').textContent = data.manual_entry.account;
                            document.getElementById('manual-secret').textContent = data.secret;
                        }

                        // Debug-Infos loggen
                        if (data.debug) {
                            console.log('Debug info:', data.debug);
                            console.log('Current test code:', data.debug.current_test_code);
                        }

                        // Zu Schritt 2 wechseln
                        document.getElementById('setup-step-1').style.display = 'none';
                        document.getElementById('setup-step-2').style.display = 'block';

                        // Focus auf Token-Input
                        setTimeout(() => {
                            document.getElementById('setup-token').focus();
                        }, 100);

                    } else {
                        console.error('Generation failed:', data.message);
                        alert('Fehler bei der 2FA-Generierung: ' + (data.message || 'Unbekannter Fehler'));

                        if (data.debug) {
                            console.log('Error debug info:', data.debug);
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    alert('Netzwerk-Fehler: ' + error.message);
                })
                .finally(() => {
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-qr-code"></i> QR-Code generieren';
                });
        });

        // Verify 2FA Setup
        document.getElementById('verify2faSetupForm').addEventListener('submit', function (e) {
            e.preventDefault();

            const token = document.getElementById('setup-token').value;
            const submitBtn = this.querySelector('button[type="submit"]');

            if (!currentUserId) {
                alert('Fehler: Keine Benutzer-ID gefunden.');
                return;
            }

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Überprüfung...';

            fetch('actions/verify_2fa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'token': token,
                    'user_id': currentUserId,
                    'action': 'verify'
                })
            })
                .then(response => response.text())
                .then(text => {
                    console.log('Verify response:', text);

                    let data;
                    try {
                        data = JSON.parse(text);
                    } catch (e) {
                        console.error('Verify JSON parse error:', e);
                        alert('Server-Fehler bei der Verifikation');
                        return;
                    }

                    if (data.success) {
                        // Backup-Codes anzeigen
                        const codesDisplay = document.getElementById('backup-codes-display');
                        codesDisplay.innerHTML = '';

                        if (data.backup_codes && data.backup_codes.length > 0) {
                            data.backup_codes.forEach(code => {
                                const div = document.createElement('div');
                                div.className = 'backup-code';
                                div.textContent = code;
                                codesDisplay.appendChild(div);
                            });

                            // Backup-Codes für Download speichern
                            window.backupCodes = data.backup_codes;
                        }

                        // Zu Schritt 3 wechseln
                        document.getElementById('setup-step-2').style.display = 'none';
                        document.getElementById('setup-step-3').style.display = 'block';

                        // Seite nach 3 Sekunden neu laden
                        setTimeout(() => {
                            location.reload();
                        }, 3000);
                    } else {
                        alert('Fehler: ' + data.message);
                        document.getElementById('setup-token').value = '';
                        document.getElementById('setup-token').focus();
                    }
                })
                .catch(error => {
                    console.error('Verify error:', error);
                    alert('Netzwerk-Fehler bei der Verifikation: ' + error.message);
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-circle"></i> 2FA aktivieren';
                });
        });

        // Auto-format 2FA token input
        document.getElementById('setup-token').addEventListener('input', function () {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Download Backup Codes
        document.getElementById('downloadBackupCodes').addEventListener('click', function () {
            if (window.backupCodes) {
                const content = `2FA Backup-Codes für DVD-Verwaltung
Generiert am: ${new Date().toLocaleString()}

WICHTIG: Bewahren Sie diese Codes sicher auf!
Jeder Code kann nur einmal verwendet werden.

${window.backupCodes.join('\n')}

Diese Codes können verwendet werden, wenn Sie keinen Zugang zu Ihrer Authenticator-App haben.`;

                const blob = new Blob([content], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `2FA-Backup-Codes-${new Date().toISOString().split('T')[0]}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            }
        });

        // Test-Funktionen für Debugging (global verfügbar)
        window.debug2FAStatus = function () {
            console.log('Current 2FA Debug Status:');
            console.log('- Current User ID:', currentUserId);
            console.log('- Setup Step 1 visible:', document.getElementById('setup-step-1').style.display !== 'none');
            console.log('- Setup Step 2 visible:', document.getElementById('setup-step-2').style.display !== 'none');
            console.log('- QR Image src:', document.getElementById('qrcode-image').src);
            console.log('- Manual secret:', document.getElementById('manual-secret').textContent);
        };

        window.test2FAGeneration = function () {
            console.log('Testing 2FA generation with current user ID:', currentUserId);

            if (!currentUserId) {
                console.log('No current user ID set, using test ID 1');
                currentUserId = '1';
            }

            fetch('actions/generate_2fa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'user_id': currentUserId,
                    'action': 'generate'
                })
            })
                .then(response => response.text())
                .then(text => {
                    console.log('Test response:', text);
                    try {
                        const data = JSON.parse(text);
                        console.log('Test parsed:', data);
                    } catch (e) {
                        console.error('Test parse error:', e);
                    }
                })
                .catch(error => {
                    console.error('Test error:', error);
                });
        };
    });
</script>