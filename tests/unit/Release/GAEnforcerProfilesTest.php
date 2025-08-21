<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Release;

use PHPUnit\Framework\TestCase;

class GAEnforcerProfilesTest extends TestCase
{
    /** @var array<string> */
    private array $files = [];

    protected function setUp(): void
    {
        if (getenv('RUN_ENFORCE') !== '1') {
            $this->markTestSkipped('RUN_ENFORCE not set');
        }
        @mkdir('artifacts/qa', 0777, true);
        @mkdir('artifacts/i18n', 0777, true);
        @mkdir('artifacts/dist', 0777, true);
        @mkdir('artifacts/coverage', 0777, true);
        @mkdir('artifacts/ga', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->files) as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        foreach (['artifacts/ga/GA_ENFORCER.json','artifacts/ga/GA_ENFORCER.txt','artifacts/ga/GA_ENFORCER.junit.xml'] as $f) {
            @unlink($f);
        }
    }

    private function write(string $path, string $content): void
    {
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
        $this->files[] = $path;
    }

    private function execEnforcer(array $args, ?int &$exit = null): void
    {
        $cmd = 'php scripts/ga-enforcer.php ' . implode(' ', array_map('escapeshellarg', $args));
        $exit = null;
        exec($cmd, $_, $exit);
    }

    public function testRcProfilePass(): void
    {
        $this->write('artifacts/qa/rest-violations.json', json_encode([]));
        $this->write('artifacts/qa/sql-violations.json', json_encode([]));
        $this->write('artifacts/qa/secrets.json', json_encode([]));
        $this->write('artifacts/qa/licenses.json', json_encode(['summary'=>['denied'=>0]]));
        $this->write('artifacts/qa/qa-report.json', json_encode([]));
        $this->write('artifacts/qa/go-no-go.json', json_encode([]));
        $this->write('artifacts/i18n/pot-refresh.json', json_encode(['pot_entries'=>5,'domain_mismatch'=>0]));
        $this->write('artifacts/dist/manifest.json', '{}');
        $this->write('artifacts/dist/sbom.json', '{}');
        $this->write('artifacts/coverage/coverage.json', json_encode(['totals'=>['lines'=>['pct'=>100]]]));
        $this->write('artifacts/dist/dist-audit.json', json_encode(['summary'=>['violations'=>0]]));
        $this->write('artifacts/qa/wporg-lint.json', json_encode(['readme'=>['ok'=>true,'missing_headers'=>[], 'short_description'=>true,'sections'=>[]],'assets'=>['present'=>true,'files'=>[]]]));

        $code = 0;
        $this->execEnforcer(['--profile=rc','--enforce','--junit'], $code);
        $this->assertSame(0, $code);
        $this->assertFileExists('artifacts/ga/GA_ENFORCER.json');
        $this->assertFileExists('artifacts/ga/GA_ENFORCER.txt');
        $this->assertFileExists('artifacts/ga/GA_ENFORCER.junit.xml');
    }

    public function testGaProfileRestFail(): void
    {
        $this->write('artifacts/qa/rest-violations.json', json_encode(['v1']));
        $this->write('artifacts/qa/sql-violations.json', json_encode([]));
        $this->write('artifacts/qa/secrets.json', json_encode([]));
        $this->write('artifacts/qa/licenses.json', json_encode(['summary'=>['denied'=>0]]));
        $this->write('artifacts/qa/qa-report.json', json_encode([]));
        $this->write('artifacts/qa/go-no-go.json', json_encode([]));
        $this->write('artifacts/i18n/pot-refresh.json', json_encode(['pot_entries'=>10,'domain_mismatch'=>0]));
        $this->write('artifacts/dist/manifest.json', '{}');
        $this->write('artifacts/dist/sbom.json', '{}');
        $this->write('artifacts/coverage/coverage.json', json_encode(['totals'=>['lines'=>['pct'=>100]]]));
        $this->write('artifacts/dist/dist-audit.json', json_encode(['summary'=>['violations'=>0]]));
        $this->write('artifacts/qa/wporg-lint.json', json_encode(['readme'=>['ok'=>true,'missing_headers'=>[], 'short_description'=>true,'sections'=>[]],'assets'=>['present'=>true,'files'=>[]]]));

        $code = 0;
        $this->execEnforcer(['--profile=ga','--enforce','--junit'], $code);
        $this->assertSame(1, $code);
        $xml = simplexml_load_file('artifacts/ga/GA_ENFORCER.junit.xml');
        $found = false;
        foreach ($xml->testcase as $tc) {
            if ((string)$tc['name'] === 'REST' && isset($tc->failure)) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'REST failure not found');
    }
}

