<?php

/**
 * Security helpers for BookLog.
 * This file is now a shim that delegates to the Recipes\Security\Security class.
 */

use Recipes\Security\Security;

// Configure secure session settings before starting session
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');

// Move sessions out from under Debian/Ubuntu's system-wide session cleanup cron.
// The default save_path (/var/lib/php/sessions) is garbage-collected every 30 min
// by /etc/cron.d/php using the php.ini gc_maxlifetime (1440s by default), which
// ignores any runtime ini_set() we do here. That was silently expiring sessions
// after ~24 min and turning legitimate form submissions into "Invalid CSRF token"
// failures. Using an app-owned directory lets our gc_maxlifetime actually apply.
$sessionDir = dirname(__DIR__) . '/storage/sessions';
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    ini_set('session.save_path', $sessionDir);
    // The app-owned dir is mode 1733 so www-data can write but cannot scandir(),
    // which means PHP's own GC can't enumerate files. Disable probabilistic GC
    // to avoid warnings; stale files accumulate harmlessly and can be pruned
    // out-of-band (e.g. by a periodic find -mtime command).
    ini_set('session.gc_probability', '0');
}
ini_set('session.gc_maxlifetime', '14400');     // 4 hours
ini_set('session.cookie_lifetime', '14400');    // 4 hours

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// These functions are kept for backward compatibility.
// New code should use the Security class directly.

function destroySession(): void
{
    Security::destroySession();
}
function generateCsrfToken(): string
{
    return Security::generateCsrfToken();
}
function validateCsrfToken(string $token): bool
{
    return Security::validateCsrfToken($token);
}
function sanitizeString($input): string
{
    return Security::sanitizeString((string)$input);
}
function sanitizeInt($input): int
{
    return Security::sanitizeInt($input);
}
function sanitizeEmail($input): string
{
    return Security::sanitizeEmail((string)$input);
}
function generateSecureToken(int $length = 32): string
{
    return Security::generateSecureToken($length);
}
function setSecurityHeaders(): void
{
    Security::setSecurityHeaders();
}
function handleError(string $error, int $status = 500): void
{
    Security::handleError($error, $status);
}
function checkRateLimit(string $action, int $maxRequests, int $windowSeconds): bool
{
    return Security::checkRateLimit($action, $maxRequests, $windowSeconds);
}
function enforceRateLimit(string $action, int $maxRequests, int $windowSeconds): void
{
    Security::enforceRateLimit($action, $maxRequests, $windowSeconds);
}
function die_miserable_death($error)
{
    Security::handleError($error, 500);
}
