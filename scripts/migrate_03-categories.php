<?php
/**
 * Database migration script to add rec_category column.
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

echo "Starting category migration..." . PHP_EOL;

try {
    // Add rec_category to rec_recipe if it doesn't exist
    echo "Adding rec_category to rec_recipe..." . PHP_EOL;
    $res = Database::query("SHOW COLUMNS FROM rec_recipe LIKE 'rec_category'");
    if (!Database::fetchRow($res)) {
        Database::query("ALTER TABLE rec_recipe ADD COLUMN rec_category VARCHAR(50) DEFAULT NULL");
        Database::query("ALTER TABLE rec_recipe ADD INDEX (rec_category)");
        echo "✓ rec_category column added." . PHP_EOL;
    } else {
        echo "- rec_category column already exists." . PHP_EOL;
    }
    Database::freeResult($res);

    echo "✓ Migration completed successfully!" . PHP_EOL;
} catch (Exception $e) {
    echo "✗ Migration failed: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
