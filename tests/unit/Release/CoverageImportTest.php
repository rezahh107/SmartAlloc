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

        $tmp = sys_get_temp_dir() . '/cov-' . uniqid();
        @mkdir($tmp, 0777, true);
        $fixture = __DIR__ . '/../../fixtures/coverage/minimal-clover.xml';
        $local = $tmp . '/clover.xml';
        copy($fixture, $local);

        putenv('COVERAGE_INPUT=' . $local);

        $covDir = __DIR__ . '/../../../artifacts/coverage';
        @mkdir($covDir, 0777, true);
        @unlink($covDir . '/coverage.json');

        $cmd = PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/../../../scripts/coverage-import.php');
        exec($cmd, $o, $rc);
        $this->assertSame(0, $rc);

        $path = __DIR__ . '/../../../artifacts/coverage/coverage.json';
        $this->assertFileExists($path);
        $j = json_decode((string)file_get_contents($path), true);
        $this->assertSame('clover', $j['source']);
        $this->assertSame(7, $j['totals']['lines_total']);
        $this->assertSame(4, $j['totals']['lines_covered']);
        $this->assertSame(57.1, $j['totals']['pct']);
        $this->assertSame(['src/A.php', 'src/B.php'], array_column($j['files'], 'path'));
        $this->assertSame(75.0, $j['files'][0]['pct']);
        $this->assertSame(33.3, $j['files'][1]['pct']);
    }
}

