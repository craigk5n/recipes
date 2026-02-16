<?php
/**
 * Tests for the I18n Translator
 */

use PHPUnit\Framework\TestCase;
use Recipes\I18n\Translator;
use Recipes\I18n\Locale;
use Recipes\I18n\MessageParameters;
use function Recipes\I18n\t;
use function Recipes\I18n\et;

require_once __DIR__ . '/../src/I18n/Translator.php';

class TranslatorTest extends TestCase
{
    private Translator $translator;
    
    protected function setUp(): void
    {
        $this->translator = new Translator(__DIR__ . '/../translations', Locale::ENGLISH);
    }
    
    public function testSimpleTranslation(): void
    {
        $result = $this->translator->trans('navigation.home');
        $this->assertEquals('Home', $result);
    }
    
    public function testTranslationWithParameters(): void
    {
        $result = $this->translator->trans('phrases.serves', ['count' => 4]);
        $this->assertEquals('Serves 4', $result);
    }
    
    public function testNestedTranslationKey(): void
    {
        $result = $this->translator->trans('recipe.title');
        $this->assertEquals('Title', $result);
    }
    
    public function testMissingTranslationReturnsKey(): void
    {
        $result = $this->translator->trans('nonexistent.key');
        $this->assertEquals('nonexistent.key', $result);
    }
    
    public function testLocaleFromBrowser(): void
    {
        // English locale
        $locale = Locale::fromBrowser('en-US,en;q=0.9');
        $this->assertEquals(Locale::ENGLISH, $locale);
        
        // Spanish locale
        $locale = Locale::fromBrowser('es-ES,es;q=0.9,en;q=0.8');
        $this->assertEquals(Locale::SPANISH, $locale);
    }
    
    public function testLocaleFallback(): void
    {
        // Test that unknown locale falls back to default
        $locale = Locale::fromBrowser('xx-XX,xx;q=0.9');
        $this->assertEquals(Locale::default(), $locale);
    }
    
    public function testHasTranslation(): void
    {
        $this->assertTrue($this->translator->has('navigation.home'));
        $this->assertFalse($this->translator->has('nonexistent.key'));
    }
    
    public function testMessageParameters(): void
    {
        $params = new MessageParameters(['name' => 'John', 'count' => 5]);
        
        $this->assertEquals('John', $params->get('name'));
        $this->assertEquals(5, $params->get('count'));
        $this->assertNull($params->get('missing'));
    }
    
    public function testGlobalHelperFunctions(): void
    {
        // Reset the singleton
        $GLOBALS['_translator_instance'] = null;
        
        // Test t() function
        $result = t('navigation.home');
        $this->assertEquals('Home', $result);
        
        // Test with parameters
        $result = t('phrases.serves', ['count' => 8]);
        $this->assertEquals('Serves 8', $result);
    }
}
