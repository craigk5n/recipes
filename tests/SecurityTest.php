<?php
use PHPUnit\Framework\TestCase;

class SecurityTest extends TestCase {
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
}
