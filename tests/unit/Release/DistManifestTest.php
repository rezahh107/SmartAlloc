<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class DistManifestTest extends TestCase
{
    public function test_manifest_entries_sorted_and_shaped(): void
    {
        if (getenv('RUN_ENFORCE') !== '1') {
            $this->markTestSkipped('opt-in');
        }

        $root = dirname(__DIR__, 3);
        $temp = sa_tests_temp_dir('manifest');
        file_put_contents($temp . '/b.txt', 'bb');
        file_put_contents($temp . '/a.txt', 'a');

        $cmd = sprintf('%s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root . '/scripts/dist-manifest.php'),
            escapeshellarg($temp)
        );
        exec($cmd, $_, $rc);
        $this->assertSame(0, $rc);

        $manifestPath = $root . '/artifacts/dist/manifest.json';
        $this->assertFileExists($manifestPath);
        $m = json_decode((string)file_get_contents($manifestPath), true);
        $this->assertIsArray($m['entries'] ?? null);
        $this->assertSame('a.txt', $m['entries'][0]['path'] ?? '');
        $this->assertSame('b.txt', $m['entries'][1]['path'] ?? '');
        foreach ($m['entries'] as $e) {
            $this->assertArrayHasKey('path', $e);
            $this->assertArrayHasKey('sha256', $e);
            $this->assertArrayHasKey('size', $e);
        }
    }
}
