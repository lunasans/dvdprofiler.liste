<?php
// Fehler anzeigen
error_reporting(E_ALL);
ini_set('display_errors', '1');

echo "<h1>Debug - index.php Fehlersuche</h1>";
echo "<pre>";

echo "1. Bootstrap laden...\n";
try {
    require __DIR__ . '/includes/bootstrap.php';
    echo "   ✅ Bootstrap geladen\n\n";
} catch (Throwable $e) {
    echo "   ❌ Bootstrap-Fehler: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    exit;
}

echo "2. Counter laden...\n";
try {
    require __DIR__ . '/includes/counter.php';
    echo "   ✅ Counter geladen\n\n";
} catch (Throwable $e) {
    echo "   ❌ Counter-Fehler: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}

echo "3. Version laden...\n";
try {
    require __DIR__ . '/includes/version.php';
    echo "   ✅ Version geladen\n\n";
} catch (Throwable $e) {
    echo "   ❌ Version-Fehler: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
}

echo "4. Settings testen...\n";
try {
    $environment = getSetting('environment', 'production');
    echo "   Environment: $environment\n";

    if ($environment === 'development') {
        error_reporting(E_ALL);
        ini_set('display_errors', '1');
    }
    echo "   ✅ Settings funktionieren\n\n";
} catch (Throwable $e) {
    echo "   ❌ Settings-Fehler: " . $e->getMessage() . "\n\n";
}

echo "5. Input-Variablen testen...\n";
try {
    $search = isset($_GET['q']) ? trim(filter_var($_GET['q'], FILTER_SANITIZE_STRING)) : '';
    $page = isset($_GET['page']) ? trim(filter_var($_GET['page'], FILTER_SANITIZE_STRING)) : 'home';
    $filmId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    echo "   Search: '$search'\n";
    echo "   Page: '$page'\n";
    echo "   FilmId: $filmId\n";
    echo "   ✅ Input-Verarbeitung OK\n\n";
} catch (Throwable $e) {
    echo "   ❌ Input-Fehler: " . $e->getMessage() . "\n\n";
}

echo "6. Site-Konfiguration...\n";
try {
    $siteTitle = getSetting('site_title', 'DVD Profiler Liste');
    $siteDescription = getSetting('site_description', 'Professionelle DVD-Sammlung verwalten und durchsuchen');
    $theme = getSetting('theme', 'default');
    echo "   Site Title: $siteTitle\n";
    echo "   Theme: $theme\n";
    echo "   ✅ Site-Config OK\n\n";
} catch (Throwable $e) {
    echo "   ❌ Site-Config-Fehler: " . $e->getMessage() . "\n\n";
}

echo "7. Base URL generieren...\n";
try {
    function generateBaseUrl(): string {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');

        // Path sanitization
        $path = str_replace(['\\', '..'], ['/', ''], $path);
        $path = rtrim($path, '/');

        return $protocol . '://' . $host . $path . '/';
    }

    $baseUrl = generateBaseUrl();
    echo "   Base URL: $baseUrl\n";
    echo "   SCRIPT_NAME: " . ($_SERVER['SCRIPT_NAME'] ?? 'nicht gesetzt') . "\n";
    echo "   HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'nicht gesetzt') . "\n";
    echo "   ✅ Base URL OK\n\n";
} catch (Throwable $e) {
    echo "   ❌ Base URL-Fehler: " . $e->getMessage() . "\n\n";
}

echo "8. Version-Konstanten prüfen...\n";
try {
    if (defined('DVDPROFILER_VERSION')) {
        echo "   DVDPROFILER_VERSION: " . DVDPROFILER_VERSION . "\n";
        echo "   DVDPROFILER_AUTHOR: " . DVDPROFILER_AUTHOR . "\n";
        echo "   DVDPROFILER_GITHUB_URL: " . DVDPROFILER_GITHUB_URL . "\n";
        echo "   ✅ Version-Konstanten OK\n\n";
    } else {
        echo "   ⚠️ DVDPROFILER_VERSION nicht definiert\n\n";
    }
} catch (Throwable $e) {
    echo "   ❌ Version-Konstanten-Fehler: " . $e->getMessage() . "\n\n";
}

echo "9. Header-Include testen...\n";
try {
    ob_start();
    include __DIR__ . '/partials/header.php';
    $headerOutput = ob_get_clean();
    echo "   ✅ Header geladen (" . strlen($headerOutput) . " Bytes)\n\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "   ❌ Header-Fehler: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack:\n" . $e->getTraceAsString() . "\n\n";
}

echo "10. Film-List-Include testen...\n";
try {
    ob_start();
    include __DIR__ . '/partials/film-list.php';
    $filmListOutput = ob_get_clean();
    echo "   ✅ Film-List geladen (" . strlen($filmListOutput) . " Bytes)\n\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "   ❌ Film-List-Fehler: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack:\n" . $e->getTraceAsString() . "\n\n";
}

echo "11. Footer-Include testen...\n";
try {
    ob_start();
    include __DIR__ . '/includes/footer.php';
    $footerOutput = ob_get_clean();
    echo "   ✅ Footer geladen (" . strlen($footerOutput) . " Bytes)\n\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo "   ❌ Footer-Fehler: " . $e->getMessage() . "\n";
    echo "   Datei: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   Stack:\n" . $e->getTraceAsString() . "\n\n";
}

echo "</pre>";

echo "<hr>";
echo "<h2>Zusammenfassung</h2>";
echo "<p>Wenn alle Tests ✅ grün sind, liegt das Problem woanders.</p>";
echo "<p>Schauen Sie in die Apache/Nginx Error-Logs für weitere Details.</p>";
