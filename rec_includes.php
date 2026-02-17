<?php
declare(strict_types=1);

date_default_timezone_set("America/New_York");
$title = 'k5n Recipes';

// Load composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Explicitly load files with functions (PSR-4 only handles classes)
require_once __DIR__ . '/src/I18n/Translator.php';
require_once __DIR__ . '/src/Auth/AuthManager.php';
require_once __DIR__ . '/src/Security/Security.php';
require_once __DIR__ . '/src/Config.php';

include "includes/security.php";
include "includes/config.php";
include "includes/pdo_db.php";
include "includes/connect.php";

// Initialize i18n
Recipes\I18n\initTranslator(__DIR__ . '/translations');

// Set security headers
setSecurityHeaders();

?>
