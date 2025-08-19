<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Tools;

use PHPUnit\Framework\TestCase;

final class PreflightSmokeTest extends TestCase
{
    public function testPreflight(): void
    {
        if (!function_exists('exec')) {
            $this->markTestSkipped('exec not available');
        }
        $script = __DIR__ . '/../../tools/preflight.php';
        $cmd = escapeshellcmd(PHP_BINARY . ' ' . $script);
        exec($cmd, $output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $output));
    }
}
