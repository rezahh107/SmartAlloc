<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Release;

use SmartAlloc\Tests\BaseTestCase;

final class RehearsalScriptTest extends BaseTestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = dirname(__DIR__, 3);
        @mkdir($this->root . '/artifacts/ga', 0777, true);
    }

    public function test_e2e_missing_is_skip_safe(): void
    {
        putenv('E2E=1');
        putenv('E2E_RTL=1');
        exec('bash ' . escapeshellarg($this->root . '/scripts/ga-rehearsal.sh'), $_, $rc);
        $this->assertSame(0, $rc);

        $txt = $this->root . '/artifacts/ga/GA_REHEARSAL.txt';
        $junit = $this->root . '/artifacts/ga/GA_REHEARSAL.junit.xml';
        $this->assertFileExists($txt);
        $this->assertFileExists($junit);
        $this->assertStringContainsString('e2e skip', (string)file_get_contents($txt));

        $xml = simplexml_load_file($junit);
        $found = false;
        foreach ($xml->testcase as $tc) {
            if ((string)$tc['name'] === 'GA.Rehearsal' && isset($tc->skipped)) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'GA.Rehearsal testcase missing');
    }
}

