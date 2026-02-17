<?php
/**
 * This file loads configuration settings from environment variables or
 * the data file settings.php and sets up some needed variables.
 *
 * This file is now a shim that delegates to the Recipes\Config class.
 */

use Recipes\Config;

// Load settings via the new Config class
$appConfig = Config::load();

// Provide global variables for backward compatibility
$db_host = $appConfig['DB_HOST'];
$db_database = $appConfig['DB_DATABASE'];
$db_login = $appConfig['DB_LOGIN'];
$db_password = $appConfig['DB_PASSWORD'];

// Other global settings
$PROGRAM_VERSION = "v1.0.4";
$PROGRAM_DATE = "07 Jun 2006";
$PROGRAM_NAME = "WebCalendar $PROGRAM_VERSION ($PROGRAM_DATE)";
$PROGRAM_URL = "http://webcalendar.sourceforge.net/";
$TROUBLE_URL = "docs/WebCalendar-SysAdmin.html#trouble";

// Ensure APP_ENV is defined for other parts of the system (e.g., error handling)
if (!defined('APP_ENV')) {
    define('APP_ENV', $appConfig['APP_ENV']);
}

// Global functions for backward compatibility for some config-related calls
if (!function_exists('die_miserable_death')) {
    function die_miserable_death(string $error) {
        // This function is still in includes/security.php, so we'll call it there.
        // It will eventually delegate to Recipes\Security\Security::handleError
        // For now, assume includes/security.php has been loaded and define a fallback.
        if (function_exists('handleError')) {
            handleError($error, 500);
        } else {
            die("Fatal Error: " . $error);
        }
    }
}

// Global $_ENV and putenv() should be set by Config::load()

// The following comments will be picked up by update_translation.pl so
// translators will be aware that they also need to translate language names.
//
// translate("English")
// translate("Basque")
// translate("Bulgarian")
// translate("Catalan")
// translate("Chinese (Traditonal/Big5)")
// translate("Chinese (Simplified/GB2312)")
// translate("Czech")
// translate("Danish")
// translate("Dutch")
// translate("Estonian")
// translate("Finnish")
// translate("French")
// translate("Galician")
// translate("German")
// translate("Greek")
// translate("Holo (Taiwanese)")
// translate("Hungarian")
// translate("Icelandic")
// translate("Italian")
// translate("Japanese")
// translate("Korean")
// translate("Norwegian")
// translate("Polish")
// translate("Portuguese")
// translate("Portuguese/Brazil")
// translate("Romanian")
// translate("Russian")
// translate("Spanish")
// translate("Swedish")
// translate("Turkish")
// translate("Welsh")
