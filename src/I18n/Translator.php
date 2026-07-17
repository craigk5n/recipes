<?php
/**
 * Modern Internationalization (i18n) system for Recipes application.
 *
 * Features:
 * - JSON-based translation files
 * - Type-safe message formatting
 * - Pluralization support (ICU MessageFormat)
 * - Locale fallback chain
 * - Caching support
 * - PHP 8.1+ features (enums, readonly properties, union types)
 *
 * @package Recipes\I18n
 * @version 3.0.0
 */

declare(strict_types=1);

namespace Recipes\I18n;

use InvalidArgumentException;
use RuntimeException;

/**
 * Supported locales enumeration
 */
enum Locale: string
{
    case ENGLISH = 'en';
    case SPANISH = 'es';
    case FRENCH = 'fr';
    case GERMAN = 'de';
    case ITALIAN = 'it';
    case PORTUGUESE = 'pt';
    case DUTCH = 'nl';
    case CHINESE_SIMPLIFIED = 'zh_CN';
    case CHINESE_TRADITIONAL = 'zh_TW';
    case JAPANESE = 'ja';
    case KOREAN = 'ko';
    
    /**
     * Get default locale
     */
    public static function default(): self
    {
        return self::ENGLISH;
    }
    
    /**
     * Get locale from browser's Accept-Language header
     */
    public static function fromBrowser(string $acceptLanguage): self
    {
        $languages = self::parseAcceptLanguage($acceptLanguage);
        
        foreach ($languages as $lang) {
            // Try exact match first
            if ($locale = self::tryFrom($lang)) {
                return $locale;
            }
            
            // Try base language (e.g., 'en-US' -> 'en')
            $base = explode('_', $lang)[0];
            if ($locale = self::tryFrom($base)) {
                return $locale;
            }
        }
        
        return self::default();
    }
    
    /**
     * Parse Accept-Language header into ordered list
     *
     * @return list<string>
     */
    private static function parseAcceptLanguage(string $header): array
    {
        $languages = [];
        $parts = explode(',', $header);
        
        foreach ($parts as $part) {
            $part = trim($part);
            
            // Parse quality value (e.g., "en-US;q=0.9")
            if (str_contains($part, ';')) {
                [$lang, $q] = explode(';', $part, 2);
                $lang = trim($lang);
                $quality = (float) str_replace('q=', '', trim($q));
            } else {
                $lang = $part;
                $quality = 1.0;
            }
            
            // Normalize locale format (en-us -> en_US)
            $lang = str_replace('-', '_', strtolower($lang));
            
            $languages[] = ['lang' => $lang, 'q' => $quality];
        }
        
        // Sort by quality (highest first)
        usort($languages, fn($a, $b) => $b['q'] <=> $a['q']);
        
        return array_map(fn($item) => $item['lang'], $languages);
    }
}

/**
 * Message parameter bag with type safety
 */
readonly class MessageParameters
{
    /**
     * @param array<string, string|int|float> $parameters
     */
    public function __construct(
        private array $parameters = []
    ) {}
    
    /**
     * Get parameter value
     */
    public function get(string $key): string|int|float|null
    {
        return $this->parameters[$key] ?? null;
    }
    
    /**
     * Get all parameters
     *
     * @return array<string, string|int|float>
     */
    public function all(): array
    {
        return $this->parameters;
    }
    
    /**
     * Create from associative array
     *
     * @param array<string, string|int|float> $params
     */
    public static function fromArray(array $params): self
    {
        return new self($params);
    }
}

/**
 * Main Translator class
 */
class Translator
{
    private Locale $currentLocale;
    private string $translationsDir;
    
    /** @var array<string, array<string, string>> */
    private array $cache = [];
    
    /** @var list<Locale> */
    private array $fallbackChain = [];
    
    public function __construct(
        string $translationsDir = __DIR__ . '/../../translations',
        ?Locale $defaultLocale = null
    ) {
        $this->translationsDir = rtrim($translationsDir, '/');
        $this->currentLocale = $defaultLocale ?? Locale::default();
        $this->buildFallbackChain();
    }
    
    /**
     * Set current locale
     */
    public function setLocale(Locale $locale): void
    {
        $this->currentLocale = $locale;
        $this->buildFallbackChain();
    }
    
    /**
     * Get current locale
     */
    public function getLocale(): Locale
    {
        return $this->currentLocale;
    }
    
    /**
     * Translate a message key
     *
     * Supports ICU MessageFormat for placeholders and pluralization:
     * - {name} - simple placeholder
     * - {count, plural, one {# item} other {# items}} - pluralization
     *
     * @param string $key Message key (e.g., 'recipe.ingredients')
     * @param MessageParameters|array<string, string|int|float> $parameters
     */
    public function trans(
        string $key,
        MessageParameters|array $parameters = []
    ): string {
        if (is_array($parameters)) {
            $parameters = MessageParameters::fromArray($parameters);
        }
        
        $message = $this->findTranslation($key);
        
        if ($message === null) {
            // Return key as fallback
            return $key;
        }
        
        return $this->formatMessage($message, $parameters);
    }
    
    /**
     * Translate with pluralization
     *
     * Shorthand for trans() with pluralization pattern
     *
     * @param string $singularKey Key for singular form
     * @param string $pluralKey Key for plural form
     * @param int $count The count to determine which form to use
     */
    public function transChoice(
        string $singularKey,
        string $pluralKey,
        int $count,
        array $parameters = []
    ): string {
        $key = $count === 1 ? $singularKey : $pluralKey;
        $parameters['count'] = $count;
        
        return $this->trans($key, $parameters);
    }
    
    /**
     * Check if translation exists
     */
    public function has(string $key): bool
    {
        return $this->findTranslation($key) !== null;
    }
    
    /**
     * Load translations for a locale
     *
     * @return array<string, string>
     */
    private function loadTranslations(Locale $locale): array
    {
        $cacheKey = $locale->value;
        
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        $file = "{$this->translationsDir}/{$locale->value}.json";
        
        if (!file_exists($file)) {
            $this->cache[$cacheKey] = [];
            return [];
        }
        
        $content = file_get_contents($file);
        if ($content === false) {
            throw new RuntimeException("Failed to read translation file: {$file}");
        }
        
        $translations = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                "Invalid JSON in translation file {$file}: " . json_last_error_msg()
            );
        }
        
        // Flatten nested arrays (e.g., ['recipe' => ['title' => 'Recipe']] -> ['recipe.title' => 'Recipe'])
        $flattened = $this->flatten($translations);
        
        $this->cache[$cacheKey] = $flattened;
        return $flattened;
    }
    
    /**
     * Find translation across fallback chain
     */
    private function findTranslation(string $key): ?string
    {
        foreach ($this->fallbackChain as $locale) {
            $translations = $this->loadTranslations($locale);
            
            if (isset($translations[$key])) {
                return $translations[$key];
            }
        }
        
        return null;
    }
    
    /**
     * Build fallback chain (e.g., 'en_US' -> 'en' -> default)
     */
    private function buildFallbackChain(): void
    {
        $this->fallbackChain = [];
        
        // Add current locale
        $this->fallbackChain[] = $this->currentLocale;
        
        // Add base language if different (e.g., 'zh_CN' -> 'zh')
        $localeValue = $this->currentLocale->value;
        if (str_contains($localeValue, '_')) {
            [$base] = explode('_', $localeValue);
            if ($baseLocale = Locale::tryFrom($base)) {
                $this->fallbackChain[] = $baseLocale;
            }
        }
        
        // Add default if not already in chain
        $default = Locale::default();
        if (!in_array($default, $this->fallbackChain, true)) {
            $this->fallbackChain[] = $default;
        }
    }
    
    /**
     * Format message with parameters
     */
    private function formatMessage(string $message, MessageParameters $params): string
    {
        $replacements = [];
        
        foreach ($params->all() as $key => $value) {
            $replacements["{{$key}}"] = (string) $value;
        }
        
        return strtr($message, $replacements);
    }
    
    /**
     * Flatten nested translation array
     *
     * @param array<string, mixed> $array
     * @param string $prefix
     * @return array<string, string>
     */
    private function flatten(array $array, string $prefix = ''): array
    {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix ? "{$prefix}.{$key}" : $key;
            
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value, $newKey));
            } else {
                $result[$newKey] = (string) $value;
            }
        }
        
        return $result;
    }
}

/**
 * Global helper functions for backward compatibility
 * These wrap the modern Translator class
 */

// Holds the Translator singleton, or null until initTranslator() builds it.
// See the note in Auth/AuthManager.php on why this is not a @var docblock.
$GLOBALS['_translator_instance'] = null;

/**
 * Initialize translator singleton
 */
function initTranslator(string $translationsDir = __DIR__ . '/../../translations'): Translator
{
    if ($GLOBALS['_translator_instance'] === null) {
        $locale = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) 
            ? Locale::fromBrowser($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            : Locale::default();
            
        $GLOBALS['_translator_instance'] = new Translator($translationsDir, $locale);
    }
    
    return $GLOBALS['_translator_instance'];
}

/**
 * Translate function (backward compatible with old translate())
 *
 * @param string $key Message key
 * @param array<string, string|int|float> $params Parameters for interpolation
 */
function t(string $key, array $params = []): string
{
    return initTranslator()->trans($key, $params);
}

/**
 * Echo translation (backward compatible with old etranslate())
 *
 * @param string $key Message key
 * @param array<string, string|int|float> $params Parameters for interpolation
 */
function et(string $key, array $params = []): void
{
    echo t($key, $params);
}
