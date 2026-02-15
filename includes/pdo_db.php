<?php
/**
 * PDO-based database abstraction layer for BookLog
 * Replaces dbi4php with secure PDO implementation
 */

class BookLogDB {
    private static $pdo = null;
    private static $inTransaction = false;

    public static function connect() {
        if (self::$pdo === null) {
            global $db_host, $db_login, $db_password, $db_database;

            try {
                $dsn = "mysql:host=$db_host;dbname=$db_database;charset=utf8mb4";
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ];

                self::$pdo = new PDO($dsn, $db_login, $db_password, $options);
            } catch (PDOException $e) {
                die_miserable_death("Database connection failed: " . $e->getMessage());
            }
        }
        return self::$pdo;
    }

    public static function query($sql, $params = []) {
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

    public static function fetchRow($stmt) {
        if ($stmt) {
            $result = $stmt->fetch(PDO::FETCH_NUM);
            return $result ?: false;
        }
        return false;
    }

    public static function freeResult($stmt) {
        // PDO statements don't need explicit freeing
        return true;
    }

    public static function error() {
        if (self::$pdo) {
            $error = self::$pdo->errorInfo();
            return $error[2] ?? 'Unknown error';
        }
        return 'No database connection';
    }

    public static function lastInsertId() {
        return self::$pdo ? self::$pdo->lastInsertId() : false;
    }

    public static function beginTransaction() {
        if (!self::$inTransaction) {
            self::connect()->beginTransaction();
            self::$inTransaction = true;
        }
    }

    public static function commit() {
        if (self::$inTransaction) {
            self::connect()->commit();
            self::$inTransaction = false;
        }
    }

    public static function rollback() {
        if (self::$inTransaction) {
            self::connect()->rollback();
            self::$inTransaction = false;
        }
    }
}

// Legacy function wrappers for backward compatibility
function dbi_connect($host, $login, $password, $database) {
    // Connection is handled automatically in BookLogDB::connect()
    return true;
}

function dbi_query($sql, $params = []) {
    // Handle both prepared statements and legacy queries
    if (is_array($params) && !empty($params)) {
        return BookLogDB::query($sql, $params);
    } else {
        return BookLogDB::query($sql);
    }
}

function dbi_fetch_row($stmt) {
    return BookLogDB::fetchRow($stmt);
}

function dbi_free_result($stmt) {
    return BookLogDB::freeResult($stmt);
}

function dbi_error() {
    return BookLogDB::error();
}

function dbi_insert_id() {
    return BookLogDB::lastInsertId();
}

// Additional helper functions
function dbi_execute($sql, $params = []) {
    return dbi_query($sql, $params);
}

function dbi_begin($table = '') {
    BookLogDB::beginTransaction();
}

function dbi_commit() {
    BookLogDB::commit();
}

function dbi_rollback() {
    BookLogDB::rollback();
}

function dbi_close() {
    // PDO handles connection closing automatically
    return true;
}
?>