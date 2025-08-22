<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ReadmeLintTest extends TestCase
{
    public function test_valid_readme(): void
    {
        $root = sa_tests_temp_dir('pkg');
        file_put_contents($root . '/readme.txt', "=== Test ===\nRequires at least: 6.4\nTested up to: 6.4\nStable tag: 1.0.0\n\n== Screenshots ==\n1. One\n2. Two\n");
        $cmd = sprintf('%s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__,3).'/scripts/validate-readme.php'),
            escapeshellarg($root)
        );
        exec($cmd, $_, $rc);
        $this->assertSame(0, $rc);
        $path = $root . '/artifacts/dist/readme-lint.json';
        $data = json_decode((string)file_get_contents($path), true);
        $this->assertEmpty($data['warnings']);
    }

    public function test_invalid_readme(): void
    {
        $root = sa_tests_temp_dir('pkg2');
        file_put_contents($root . '/readme.txt', "=== Test ===\nTested up to: 6.4\nStable tag:\n\n== Screenshots ==\n* One\n");
        $cmd = sprintf('%s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__,3).'/scripts/validate-readme.php'),
            escapeshellarg($root)
        );
        exec($cmd, $_, $rc);
        $this->assertSame(0, $rc);
        $path = $root . '/artifacts/dist/readme-lint.json';
        $data = json_decode((string)file_get_contents($path), true);
        $this->assertNotEmpty($data['warnings']);
    }
}
