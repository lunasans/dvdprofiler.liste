<?php
/**
 * DVD Profiler Liste - Admin Sidebar (Clean)
 * 
 * @version 1.4.9
 */

require_once dirname(__DIR__) . '/includes/version.php';

$currentVersion = DVDPROFILER_VERSION;
$latestVersion = $latestRelease['version'] ?? 'Unbekannt';
$dvdProfilerStats = getDVDProfilerStatistics();
?>

<aside class="sidebar">
    <!-- Header -->
    <div class="sidebar-header">
        <h4>
            <i class="bi bi-film"></i>
            Admin Center
        </h4>
        <span class="version-badge">
            v<?= $currentVersion ?>
            <?php if ($isUpdateAvailable): ?>
                <i class="bi bi-exclamation-circle text-warning ms-1" title="Update verfügbar: v<?= $latestVersion ?>"></i>
            <?php endif; ?>
        </span>
    </div>
    
    <!-- Navigation -->
    <nav class="nav flex-column">
        <a href="?page=dashboard" class="nav-link <?= ($_GET['page'] ?? 'dashboard') === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i>
            Dashboard
        </a>
        
        <a href="?page=films" class="nav-link <?= ($_GET['page'] ?? '') === 'films' ? 'active' : '' ?>">
            <i class="bi bi-film"></i>
            Filme
        </a>
        
        <a href="#" class="nav-link" data-bs-toggle="collapse" data-bs-target="#importSubmenu">
        <i class="bi bi-upload"></i>
        Import
        <i class="bi bi-chevron-down ms-auto"></i>
        </a>
            <div class="collapse" id="importSubmenu">
        <a href="?page=import" class="nav-link ps-4 <?= ($_GET['page'] ?? '') === 'import' ? 'active' : '' ?>">
            <i class="bi bi-file-earmark-code"></i>
            XML Import
        </a>
        <a href="?page=tmdb-import" class="nav-link ps-4 <?= ($_GET['page'] ?? '') === 'tmdb-import' ? 'active' : '' ?>">
            <i class="bi bi-cloud-download"></i>
            TMDb Import
        </a>   
    </div>
        
        <a href="?page=users" class="nav-link <?= ($_GET['page'] ?? '') === 'users' ? 'active' : '' ?>">
            <i class="bi bi-people"></i>
            Benutzer
        </a>
        
        <a href="?page=settings" class="nav-link <?= ($_GET['page'] ?? '') === 'settings' ? 'active' : '' ?>">
            <i class="bi bi-gear"></i>
            Einstellungen
            <?php if ($isUpdateAvailable): ?>
                <span class="badge bg-warning text-dark ms-auto">!</span>
            <?php endif; ?>
        </a>

        <a href="?page=statistics" class="nav-link <?= ($_GET['page'] ?? '') === 'settings' ? 'active' : '' ?>">
            <i class="bi bi-graph-up"></i> Statistiken
        </a>

       
        <a href="?page=impressum" class="nav-link <?= ($_GET['page'] ?? '') === 'impressum' ? 'active' : '' ?>">
            <i class="bi bi-info-circle"></i>
            Impressum
         </a>
        
        <hr class="sidebar-divider">
        
        <!-- Stats -->
        <div class="sidebar-stats">
            <div class="stat-item">
                <i class="bi bi-database"></i>
                <span><?= number_format($dvdProfilerStats['total_films'] ?? 0) ?> Filme</span>
            </div>
            <div class="stat-item">
                <i class="bi bi-collection"></i>
                <span><?= number_format($dvdProfilerStats['total_boxsets'] ?? 0) ?> BoxSets</span>
            </div>
            <div class="stat-item">
                <i class="bi bi-eye"></i>
                <span><?= number_format($dvdProfilerStats['total_visits'] ?? 0) ?> Besucher</span>
            </div>
        </div>
        
        <!-- Spacer -->
        <div style="flex-grow: 1;"></div>
        
        <!-- Actions -->
        <div class="sidebar-actions">
            <a href="../" class="btn btn-outline-light btn-sm" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i>
                Website
            </a>
            <a href="logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i>
                Logout
            </a>
        </div>
    </nav>
</aside>

<style>
/* Sidebar Clean Styles */
.sidebar {
    width: 260px;
    background: linear-gradient(135deg, var(--clr-secondary) 0%, var(--clr-card) 100%);
    color: var(--clr-text);
    display: flex;
    flex-direction: column;
    box-shadow: 2px 0 10px rgba(0, 0, 0, 0.3);
}

.sidebar-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--clr-border);
    background: rgba(255, 255, 255, 0.05);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.sidebar-header h4 {
    font-size: 1.2rem;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.version-badge {
    background: var(--clr-accent);
    color: white;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.75rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    align-self: flex-start;
}

.nav {
    padding: 1rem 0;
    flex: 1;
    display: flex;
    flex-direction: column;
}

.nav-link {
    padding: 0.75rem 1.5rem;
    color: var(--clr-text-muted);
    text-decoration: none;
    transition: all 0.2s ease;
    border-left: 3px solid transparent;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-weight: 500;
}

.nav-link:hover {
    background: rgba(255, 255, 255, 0.1);
    color: var(--clr-text);
    border-left-color: var(--clr-accent);
}

.nav-link.active {
    background: rgba(52, 152, 219, 0.2);
    color: var(--clr-accent);
    border-left-color: var(--clr-accent);
}

.nav-link i {
    width: 18px;
    text-align: center;
}

.nav-link .badge {
    font-size: 0.7rem;
    padding: 0.15rem 0.35rem;
}

.sidebar-divider {
    border: 0;
    border-top: 1px solid var(--clr-border);
    margin: 0.5rem 1.5rem;
}

.sidebar-stats {
    padding: 0.5rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.8rem;
    color: var(--clr-text-muted);
}

.stat-item i {
    width: 16px;
    text-align: center;
    opacity: 0.7;
}

.sidebar-actions {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--clr-border);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.sidebar-actions .btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    padding: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .sidebar {
        width: 100%;
        position: static;
    }
}
</style>