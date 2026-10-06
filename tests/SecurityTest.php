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
