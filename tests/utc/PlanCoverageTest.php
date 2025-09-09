<?php

namespace SmartAlloc\Tests\UTC;

use PHPUnit\Framework\TestCase;
use SmartAlloc\UTC\UTCSweepScanner;

class PlanCoverageTest extends TestCase
{
    private string $fixture_dir;

    protected function setUp(): void
    {
        require_once dirname(__DIR__, 2) . '/scripts/utc_sweep/UTCSweepScanner.php';
        $this->fixture_dir = __DIR__ . '/../fixtures/utc';
    }

    public function testScannerDetectsWritePatterns(): void
    {
        $scanner = new UTCSweepScanner();
        $results = $scanner->scan($this->fixture_dir . '/sample_write.php');

        $this->assertEquals(1, $results['summary']['total']);
        $this->assertEquals(1, $results['summary']['writes']);
        $this->assertEquals('write', $results['items'][0]['kind']);
    }

    public function testScannerDetectsReadPatterns(): void
    {
        $scanner = new UTCSweepScanner();
        $results = $scanner->scan($this->fixture_dir . '/sample_read.php');

        $this->assertEquals(1, $results['summary']['total']);
        $this->assertEquals(1, $results['summary']['reads']);
        $this->assertEquals('read', $results['items'][0]['kind']);
    }

    public function testScannerHandlesMixedPatterns(): void
    {
        $scanner = new UTCSweepScanner();
        $results = $scanner->scan($this->fixture_dir . '/sample_mixed.php');

        $this->assertEquals(3, $results['summary']['total']);
        $this->assertEquals(2, $results['summary']['writes']);
        $this->assertEquals(1, $results['summary']['reads']);
    }

    public function testScannerHandlesWhitespaceVariations(): void
    {
        $line_variations = [
            "current_time('mysql')",
            "current_time( 'mysql' )",
            "current_time(  'mysql'  )",
            'current_time("mysql")',
            'current_time( "mysql" )',
        ];

        foreach ($line_variations as $line) {
            $scanner = new UTCSweepScanner();
            $this->assertTrue(
                $scanner->hasCurrentTimeMysql($line),
                "Failed to match: $line"
            );
        }
    }
}
