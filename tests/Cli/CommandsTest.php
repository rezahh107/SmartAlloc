<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class CommandsTest extends BaseTestCase
{
    public function test_wp_cli_available_or_skipped(): void
    {
        $wp = trim((string) shell_exec('command -v wp')); // @phpstan-ignore-line
        if ($wp === '') {
            $this->markTestSkipped('wp not found');
        }
        $this->assertNotEmpty($wp);
    }
}
