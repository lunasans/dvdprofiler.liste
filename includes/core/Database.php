<?php
/**
 * DVD Profiler Liste - Database Wrapper
 * Zentraler Database-Wrapper mit erweiterten Features
 * 
 * @package    dvdprofiler.liste
 * @author     René Neuhaus
 * @version    1.4.8
 */

declare(strict_types=1);

namespace DVDProfiler\Core;

use PDO;
use PDOStatement;
use PDOException;
use Exception;

/**
 * Database-Wrapper-Klasse
 * Erweitert PDO um zusätzliche Features und bessere Fehlerbehandlung
 */
class Database
{
    private ?PDO $pdo = null;
    private array $config;
    private int $queryCount = 0;
    private float $totalQueryTime = 0.0;
    private array $queryLog = [];
    private bool $debugMode = false;
    
    /** @var array<string, PDOStatement> Prepared Statement Cache */
    private array $statementCache = [];
    
    /**
     * Constructor
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->debugMode = ($config['environment'] ?? 'production') === 'development';
        $this->connect();
    }
    
    /**
     * Datenbank-Verbindung herstellen
     * 
     * @throws PDOException Bei Verbindungsfehlern
     */
    private function connect(): void
    {
        $charset = $this->config['db_charset'] ?? 'utf8mb4';
        $dsn = "mysql:host={$this->config['db_host']};dbname={$this->config['db_name']};charset={$charset}";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_PERSISTENT => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$charset} COLLATE {$charset}_unicode_ci",
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ];
        
        try {
            $this->pdo = new PDO(
                $dsn,
                $this->config['db_user'],
                $this->config['db_pass'],
                $options
            );
            
            // Zusätzliche MySQL-Konfiguration
            $this->pdo->exec("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");
            $this->pdo->exec("SET time_zone = '+00:00'");
            
            if ($this->debugMode) {
                error_log('[Database] Connected to MySQL successfully');
            }
            
        } catch (PDOException $e) {
            error_log('[Database] Connection failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Raw PDO-Instance abrufen (für Legacy-Code)
     */
    public function getPDO(): PDO
    {
        return $this->pdo;
    }
    
    /**
     * Query ausführen mit Performance-Tracking
     */
    public function query(string $sql): PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $statement = $this->pdo->query($sql);
            $this->trackQuery($sql, microtime(true) - $startTime);
            return $statement;
            
        } catch (PDOException $e) {
            $this->logQueryError($sql, [], $e);
            throw $e;
        }
    }
    
    /**
     * Prepared Statement erstellen oder aus Cache abrufen
     */
    public function prepare(string $sql): PDOStatement
    {
        $cacheKey = md5($sql);
        
        if (!isset($this->statementCache[$cacheKey])) {
            try {
                $this->statementCache[$cacheKey] = $this->pdo->prepare($sql);
            } catch (PDOException $e) {
                error_log('[Database] Prepare failed: ' . $e->getMessage() . ' | SQL: ' . $sql);
                throw $e;
            }
        }
        
        return $this->statementCache[$cacheKey];
    }
    
    /**
     * Prepared Statement ausführen mit besserer Fehlerbehandlung
     */
    public function execute(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);
        
        try {
            $statement = $this->prepare($sql);
            $result = $statement->execute($params);
            
            if (!$result) {
                throw new Exception('Statement execution failed');
            }
            
            $this->trackQuery($sql, microtime(true) - $startTime, $params);
            return $statement;
            
        } catch (PDOException $e) {
            $this->logQueryError($sql, $params, $e);
            throw $e;
        }
    }
    
    /**
     * Einzelnen Wert abrufen
     */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $statement = $this->execute($sql, $params);
        return $statement->fetchColumn();
    }
    
    /**
     * Einzelne Zeile abrufen
     */
    public function fetchRow(string $sql, array $params = []): ?array
    {
        $statement = $this->execute($sql, $params);
        $result = $statement->fetch();
        return $result ?: null;
    }
    
    /**
     * Alle Zeilen abrufen
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->execute($sql, $params);
        return $statement->fetchAll();
    }
    
    /**
     * Key-Value Pairs abrufen
     */
    public function fetchPairs(string $sql, array $params = []): array
    {
        $statement = $this->execute($sql, $params);
        return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    /**
     * INSERT mit Auto-Increment ID zurückgeben
     */
    public function insert(string $table, array $data): int|string
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($field) => ":{$field}", $fields);
        
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $fields) . "`) 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->execute($sql, $data);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * UPDATE mit Anzahl betroffener Zeilen
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $setParts = array_map(fn($field) => "`{$field}` = :{$field}", array_keys($data));
        
        $sql = "UPDATE `{$table}` SET " . implode(', ', $setParts) . " WHERE {$where}";
        
        $params = array_merge($data, $whereParams);
        $statement = $this->execute($sql, $params);
        
        return $statement->rowCount();
    }
    
    /**
     * DELETE mit Anzahl betroffener Zeilen
     */
    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = "DELETE FROM `{$table}` WHERE {$where}";
        $statement = $this->execute($sql, $params);
        return $statement->rowCount();
    }
    
    /**
     * UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
     */
    public function upsert(string $table, array $data, array $updateFields = []): int|string
    {
        $fields = array_keys($data);
        $placeholders = array_map(fn($field) => ":{$field}", $fields);
        
        // Wenn keine Update-Felder angegeben, verwende alle außer PRIMARY KEY
        if (empty($updateFields)) {
            $updateFields = $fields;
        }
        
        $updateParts = array_map(fn($field) => "`{$field}` = VALUES(`{$field}`)", $updateFields);
        
        $sql = "INSERT INTO `{$table}` (`" . implode('`, `', $fields) . "`) 
                VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updateParts);
        
        $this->execute($sql, $data);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * Transaktion starten
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * Transaktion bestätigen
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }
    
    /**
     * Transaktion zurückrollen
     */
    public function rollback(): bool
    {
        return $this->pdo->rollback();
    }
    
    /**
     * Prüft ob in Transaktion
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
    
    /**
     * Transaktion mit Callback ausführen
     */
    public function transaction(callable $callback): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
            
        } catch (Exception $e) {
            $this->rollback();
            throw $e;
        }
    }
    
    /**
     * SQL-Escape für LIKE-Queries
     */
    public function escapeLike(string $value): string
    {
        return str_replace(['%', '_', '\\'], ['\\%', '\\_', '\\\\'], $value);
    }
    
    /**
     * Tabelle existiert prüfen
     */
    public function tableExists(string $table): bool
    {
        try {
            $sql = "SELECT 1 FROM `{$table}` LIMIT 1";
            $this->query($sql);
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }
    
    /**
     * Query-Performance tracking
     */
    private function trackQuery(string $sql, float $executionTime, array $params = []): void
    {
        $this->queryCount++;
        $this->totalQueryTime += $executionTime;
        
        if ($this->debugMode) {
            $this->queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'execution_time' => $executionTime,
                'timestamp' => microtime(true)
            ];
            
            // Log slow queries
            if ($executionTime > 0.1) { // 100ms
                error_log(sprintf('[Database] Slow query (%.3fs): %s', $executionTime, $sql));
            }
        }
    }
    
    /**
     * Query-Fehler loggen
     */
    private function logQueryError(string $sql, array $params, PDOException $e): void
    {
        $errorInfo = [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'sql' => $sql,
            'params' => $params
        ];
        
        error_log('[Database] Query Error: ' . json_encode($errorInfo, JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Performance-Statistiken abrufen
     */
    public function getStats(): array
    {
        return [
            'query_count' => $this->queryCount,
            'total_query_time' => $this->totalQueryTime,
            'avg_query_time' => $this->queryCount > 0 ? $this->totalQueryTime / $this->queryCount : 0,
            'slow_queries' => array_filter($this->queryLog, fn($query) => $query['execution_time'] > 0.1),
            'cache_size' => count($this->statementCache)
        ];
    }
    
    /**
     * Query-Log abrufen (nur Development)
     */
    public function getQueryLog(): array
    {
        return $this->debugMode ? $this->queryLog : [];
    }
    
    /**
     * Statement-Cache leeren
     */
    public function clearCache(): void
    {
        $this->statementCache = [];
    }
    
    /**
     * Verbindung schließen
     */
    public function close(): void
    {
        $this->clearCache();
        $this->pdo = null;
    }
    
    /**
     * Destructor - Automatisches Cleanup
     */
    public function __destruct()
    {
        if ($this->debugMode && $this->queryCount > 0) {
            error_log(sprintf(
                '[Database] Session stats: %d queries, %.3fs total time, %.3fs avg',
                $this->queryCount,
                $this->totalQueryTime,
                $this->totalQueryTime / $this->queryCount
            ));
        }
    }
}