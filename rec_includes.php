<?php
date_default_timezone_set("America/New_York");
$title = 'k5n Recipes';

$LANGUAGE = 'English-US';

include "includes/security.php";
include "includes/config.php";
include "includes/pdo_db.php";
include "includes/functions.php";
include "includes/dbtable.php";
include "includes/connect.php";

load_global_settings ();
load_user_preferences ();

include "includes/translate.php";

// Set security headers
setSecurityHeaders();

?>
