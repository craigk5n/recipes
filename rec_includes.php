<?php
declare(strict_types=1);

date_default_timezone_set("America/New_York");
$title = 'k5n Recipes';

// Autoload modern classes
require_once __DIR__ . '/src/I18n/Translator.php';

include "includes/security.php";
include "includes/config.php";
include "includes/pdo_db.php";
include "includes/functions.php";
include "includes/dbtable.php";
include "includes/connect.php";

// Initialize i18n
Recipes\I18n\initTranslator(__DIR__ . '/translations');

// Legacy compatibility - provides translate() function
include "includes/translate_compat.php";

// Set security headers
setSecurityHeaders();

?>
