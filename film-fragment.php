<?php
declare(strict_types=1);
// echo "<div style='padding:1rem; background:#eef;'>Filmfragment geladen mit ID: " . htmlspecialchars($_GET['id'] ?? '???') . "</div>";

// Fehleranzeige aktivieren (nur für lokale Entwicklung)
//ini_set('display_errors', '1');
//error_reporting(E_ALL);

// ID aus der URL lesen
$id = (int)($_GET['id'] ?? 0);

// Film aus Datenbank laden
$stmt = $pdo->prepare("SELECT * FROM dvds WHERE id = ?");
$stmt->execute([$id]);
$dvd = $stmt->fetch();

// Rückmeldung wenn Film nicht gefunden wurde
if (!$dvd) {
    echo "<div style='color: red;'> Film mit ID $id nicht gefunden.</div>";
    exit;
}

// Detailansicht anzeigen (verwendet partials/detail-view.php)
include __DIR__ . '/partials/film-view.php';
