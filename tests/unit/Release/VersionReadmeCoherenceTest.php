<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class VersionReadmeCoherenceTest extends TestCase
{
    public function test_mismatch_emits_warning(): void
    {
        $root = sa_tests_temp_dir('pkg');
        file_put_contents($root . '/plugin.php', "<?php\n/*\nPlugin Name: Test\nVersion: 1.0.0\n*/\n");
        file_put_contents($root . '/readme.txt', "=== Test ===\nRequires at least: 6.4\nTested up to: 6.4\nStable tag: 2.0.1\n\n== Changelog ==\n");
        file_put_contents($root . '/CHANGELOG.md', "# Changelog\n## 1.0.0\n");
        $cmd = sprintf('%s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__,3).'/scripts/version-coherence.php'),
            escapeshellarg($root)
        );
        exec($cmd, $_, $rc);
        $this->assertSame(0, $rc);
        $path = $root . '/artifacts/dist/version-coherence.json';
        $data = json_decode((string)file_get_contents($path), true);
        $this->assertNotEmpty($data['warnings']);
    }
}
