<?php

namespace SmartAlloc\Tests\UTC;

use PHPUnit\Framework\TestCase;

class UtcCodemodUnitTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../scripts/utc_sweep/UTCSweepScanner.php';
        $this->fixtureDir = __DIR__ . '/../fixtures/utc';
    }

    private function applyCodemod(string $file): string
    {
        /** @phpstan-ignore-next-line */
        $scanner = new \SmartAlloc\UTC\UTCSweepScanner();
        /** @phpstan-ignore-next-line */
        $results = $scanner->scan($file);
        $lines = file($file);
        foreach ($results['items'] as $item) {
            if ($item['kind'] === 'write') {
                $index = $item['line'] - 1;
                $lines[$index] = preg_replace(
                    "/current_time\('mysql'\)/",
                    "current_time('mysql', true)",
                    $lines[$index]
                );
            }
        }
        return implode('', $lines);
    }

    public function testWriteConverted(): void
    {
        $output = $this->applyCodemod($this->fixtureDir . '/sample_write.php');
        $this->assertStringContainsString("current_time('mysql', true)", $output);
    }

    public function testReadUnchanged(): void
    {
        $output = $this->applyCodemod($this->fixtureDir . '/sample_read.php');
        $this->assertStringNotContainsString("current_time('mysql', true)", $output);
    }

    public function testMixedPartialConversion(): void
    {
        $output = $this->applyCodemod($this->fixtureDir . '/sample_mixed.php');
        $this->assertSame(2, substr_count($output, "current_time('mysql', true)"));
        $this->assertSame(1, substr_count($output, "current_time('mysql');"));
    }
}
