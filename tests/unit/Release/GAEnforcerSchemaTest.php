<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GAEnforcerSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        @mkdir('artifacts/schema',0777,true);
        @mkdir('artifacts/ga',0777,true);
        file_put_contents('artifacts/schema/schema-validate.json', json_encode(['count'=>5]));
    }

    protected function tearDown(): void
    {
        @unlink('scripts/.ga-enforce.json');
    }

    public function test_schema_warning_gates(): void
    {
        file_put_contents('scripts/.ga-enforce.json', json_encode([
            'schema_warnings' => 0,
        ]));
        exec('RUN_ENFORCE=0 php scripts/ga-enforcer.php --profile=rc --junit', $_, $rc);
        $this->assertSame(0,$rc);
        $xml = (string)file_get_contents('artifacts/ga/GA_ENFORCER.junit.xml');
        $this->assertStringContainsString('<testcase name="Artifacts.Schema"', $xml);
        $this->assertStringContainsString('<skipped', $xml);

        exec('RUN_ENFORCE=1 php scripts/ga-enforcer.php --profile=ga --enforce --junit', $_, $rc2);
        $this->assertNotSame(0,$rc2);
        $xml = (string)file_get_contents('artifacts/ga/GA_ENFORCER.junit.xml');
        $this->assertStringContainsString('<testcase name="Artifacts.Schema"', $xml);
        $this->assertStringContainsString('<failure', $xml);
    }
}
