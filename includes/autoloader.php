<?php
/**
 * DVD Profiler Liste - PSR-4 Autoloader
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

/**
 * Simple PSR-4 Autoloader für DVD Profiler Liste
 */
class DVDProfilerAutoloader
{
    /** @var array<string, string> Namespace-zu-Pfad Mapping */
    private array $prefixes = [];

    /**
     * Namespace-Präfix mit Basis-Pfad registrieren
     */
    public function addNamespace(string $prefix, string $baseDir): void
    {
        // Normalisiere Namespace-Präfix
        $prefix = trim($prefix, '\\') . '\\';

        // Normalisiere Basis-Verzeichnis
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';

        // Initialisiere Präfix-Array falls notwendig
        if (!isset($this->prefixes[$prefix])) {
            $this->prefixes[$prefix] = [];
        }

        // Füge Basis-Verzeichnis zur Präfix-Liste hinzu
        array_push($this->prefixes[$prefix], $baseDir);
    }

    /**
     * Lädt die Klassendatei für einen gegebenen Klassennamen
     */
    public function loadClass(string $class): ?string
    {
        // Aktueller Namespace-Präfix
        $prefix = $class;

        // Arbeite durch Namespace-Namen rückwärts, um eine gemappte
        // Datei zu finden
        while (false !== $pos = strrpos($prefix, '\\')) {
            
            // Behalte abschließenden Namespace-Separator im Präfix
            $prefix = substr($class, 0, $pos + 1);

            // Rest ist der relative Klassenname
            $relativeClass = substr($class, $pos + 1);

            // Versuche eine gemappte Datei für Präfix und relative Klasse zu laden
            $mappedFile = $this->loadMappedFile($prefix, $relativeClass);
            if ($mappedFile) {
                return $mappedFile;
            }

            // Entferne abschließenden Namespace-Separator für nächste Iteration
            $prefix = rtrim($prefix, '\\');   
        }

        // Keine gemappte Datei gefunden
        return null;
    }

    /**
     * Lädt die gemappte Datei für einen Namespace-Präfix und relative Klasse
     */
    protected function loadMappedFile(string $prefix, string $relativeClass): ?string
    {
        // Gibt es Basis-Verzeichnisse für diesen Namespace-Präfix?
        if (!isset($this->prefixes[$prefix])) {
            return null;
        }

        // Schaue durch Basis-Verzeichnisse für diesen Namespace-Präfix
        foreach ($this->prefixes[$prefix] as $baseDir) {

            // Ersetze Namespace-Separatoren mit Verzeichnis-Separatoren
            // im relativen Klassennamen, füge .php hinzu
            $file = $baseDir
                  . str_replace('\\', '/', $relativeClass)
                  . '.php';

            // Wenn die gemappte Datei existiert, lade sie
            if ($this->requireFile($file)) {
                return $file;
            }
        }

        // Keine gemappte Datei gefunden
        return null;
    }

    /**
     * Lädt Datei falls sie existiert
     */
    protected function requireFile(string $file): bool
    {
        if (file_exists($file)) {
            require $file;
            return true;
        }
        return false;
    }
    
    /**
     * Anzahl registrierter Namespaces abrufen (für Debug)
     */
    public function getNamespaceCount(): int
    {
        return count($this->prefixes);
    }
}

// Autoloader initialisieren
$autoloader = new DVDProfilerAutoloader();

// Namespace-Mappings registrieren
$autoloader->addNamespace('DVDProfiler\\Core', __DIR__ . '/core');
$autoloader->addNamespace('DVDProfiler\\Admin', __DIR__ . '/admin');
$autoloader->addNamespace('DVDProfiler\\Utils', __DIR__ . '/utils');

// Autoloader bei SPL registrieren
spl_autoload_register([$autoloader, 'loadClass']);

// Globale Konstanten für einfachen Zugriff
define('DVDPROFILER_CORE_LOADED', true);
define('DVDPROFILER_AUTOLOADER_VERSION', '1.0.0');

// Legacy-Support: Alte Funktionen als Wrapper verfügbar halten
if (!function_exists('getSetting')) {
    function getSetting(string $key, string $default = ''): string {
        return \DVDProfiler\Core\Application::getInstance()->getSettings()->get($key, $default);
    }
}

if (!function_exists('setSetting')) {
    function setSetting(string $key, string $value): bool {
        return \DVDProfiler\Core\Application::getInstance()->getSettings()->set($key, $value);
    }
}

if (!function_exists('validateCSRFToken')) {
    function validateCSRFToken(string $token): bool {
        return \DVDProfiler\Core\Security::validateCSRFToken($token);
    }
}

if (!function_exists('generateCSRFToken')) {
    function generateCSRFToken(): string {
        return \DVDProfiler\Core\Security::generateCSRFToken();
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput(string $input, int $maxLength = 255): string {
        return \DVDProfiler\Core\Validation::sanitizeInput($input, $maxLength);
    }
}

if (!function_exists('formatBytes')) {
    function formatBytes(int|float $bytes, int $precision = 2): string {
        return \DVDProfiler\Core\Utils::formatBytes($bytes, $precision);
    }
}

// Debug-Information (nur im Development-Modus)
if (defined('DVDPROFILER_DEBUG') && DVDPROFILER_DEBUG === true) {
    error_log('DVDProfiler Autoloader initialized with ' . $autoloader->getNamespaceCount() . ' namespace(s)');
}