<?php
use SmartAlloc\Tests\BaseTestCase;

class GAEnforcerI18nWporgTest extends BaseTestCase
{
    private string $badFile;
    private string $wporgDir;

    protected function tearDown(): void
    {
        if (isset($this->badFile)) { @unlink($this->badFile); }
        if (isset($this->wporgDir) && is_dir($this->wporgDir)) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->wporgDir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
            @rmdir($this->wporgDir);
        }
    }

    public function testRcProfileSkips(): void
    {
        shell_exec('php scripts/ga-enforcer.php --profile=rc --junit');
        $xml = file_get_contents('artifacts/ga/GA_ENFORCER.junit.xml');
        $this->assertStringContainsString('testcase name="I18N.Lint"', $xml);
        $this->assertStringContainsString('testcase name="WPOrg.Preflight"', $xml);
        $this->assertStringContainsString('<skipped', $xml);
    }

    public function testGaEnforceFailsOnViolations(): void
    {
        $this->badFile = getcwd() . '/i18n-ga.php';
        file_put_contents($this->badFile, "<?php __('Bad','wrong');");
        $this->wporgDir = getcwd() . '/wporg';
        mkdir($this->wporgDir . '/trunk', 0777, true);
        file_put_contents($this->wporgDir . '/trunk/plugin.php', "<?php\n/*Plugin Name: T*/\n/*Version:1.0*/");
        file_put_contents($this->wporgDir . '/trunk/readme.txt', "=== T ===\nStable tag: 2.0\n");
        mkdir($this->wporgDir . '/tags/1.0', 0777, true);
        putenv('RUN_ENFORCE=1');
        shell_exec('RUN_ENFORCE=1 php scripts/ga-enforcer.php --profile=ga --enforce --junit');
        $xml = file_get_contents('artifacts/ga/GA_ENFORCER.junit.xml');
        $this->assertStringContainsString('<testcase name="I18N.Lint"', $xml);
        $this->assertStringContainsString('i18n lint warnings present', $xml);
        $this->assertStringContainsString('<testcase name="WPOrg.Preflight"', $xml);
        $this->assertStringContainsString('wporg preflight warnings present', $xml);
        putenv('RUN_ENFORCE');
    }
}
