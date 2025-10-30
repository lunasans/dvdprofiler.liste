<?php
// admin/sidebar.php - Saubere Sidebar ohne Syntaxfehler
$currentVersion = '1.4.7';
$isUpdateAvailable = false;
$latestVersion = 'Unbekannt';
$tooltipText = "Version: {$currentVersion}";

try {
    // Versuche version.php zu laden
    if (file_exists(dirname(__DIR__) . '/includes/version.php')) {
        require_once dirname(__DIR__) . '/includes/version.php';
        $currentVersion = DVDPROFILER_VERSION;
        
        // GitHub Update-Check
        if (function_exists('isGitHubUpdateAvailable')) {
            $isUpdateAvailable = isGitHubUpdateAvailable();
        }
        
        if (function_exists('getLatestGitHubRelease')) {
            $latestRelease = getLatestGitHubRelease();
            $latestVersion = $latestRelease['version'] ?? 'Unbekannt';
        }
        
        $tooltipText = "Version: {$currentVersion}";
        if ($isUpdateAvailable) {
            $tooltipText .= "\nUpdate verfügbar: {$latestVersion}";
        }
    }
} catch (Exception $e) {
    error_log("Sidebar error: " . $e->getMessage());
}

// DVD-Statistiken
$totalFilms = 0;
try {
    if (isset($pdo)) {
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM dvds");
        $result = $stmt->fetch();
        $totalFilms = $result['total'] ?? 0;
    }
} catch (Exception $e) {
    // Ignoriere DB-Fehler
}
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h4>
            <i class="bi bi-film"></i>
            Admin Center
        </h4>
        <div class="version-info-sidebar" title="<?= htmlspecialchars($tooltipText) ?>">
            <span class="version-badge-small">
                v<?= htmlspecialchars($currentVersion) ?>
                <?php if ($isUpdateAvailable): ?>
                    <span class="update-dot" title="Update verfügbar"></span>
                <?php endif; ?>
            </span>
        </div>
    </div>
    
    <nav class="nav flex-column">
        <a href="?page=dashboard" class="nav-link <?= ($_GET['page'] ?? 'dashboard') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            Dashboard
        </a>
        
        <a href="?page=import" class="nav-link <?= ($_GET['page'] ?? '') === 'import' ? 'active' : '' ?>">
            <i class="bi bi-upload"></i>
            Film Import
        </a>
        
        <a href="?page=users" class="nav-link <?= ($_GET['page'] ?? '') === 'users' ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            Benutzer
        </a>
        
        <a href="?page=settings" class="nav-link <?= ($_GET['page'] ?? '') === 'settings' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i>
            Einstellungen
            <?php if ($isUpdateAvailable): ?>
                <span class="badge rounded-pill bg-warning ms-2" title="Update verfügbar">
                    Update!
                </span>
            <?php endif; ?>
        </a>
        
        <!-- Divider -->
        <hr class="sidebar-divider">
        
        <!-- System Info -->
        <div class="nav-item-info">
            <small class="text-muted">
                <i class="bi bi-info-circle"></i>
                System-Status
            </small>
            <div class="system-stats">
                <div class="stat-small">
                    <i class="bi bi-database"></i>
                    <span><?= number_format($totalFilms) ?> Filme</span>
                </div>
                <div class="stat-small">
                    <i class="bi bi-memory"></i>
                    <span><?= round(memory_get_usage(true) / 1024 / 1024, 1) ?>MB</span>
                </div>
            </div>
        </div>
        
        <!-- Spacer -->
        <div style="flex-grow: 1;"></div>
        
        <!-- User Actions -->
        <div class="nav-item-info border-top pt-3">
            <div class="user-actions">
                <a href="../" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-house"></i>
                    Zur Website
                </a>
                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </div>
        </div>
    </nav>
</aside>

<style>
/* Sidebar Styles */
.sidebar {
    --clr-bg: #1a1a2e;
    --clr-accent: #3498db;
    --clr-text: #ffffff;
    --clr-text-muted: #bdc3c7;
    --clr-border: #34495e;
    --clr-warning: #f39c12;
}

.sidebar-header {
    position: relative;
    padding: 2rem 1.5rem 1rem;
    border-bottom: 1px solid var(--clr-border);
    background: rgba(255, 255, 255, 0.05);
}

.version-info-sidebar {
    position: absolute;
    top: 0.5rem;
    right: 1rem;
    cursor: help;
}

.version-badge-small {
    background: var(--clr-accent);
    color: white;
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 0.7rem;
    font-weight: 600;
    position: relative;
    display: inline-block;
}

.update-dot {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 8px;
    height: 8px;
    background: var(--clr-warning);
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

.sidebar-divider {
    border-color: var(--clr-border);
    margin: 1rem 0;
}

.nav-item-info {
    padding: 0.75rem 1.5rem;
    margin-bottom: 0.5rem;
}

.system-stats {
    margin-top: 0.5rem;
}

.stat-small {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 0.25rem;
    font-size: 0.8rem;
    color: var(--clr-text-muted);
}

.user-actions {
    display: flex;
    gap: 0.5rem;
    flex-direction: column;
}

.user-actions .btn {
    font-size: 0.8rem;
    padding: 0.375rem 0.75rem;
}
</style>