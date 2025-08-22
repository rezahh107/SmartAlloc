<?php
use SmartAlloc\Tests\BaseTestCase;

class PotDiffTest extends BaseTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/potdiff_' . uniqid();
        mkdir($this->dir);
        mkdir($this->dir . '/languages', 0777, true);
        file_put_contents($this->dir . '/sample.php', "<?php __('Hello','smartalloc');");
        $pot = "msgid \"\"\nmsgstr \"\"\n\nmsgid \"Old\"\nmsgstr \"\"\n";
        file_put_contents($this->dir . '/languages/smartalloc.pot', $pot);
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($this->dir);
    }

    public function testDiffListsMissingAndExtraneous(): void
    {
        $cmd = 'php scripts/pot-diff.php --path=' . escapeshellarg($this->dir);
        $json = shell_exec($cmd);
        $data = json_decode($json, true);
        $this->assertSame(['Hello'], $data['missing']);
        $this->assertSame(['Old'], $data['extraneous']);
    }
}
