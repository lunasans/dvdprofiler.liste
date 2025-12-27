<?php
// upload-limits.php - Upload-Limits prüfen

echo "<h2>📤 Upload-Limits prüfen</h2>";

$limits = [
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'), 
    'max_execution_time' => ini_get('max_execution_time'),
    'memory_limit' => ini_get('memory_limit'),
    'max_input_time' => ini_get('max_input_time'),
    'max_file_uploads' => ini_get('max_file_uploads')
];

echo "<table border='1' style='border-collapse: collapse; margin: 20px 0;'>";
echo "<tr><th style='padding: 8px; background: #f0f0f0;'>Setting</th><th style='padding: 8px; background: #f0f0f0;'>Aktueller Wert</th><th style='padding: 8px; background: #f0f0f0;'>Status</th></tr>";

foreach ($limits as $setting => $value) {
    $status = '✅ OK';
    $color = 'green';
    
    if ($setting === 'upload_max_filesize' || $setting === 'post_max_size') {
        $bytes = return_bytes($value);
        if ($bytes < 50 * 1024 * 1024) { // Weniger als 50MB
            $status = '❌ Zu klein';
            $color = 'red';
        }
    }
    
    if ($setting === 'max_execution_time') {
        if ((int)$value < 180) { // Weniger als 3 Minuten
            $status = '⚠️ Eventuell zu kurz';
            $color = 'orange';
        }
    }
    
    echo "<tr>";
    echo "<td style='padding: 8px;'>{$setting}</td>";
    echo "<td style='padding: 8px;'>{$value}</td>";
    echo "<td style='padding: 8px; color: {$color};'>{$status}</td>";
    echo "</tr>";
}

echo "</table>";

// Server-Info
echo "<h3>🔧 Server-Info:</h3>";
echo "<ul>";
echo "<li><strong>Webserver:</strong> " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unbekannt') . "</li>";
echo "<li><strong>PHP Version:</strong> " . PHP_VERSION . "</li>";
echo "<li><strong>Max Upload:</strong> " . $limits['upload_max_filesize'] . "</li>";
echo "</ul>";

// Empfohlene Werte
echo "<h3>💡 Empfohlene Einstellungen für DVD-XML Import:</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo "upload_max_filesize = 100M\n";
echo "post_max_size = 100M\n"; 
echo "max_execution_time = 300\n";
echo "memory_limit = 512M\n";
echo "max_input_time = 300\n";
echo "</pre>";

// Nginx zusätzlich
echo "<h3>🌐 Nginx zusätzlich:</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
echo "client_max_body_size 100M;\n";
echo "</pre>";

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}
?>