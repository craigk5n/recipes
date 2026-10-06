<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Recipes\Config;

class ConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('SESSION_SAVE_PATH');
        putenv('LOG_PATH');
        unset($_ENV['SESSION_SAVE_PATH'], $_ENV['LOG_PATH']);
        Config::reset();
    }

    public function testGetReadsKeysNotLoadedExplicitlyFromEnvironment(): void
    {
        putenv('SESSION_SAVE_PATH=/var/lib/recipes-sessions');
        Config::reset();
        $this->assertSame('/var/lib/recipes-sessions', Config::get('SESSION_SAVE_PATH'));
    }

    public function testGetReadsKeysFromEnvSuperglobal(): void
    {
        $_ENV['LOG_PATH'] = '/var/log/recipes';
        Config::reset();
        $this->assertSame('/var/log/recipes', Config::get('LOG_PATH'));
    }

    public function testGetReturnsDefaultWhenUnset(): void
    {
        $this->assertSame('fallback', Config::get('LOG_PATH', 'fallback'));
    }
}
