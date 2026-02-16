<?php
/**
 * Backward compatibility wrapper for translate functions.
 * 
 * This file provides compatibility for existing code using translate().
 * New code should use \Recipes\I18n\t() directly.
 */

declare(strict_types=1);

use function Recipes\I18n\t;

/**
 * Legacy translate function - maps to new i18n system
 */
function translate(string $str): string
{
    // Map common legacy keys to new keys
    $keyMap = [
        'Home' => 'navigation.home',
        'Yes' => 'navigation.yes',
        'No' => 'navigation.no',
        'Database error' => 'errors.db_error',
    ];
    
    $key = $keyMap[$str] ?? $str;
    return t($key);
}
