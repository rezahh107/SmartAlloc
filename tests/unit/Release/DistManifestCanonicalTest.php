<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class DistManifestCanonicalTest extends BaseTestCase
{
    public function test_manifest_only_has_canonical_entries(): void
    {
        $root = dirname(__DIR__,3);
        $tmp = sa_tests_temp_dir('manifest2');
        file_put_contents($tmp.'/one.txt','1');
        exec(sprintf('%s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg($root.'/scripts/dist-manifest.php'),
            escapeshellarg($tmp)
        ), $_, $rc);
        $this->assertSame(0,$rc);
        $manifest = json_decode((string)file_get_contents($root.'/artifacts/dist/manifest.json'), true);
        $this->assertSame(['entries'], array_keys($manifest));
        foreach ($manifest['entries'] as $e) {
            $this->assertSame(['path','sha256','size'], array_keys($e));
            $this->assertSame(64, strlen($e['sha256']));
        }
    }
}
