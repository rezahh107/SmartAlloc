<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminNonceCapabilityTest extends TestCase
{
    public function test_placeholder(): void
    {
        $this->markTestSkipped('Security checks require full WP environment');
    }
}
