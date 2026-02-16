<?php
/**
 * Core utility functions for the Recipes application.
 *
 * This file contains general-purpose utility functions used throughout
 * the application. Previously contained extensive WebCalendar code that
 * has been removed as part of the refactoring.
 *
 * @package Recipes
 * @version 2.0.0
 */

declare(strict_types=1);

// Prevent direct access to this file
if (!empty($_SERVER['PHP_SELF']) && str_contains($_SERVER['PHP_SELF'], '/includes/')) {
    die("You can't access this file directly!");
}

/**
 * Gets the browser's preferred language from HTTP headers.
 *
 * Parses the Accept-Language header and returns the best matching
 * language code based on configured browser language mappings.
 *
 * @return string The language code (e.g., 'English-US') or 'none' if not determined
 */
function get_browser_language(): string
{
    $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
    
    if (empty($acceptLanguage)) {
        return 'none';
    }
    
    // Get browser language mappings from global config
    global $browser_languages;
    
    $languages = explode(',', $acceptLanguage);
    
    foreach ($languages as $lang) {
        // Remove quality values (e.g., "en-US;q=0.9" -> "en-US")
        $lang = strtolower(trim(preg_replace('/;.*/', '', $lang)));
        
        if (!empty($browser_languages[$lang])) {
            return $browser_languages[$lang];
        }
    }
    
    return 'none';
}

/**
 * Loads global application settings.
 *
 * Note: This function is retained for backward compatibility but is
 * currently a no-op. Settings are now loaded from config.php and
 * environment variables.
 *
 * @deprecated Settings are now loaded via Config class in config.php
 * @return void
 */
function load_global_settings(): void
{
    // Settings are now loaded from config.php via environment variables
    // This function is kept for backward compatibility
    
    // Ensure application_name is set for translation compatibility
    if (empty($GLOBALS['application_name'])) {
        $GLOBALS['application_name'] = 'Recipes';
    }
}

/**
 * Loads user-specific preferences.
 *
 * Note: This function is retained for backward compatibility but is
 * currently a no-op. The recipes app is single-user and doesn't
 * store user preferences in the database.
 *
 * @deprecated User preferences are not used in this application
 * @return void
 */
function load_user_preferences(): void
{
    // The recipes app is single-user and doesn't use user preferences
    // This function is kept for backward compatibility
    
    // Set default date format preferences if not already set
    $GLOBALS['DATE_FORMAT_MY'] ??= '__month__ __yyyy__';
    $GLOBALS['DATE_FORMAT_MD'] ??= '__month__ __dd__';
}
