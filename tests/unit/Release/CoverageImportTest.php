<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Release;

use PHPUnit\Framework\TestCase;

class CoverageImportTest extends TestCase
{
    /** @var array<string> */
    private array $files = [];

    protected function setUp(): void
    {
        if (getenv('RUN_ENFORCE') !== '1') {
            $this->markTestSkipped('RUN_ENFORCE not set');
        }
        @mkdir('artifacts/coverage', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->files) as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
    }

    private function write(string $path, string $content): void
    {
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
        $this->files[] = $path;
    }

    public function testCloverImport(): void
    {
        $xml = '<coverage><project><metrics statements="10" coveredstatements="7"/></project></coverage>';
        $this->write('artifacts/coverage/clover.xml', $xml);

        $code = 0;
        exec('php scripts/coverage-import.php', $_, $code);
        $this->assertSame(0, $code);
        $this->assertFileExists('artifacts/coverage/coverage.json');
        $data = json_decode((string)file_get_contents('artifacts/coverage/coverage.json'), true);
        $this->assertSame(10, $data['lines_total']);
        $this->assertSame(7, $data['lines_covered']);
        $this->assertSame(70.0, (float)$data['lines_pct']);
        $this->assertSame('clover.xml', $data['source']);
    }
}
