<?php
declare(strict_types=1);

use SmartAlloc\Security\RestValidator;

final class RestValidatorTest extends WP_UnitTestCase
{
    public function test_sanitize_email(): void
    {
        $result = RestValidator::sanitize(['email' => ' TEST@example.com '], ['email' => 'email']);
        $this->assertSame('test@example.com', $result['email']);
    }
}
