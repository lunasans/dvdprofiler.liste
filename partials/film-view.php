<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$frontCover = findCoverImage($dvd['cover_id'], 'f');
$backCover  = findCoverImage($dvd['cover_id'], 'b');
$actors     = getActorsByDvdId($pdo, $dvd['id']);

// Gehört dieser Film zu einem BoxSet?
$parentId = $dvd['id'];

// BoxSet-Filme abrufen, die diesen Film als Parent haben
$boxsetFilms = $pdo->prepare("
    SELECT * FROM dvds
     WHERE boxset_parent = :parent
     ORDER BY year, title
");
$boxsetFilms->execute(['parent' => $parentId]);
$boxChildren = $boxsetFilms->fetchAll();

?>

<div class="detail-inline">
    <h2><?= htmlspecialchars($dvd['title']) ?> (<?= $dvd['year'] ?>)</h2>

    
        <div class="cover-pair">
            <a href="<?= htmlspecialchars($frontCover) ?>" data-fancybox="gallery"><img class="thumb" src="<?= htmlspecialchars($frontCover) ?>" alt="Frontcover"></a>
            <a href="<?= htmlspecialchars($backCover) ?>" data-fancybox="gallery"><img class="thumb" src="<?= htmlspecialchars($backCover) ?>" alt="Backcover"></a>
        </div>
    

    <div class="meta-card">
        <?php if (!empty($dvd['genre'])): ?>
            <p><strong>Genre:</strong> <?= htmlspecialchars($dvd['genre']) ?></p>
        <?php endif; ?>

        <?php if (!empty($dvd['runtime'])): ?>
            <p><strong>Laufzeit:</strong> <?= formatRuntime($dvd['runtime']) ?></p>
        <?php endif; ?>

        <?php if (!empty($dvd['rating_age'])): ?>
            <p><strong>Altersfreigabe:</strong> ab <?= htmlspecialchars($dvd['rating_age']) ?> Jahren</p>
        <?php endif; ?>
    </div>

    <div class="meta-card">
        <?php if (!empty($dvd['overview'])): ?>
            <p><strong>Inhalt:</strong><br><?= nl2br(htmlspecialchars($dvd['overview'])) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($actors): ?>
        <div class="meta-card">
            <div class="actor-list">
                <p><strong>Schauspieler:</strong></p>
                    <ul>
                        <?php foreach ($actors as $a): ?>
                            <li><?= htmlspecialchars("{$a['first_name']} {$a['last_name']} als {$a['role']}") ?></li>
                        <?php endforeach; ?>
                    </ul>
            </div>
        </div>
    <?php endif; ?>

    <div class="meta-card">
        <?php $trailerUrl = $dvd['trailer_url'] ?? null;
            if (!$trailerUrl && strtolower($dvd['title']) === '13 geister') {
                $trailerUrl = 'https://www.youtube.com/watch?v=rjwgpwN3HNE'; } ?>
        <?php if ($trailerUrl): ?>
            <div class="trailer-box">
                <div class="trailer-placeholder" data-yt="<?= htmlspecialchars(str_replace('watch?v=', 'embed/', $trailerUrl)) ?>">
                    <img src="<?= htmlspecialchars($frontCover) ?>" alt="Trailer Cover">
                <div class="play-icon">▶</div>
                </div>
            </div>
    </div>
<?php endif; ?>

<?php if ($boxChildren): ?>

  <div class="boxset-section">
    <h3>Dieses Boxset enthält:</h3>
    <ul class="boxset-list">
      <?php foreach ($boxChildren as $child): ?>
        <li>
          <a href="?id=<?= $child['id'] ?>" class="route-link">
            <?= htmlspecialchars($child['title']) ?> (<?= $child['year'] ?>)
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

    <hr>
    <p style="margin-top:1rem;">
        <a href="#" class="close-detail-button"><i class="bi bi-x-lg"></i> Detail schließen</a>
    </p>
