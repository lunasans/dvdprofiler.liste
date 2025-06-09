<?php

header('Content-Type: text/plain');

// Reihenfolge wichtig wegen Foreign Key
$pdo->exec("DELETE FROM actors");
$pdo->exec("DELETE FROM dvds");

echo "Datenbank wurde geleert (dvds & actors)";