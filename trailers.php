<?php
/**
 * trailers.php - Neueste Trailer Seite (Partial)
 * Zeigt die neuesten hinzugefügten Trailer
 * 
 * @package    dvdprofiler.liste
 * @version    1.4.8
 */

// Datenbankverbindung sicherstellen
global $pdo;
if (!isset($pdo) || !($pdo instanceof PDO)) {
    // Fallback: Bootstrap laden falls nicht vorhanden
    if (file_exists(__DIR__ . '/includes/bootstrap.php')) {
        require_once __DIR__ . '/includes/bootstrap.php';
    }
}

// Helper: YouTube URL zu Embed URL
function getYouTubeEmbedUrl($url) {
    if (empty($url)) return '';
    
    // Extract Video ID
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $matches);
    $videoId = $matches[1] ?? '';
    
    if (!$videoId) return '';
    
    return "https://www.youtube.com/embed/{$videoId}";
}

// Helper: YouTube Thumbnail URL
function getYouTubeThumbnail($url) {
    if (empty($url)) return 'cover/placeholder.png';
    
    preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]+)/', $url, $matches);
    $videoId = $matches[1] ?? '';
    
    if (!$videoId) return 'cover/placeholder.png';
    
    // YouTube Thumbnail (maxresdefault für beste Qualität)
    return "https://img.youtube.com/vi/{$videoId}/maxresdefault.jpg";
}

// Anzahl Trailer pro Seite
$trailersPerPage = 12;
$page = max(1, (int)($_GET['p'] ?? 1));
$offset = ($page - 1) * $trailersPerPage;

try {
    // Gesamtanzahl Filme mit Trailer
    $countStmt = $pdo->query("SELECT COUNT(*) FROM dvds WHERE trailer_url IS NOT NULL AND trailer_url != ''");
    $totalTrailers = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalTrailers / $trailersPerPage);
    
    // Neueste Filme mit Trailer laden (sortiert nach ID DESC = neueste zuerst)
    $stmt = $pdo->prepare("
        SELECT id, title, year, genre, cover_id, trailer_url, created_at
        FROM dvds 
        WHERE trailer_url IS NOT NULL AND trailer_url != ''
        ORDER BY id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $trailersPerPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $trailers = $stmt->fetchAll();
    
} catch (Exception $e) {
    error_log('Trailer page error: ' . $e->getMessage());
    $trailers = [];
    $totalTrailers = 0;
    $totalPages = 0;
}
?>

<main class="trailers-page">
    <div class="page-header">
        <div class="page-header-content">
            <h1>
                <i class="bi bi-play-circle"></i>
                Neueste Trailer
            </h1>
            <p class="page-subtitle">
                <?= $totalTrailers ?> Trailer in der Sammlung
            </p>
        </div>
    </div>
    
    <?php if (empty($trailers)): ?>
        <div class="empty-state">
            <i class="bi bi-play-circle"></i>
            <h2>Keine Trailer verfügbar</h2>
            <p>Es wurden noch keine Trailer hinzugefügt.</p>
        </div>
    <?php else: ?>
        <div class="trailers-grid">
            <?php foreach ($trailers as $trailer): ?>
                <div class="trailer-card" data-trailer-id="<?= $trailer['id'] ?>">
                    <div class="trailer-thumbnail" onclick="playTrailer(this)" data-embed-url="<?= htmlspecialchars(getYouTubeEmbedUrl($trailer['trailer_url'])) ?>">
                        <img src="<?= htmlspecialchars(getYouTubeThumbnail($trailer['trailer_url'])) ?>" 
                             alt="<?= htmlspecialchars($trailer['title']) ?> Trailer"
                             loading="lazy"
                             onerror="this.src='cover/placeholder.png'">
                        <div class="play-overlay">
                            <i class="bi bi-play-circle-fill"></i>
                        </div>
                        <div class="trailer-duration">Trailer</div>
                    </div>
                    
                    <div class="trailer-info">
                        <h3 class="trailer-title">
                            <a href="?page=film&id=<?= $trailer['id'] ?>">
                                <?= htmlspecialchars($trailer['title']) ?>
                            </a>
                        </h3>
                        <div class="trailer-meta">
                            <span class="trailer-year">
                                <i class="bi bi-calendar3"></i>
                                <?= $trailer['year'] ?>
                            </span>
                            <span class="trailer-genre">
                                <i class="bi bi-tag"></i>
                                <?= htmlspecialchars($trailer['genre']) ?>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <nav class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=trailers&p=1" class="pagination-link">« Erste</a>
                    <a href="?page=trailers&p=<?= $page - 1 ?>" class="pagination-link">‹ Zurück</a>
                <?php endif; ?>
                
                <?php
                $window = 2;
                $start = max(1, $page - $window);
                $end = min($totalPages, $page + $window);
                
                for ($i = $start; $i <= $end; $i++):
                    if ($i === $page): ?>
                        <span class="pagination-current"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=trailers&p=<?= $i ?>" class="pagination-link"><?= $i ?></a>
                    <?php endif;
                endfor;
                ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=trailers&p=<?= $page + 1 ?>" class="pagination-link">Weiter ›</a>
                    <a href="?page=trailers&p=<?= $totalPages ?>" class="pagination-link">Letzte »</a>
                <?php endif; ?>
            </nav>
            
            <div class="pagination-info">
                Seite <?= $page ?> von <?= $totalPages ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Trailer Modal -->
<div id="trailerModal" class="trailer-modal">
    <div class="trailer-modal-backdrop" onclick="closeTrailerModal()"></div>
    <div class="trailer-modal-content">
        <button class="trailer-modal-close" onclick="closeTrailerModal()" aria-label="Schließen">
            <i class="bi bi-x-lg"></i>
        </button>
        <div class="trailer-video-container" id="trailerVideoContainer">
            <!-- YouTube iframe wird hier eingefügt -->
        </div>
    </div>
</div>

<style>
/* Trailers Page Styles */
.trailers-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: var(--space-xl, 2rem) var(--space-lg, 1.5rem);
}

.page-header {
    text-align: center;
    margin-bottom: var(--space-2xl, 3rem);
}

.page-header-content h1 {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-md, 1rem);
    font-size: 2.5rem;
    margin-bottom: var(--space-sm, 0.5rem);
    color: var(--text-primary, #e4e4e7);
}

.page-header-content h1 i {
    color: var(--accent-primary, #667eea);
    font-size: 2.8rem;
}

.page-subtitle {
    font-size: 1.1rem;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
}

/* Trailers Grid */
.trailers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-lg, 1.5rem);
    margin-bottom: var(--space-2xl, 3rem);
}

.trailer-card {
    background: var(--bg-secondary, rgba(255, 255, 255, 0.05));
    border: 1px solid var(--border-color, rgba(255, 255, 255, 0.1));
    border-radius: var(--radius-lg, 12px);
    overflow: hidden;
    transition: all 0.3s ease;
}

.trailer-card:hover {
    border-color: var(--accent-primary, #667eea);
    box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
    transform: translateY(-4px);
}

.trailer-thumbnail {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
    overflow: hidden;
    cursor: pointer;
    background: var(--bg-tertiary, #16213e);
}

.trailer-thumbnail img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.trailer-card:hover .trailer-thumbnail img {
    transform: scale(1.05);
}

.play-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0, 0, 0, 0.4);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.trailer-card:hover .play-overlay {
    opacity: 1;
}

.play-overlay i {
    font-size: 4rem;
    color: white;
    filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.5));
    transition: transform 0.3s ease;
}

.trailer-card:hover .play-overlay i {
    transform: scale(1.2);
}

.trailer-duration {
    position: absolute;
    bottom: 8px;
    right: 8px;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.trailer-info {
    padding: var(--space-md, 1rem);
}

.trailer-title {
    margin: 0 0 var(--space-sm, 0.5rem) 0;
    font-size: 1.1rem;
}

.trailer-title a {
    color: var(--text-primary, #e4e4e7);
    text-decoration: none;
    transition: color 0.3s ease;
}

.trailer-title a:hover {
    color: var(--accent-primary, #667eea);
}

.trailer-meta {
    display: flex;
    gap: var(--space-md, 1rem);
    font-size: 0.9rem;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
}

.trailer-meta span {
    display: flex;
    align-items: center;
    gap: var(--space-xs, 0.35rem);
}

/* Trailer Modal */
.trailer-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.trailer-modal.show {
    display: flex;
}

.trailer-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.95);
    animation: fadeIn 0.3s ease;
}

.trailer-modal-content {
    position: relative;
    width: 100%;
    max-width: 1200px;
    z-index: 2;
    animation: zoomIn 0.3s ease;
}

.trailer-modal-close {
    position: absolute;
    top: -50px;
    right: 0;
    width: 44px;
    height: 44px;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    border-radius: 50%;
    color: white;
    font-size: 1.5rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
}

.trailer-modal-close:hover {
    background: rgba(255, 71, 87, 0.9);
    transform: rotate(90deg);
}

.trailer-video-container {
    position: relative;
    width: 100%;
    padding-bottom: 56.25%;
    background: #000;
    border-radius: 8px;
    overflow: hidden;
}

.trailer-video-container iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: none;
}

/* Animations */
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes zoomIn {
    from {
        opacity: 0;
        transform: scale(0.9);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: var(--space-3xl, 4rem) var(--space-lg, 1.5rem);
}

.empty-state i {
    font-size: 4rem;
    color: var(--text-muted, rgba(228, 228, 231, 0.6));
    margin-bottom: var(--space-md, 1rem);
}

/* Responsive */
@media (max-width: 768px) {
    .trailers-grid {
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: var(--space-md, 1rem);
    }
    
    .page-header-content h1 {
        font-size: 2rem;
    }
    
    .trailer-modal-close {
        top: 10px;
        right: 10px;
    }
}

@media (max-width: 480px) {
    .trailers-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Trailer Modal Functions
function playTrailer(element) {
    const embedUrl = element.dataset.embedUrl;
    if (!embedUrl) return;
    
    const modal = document.getElementById('trailerModal');
    const container = document.getElementById('trailerVideoContainer');
    
    // YouTube iframe einfügen
    container.innerHTML = `
        <iframe 
            src="${embedUrl}?autoplay=1&rel=0" 
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
            allowfullscreen>
        </iframe>
    `;
    
    modal.classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeTrailerModal() {
    const modal = document.getElementById('trailerModal');
    const container = document.getElementById('trailerVideoContainer');
    
    // Video stoppen
    container.innerHTML = '';
    
    modal.classList.remove('show');
    document.body.style.overflow = '';
}

// ESC-Key schließt Modal
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeTrailerModal();
    }
});
</script>