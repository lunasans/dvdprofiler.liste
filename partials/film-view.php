<?php
// Fehler SOFORT anzeigen - kein Try/Catch
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "DEBUG: Start der Datei<br>";

echo "DEBUG: Lade Bootstrap...<br>";
require_once __DIR__ . '/includes/bootstrap.php';
echo "DEBUG: Bootstrap geladen!<br>";

echo "DEBUG: Teste Datenbank...<br>";
$stmt = $pdo->query("SELECT COUNT(*) FROM dvds");
$count = $stmt->fetchColumn();
echo "DEBUG: Gefunden: $count Filme<br>";

echo "DEBUG: Lade Filme...<br>";
$stmt = $pdo->query("SELECT * FROM dvds ORDER BY id DESC LIMIT 5");
$latest = $stmt->fetchAll();
echo "DEBUG: Geladen: " . count($latest) . " Filme<br>";

echo "DEBUG: Alles OK! Zeige HTML...<br>";
?>

<h2>Neu hinzugefügt (<?= $count ?> Filme)</h2>

<div class="latest-list">
    <?php foreach ($latest as $dvd): ?>
        <div class="latest-card">
            <h3><?= htmlspecialchars($dvd['title']) ?></h3>
            <p>Jahr: <?= $dvd['year'] ?></p>
            <p>Genre: <?= htmlspecialchars($dvd['genre']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<style>
.latest-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    margin: 1rem;
}

.latest-card {
    background: #f0f0f0;
    padding: 1rem;
    border-radius: 8px;
    border: 1px solid #ccc;
}

.latest-card h3 {
    margin-top: 0;
    color: #333;
}
</style>