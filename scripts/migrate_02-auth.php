<?php
/**
 * Database migration script for authentication system.
 * This script adds rec_users table and user_id columns to existing tables.
 */

declare(strict_types=1);

// Migrations change the schema; they run only from scripts/setup.php on the
// command line, never from a web request.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Only the autoloader: setup.php runs every migration in one process, and the
// full web bootstrap (rec_includes.php) redeclares functions and starts a
// session. require_once also lets this file run on its own from the CLI.
require_once __DIR__ . "/../vendor/autoload.php";

use Recipes\Database\Database;

echo "Starting authentication migration..." . PHP_EOL;

try {
    // 1. Create rec_users table
    echo "Creating rec_users table..." . PHP_EOL;
    Database::query("
        CREATE TABLE IF NOT EXISTS rec_users (
            user_id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            email VARCHAR(100),
            is_admin TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // 2. Add user_id to rec_recipe if it doesn't exist
    echo "Adding user_id to rec_recipe..." . PHP_EOL;
    $res = Database::query("SHOW COLUMNS FROM rec_recipe LIKE 'user_id'");
    if (!Database::fetchRow($res)) {
        Database::query("ALTER TABLE rec_recipe ADD COLUMN user_id INT DEFAULT NULL");
        Database::query("ALTER TABLE rec_recipe ADD INDEX (user_id)");
    }
    Database::freeResult($res);

    // 3. Add user_id to rec_note if it doesn't exist
    echo "Adding user_id to rec_note..." . PHP_EOL;
    $res = Database::query("SHOW COLUMNS FROM rec_note LIKE 'user_id'");
    if (!Database::fetchRow($res)) {
        Database::query("ALTER TABLE rec_note ADD COLUMN user_id INT DEFAULT NULL");
        Database::query("ALTER TABLE rec_note ADD INDEX (user_id)");
    }
    Database::freeResult($res);

    echo "✓ Migration completed successfully!" . PHP_EOL;
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
