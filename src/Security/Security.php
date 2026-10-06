<?php

declare(strict_types=1);

namespace Recipes\Security;

use Exception;
use Recipes\Config;
use RuntimeException;

class Security
{
    /**
     * Destroy the current session: clear its data, expire the browser cookie,
     * and discard the server-side session file.
     */
    public static function destroySession(): void
    {
        $_SESSION = [];

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // Expire the cookie using the attributes it was set with. Passing
        // different ones leaves the original cookie in place, since browsers
        // match on name + path + domain.
        if (ini_get('session.use_cookies') && isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 3600,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?: 'Strict',
            ]);
            unset($_COOKIE[session_name()]);
        }

        session_destroy();
    }

    /**
     * Generate CSRF token.
     */
    public static function generateCsrfToken(): string
    {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token.
     */
    public static function validateCsrfToken(string $token): bool
    {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize string input.
     */
    public static function sanitizeString(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * True only for absolute http:// or https:// URLs. Use before putting a
     * user-supplied URL in an href or fetching it, since FILTER_VALIDATE_URL
     * alone accepts javascript:, file:, data: and other schemes.
     */
    public static function isHttpUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && preg_match('#^https?://#i', $url) === 1;
    }

    /**
     * Encode data as JSON that is safe to place inside a <script> element:
     * <, >, & and quotes are emitted as \u escapes so the text can never
     * close the tag or open an HTML comment.
     */
    public static function jsonForScriptTag(mixed $data): string
    {
        return (string)json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
    }

    /**
     * Sanitize integer input.
     */
    public static function sanitizeInt(string|int|float $input): int
    {
        return (int)filter_var($input, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitize email.
     */
    public static function sanitizeEmail(string $input): string
    {
        $sanitized = filter_var($input, FILTER_SANITIZE_EMAIL);
        return $sanitized === false ? '' : $sanitized; // filter_var returns false on failure
    }

    /**
     * Generate secure random string.
     */
    public static function generateSecureToken(int $length = 32): string
    {
        try {
            return bin2hex(random_bytes($length));
        } catch (Exception $e) {
            throw new RuntimeException("Could not generate secure token: " . $e->getMessage());
        }
    }

    /**
     * Set HTTP security headers.
     */
    public static function setSecurityHeaders(): void
    {
        header("X-Frame-Options: DENY");
        header("X-Content-Type-Options: nosniff");
        header("X-XSS-Protection: 1; mode=block");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: http: https:; font-src 'self'");
    }

    /**
     * Log error to file and display generic error message.
     */
    public static function handleError(string $error, int $status = 500): void
    {
        // ... (Error handling logic from includes/security.php) ...
        // Note: For now, I'll copy the existing logic directly.
        // It should be refactored further to use a proper PSR-3 logger.

        $logFile = __DIR__ . '/../../logs/error.log'; // Adjust path for src/Security
        $timestamp = date('Y-m-d H:i:s');
        $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }

        $logEntry = sprintf(
            "[%s] %s | IP: %s | URI: %s
%s
%s
",
            $timestamp,
            $status,
            $remoteAddr,
            $requestUri,
            $error,
            str_repeat('-', 80)
        );

        @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
        error_log("Recipe App Error: " . $error);

        http_response_code($status);

        if (Config::get('APP_ENV') === 'development') {
            echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
            echo "<h2>Application Error</h2>";
            echo "<p><strong>Status:</strong> " . htmlspecialchars((string)$status) . "</p>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($error) . "</p>";
            echo "<p><em>Check error log: logs/error.log</em></p>";
            echo "</body></html>";
        } else {
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
     * Check rate limit for a specific action.
     */
    public static function checkRateLimit(string $action, int $maxRequests, int $windowSeconds): bool
    {
        $sessionKey = 'rate_limit_' . $action;
        $now = time();
        
        if (!isset($_SESSION[$sessionKey])) {
            $_SESSION[$sessionKey] = [
                'requests' => [],
                'blocked_until' => 0
            ];
        }
        
        $rateData = &$_SESSION[$sessionKey];
        
        if ($rateData['blocked_until'] > $now) {
            $logFile = __DIR__ . '/../../logs/security.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0750, true);
            }
            $logEntry = sprintf(
                "[%s] RATE LIMIT VIOLATION | Action: %s | IP: %s | URI: %s
",
                date('Y-m-d H:i:s'),
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['REQUEST_URI'] ?? 'unknown'
            );
            @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            
            return false;
        }
        
        $cutoff = $now - $windowSeconds;
        $rateData['requests'] = array_filter($rateData['requests'], function($timestamp) use ($cutoff) {
            return $timestamp > $cutoff;
        });
        
        if (count($rateData['requests']) >= $maxRequests) {
            $rateData['blocked_until'] = $now + $windowSeconds;
            
            $logFile = __DIR__ . '/../../logs/security.log';
            $logDir = dirname($logFile);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0750, true);
            }
            $logEntry = sprintf(
                "[%s] RATE LIMIT EXCEEDED | Action: %s | IP: %s | Count: %d | Window: %ds
",
                date('Y-m-d H:i:s'),
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                count($rateData['requests']),
                $windowSeconds
            );
            @file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
            
            return false;
        }
        
        $rateData['requests'][] = $now;
        
        return true;
    }

    /**
     * Enforce rate limit or return 429 error.
     */
    public static function enforceRateLimit(string $action, int $maxRequests, int $windowSeconds): void
    {
        if (!self::checkRateLimit($action, $maxRequests, $windowSeconds)) {
            self::handleError("Rate limit exceeded. Please try again later.", 429);
        }
    }
}
