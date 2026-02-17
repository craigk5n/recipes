<?php
/**
 * Interactive setup script for k5n Recipes.
 * - Creates the database if it doesn't exist.
 * - Runs all necessary database migrations.
 */

declare(strict_types=1);

// This script should only be run from the command line.
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.");
}

// Manually include necessary files without the full bootstrap,
// as we might not have a database connection yet.
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Config.php';
use Recipes\Config;

// Define a minimal error handler for this script
function setup_error(string $message): void {
    echo "✗ ERROR: {$message}\n";
    exit(1);
}

echo "--- k5n Recipes Interactive Setup ---\n\n";

// --- Step 1: Load environment configuration ---
echo "1. Loading database configuration...\n";
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    echo "   - .env file not found. Please create one from .env.example.\n";
    // We will ask for credentials interactively.
} else {
    // Manually parse the .env file
    // Config::load() will do this for us
    Config::load();
}

$dbHost = Config::get('DB_HOST');
$dbUser = Config::get('DB_LOGIN');
$dbPass = Config::get('DB_PASSWORD');
$dbName = Config::get('DB_DATABASE');
// Interactively ask for any missing credentials
if (!$dbHost) $dbHost = readline(" > DB Host (e.g., 127.0.0.1): ");
if (!$dbUser) $dbUser = readline(" > DB User: ");
if (!$dbPass) $dbPass = readline(" > DB Password: ");
if (!$dbName) $dbName = readline(" > DB Name: ");

if (empty($dbHost) || empty($dbUser) || empty($dbName)) {
    setup_error("Database host, user, and name are required.");
}

echo "   - Configuration loaded.\n";

// --- Step 2: Create database if it doesn't exist ---
echo "\n2. Connecting to MySQL and creating database if needed...\n";
try {
    $pdo = new PDO("mysql:host={$dbHost}", Config::get('DB_LOGIN'), Config::get('DB_PASSWORD'));
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   - Database '{$dbName}' is ready.\n";
} catch (PDOException $e) {
    setup_error("Could not connect to MySQL or create database: " . $e->getMessage());
}

// --- Step 3: Run Migrations ---
echo "\n3. Running database migrations...\n";
// Now we can connect to the specific database
$GLOBALS['db_host'] = Config::get('DB_HOST');
$GLOBALS['db_login'] = Config::get('DB_LOGIN');
$GLOBALS['db_password'] = Config::get('DB_PASSWORD');
$GLOBALS['db_database'] = Config::get('DB_DATABASE');

// Include only what's necessary to run migrations
require_once __DIR__ . '/../src/Database/Database.php';

$migrations = glob(__DIR__ . '/migrate_*.php');
sort($migrations);

foreach ($migrations as $migration) {
    echo "   - Running " . basename($migration) . "...\n";
    try {
        // Use a function to isolate the scope of the included file
        $runMigration = function($file) {
            include $file;
        };
        $runMigration($migration);
        echo "     ✓ Success.\n";
    } catch (Exception $e) {
        setup_error("Migration " . basename($migration) . " failed: " . $e->getMessage());
    }
}

echo "\n✓ Setup completed successfully!\n";
echo "You can now access the application in your browser.\n";

exit(0);
