<?php
use PHPUnit\Framework\TestCase;

class DeployChecklistTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/wporg_' . uniqid();
        mkdir($this->dir);
        mkdir($this->dir . '/trunk', 0777, true);
        file_put_contents($this->dir . '/trunk/plugin.php', "<?php\n/*\nPlugin Name: T\nVersion: 1.0\n*/");
        file_put_contents($this->dir . '/trunk/readme.txt', "=== T ===\nStable tag: 2.0\n");
        mkdir($this->dir . '/tags/1.0', 0777, true);
    }

    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
        @rmdir($this->dir);
    }

    public function testWarnsMissingAssetsAndStableTagMismatch(): void
    {
        $cmd = 'php scripts/wporg-deploy-checklist.php --dir=' . escapeshellarg($this->dir);
        $json = shell_exec($cmd);
        $data = json_decode($json, true);
        $this->assertContains('assets_missing', $data['warnings']);
        $this->assertContains('stable_tag_mismatch', $data['warnings']);
    }

    public function testWarnsMissingReadme(): void
    {
        @unlink($this->dir . '/trunk/readme.txt');
        $cmd = 'php scripts/wporg-deploy-checklist.php --dir=' . escapeshellarg($this->dir);
        $json = shell_exec($cmd);
        $data = json_decode($json, true);
        $this->assertContains('readme_missing', $data['warnings']);
    }
}
