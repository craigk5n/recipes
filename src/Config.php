<?php

declare(strict_types=1);

namespace Recipes;

use RuntimeException;

class Config
{
    private static ?array $settings = null;

    /**
     * Load configuration settings from .env file, environment variables,
     * and legacy settings.php file.
     *
     * Settings are loaded once and cached.
     */
    public static function load(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        self::$settings = [];

        // 1. Load from .env file
        self::loadEnvFile();

        // 2. Load from environment variables (already handled by loadEnvFile for some)
        //    Ensure all relevant DB settings are read.
        self::$settings['DB_HOST'] = getenv('DB_HOST') ?: null;
        self::$settings['DB_DATABASE'] = getenv('DB_DATABASE') ?: null;
        self::$settings['DB_LOGIN'] = getenv('DB_LOGIN') ?: null;
        self::$settings['DB_PASSWORD'] = getenv('DB_PASSWORD') ?: null;

        // 3. Load from legacy settings.php file if environment variables are not set
        $legacySettings = self::parseLegacySettingsFile();
        self::$settings['DB_HOST'] = self::$settings['DB_HOST'] ?? $legacySettings['db_host'] ?? null;
        self::$settings['DB_DATABASE'] = self::$settings['DB_DATABASE'] ?? $legacySettings['db_database'] ?? null;
        self::$settings['DB_LOGIN'] = self::$settings['DB_LOGIN'] ?? $legacySettings['db_login'] ?? null;
        self::$settings['DB_PASSWORD'] = self::$settings['DB_PASSWORD'] ?? $legacySettings['db_password'] ?? null;
        
        // 4. Load AUTH_MODE and ACCESS_PIN
        self::$settings['AUTH_MODE'] = getenv('AUTH_MODE') ?: ($_ENV['AUTH_MODE'] ?? 'open');
        self::$settings['ACCESS_PIN'] = getenv('ACCESS_PIN') ?: ($_ENV['ACCESS_PIN'] ?? null);

        // Set application environment
        self::$settings['APP_ENV'] = getenv('APP_ENV') ?: 'production';
        if (!defined('APP_ENV')) {
            define('APP_ENV', self::$settings['APP_ENV']);
        }

        return self::$settings;
    }

    /**
     * Get a configuration setting.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::load(); // Ensure settings are loaded
        return self::$settings[$key] ?? $default;
    }

    /**
     * Load environment variables from .env file into $_ENV and putenv().
     */
    private static function loadEnvFile(string $file = '.env'): void
    {
        $envFile = dirname(__DIR__) . '/' . $file;
        if (!file_exists($envFile)) {
            return;
        }
        
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Skip comments and empty lines
            if (str_starts_with(trim($line), '#') || empty(trim($line))) {
                continue;
            }
            
            // Parse KEY=value
            if (str_contains($line, '=')) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                    (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                    $value = substr($value, 1, -1);
                }
                
                // Set environment variable if not already set
                if (getenv($key) === false) {
                    putenv("{$key}={$value}");
                    $_ENV[$key] = $value;
                }
            }
        }
    }

    /**
     * Parse legacy settings.php file.
     */
    private static function parseLegacySettingsFile(): array
    {
        $settings = [];
        $settings_file = dirname(__DIR__) . '/includes/settings.php';

        if (!file_exists($settings_file)) {
            return $settings;
        }

        $content = file_get_contents($settings_file);
        if ($content === false) {
            return $settings;
        }

        // Replace any combination of carriage return and new line
        // with a single new line.
        $content = preg_replace("/[\r\n]+/", "\n", $content);

        // Split the data into lines.
        $configLines = explode("\n", $content);

        foreach ($configLines as $buffer) {
            $buffer = trim($buffer);
            if (str_starts_with($buffer, '#') || str_starts_with($buffer, '<?') || str_starts_with($buffer, '?>') || empty($buffer)) {
                continue;
            }
            if (preg_match("/(\S+):\s*(\S+)/", $buffer, $matches)) {
                $settings[$matches[1]] = $matches[2];
            }
        }
        return $settings;
    }
}
