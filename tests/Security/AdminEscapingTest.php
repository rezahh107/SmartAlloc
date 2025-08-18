<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class AdminEscapingTest extends TestCase
{
    public function test_placeholder(): void
    {
        $this->markTestSkipped('Security escaping checks require full WP environment');
    }
}
