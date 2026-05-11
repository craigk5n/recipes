<?php
/**
 * Script to copy frontend assets from vendor to pub directory.
 * Run this after composer install/update.
 */

declare(strict_types=1);

$baseDir = dirname(__DIR__);
$vendorDir = $baseDir . '/vendor';
$pubDir = $baseDir . '/pub';

// Define asset mappings: source => destination
$assets = [
    // Bootstrap CSS
    $vendorDir . '/twbs/bootstrap/dist/css/bootstrap.min.css' => $pubDir . '/bootstrap.min.css',
    $vendorDir . '/twbs/bootstrap/dist/css/bootstrap.min.css.map' => $pubDir . '/bootstrap.min.css.map',
    
    // Bootstrap JS
    $vendorDir . '/twbs/bootstrap/dist/js/bootstrap.bundle.min.js' => $pubDir . '/bootstrap.bundle.min.js',
    $vendorDir . '/twbs/bootstrap/dist/js/bootstrap.bundle.min.js.map' => $pubDir . '/bootstrap.bundle.min.js.map',

    // SortableJS (drag-and-drop for ingredient reordering)
    $vendorDir . '/sortablejs/sortablejs/Sortable.min.js' => $pubDir . '/Sortable.min.js',
];

$created = 0;
$errors = [];

foreach ($assets as $source => $destination) {
    if (!file_exists($source)) {
        $errors[] = "Source file not found: {$source}";
        continue;
    }
    
    if (!copy($source, $destination)) {
        $errors[] = "Failed to copy: {$source} -> {$destination}";
    } else {
        echo "Copied: " . basename($destination) . PHP_EOL;
        $created++;
    }
}

if (empty($errors)) {
    echo PHP_EOL . "✓ Successfully installed {$created} assets to pub/" . PHP_EOL;
    exit(0);
} else {
    echo PHP_EOL . "✗ Errors occurred:" . PHP_EOL;
    foreach ($errors as $error) {
        echo "  - {$error}" . PHP_EOL;
    }
    exit(1);
}
