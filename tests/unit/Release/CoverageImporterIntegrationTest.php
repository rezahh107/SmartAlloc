<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Release;

use SmartAlloc\Tests\BaseTestCase;

final class CoverageImporterIntegrationTest extends BaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
        @mkdir($this->root . '/artifacts/coverage', 0777, true);
    }

    public function test_search_order_and_determinism(): void
    {
        $json = [
            'totals' => ['lines_total' => 3, 'lines_covered' => 2],
            'files' => [
                ['path' => 'foo.php', 'lines_total' => 1, 'lines_covered' => 1],
                ['path' => 'bar.php', 'lines_total' => 2, 'lines_covered' => 1],
            ],
        ];
        file_put_contents($this->root . '/coverage.json', json_encode($json));
        file_put_contents($this->root . '/clover.xml', '<coverage/>');
        putenv('COVERAGE_INPUT=' . $this->root . '/coverage.json');

        exec(PHP_BINARY . ' ' . escapeshellarg($this->root . '/scripts/coverage-import.php'), $_, $rc);
        $this->assertSame(0, $rc);

        $out = json_decode((string)file_get_contents($this->root . '/artifacts/coverage/coverage.json'), true);
        $this->assertSame('json', $out['source']);
        $this->assertSame(66.7, $out['totals']['pct']);
        $this->assertMatchesRegularExpression('/Z$/', $out['generated_at']);
        $this->assertSame(['bar.php', 'foo.php'], array_column($out['files'], 'path'));

        @unlink($this->root . '/coverage.json');
        @unlink($this->root . '/clover.xml');
    }
}

