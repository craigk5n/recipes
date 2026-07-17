<?php

/**
 * Compatibility shim for legacy database access.
 * Maps legacy BookLogDB and dbi_* functions to the modern Database class.
 */

use Recipes\Database\Database;

// Define legacy BookLogDB class as an alias to the new Database class
if (!class_exists('BookLogDB')) {
    class_alias(Database::class, 'BookLogDB');
}

/**
 * Legacy function wrappers for backward compatibility.
 * New code should use Recipes\Database\Database directly.
 */

function dbi_connect($host, $login, $password, $database)
{
    return Database::connect();
}

function dbi_query($sql, $params = [])
{
    return Database::query($sql, $params);
}

function dbi_fetch_row($stmt)
{
    return Database::fetchRow($stmt);
}

function dbi_free_result($stmt)
{
    return Database::freeResult($stmt);
}

function dbi_error()
{
    return Database::error();
}

function dbi_insert_id()
{
    return Database::lastInsertId();
}

function dbi_execute($sql, $params = [])
{
    return Database::query($sql, $params);
}

function dbi_begin($table = '')
{
    Database::beginTransaction();
}

function dbi_commit()
{
    Database::commit();
}

function dbi_rollback()
{
    Database::rollback();
}

function dbi_close()
{
    return true;
}
