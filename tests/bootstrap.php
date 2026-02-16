<?php
declare(strict_types=1);

date_default_timezone_set("America/New_York");
$title = 'k5n Recipes';

// Define some globals needed by functions
$TROUBLE_URL = "";

include_once __DIR__ . "/../includes/security.php";
// Mock session for testing if not already started
if (session_status() === PHP_SESSION_NONE) {
    $_SESSION = [];
}

include_once __DIR__ . "/../includes/config.php";
include_once __DIR__ . "/../includes/pdo_db.php";
include_once __DIR__ . "/../includes/functions.php";
include_once __DIR__ . "/../includes/dbtable.php";
// skip includes/connect.php as it tries to connect to real DB

// Initialize i18n system
require_once __DIR__ . '/../src/I18n/Translator.php';
Recipes\I18n\initTranslator(__DIR__ . '/../translations');
