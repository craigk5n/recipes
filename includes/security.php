<?php
/**
 * Security helpers for BookLog
 */

// Configure secure session settings before starting session
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', '1800'); // 30 minutes

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Destroy session completely
 */
function destroySession() {
    // Clear all session data
    $_SESSION = array();
    
    // Delete session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Generate CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize string input
 */
function sanitizeString($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize integer input
 */
function sanitizeInt($input) {
    return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
}

/**
 * Sanitize email
 */
function sanitizeEmail($input) {
    return filter_var($input, FILTER_SANITIZE_EMAIL);
}

/**
 * Generate secure random string
 */
function generateSecureToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Set HTTP security headers
 */
function setSecurityHeaders() {
    header("X-Frame-Options: DENY");
    header("X-Content-Type-Options: nosniff");
    header("X-XSS-Protection: 1; mode=block");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'");
}

/**
 * Application environment: 'development' or 'production'
 * Default to production for safety
 */
if (!defined('APP_ENV')) {
    define('APP_ENV', getenv('APP_ENV') ?: 'production');
}

/**
 * Log error to file and display generic error message
 * Prevents information leakage in production
 *
 * @param string $error Detailed error message (logged but not displayed)
 * @param int $status HTTP status code (default 500)
 */
function handleError($error, $status = 500) {
    $logFile = __DIR__ . '/../logs/error.log';
    $timestamp = date('Y-m-d H:i:s');
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    // Create logs directory if it doesn't exist
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0750, true);
    }

    // Build log entry with full details
    $logEntry = sprintf(
        "[%s] %s | IP: %s | URI: %s\n%s\n%s\n",
        $timestamp,
        $status,
        $remoteAddr,
        $requestUri,
        $error,
        str_repeat('-', 80)
    );

    // Write to log file
    @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

    // Set HTTP status code
    http_response_code($status);

    // Display error (different for dev vs production)
    if (APP_ENV === 'development') {
        // In development, show detailed error
        echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
        echo "<h2>Application Error</h2>";
        echo "<p><strong>Status:</strong> " . htmlspecialchars((string)$status) . "</p>";
        echo "<p><strong>Error:</strong> " . htmlspecialchars($error) . "</p>";
        echo "<p><em>Check error log: logs/error.log</em></p>";
        echo "</body></html>";
    } else {
        // In production, show generic error
        echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
        echo "<h2>An Error Occurred</h2>";
        echo "<p>We apologize, but something went wrong. Please try again later.</p>";
        if ($status === 403) {
            echo "<p>Access denied.</p>";
        } elseif ($status === 404) {
            echo "<p>The requested resource was not found.</p>";
        } elseif ($status === 429) {
            echo "<p>Too many requests. Please try again later.</p>";
        }
        echo "</body></html>";
    }

    exit;
}

/**
 * Legacy wrapper for backward compatibility
 * @deprecated Use handleError() instead
 */
function die_miserable_death($error) {
    handleError($error, 500);
}

/**
 * Check rate limit for a specific action
 * Uses session to track requests per action type
 *
 * @param string $action Action identifier (e.g., 'import', 'upload', 'login')
 * @param int $maxRequests Maximum number of requests allowed
 * @param int $windowSeconds Time window in seconds
 * @return bool True if request is allowed, false if rate limited
 */
function checkRateLimit($action, $maxRequests, $windowSeconds) {
    $sessionKey = 'rate_limit_' . $action;
    $now = time();
    
    // Initialize rate limit tracking
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [
            'requests' => [],
            'blocked_until' => 0
        ];
    }
    
    $rateData = &$_SESSION[$sessionKey];
    
    // Check if currently blocked
    if ($rateData['blocked_until'] > $now) {
        // Log rate limit violation
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
        $logEntry = sprintf(
            "[%s] RATE LIMIT VIOLATION | Action: %s | IP: %s | URI: %s\n",
            date('Y-m-d H:i:s'),
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown'
        );
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        return false;
    }
    
    // Clean up old requests outside the time window
    $cutoff = $now - $windowSeconds;
    $rateData['requests'] = array_filter($rateData['requests'], function($timestamp) use ($cutoff) {
        return $timestamp > $cutoff;
    });
    
    // Check if we've exceeded the limit
    if (count($rateData['requests']) >= $maxRequests) {
        // Block for the duration of the window
        $rateData['blocked_until'] = $now + $windowSeconds;
        
        // Log rate limit violation
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }
        $logEntry = sprintf(
            "[%s] RATE LIMIT EXCEEDED | Action: %s | IP: %s | Count: %d | Window: %ds\n",
            date('Y-m-d H:i:s'),
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            count($rateData['requests']),
            $windowSeconds
        );
        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        return false;
    }
    
    // Record this request
    $rateData['requests'][] = $now;
    
    return true;
}

/**
 * Enforce rate limit or return 429 error
 *
 * @param string $action Action identifier
 * @param int $maxRequests Maximum number of requests allowed
 * @param int $windowSeconds Time window in seconds
 */
function enforceRateLimit($action, $maxRequests, $windowSeconds) {
    if (!checkRateLimit($action, $maxRequests, $windowSeconds)) {
        handleError("Rate limit exceeded. Please try again later.", 429);
    }
}
?>