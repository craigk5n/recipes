<?php
/**
 * Database migration script to add rec_category column.
 */

declare(strict_types=1);

include __DIR__ . "/../rec_includes.php";

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
