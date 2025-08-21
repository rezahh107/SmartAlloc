<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CoverageImportTest extends TestCase
{
    public function test_import_normalizes_clover_fixture(): void
    {
        if (getenv('RUN_ENFORCE') !== '1') {
            $this->markTestSkipped('opt-in');
        }

        @mkdir(__DIR__ . '/../../../artifacts/coverage', 0777, true);
        $fixture = __DIR__ . '/../../fixtures/clover/minimal-clover.xml';
        putenv('COVERAGE_INPUT=' . $fixture);
        $cmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../../scripts/coverage-import.php');
        exec($cmd, $o, $rc);
        $this->assertSame(0, $rc);

        $path = __DIR__ . '/../../../artifacts/coverage/coverage.json';
        $this->assertFileExists($path);
        $j = json_decode((string)file_get_contents($path), true);
        $this->assertSame('clover', $j['source']);
        $this->assertSame(7, $j['totals']['lines_total']);
        $this->assertSame(4, $j['totals']['lines_covered']);
        $this->assertSame(57.14, $j['totals']['pct']);
        $this->assertSame('src/A.php', $j['files'][0]['path']);
        $this->assertSame('src/B.php', $j['files'][1]['path']);
        $this->assertSame(33.33, $j['files'][1]['pct']);
    }
}

