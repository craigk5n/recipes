<?php

declare(strict_types=1);

namespace Recipes\Database;

use PDO;
use PDOException;
use PDOStatement;

/**
 * PDO-based database abstraction layer.
 * Refactored for PHP 8.1+ and PSR-4 autoloading.
 */
class Database
{
    private static ?PDO $pdo = null;
    private static bool $inTransaction = false;

    // For testing purposes
    private static array $mockQueryResults = [];

    /**
     * Set a mock query result for testing Database::query and fetchRow.
     * This is intended for unit testing where a real DB connection is not desired.
     * The key is typically the recipe_id or user_id for ownership checks.
     */
    public static function setMockQueryResult(int $key, object $result): void
    {
        $result->fetchNumCalls = 0; // Initialize for fetchRow tracking
        self::$mockQueryResults[$key] = $result;
    }

    /**
     * Clear all mock query results.
     */
    public static function clearMockQueryResults(): void
    {
        self::$mockQueryResults = [];
    }

    /**
     * Establish a database connection using PDO.
     */
    public static function connect(): PDO
    {
        if (self::$pdo === null) {
            $dbHost = \Recipes\Config::get('DB_HOST');
            $dbLogin = \Recipes\Config::get('DB_LOGIN');
            $dbPassword = \Recipes\Config::get('DB_PASSWORD');
            $dbDatabase = \Recipes\Config::get('DB_DATABASE');

            if (empty($dbHost) || empty($dbLogin) || empty($dbDatabase)) {
                // Fallback to minimal if config not fully loaded, though this should ideally not happen
                $dbHost = $dbHost ?: '127.0.0.1';
                $dbLogin = $dbLogin ?: '';
                $dbDatabase = $dbDatabase ?: '';
                $dbPassword = $dbPassword ?: '';
            }

            try {
                $dsn = "mysql:host={$dbHost};dbname={$dbDatabase};charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                self::$pdo = new PDO($dsn, (string)$dbLogin, (string)$dbPassword, $options);
            } catch (PDOException $e) {
                // Use the global handleError if available, or fall back to die
                if (function_exists('handleError')) {
                    handleError("Database connection failed: " . $e->getMessage());
                } else {
                    die("Database connection failed.");
                }
            }
        }
        return self::$pdo;
    }

    /**
     * Execute a SQL query with optional parameters.
     */
    public static function query(string $sql, array $params = []): PDOStatement|bool
    {
        // Check for mock results first
        foreach (self::$mockQueryResults as $key => $mockResult) {
            // Very basic heuristic: check if the SQL contains a common ownership check pattern
            // and if the key is in the params.
            if (str_contains($sql, 'FROM rec_recipe WHERE rec_id = ?') && in_array($key, $params)) {
                // To satisfy PDOStatement|bool return type, return a simple mock object with fetchNumCalls and returnValues.
                // The properties are declared rather than assigned dynamically: PHP 8.2 deprecates
                // dynamic properties and PHP 9 will make them a fatal error.
                $stmt = new class extends PDOStatement {
                    public int $fetchNumCalls = 0;
                    /** @var array<int, array<int, mixed>> */
                    public array $returnValues = [];
                };
                $stmt->returnValues = $mockResult->returnValues;
                return $stmt;
            }
        }
        
        $pdo = self::connect();
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Fetch a single row as a numeric array.
     */
    public static function fetchRow(?PDOStatement $stmt): array|bool
    {
        // For mock results, we return pre-defined values
        if (isset($stmt->returnValues)) {
            if ($stmt->fetchNumCalls < count($stmt->returnValues)) {
                $row = $stmt->returnValues[$stmt->fetchNumCalls];
                $stmt->fetchNumCalls++;
                return $row;
            }
            return false;
        }

        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_NUM);
            return $result ?: false;
        }
        return false;
    }

    /**
     * Free result (No-op for PDO).
     */
    public static function freeResult(?PDOStatement $stmt): bool
    {
        return true;
    }

    /**
     * Get the last database error message.
     */
    public static function error(): string
    {
        if (self::$pdo) {
            $error = self::$pdo->errorInfo();
            return $error[2] ?? 'Unknown error';
        }
        return 'No database connection';
    }

    /**
     * Get the last inserted ID.
     */
    public static function lastInsertId(): string|bool
    {
        return self::$pdo ? self::$pdo->lastInsertId() : false;
    }

    /**
     * Begin a database transaction.
     */
    public static function beginTransaction(): void
    {
        if (!self::$inTransaction) {
            self::connect()->beginTransaction();
            self::$inTransaction = true;
        }
    }

    /**
     * Commit the current transaction.
     */
    public static function commit(): void
    {
        if (self::$inTransaction) {
            self::connect()->commit();
            self::$inTransaction = false;
        }
    }

    /**
     * Rollback the current transaction.
     */
    public static function rollback(): void
    {
        if (self::$inTransaction) {
            self::connect()->rollback();
            self::$inTransaction = false;
        }
    }
}
