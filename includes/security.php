<?php

/**
 * Security helpers for BookLog.
 * This file is now a shim that delegates to the Recipes\Security\Security class.
 */

use Recipes\Config;
use Recipes\Security\Security;

// Configure secure session settings before starting session
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', $isHttps ? '1' : '0');
ini_set('session.cookie_samesite', 'Strict');

// Optionally move sessions out from under the distro's system-wide session
// cleanup. Debian/Ubuntu sweep the default save_path (/var/lib/php/sessions)
// every 30 minutes using the php.ini gc_maxlifetime (1440s), ignoring any
// runtime ini_set() here — which silently expired sessions after ~24 minutes and
// turned legitimate form submissions into "Invalid CSRF token" failures.
//
// SESSION_SAVE_PATH must point OUTSIDE the web root. Everything under this app
// is web-served (there is no public/ subdirectory), so a session directory in
// the project is fetchable over HTTP by anyone who knows a session id, and
// .htaccess cannot be relied on to stop it — AllowOverride is commonly None.
$sessionDir = Config::get('SESSION_SAVE_PATH');
if (is_string($sessionDir) && $sessionDir !== '' && is_dir($sessionDir) && is_writable($sessionDir)) {
    ini_set('session.save_path', $sessionDir);
    // Debian ships session.gc_probability=0 in php.ini because its own timer does
    // the sweeping. That timer only knows about the default save_path, so our own
    // directory needs probabilistic GC turned back on or sessions never expire.
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    ini_set('session.gc_maxlifetime', '14400');     // 4 hours
    ini_set('session.cookie_lifetime', '14400');    // 4 hours
}

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
function enforceRateLimit(string $action, int $maxRequests, int $windowSeconds, bool $failClosed = false): void
{
    Security::enforceRateLimit($action, $maxRequests, $windowSeconds, $failClosed);
}
function die_miserable_death($error)
{
    Security::handleError($error, 500);
}
