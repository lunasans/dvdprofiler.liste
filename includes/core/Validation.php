<?php
/**
 * DVD Profiler Liste - Input Validation
 * Zentrale Input-Validierung mit konfigurierbaren Regeln
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.7+
 */

declare(strict_types=1);

namespace DVDProfiler\Core;

use Exception;

/**
 * Validation-Klasse
 * Verwaltet alle Input-Validierungen mit konfigurierbaren Regeln
 */
class Validation
{
    /** @var array<string, mixed> Validierungsregeln */
    private array $rules = [];
    
    /** @var array<string, string> Fehlermeldungen */
    private array $errors = [];
    
    /** @var array<string, mixed> Validierte Daten */
    private array $validatedData = [];
    
    /** @var array<string, string> Custom Error Messages */
    private array $customMessages = [];
    
    /**
     * Constructor
     */
    public function __construct(array $rules = [], array $customMessages = [])
    {
        $this->rules = $rules;
        $this->customMessages = $customMessages;
    }
    
    /**
     * Daten gegen Regeln validieren
     */
    public function validate(array $data): bool
    {
        $this->errors = [];
        $this->validatedData = [];
        
        foreach ($this->rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            $this->validateField($field, $value, $fieldRules);
        }
        
        return empty($this->errors);
    }
    
    /**
     * Einzelnes Feld validieren
     */
    private function validateField(string $field, mixed $value, array|string $rules): void
    {
        // Regeln in Array umwandeln falls String
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }
        
        $isRequired = in_array('required', $rules);
        $isEmpty = $this->isEmpty($value);
        
        // Required-Check
        if ($isRequired && $isEmpty) {
            $this->addError($field, 'required');
            return;
        }
        
        // Wenn leer und nicht required, skip weitere Validierung
        if ($isEmpty && !$isRequired) {
            $this->validatedData[$field] = $value;
            return;
        }
        
        // Alle Regeln durchlaufen
        foreach ($rules as $rule) {
            if ($rule === 'required') {
                continue; // Bereits geprüft
            }
            
            if (!$this->validateRule($field, $value, $rule)) {
                break; // Stop bei erstem Fehler pro Feld
            }
        }
        
        // Wenn keine Fehler, zu validated data hinzufügen
        if (!isset($this->errors[$field])) {
            $this->validatedData[$field] = $this->transformValue($value, $rules);
        }
    }
    
    /**
     * Einzelne Regel validieren
     */
    private function validateRule(string $field, mixed $value, string $rule): bool
    {
        // Parameter aus Regel extrahieren
        $parameters = [];
        if (str_contains($rule, ':')) {
            [$ruleName, $paramString] = explode(':', $rule, 2);
            $parameters = explode(',', $paramString);
        } else {
            $ruleName = $rule;
        }
        
        $method = 'validate' . ucfirst($ruleName);
        
        if (method_exists($this, $method)) {
            return $this->$method($field, $value, $parameters);
        }
        
        throw new Exception("Unknown validation rule: {$ruleName}");
    }
    
    /**
     * Wert ist leer prüfen
     */
    private function isEmpty(mixed $value): bool
    {
        return $value === null || $value === '' || $value === [] || 
               (is_string($value) && trim($value) === '');
    }
    
    /**
     * Validierungsregeln
     */
    
    protected function validateString(string $field, mixed $value, array $params = []): bool
    {
        if (!is_string($value)) {
            $this->addError($field, 'string');
            return false;
        }
        return true;
    }
    
    protected function validateInteger(string $field, mixed $value, array $params = []): bool
    {
        if (!is_numeric($value) || (int)$value != $value) {
            $this->addError($field, 'integer');
            return false;
        }
        return true;
    }
    
    protected function validateNumeric(string $field, mixed $value, array $params = []): bool
    {
        if (!is_numeric($value)) {
            $this->addError($field, 'numeric');
            return false;
        }
        return true;
    }
    
    protected function validateEmail(string $field, mixed $value, array $params = []): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email');
            return false;
        }
        return true;
    }
    
    protected function validateUrl(string $field, mixed $value, array $params = []): bool
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url');
            return false;
        }
        return true;
    }
    
    protected function validateMin(string $field, mixed $value, array $params = []): bool
    {
        $min = (int)($params[0] ?? 0);
        
        if (is_string($value)) {
            $length = mb_strlen($value);
            if ($length < $min) {
                $this->addError($field, 'min', ['min' => $min, 'actual' => $length]);
                return false;
            }
        } elseif (is_numeric($value)) {
            if ((float)$value < $min) {
                $this->addError($field, 'min', ['min' => $min, 'actual' => $value]);
                return false;
            }
        }
        
        return true;
    }
    
    protected function validateMax(string $field, mixed $value, array $params = []): bool
    {
        $max = (int)($params[0] ?? 0);
        
        if (is_string($value)) {
            $length = mb_strlen($value);
            if ($length > $max) {
                $this->addError($field, 'max', ['max' => $max, 'actual' => $length]);
                return false;
            }
        } elseif (is_numeric($value)) {
            if ((float)$value > $max) {
                $this->addError($field, 'max', ['max' => $max, 'actual' => $value]);
                return false;
            }
        }
        
        return true;
    }
    
    protected function validateBetween(string $field, mixed $value, array $params = []): bool
    {
        $min = (float)($params[0] ?? 0);
        $max = (float)($params[1] ?? 0);
        
        if (is_string($value)) {
            $length = mb_strlen($value);
            if ($length < $min || $length > $max) {
                $this->addError($field, 'between', ['min' => $min, 'max' => $max, 'actual' => $length]);
                return false;
            }
        } elseif (is_numeric($value)) {
            $numValue = (float)$value;
            if ($numValue < $min || $numValue > $max) {
                $this->addError($field, 'between', ['min' => $min, 'max' => $max, 'actual' => $numValue]);
                return false;
            }
        }
        
        return true;
    }
    
    protected function validateIn(string $field, mixed $value, array $params = []): bool
    {
        if (!in_array($value, $params, true)) {
            $this->addError($field, 'in', ['allowed' => implode(', ', $params)]);
            return false;
        }
        return true;
    }
    
    protected function validateRegex(string $field, mixed $value, array $params = []): bool
    {
        $pattern = $params[0] ?? '';
        
        if (!preg_match($pattern, (string)$value)) {
            $this->addError($field, 'regex');
            return false;
        }
        return true;
    }
    
    protected function validateAlpha(string $field, mixed $value, array $params = []): bool
    {
        if (!preg_match('/^[a-zA-ZäöüÄÖÜß\s]+$/', (string)$value)) {
            $this->addError($field, 'alpha');
            return false;
        }
        return true;
    }
    
    protected function validateAlphaNum(string $field, mixed $value, array $params = []): bool
    {
        if (!preg_match('/^[a-zA-Z0-9äöüÄÖÜß\s]+$/', (string)$value)) {
            $this->addError($field, 'alpha_num');
            return false;
        }
        return true;
    }
    
    protected function validateAlphaDash(string $field, mixed $value, array $params = []): bool
    {
        if (!preg_match('/^[a-zA-Z0-9äöüÄÖÜß\s_-]+$/', (string)$value)) {
            $this->addError($field, 'alpha_dash');
            return false;
        }
        return true;
    }
    
    protected function validateBoolean(string $field, mixed $value, array $params = []): bool
    {
        $booleanValues = [true, false, 1, 0, '1', '0', 'true', 'false', 'on', 'off', 'yes', 'no'];
        
        if (!in_array($value, $booleanValues, true)) {
            $this->addError($field, 'boolean');
            return false;
        }
        return true;
    }
    
    protected function validateDate(string $field, mixed $value, array $params = []): bool
    {
        $format = $params[0] ?? 'Y-m-d';
        
        $date = \DateTime::createFromFormat($format, (string)$value);
        
        if (!$date || $date->format($format) !== $value) {
            $this->addError($field, 'date', ['format' => $format]);
            return false;
        }
        return true;
    }
    
    protected function validateFile(string $field, mixed $value, array $params = []): bool
    {
        if (!is_array($value) || !isset($value['tmp_name']) || !is_uploaded_file($value['tmp_name'])) {
            $this->addError($field, 'file');
            return false;
        }
        return true;
    }
    
    protected function validateImage(string $field, mixed $value, array $params = []): bool
    {
        if (!$this->validateFile($field, $value, $params)) {
            return false;
        }
        
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $value['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedMimes)) {
            $this->addError($field, 'image');
            return false;
        }
        return true;
    }
    
    protected function validateMimes(string $field, mixed $value, array $params = []): bool
    {
        if (!$this->validateFile($field, $value, $params)) {
            return false;
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $value['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $params)) {
            $this->addError($field, 'mimes', ['allowed' => implode(', ', $params)]);
            return false;
        }
        return true;
    }
    
    protected function validateMaxFileSize(string $field, mixed $value, array $params = []): bool
    {
        if (!$this->validateFile($field, $value, $params)) {
            return false;
        }
        
        $maxSize = (int)($params[0] ?? 0);
        
        if ($value['size'] > $maxSize) {
            $this->addError($field, 'max_file_size', [
                'max' => Utils::formatBytes($maxSize),
                'actual' => Utils::formatBytes($value['size'])
            ]);
            return false;
        }
        return true;
    }
    
    /**
     * Fehler hinzufügen
     */
    private function addError(string $field, string $rule, array $context = []): void
    {
        $message = $this->getErrorMessage($field, $rule, $context);
        $this->errors[$field] = $message;
    }
    
    /**
     * Error-Message generieren
     */
    private function getErrorMessage(string $field, string $rule, array $context = []): string
    {
        // Custom Message prüfen
        $customKey = "{$field}.{$rule}";
        if (isset($this->customMessages[$customKey])) {
            return $this->interpolateMessage($this->customMessages[$customKey], $context);
        }
        
        // Standard-Messages
        $messages = [
            'required' => 'Das Feld :field ist erforderlich.',
            'string' => 'Das Feld :field muss ein Text sein.',
            'integer' => 'Das Feld :field muss eine ganze Zahl sein.',
            'numeric' => 'Das Feld :field muss eine Zahl sein.',
            'email' => 'Das Feld :field muss eine gültige E-Mail-Adresse sein.',
            'url' => 'Das Feld :field muss eine gültige URL sein.',
            'min' => 'Das Feld :field muss mindestens :min Zeichen/Wert haben.',
            'max' => 'Das Feld :field darf maximal :max Zeichen/Wert haben.',
            'between' => 'Das Feld :field muss zwischen :min und :max liegen.',
            'in' => 'Das Feld :field muss einen der folgenden Werte haben: :allowed',
            'regex' => 'Das Feld :field hat ein ungültiges Format.',
            'alpha' => 'Das Feld :field darf nur Buchstaben enthalten.',
            'alpha_num' => 'Das Feld :field darf nur Buchstaben und Zahlen enthalten.',
            'alpha_dash' => 'Das Feld :field darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
            'boolean' => 'Das Feld :field muss ein Boolean-Wert sein.',
            'date' => 'Das Feld :field muss ein gültiges Datum im Format :format sein.',
            'file' => 'Das Feld :field muss eine gültige Datei sein.',
            'image' => 'Das Feld :field muss ein gültiges Bild sein.',
            'mimes' => 'Das Feld :field muss einen der folgenden Dateitypen haben: :allowed',
            'max_file_size' => 'Das Feld :field darf maximal :max groß sein (aktuell: :actual).'
        ];
        
        $message = $messages[$rule] ?? 'Das Feld :field ist ungültig.';
        
        $context['field'] = $field;
        return $this->interpolateMessage($message, $context);
    }
    
    /**
     * Variablen in Message interpolieren
     */
    private function interpolateMessage(string $message, array $context = []): string
    {
        foreach ($context as $key => $value) {
            $message = str_replace(":{$key}", (string)$value, $message);
        }
        return $message;
    }
    
    /**
     * Wert transformieren (Type-Casting)
     */
    private function transformValue(mixed $value, array $rules): mixed
    {
        if (in_array('integer', $rules)) {
            return (int)$value;
        }
        
        if (in_array('numeric', $rules)) {
            return is_float($value) ? (float)$value : (int)$value;
        }
        
        if (in_array('boolean', $rules)) {
            return in_array($value, [true, 1, '1', 'true', 'on', 'yes'], true);
        }
        
        if (in_array('string', $rules)) {
            return (string)$value;
        }
        
        return $value;
    }
    
    /**
     * Alle Fehler abrufen
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * Ersten Fehler abrufen
     */
    public function getFirstError(): ?string
    {
        return !empty($this->errors) ? array_values($this->errors)[0] : null;
    }
    
    /**
     * Fehler für spezifisches Feld
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }
    
    /**
     * Validierte Daten abrufen
     */
    public function getValidatedData(): array
    {
        return $this->validatedData;
    }
    
    /**
     * Hat Fehler
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
    
    /**
     * Schnelle statische Validierung
     */
    public static function make(array $data, array $rules, array $messages = []): self
    {
        $validator = new self($rules, $messages);
        $validator->validate($data);
        return $validator;
    }
    
    /**
     * Einfache Input-Sanitization (Statisch für Legacy-Support)
     */
    public static function sanitizeInput(string $input, int $maxLength = 255, bool $allowHtml = false): string
    {
        return Security::sanitizeInput($input, $maxLength, $allowHtml);
    }
    
    /**
     * Validierungsregeln hinzufügen
     */
    public function addRule(string $field, array|string $rules): self
    {
        $this->rules[$field] = $rules;
        return $this;
    }
    
    /**
     * Custom Message hinzufügen
     */
    public function addMessage(string $key, string $message): self
    {
        $this->customMessages[$key] = $message;
        return $this;
    }
}