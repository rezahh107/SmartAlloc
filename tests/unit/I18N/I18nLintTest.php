<?php
use PHPUnit\Framework\TestCase;

class I18nLintTest extends TestCase
{
    private string $fixture;

    protected function setUp(): void
    {
        $this->fixture = dirname(__DIR__, 3) . '/i18n-fixture.php';
        file_put_contents($this->fixture, <<<'PHP1'
<?php
__('Good','smartalloc');
__('Bad','wrongdomain');
sprintf(__('Hi %1$s %s','smartalloc'), 'a','b');
echo 'plain string';
PHP1);
    }

    protected function tearDown(): void
    {
        @unlink($this->fixture);
    }

    public function testDetectsDomainAndPlaceholderIssues(): void
    {
        $json = shell_exec('php scripts/i18n-lint.php');
        $data = json_decode($json, true);
        $filesWrong = array_column($data['wrong_domain'], 'file');
        $filesPlaceholder = array_column($data['placeholder_mismatch'], 'file');
        $filesUntranslated = array_column($data['untranslated'], 'file');
        $this->assertContains($this->fixture, $filesWrong);
        $this->assertContains($this->fixture, $filesPlaceholder);
        $this->assertContains($this->fixture, $filesUntranslated);
    }
}
