<?php


$updateStatus = getDVDProfilerUpdateStatus();
$dvdProfilerStats = getDVDProfilerStatistics();

// Build-Info für Tooltip
$tooltipText = "Aktuelle Version: {$updateStatus['version']} \"{$updateStatus['codename']}\"\nBuild: {$updateStatus['build_date']}\nPHP: " . PHP_VERSION;

if (!empty($updateStatus['has_update'])) {
    $tooltipText .= "\n\nNeue Version verfügbar: {$updateStatus['latest_version']}";
}

// Ensure commonly used variables are set to avoid undefined variable notices.
$currentVersion = $currentVersion ?? $updateStatus['version'];
$isUpdateAvailable = $isUpdateAvailable ?? !empty($updateStatus['has_update']);
$latestVersion = $latestVersion ?? ($updateStatus['latest_version'] ?? null);
$buildInfo = $buildInfo ?? [
    'version' => $updateStatus['version'] ?? '',
    'codename' => $updateStatus['codename'] ?? '',
    'build_date' => $updateStatus['build_date'] ?? '',
    'branch' => $updateStatus['branch'] ?? '',
    'commit' => $updateStatus['commit'] ?? (defined('DVDPROFILER_COMMIT') ? DVDPROFILER_COMMIT : ''),
    'php_version' => PHP_VERSION,
];
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <h4>
            <i class="bi bi-film"></i>
            Admin Center
        </h4>
        <div class="version-info-sidebar" title="<?= htmlspecialchars($tooltipText) ?>">
            <span class="version-badge-small">
                v<?= $currentVersion ?>
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
                <span class="badge rounded-pill bg-warning ms-2" title="Update auf v<?= htmlspecialchars($latestVersion) ?> verfügbar">
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
                    <span><?= number_format($dvdProfilerStats['total_films'] ?? 0) ?> Filme</span>
                </div>
                <div class="stat-small">
                    <i class="bi bi-eye"></i>
                    <span><?= number_format($dvdProfilerStats['total_visits'] ?? 0) ?> Besucher</span>
                </div>
                <?php if (isDVDProfilerFeatureEnabled('boxset_support')): ?>
                <div class="stat-small">
                    <i class="bi bi-collection-play"></i>
                    <span><?= number_format($dvdProfilerStats['total_boxsets'] ?? 0) ?> BoxSets</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Feature Status -->
        <?php if (isDVDProfilerFeatureEnabled('system_updates')): ?>
        <div class="nav-item-info">
            <small class="text-muted">
                <i class="bi bi-puzzle"></i>
                Features
            </small>
            <div class="feature-summary">
                <?php
                $enabledFeatures = array_filter(DVDPROFILER_FEATURES);
                $totalFeatures = count(DVDPROFILER_FEATURES);
                $enabledCount = count($enabledFeatures);
                $percentage = round(($enabledCount / $totalFeatures) * 100);
                ?>
                <div class="feature-progress">
                    <div class="progress-bar" style="width: <?= $percentage ?>%"></div>
                </div>
                <small><?= $enabledCount ?>/<?= $totalFeatures ?> aktiv (<?= $percentage ?>%)</small>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- GitHub Integration -->
        <div class="nav-item-info">
            <small class="text-muted">
                <i class="bi bi-github"></i>
                Repository
            </small>
            <div class="github-info">
                <a href="<?= DVDPROFILER_GITHUB_URL ?>" target="_blank" rel="noopener" class="github-link">
                    <i class="bi bi-box-arrow-up-right"></i>
                    <?= DVDPROFILER_REPOSITORY ?>
                </a>
                <small class="commit-info">
                    Commit: <?= DVDPROFILER_COMMIT ?>
                </small>
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
/* Erweiterte Sidebar Styles */
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

.sidebar-divider {
    border-color: var(--clr-border);
    margin: 1rem 0;
}

.nav-item-info {
    padding: 0.75rem 1.5rem;
    margin-bottom: 0.5rem;
}

.nav-item-info small {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    opacity: 0.8;
}

.system-stats {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-small {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.75rem;
    color: var(--clr-text-muted);
}

.stat-small i {
    width: 14px;
    text-align: center;
    opacity: 0.7;
}

.feature-progress {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 2px;
    height: 4px;
    margin-bottom: 0.25rem;
    overflow: hidden;
}

.progress-bar {
    background: var(--clr-success);
    height: 100%;
    transition: width 0.3s ease;
}

.github-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.github-link {
    color: var(--clr-text-muted);
    text-decoration: none;
    font-size: 0.75rem;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    transition: color 0.3s ease;
}

.github-link:hover {
    color: var(--clr-accent);
}

.commit-info {
    font-size: 0.65rem;
    opacity: 0.6;
    font-family: monospace;
}

.user-actions {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.user-actions .btn {
    justify-content: center;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    padding: 0.5rem;
}

/* Animations */
@keyframes pulse {
    0%, 100% {
        opacity: 1;
        transform: scale(1);
    }
    50% {
        opacity: 0.5;
        transform: scale(1.2);
    }
}

/* Responsive Adjustments */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        position: relative;
    }
    
    .version-info-sidebar {
        position: static;
        margin-top: 0.5rem;
        text-align: center;
    }
    
    .system-stats {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    
    .user-actions {
        flex-direction: row;
    }
}

/* Feature-specific styling */
.nav-link .badge {
    font-size: 0.6rem;
    padding: 0.2rem 0.4rem;
}

.bg-warning {
    background: var(--clr-warning) !important;
    color: #000 !important;
}

/* Enhanced tooltips */
.version-info-sidebar:hover .version-badge-small {
    transform: scale(1.05);
    transition: transform 0.2s ease;
}
</style>

<script>
// Enhanced sidebar functionality
document.addEventListener('DOMContentLoaded', function() {
    // Version badge click handler
    const versionBadge = document.querySelector('.version-badge-small');
    if (versionBadge) {
        versionBadge.addEventListener('click', function() {
            const buildInfo = <?= json_encode($buildInfo) ?>;
            const info = `DVD Profiler Liste v${buildInfo.version} "${buildInfo.codename}"
Build: ${buildInfo.build_date}
Branch: ${buildInfo.branch}
Commit: ${buildInfo.commit}
PHP: ${buildInfo.php_version}`;
            
            alert(info);
        });
    }
    
    // Update notification enhancement
    <?php if ($isUpdateAvailable): ?>
    const updateBadge = document.querySelector('.badge.bg-warning');
    if (updateBadge) {
        updateBadge.addEventListener('click', function() {
            if (confirm('Möchten Sie zur Update-Seite wechseln?')) {
                window.location.href = '?page=settings&action=update';
            }
        });
    }
    <?php endif; ?>
    
    // Feature progress animation
    const progressBar = document.querySelector('.progress-bar');
    if (progressBar) {
        setTimeout(() => {
            progressBar.style.width = '<?= $percentage ?>%';
        }, 500);
    }
});
</script>