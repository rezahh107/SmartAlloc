<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Security\InputRedactor;

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($s) { return trim(strip_tags((string) $s)); }
}

final class InputRedactorTest extends TestCase
{
    public function test_sensitive_keys_are_redacted(): void
    {
        $out = InputRedactor::sanitizeServerVar('HTTP_AUTHORIZATION', 'Bearer abc');
        $this->assertSame('[REDACTED]', $out);
    }

    public function test_normal_values_are_sanitized(): void
    {
        $out = InputRedactor::sanitizeServerVar('REQUEST_METHOD', '<script>GET</script>');
        $this->assertSame('GET', $out);
    }
}
