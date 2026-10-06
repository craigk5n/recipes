<?php
use PHPUnit\Framework\TestCase;
use Recipes\Security\Security;

class SecurityTest extends TestCase {
    public function testDestroySessionClearsDataAndEndsSession() {
        // Regression: the destroySession() shim delegated to
        // Security::destroySession(), which was never ported to the class
        // during the PSR-4 refactor, so calling it was a fatal error.
        $this->assertTrue(method_exists(Security::class, 'destroySession'));

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['user_id'] = 42;

        destroySession();

        $this->assertSame([], $_SESSION);
        $this->assertSame(PHP_SESSION_NONE, session_status());

        // Restore the session the bootstrap started, so this test leaves global
        // state as it found it for whatever runs next.
        session_start();
    }

    public function testDestroySessionIsSafeWhenNoSessionIsActive() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        destroySession(); // must not warn or fatal

        $this->assertSame(PHP_SESSION_NONE, session_status());
        session_start();
    }

    public function testGenerateSecureTokenReturnsHexOfRequestedByteLength() {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', generateSecureToken());
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', generateSecureToken(8));
        $this->assertNotEquals(generateSecureToken(), generateSecureToken());
    }

    public function testGenerateSecureTokenRejectsInvalidLength() {
        // A bad argument is a programming error, so it must surface as-is
        // rather than be wrapped as "could not generate secure token".
        $this->expectException(ValueError::class);
        generateSecureToken(0);
    }

    public function testSanitizeString() {
        $input = " <script>alert('xss')</script> ";
        $expected = "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;";
        $this->assertEquals($expected, sanitizeString($input));
    }

    public function testSanitizeInt() {
        $this->assertEquals(123, sanitizeInt("123abc"));
        $this->assertEquals(-45678, sanitizeInt("-456.78"));
    }

    public function testCsrfTokenGeneration() {
        $token = generateCsrfToken();
        $this->assertNotEmpty($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes in hex
        $this->assertTrue(validateCsrfToken($token));
    }

    public function testInvalidCsrfToken() {
        $this->assertFalse(validateCsrfToken("invalid_token"));
    }

    private function withRateLimitDir(callable $test): void {
        $dir = sys_get_temp_dir() . '/recipes-rl-test-' . bin2hex(random_bytes(4));
        putenv("RATE_LIMIT_PATH=$dir");
        \Recipes\Config::reset();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        try {
            $test($dir);
        } finally {
            array_map('unlink', glob("$dir/*") ?: []);
            @rmdir($dir);
            putenv('RATE_LIMIT_PATH');
            \Recipes\Config::reset();
            unset($_SERVER['REMOTE_ADDR']);
        }
    }

    public function testRateLimitBlocksAfterMaxRequests() {
        $this->withRateLimitDir(function () {
            for ($i = 0; $i < 3; $i++) {
                $this->assertTrue(Security::checkRateLimit('login', 3, 60));
            }
            $this->assertFalse(Security::checkRateLimit('login', 3, 60));
        });
    }

    public function testRateLimitSurvivesLosingTheSession() {
        $this->withRateLimitDir(function () {
            for ($i = 0; $i < 3; $i++) {
                Security::checkRateLimit('login', 3, 60);
            }
            $_SESSION = []; // attacker drops the session cookie
            $this->assertFalse(Security::checkRateLimit('login', 3, 60));
        });
    }

    public function testRateLimitIsPerClientAndPerAction() {
        $this->withRateLimitDir(function () {
            for ($i = 0; $i < 3; $i++) {
                Security::checkRateLimit('login', 3, 60);
            }
            $this->assertTrue(Security::checkRateLimit('import', 3, 60));
            $_SERVER['REMOTE_ADDR'] = '198.51.100.9';
            $this->assertTrue(Security::checkRateLimit('login', 3, 60));
        });
    }

    public function testRateLimitGroupsIpv6ClientsByPrefix() {
        $this->withRateLimitDir(function () {
            $_SERVER['REMOTE_ADDR'] = '2001:4860:1:2::a';
            for ($i = 0; $i < 3; $i++) {
                Security::checkRateLimit('login', 3, 60);
            }
            // Same /64, different interface id: still the same client.
            $_SERVER['REMOTE_ADDR'] = '2001:4860:1:2:ffff::b';
            $this->assertFalse(Security::checkRateLimit('login', 3, 60));
        });
    }

    public function testRateLimitCanFailClosed() {
        putenv('RATE_LIMIT_PATH=/proc/no-such-dir/recipes');
        \Recipes\Config::reset();
        try {
            $this->assertTrue(Security::checkRateLimit('import', 3, 60));
            $this->assertFalse(Security::checkRateLimit('auth', 3, 60, true));
        } finally {
            putenv('RATE_LIMIT_PATH');
            \Recipes\Config::reset();
        }
    }

    public function testRateLimitStateIsNotKeptInWebRoot() {
        $this->withRateLimitDir(function (string $dir) {
            Security::checkRateLimit('login', 3, 60);
            $this->assertNotEmpty(glob("$dir/*"));
        });
    }

    public function testLocalRedirectKeepsAppRelativePaths() {
        $this->assertSame('view.php?id=3', Security::localRedirect('view.php?id=3'));
        $this->assertSame('index.php', Security::localRedirect('index.php'));
    }

    public function testLocalRedirectRejectsOffsiteTargets() {
        foreach ([
            'https://evil.example/', '//evil.example/', '/\\evil.example', '\\\\evil.example',
            'javascript:alert(1)', "view.php\r\nSet-Cookie: x=1", "view.php\n", '', null, '/etc/passwd', '../admin.php',
        ] as $target) {
            $this->assertSame('index.php', Security::localRedirect($target), var_export($target, true));
        }
    }

    public function testIsHttpUrlAcceptsWebUrls() {
        $this->assertTrue(Security::isHttpUrl('https://example.com/recipe/'));
        $this->assertTrue(Security::isHttpUrl('HTTP://example.com'));
    }

    public function testIsHttpUrlRejectsOtherSchemes() {
        $this->assertFalse(Security::isHttpUrl('javascript:alert(1)'));
        $this->assertFalse(Security::isHttpUrl('javascript://example.com/%0Aalert(1)'));
        $this->assertFalse(Security::isHttpUrl('file:///etc/passwd'));
        $this->assertFalse(Security::isHttpUrl('data:text/html,hi'));
        $this->assertFalse(Security::isHttpUrl('example.com'));
        $this->assertFalse(Security::isHttpUrl(''));
    }

    public function testJsonForScriptTagCannotCloseTheTag() {
        $json = Security::jsonForScriptTag(['name' => '</script><script>alert(1)</script> & <!--']);
        $this->assertStringNotContainsString('<', $json);
        $this->assertStringNotContainsString('>', $json);
        $this->assertSame(['name' => '</script><script>alert(1)</script> & <!--'], json_decode($json, true));
    }
}
