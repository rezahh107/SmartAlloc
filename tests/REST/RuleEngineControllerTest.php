<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class RuleEngineControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_requires_capability(): void
    {
        Functions\when('current_user_can')
            ->justReturn(false);

        // Mock REST request without capability
        $this->markTestIncomplete(
            'Implement with WP_REST_Server mock'
        );
    }

    public function test_requires_valid_nonce(): void
    {
        Functions\when('current_user_can')
            ->justReturn(true);
        Functions\when('wp_verify_nonce')
            ->justReturn(false);

        $this->markTestIncomplete(
            'Implement nonce validation test'
        );
    }
}
