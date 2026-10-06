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
     * Queue an error message for the next page view. Messages travel in the
     * session rather than the URL, so a crafted link cannot make the site
     * display attacker-chosen text.
     */
    public static function flashError(string $message): void
    {
        $_SESSION['flash_error'] = $message;
    }

    /**
     * Return and clear the queued error message, if any.
     */
    public static function takeFlashError(): ?string
    {
        $message = $_SESSION['flash_error'] ?? null;
        unset($_SESSION['flash_error']);
        return is_string($message) ? $message : null;
    }

    /**
     * Return $target if it is a plain page of this app (e.g. "view.php?id=3"),
     * otherwise $default. Prevents open redirects through user-supplied
     * return URLs: no scheme, host, leading slash, backslash, "..", or
     * control characters are allowed.
     */
    public static function localRedirect(?string $target, string $default = 'index.php'): string
    {
        if ($target === null || preg_match('#^[A-Za-z0-9_-]+\.php(?:\?[^\s\\\\]*)?$#D', $target) !== 1) {
            return $default;
        }
        return $target;
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
        header("X-XSS-Protection: 0");
        header("Referrer-Policy: strict-origin-when-cross-origin");
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob: http: https:; font-src 'self'");
    }

    /**
     * Log error to file and display generic error message.
     */
    public static function handleError(string $error, int $status = 500): void
    {
        self::writeLog('error.log', sprintf(
            "[%s] %s | IP: %s | URI: %s\n%s\n%s\n",
            date('Y-m-d H:i:s'),
            $status,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['REQUEST_URI'] ?? 'unknown',
            $error,
            str_repeat('-', 80)
        ));

        http_response_code($status);

        if (Config::get('APP_ENV') === 'development') {
            echo "<!DOCTYPE html><html><head><title>Error</title></head><body>";
            echo "<h2>Application Error</h2>";
            echo "<p><strong>Status:</strong> " . htmlspecialchars((string)$status) . "</p>";
            echo "<p><strong>Error:</strong> " . htmlspecialchars($error) . "</p>";
            echo "<p><em>Check the error log (LOG_PATH, or the PHP error log).</em></p>";
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
     * Append to a log file in LOG_PATH. Without LOG_PATH (or if it isn't
     * writable) the entry goes to PHP's error log instead: everything under
     * this app is web-served, so logs must never be written inside it.
     */
    public static function writeLog(string $file, string $entry): void
    {
        $dir = Config::get('LOG_PATH');
        if (is_string($dir) && $dir !== '' && is_dir($dir) && is_writable($dir)) {
            if (@file_put_contents($dir . '/' . basename($file), $entry, FILE_APPEND | LOCK_EX) !== false) {
                return;
            }
        }
        error_log('Recipe App [' . $file . '] ' . trim($entry));
    }

    /**
     * Check rate limit for an action, per client IP.
     *
     * State lives in files under RATE_LIMIT_PATH (default: a private
     * directory in the system temp dir), not in the session, so a client
     * cannot reset its count by discarding the session cookie.
     * Fails open (allows the request) if the state cannot be stored.
     */
    public static function checkRateLimit(
        string $action,
        int $maxRequests,
        int $windowSeconds,
        bool $failClosed = false
    ): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'cli';
        $dir = self::rateLimitDir();
        $handle = $dir === null ? false : @fopen($dir . '/' . hash('sha256', $action . '|' . self::clientKey($ip)), 'c+');
        if ($handle === false) {
            error_log("Recipe App: rate limit state unavailable for '$action' (RATE_LIMIT_PATH not writable?)");
            return !$failClosed;
        }
        if ($dir !== null && random_int(1, 100) === 1) {
            self::pruneRateLimitDir($dir);
        }

        try {
            flock($handle, LOCK_EX);
            $data = json_decode((string)stream_get_contents($handle), true);
            $requests = is_array($data['requests'] ?? null) ? $data['requests'] : [];
            $blockedUntil = (int)($data['blocked_until'] ?? 0);
            $now = time();

            $allowed = true;
            if ($blockedUntil > $now) {
                $allowed = false;
            } else {
                $cutoff = $now - $windowSeconds;
                $requests = array_values(array_filter($requests, fn($t) => $t > $cutoff));
                if (count($requests) >= $maxRequests) {
                    $blockedUntil = $now + $windowSeconds;
                    $allowed = false;
                } else {
                    $requests[] = $now;
                }
            }

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, (string)json_encode(['requests' => $requests, 'blocked_until' => $blockedUntil]));
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }

        if (!$allowed) {
            self::writeLog('security.log', sprintf(
                "[%s] RATE LIMIT | Action: %s | IP: %s | URI: %s\n",
                date('Y-m-d H:i:s'),
                $action,
                $ip,
                $_SERVER['REQUEST_URI'] ?? 'unknown'
            ));
        }
        return $allowed;
    }

    /**
     * Rate-limit identity for an address. IPv6 clients usually control a whole
     * /64, so they are grouped by that prefix to stop address rotation.
     */
    private static function clientKey(string $ip): string
    {
        $packed = @inet_pton($ip);
        if ($packed !== false && strlen($packed) === 16) {
            return bin2hex(substr($packed, 0, 8)) . '/64';
        }
        return $ip;
    }

    /**
     * Delete state files untouched for a day so the directory cannot grow
     * without bound.
     */
    private static function pruneRateLimitDir(string $dir): void
    {
        $cutoff = time() - 86400;
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (@filemtime($file) < $cutoff) {
                @unlink($file);
            }
        }
    }

    private static function rateLimitDir(): ?string
    {
        $dir = Config::get('RATE_LIMIT_PATH');
        if (!is_string($dir) || $dir === '') {
            // Include the user id: a directory created by a CLI run as another
            // user would otherwise be unwritable by the web server.
            $uid = function_exists('posix_geteuid') ? (string)posix_geteuid() : get_current_user();
            $dir = sys_get_temp_dir() . '/recipes-ratelimit-' . $uid . '-' . substr(hash('sha256', __DIR__), 0, 12);
        }
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            error_log("Recipe App: cannot create rate limit directory $dir");
            return null;
        }
        return $dir;
    }

    /**
     * Enforce rate limit or return 429 error.
     */
    public static function enforceRateLimit(
        string $action,
        int $maxRequests,
        int $windowSeconds,
        bool $failClosed = false
    ): void {
        if (!self::checkRateLimit($action, $maxRequests, $windowSeconds, $failClosed)) {
            self::handleError("Rate limit exceeded. Please try again later.", 429);
        }
    }
}
