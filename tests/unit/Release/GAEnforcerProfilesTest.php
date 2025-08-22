<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Release;

use PHPUnit\Framework\TestCase;

final class GAEnforcerProfilesTest extends TestCase
{
    private string $root;
    private string $schemaScriptBak;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
        @mkdir($this->root . '/artifacts/coverage', 0777, true);
        @mkdir($this->root . '/artifacts/schema', 0777, true);
        @mkdir($this->root . '/artifacts/dist', 0777, true);
        @mkdir($this->root . '/artifacts/ga', 0777, true);
        file_put_contents($this->root . '/artifacts/coverage/coverage.json', json_encode(['totals' => ['pct' => 50]]) );
        $this->schemaScriptBak = $this->root . '/scripts/artifact-schema-validate.php.bak';
        rename($this->root . '/scripts/artifact-schema-validate.php', $this->schemaScriptBak);
        file_put_contents($this->root . '/scripts/artifact-schema-validate.php', "<?php\nfile_put_contents(__DIR__.'/../../artifacts/schema/schema-validate.json', json_encode(['count'=>5]));\n");
    }

    public function test_rc_profile_skips_failures(): void
    {
        exec('php ' . escapeshellarg($this->root . '/scripts/ga-enforcer.php') . ' --profile=rc --junit', $_, $rc);
        $this->assertSame(0, $rc);
        $xml = simplexml_load_file($this->root . '/artifacts/ga/GA_ENFORCER.junit.xml');
        $cov = $xml->xpath('//testcase[@name="Coverage"]')[0];
        $this->assertNotEmpty($cov->skipped);
        $schema = $xml->xpath('//testcase[@name="Artifacts.Schema"]')[0];
        $this->assertNotEmpty($schema->skipped);
    }

    public function test_ga_profile_enforces_failures(): void
    {
        exec('php ' . escapeshellarg($this->root . '/scripts/ga-enforcer.php') . ' --profile=ga --enforce --junit', $_, $rc);
        $this->assertSame(1, $rc);
        $xml = simplexml_load_file($this->root . '/artifacts/ga/GA_ENFORCER.junit.xml');
        $cov = $xml->xpath('//testcase[@name="Coverage"]')[0];
        $this->assertNotEmpty($cov->failure);
        $schema = $xml->xpath('//testcase[@name="Artifacts.Schema"]')[0];
        $this->assertNotEmpty($schema->failure);
    }

    protected function tearDown(): void
    {
        if (isset($this->schemaScriptBak)) {
            rename($this->schemaScriptBak, $this->root . '/scripts/artifact-schema-validate.php');
        }
    }
}

