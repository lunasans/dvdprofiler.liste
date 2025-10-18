/**
 * Admin Users Page - Enhanced JavaScript with Robust Error Handling
 * Production-ready version with XSS protection and comprehensive error handling
 */

// Global error handler
window.addEventListener('error', function(event) {
    console.error('Global JavaScript error:', event.error);
    console.error('Stack:', event.error?.stack);
});

window.addEventListener('unhandledrejection', function(event) {
    console.error('Unhandled promise rejection:', event.reason);
});

// Safe utility functions
const Utils = {
    // XSS-safe text escaping
    escapeHtml: function(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    },

    // Safe JSON parsing with error handling
    safeJsonParse: function(text, context = '') {
        try {
            return JSON.parse(text);
        } catch (error) {
            console.error(`JSON Parse Error ${context}:`, error);
            console.error('Raw text:', text.substring(0, 500));
            throw new Error(`Invalid JSON response ${context}`);
        }
    },

    // Safe notification system
    showNotification: function(message, type = 'info', duration = 5000) {
        // Remove existing notifications
        const existing = document.querySelectorAll('.custom-notification');
        existing.forEach(el => el.remove());

        const notification = document.createElement('div');
        notification.className = `custom-notification alert alert-${type} alert-dismissible fade show`;
        notification.innerHTML = `
            ${this.escapeHtml(message)}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        if (duration > 0) {
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, duration);
        }
    },

    // Safe fetch with timeout and error handling
    safeFetch: async function(url, options = {}, timeout = 30000) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeout);
        
        try {
            const response = await fetch(url, {
                ...options,
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            return response;
            
        } catch (error) {
            clearTimeout(timeoutId);
            if (error.name === 'AbortError') {
                throw new Error('Request timeout');
            }
            throw error;
        }
    }
};

// Main application
document.addEventListener('DOMContentLoaded', function() {
    let currentUserId = null;

    try {
        initializeUsers();
    } catch (error) {
        console.error('Failed to initialize users page:', error);
        Utils.showNotification('Fehler beim Laden der Benutzer-Verwaltung', 'danger');
    }

    function initializeUsers() {
        // Setup 2FA Modal handlers
        const setup2FAButtons = document.querySelectorAll('.setup-2fa-btn');
        setup2FAButtons.forEach(button => {
            button.addEventListener('click', handleSetup2FA);
        });

        // Generate 2FA QR Code
        const generate2FABtn = document.getElementById('generate2faBtn');
        if (generate2FABtn) {
            generate2FABtn.addEventListener('click', handleGenerate2FA);
        }

        // Verify 2FA Setup
        const verify2FAForm = document.getElementById('verify2faSetupForm');
        if (verify2FAForm) {
            verify2FAForm.addEventListener('submit', handleVerify2FA);
        }

        // Download backup codes
        const downloadBtn = document.getElementById('downloadBackupCodes');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', handleDownloadBackupCodes);
        }
    }

    function handleSetup2FA(event) {
        try {
            currentUserId = parseInt(this.dataset.userId);
            
            if (!currentUserId || currentUserId <= 0) {
                throw new Error('Invalid user ID');
            }

            console.log('Setting up 2FA for user:', currentUserId);

            // Reset modal state
            resetModal();
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('setup2faModal'));
            modal.show();
            
        } catch (error) {
            console.error('Setup 2FA error:', error);
            Utils.showNotification('Fehler beim Öffnen der 2FA-Einrichtung', 'danger');
        }
    }

    async function handleGenerate2FA(event) {
        event.preventDefault();
        
        if (!currentUserId) {
            Utils.showNotification('Keine Benutzer-ID gefunden. Bitte Modal schließen und erneut versuchen.', 'warning');
            return;
        }

        const button = this;
        const originalContent = button.innerHTML;
        
        try {
            // Update button state
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generierung...';

            const response = await Utils.safeFetch('actions/generate_2fa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'user_id': currentUserId,
                    'action': 'generate'
                })
            });

            const text = await response.text();
            console.log('Generate 2FA response received');

            // Check for HTML error pages
            if (text.trim().startsWith('<')) {
                const titleMatch = text.match(/<title>(.*?)<\/title>/i);
                const title = titleMatch ? Utils.escapeHtml(titleMatch[1]) : 'Server Error';
                throw new Error(`Server returned HTML error page: ${title}`);
            }

            const data = Utils.safeJsonParse(text, 'from generate_2fa.php');

            if (data.success) {
                await handleSuccessfulGeneration(data);
            } else {
                throw new Error(data.message || 'Unknown error during 2FA generation');
            }

        } catch (error) {
            console.error('Generate 2FA error:', error);
            Utils.showNotification(`2FA-Generierung fehlgeschlagen: ${error.message}`, 'danger');
        } finally {
            // Restore button state
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    }

    async function handleSuccessfulGeneration(data) {
        try {
            // Set QR code with fallback handling
            await setupQRCode(data);
            
            // Setup manual entry
            setupManualEntry(data);
            
            // Log debug info
            if (data.debug) {
                console.log('2FA Debug info:', data.debug);
            }
            
            // Switch to step 2
            showStep(2);
            
            // Focus token input
            setTimeout(() => {
                const tokenInput = document.getElementById('setup-token');
                if (tokenInput) {
                    tokenInput.focus();
                }
            }, 100);
            
        } catch (error) {
            console.error('Error setting up 2FA UI:', error);
            Utils.showNotification('Fehler beim Anzeigen der 2FA-Daten', 'danger');
        }
    }

    async function setupQRCode(data) {
        const qrImage = document.getElementById('qrcode-image');
        if (!qrImage || !data.qrcode) {
            throw new Error('QR code image element not found or no QR code data');
        }

        return new Promise((resolve, reject) => {
            let providerIndex = 0;
            const providers = data.debug?.qr_providers ? Object.values(data.debug.qr_providers) : [data.qrcode];
            
            function tryNextProvider() {
                if (providerIndex >= providers.length) {
                    Utils.showNotification('QR-Code konnte nicht geladen werden. Verwende die manuelle Eingabe.', 'warning');
                    resolve(); // Don't reject, manual entry is still available
                    return;
                }
                
                const providerUrl = providers[providerIndex];
                console.log(`Trying QR provider ${providerIndex + 1}:`, providerUrl);
                
                qrImage.onload = function() {
                    console.log('QR code loaded successfully');
                    resolve();
                };
                
                qrImage.onerror = function() {
                    console.warn(`QR provider ${providerIndex + 1} failed`);
                    providerIndex++;
                    tryNextProvider();
                };
                
                qrImage.src = providerUrl;
            }
            
            tryNextProvider();
        });
    }

    function setupManualEntry(data) {
        const elements = {
            issuer: document.getElementById('manual-issuer'),
            account: document.getElementById('manual-account'),
            secret: document.getElementById('manual-secret')
        };

        if (data.manual_entry && elements.issuer && elements.account) {
            elements.issuer.textContent = data.manual_entry.issuer || '';
            elements.account.textContent = data.manual_entry.account || '';
        }

        if (elements.secret && data.secret) {
            elements.secret.textContent = data.secret;
        }
    }

    async function handleVerify2FA(event) {
        event.preventDefault();
        
        const form = event.target;
        const tokenInput = document.getElementById('setup-token');
        const submitBtn = form.querySelector('button[type="submit"]');
        
        if (!currentUserId) {
            Utils.showNotification('Keine Benutzer-ID gefunden', 'danger');
            return;
        }
        
        if (!tokenInput || !tokenInput.value.trim()) {
            Utils.showNotification('Bitte geben Sie einen Token ein', 'warning');
            tokenInput?.focus();
            return;
        }

        const token = tokenInput.value.trim();
        const originalContent = submitBtn.innerHTML;
        
        try {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Überprüfung...';

            const response = await Utils.safeFetch('actions/verify_2fa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    'token': token,
                    'user_id': currentUserId,
                    'action': 'verify'
                })
            });

            const text = await response.text();
            const data = Utils.safeJsonParse(text, 'from verify_2fa.php');

            if (data.success) {
                await handleSuccessfulVerification(data);
            } else {
                throw new Error(data.message || 'Token verification failed');
            }

        } catch (error) {
            console.error('Verify 2FA error:', error);
            Utils.showNotification(`Verifikation fehlgeschlagen: ${error.message}`, 'danger');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalContent;
        }
    }

    async function handleSuccessfulVerification(data) {
        try {
            // Display backup codes
            if (data.backup_codes && data.backup_codes.length > 0) {
                displayBackupCodes(data.backup_codes);
                window.backupCodes = data.backup_codes; // For download
            }
            
            // Switch to step 3
            showStep(3);
            
            Utils.showNotification('2FA erfolgreich eingerichtet!', 'success');
            
            // Auto-reload after delay
            setTimeout(() => {
                location.reload();
            }, 3000);
            
        } catch (error) {
            console.error('Error handling verification success:', error);
            Utils.showNotification('2FA wurde eingerichtet, aber es gab einen Anzeigefehler', 'warning');
        }
    }

    function displayBackupCodes(codes) {
        const codesDisplay = document.getElementById('backup-codes-display');
        if (!codesDisplay) return;
        
        codesDisplay.innerHTML = '';
        
        codes.forEach(code => {
            const div = document.createElement('div');
            div.className = 'backup-code';
            div.textContent = code;
            codesDisplay.appendChild(div);
        });
    }

    function handleDownloadBackupCodes() {
        try {
            if (!window.backupCodes || !Array.isArray(window.backupCodes)) {
                throw new Error('No backup codes available');
            }
            
            const content = 'DVD Profiler Liste - 2FA Backup Codes\n' +
                           'Generated: ' + new Date().toISOString() + '\n\n' +
                           window.backupCodes.join('\n') + '\n\n' +
                           'Keep these codes secure! Each can only be used once.';
            
            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            
            const a = document.createElement('a');
            a.href = url;
            a.download = 'dvd-profiler-backup-codes.txt';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            Utils.showNotification('Backup-Codes heruntergeladen', 'success');
            
        } catch (error) {
            console.error('Download backup codes error:', error);
            Utils.showNotification('Fehler beim Herunterladen der Backup-Codes', 'danger');
        }
    }

    function resetModal() {
        try {
            // Reset steps
            showStep(1);
            
            // Reset form elements
            const elements = {
                qrImage: document.getElementById('qrcode-image'),
                tokenInput: document.getElementById('setup-token'),
                secretDisplay: document.getElementById('manual-secret'),
                codesDisplay: document.getElementById('backup-codes-display')
            };
            
            if (elements.qrImage) elements.qrImage.src = '';
            if (elements.tokenInput) elements.tokenInput.value = '';
            if (elements.secretDisplay) elements.secretDisplay.textContent = '';
            if (elements.codesDisplay) elements.codesDisplay.innerHTML = '';
            
            // Clear backup codes
            delete window.backupCodes;
            
        } catch (error) {
            console.error('Error resetting modal:', error);
        }
    }

    function showStep(stepNumber) {
        try {
            // Hide all steps
            for (let i = 1; i <= 3; i++) {
                const step = document.getElementById(`setup-step-${i}`);
                if (step) {
                    step.style.display = 'none';
                }
            }
            
            // Show target step
            const targetStep = document.getElementById(`setup-step-${stepNumber}`);
            if (targetStep) {
                targetStep.style.display = 'block';
            }
            
        } catch (error) {
            console.error('Error showing step:', error);
        }
    }
});

// CSS for notifications
const notificationStyles = `
<style>
.custom-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    min-width: 300px;
    max-width: 500px;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    border-radius: 8px;
}

.backup-code {
    background: var(--bs-light);
    border: 1px solid var(--bs-border-color);
    border-radius: 4px;
    padding: 8px 12px;
    margin: 4px 0;
    font-family: monospace;
    font-size: 1.1em;
    text-align: center;
    user-select: all;
    cursor: pointer;
}

.backup-code:hover {
    background: var(--bs-primary);
    color: white;
}

@media (max-width: 768px) {
    .custom-notification {
        left: 10px;
        right: 10px;
        min-width: auto;
    }
}
</style>`;

document.head.insertAdjacentHTML('beforeend', notificationStyles);