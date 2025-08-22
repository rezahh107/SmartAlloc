<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class DistBuildTest extends BaseTestCase
{
    public function test_build_excludes_and_normalises(): void
    {
        $src = sa_tests_temp_dir('src');
        file_put_contents($src . '/plugin.php', "<?php\n/*Plugin Name: Test*/\n");
        file_put_contents($src . '/readme.txt', "line1\r\nline2\r\n");
        file_put_contents($src . '/notes.md', 'skip');
        mkdir($src . '/node_modules', 0777, true);
        file_put_contents($src . '/node_modules/mod.js', '');
        mkdir($src . '/tests', 0777, true);
        file_put_contents($src . '/tests/t.php', '');
        mkdir($src . '/dir', 0777, true);
        file_put_contents($src . '/dir/keep.php', "<?php\n");
        $dest = sa_tests_temp_dir('distbuild');
        $cmd = sprintf('%s %s %s %s',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__,3).'/scripts/dist-build.php'),
            escapeshellarg($src),
            escapeshellarg($dest)
        );
        exec($cmd, $_, $rc);
        $this->assertSame(0, $rc);
        $base = $dest . '/SmartAlloc';
        $this->assertFileExists($base . '/plugin.php');
        $this->assertFileExists($base . '/readme.txt');
        $this->assertFileExists($base . '/dir/keep.php');
        $this->assertFileDoesNotExist($base . '/notes.md');
        $this->assertFileDoesNotExist($base . '/node_modules');
        $this->assertStringNotContainsString("\r", file_get_contents($base . '/readme.txt'));
        $this->assertSame('0644', substr(sprintf('%o', fileperms($base . '/plugin.php')), -4));
        $this->assertSame('0755', substr(sprintf('%o', fileperms($base . '/dir')), -4));
    }
}
