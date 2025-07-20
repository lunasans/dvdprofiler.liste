<?php
declare(strict_types=1);

// Bootstrap (startet bereits die Session)
require_once __DIR__ . '/../includes/bootstrap.php';

// Zugriffsschutz
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Erlaubte Seiten
$allowedPages = ['dashboard', 'users', 'settings', 'import', 'update'];
$page = $_GET['page'] ?? 'dashboard';
$siteTitle = getSetting('site_title', 'Meine DVD-Verwaltung');

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard'; // Fallback
}

// Version für Update-Badge
$currentVersion = getSetting('version', '1.0.0');
$isUpdateAvailable = false; // Wird in sidebar.php gesetzt

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle) ?> - Admin Center</title>
    
    <!-- Bootstrap CSS (für Grid & Components) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    
    <!-- Custom Admin CSS (überschreibt Bootstrap) -->
    <link href="css/admin.css" rel="stylesheet">
    
    <style>
        /* Smooth loading animation */
        .page-loader {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--clr-bg);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            transition: opacity 0.3s ease;
        }
        
        .page-loader.hidden {
            opacity: 0;
            pointer-events: none;
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="page-loader" id="pageLoader">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Laden...</span>
        </div>
    </div>

    <div class="admin-layout">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <h4>
                    <i class="bi bi-film"></i>
                    Admin Center
                </h4>
            </div>
            
            <nav class="nav flex-column">
                <a href="?page=dashboard" class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>">
                    <i class="bi bi-speedometer2"></i>
                    Dashboard
                </a>
                
                <a href="?page=import" class="nav-link <?= $page === 'import' ? 'active' : '' ?>">
                    <i class="bi bi-upload"></i>
                    Film Import
                </a>
                
                <a href="?page=users" class="nav-link <?= $page === 'users' ? 'active' : '' ?>">
                    <i class="bi bi-people"></i>
                    Benutzer
                </a>
                
                <a href="?page=settings" class="nav-link <?= $page === 'settings' ? 'active' : '' ?>">
                    <i class="bi bi-gear"></i>
                    Einstellungen
                    <?php if ($isUpdateAvailable): ?>
                        <span class="badge bg-warning ms-auto">Update!</span>
                    <?php endif; ?>
                </a>
                
                <hr style="border-color: var(--clr-border); margin: 1rem 0;">
                
                <a href="../" class="nav-link">
                    <i class="bi bi-house"></i>
                    Zur Website
                </a>
                
                <a href="logout.php" class="nav-link text-danger">
                    <i class="bi bi-box-arrow-right"></i>
                    Logout
                </a>
            </nav>
        </aside>

        <!-- Hauptinhalt -->
        <main class="admin-content">
            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="?page=dashboard" style="color: var(--clr-accent);">
                            <i class="bi bi-house"></i> Admin
                        </a>
                    </li>
                    <li class="breadcrumb-item active" style="color: var(--clr-text);">
                        <?= ucfirst($page) ?>
                    </li>
                </ol>
            </nav>

            <!-- Page Content -->
            <div class="page-content">
                <?php
                if (in_array($page, $allowedPages) && file_exists(__DIR__ . "/pages/{$page}.php")) {
                    include __DIR__ . "/pages/{$page}.php";
                } else {
                    echo '<div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            <strong>Fehler:</strong> Seite nicht gefunden.
                          </div>';
                }
                ?>
            </div>
        </main>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom Admin JS -->
    <script>
        // Page Loader
        document.addEventListener('DOMContentLoaded', function() {
            const loader = document.getElementById('pageLoader');
            setTimeout(() => {
                loader.classList.add('hidden');
                setTimeout(() => {
                    loader.style.display = 'none';
                }, 300);
            }, 500);
        });

        // Active navigation highlighting
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
            const navLinks = document.querySelectorAll('.nav-link');
            
            navLinks.forEach(link => {
                const href = link.getAttribute('href');
                if (href && href.includes(`page=${currentPage}`)) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        });

        // Enhanced form validation feedback
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        const originalText = submitBtn.innerHTML;
                        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Verarbeitung...';
                        
                        // Re-enable after timeout (fallback)
                        setTimeout(() => {
                            submitBtn.disabled = false;
                            submitBtn.innerHTML = originalText;
                        }, 5000);
                    }
                });
            });
        });

        // Tooltip initialization
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Confirmation dialogs
        document.addEventListener('click', function(e) {
            if (e.target.matches('[data-confirm]')) {
                const message = e.target.getAttribute('data-confirm');
                if (!confirm(message)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>

    <script src="js/admin.js"></script>
</body>
</html>