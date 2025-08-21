<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Release;

use PHPUnit\Framework\TestCase;

class GAEnforcerCoverageTest extends TestCase
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

    private function writeCommonArtifacts(): void
    {
        $this->write('artifacts/qa/rest-violations.json', json_encode([]));
        $this->write('artifacts/qa/sql-violations.json', json_encode([]));
        $this->write('artifacts/qa/secrets.json', json_encode([]));
        $this->write('artifacts/qa/licenses.json', json_encode(['summary'=>['denied'=>0]]));
        $this->write('artifacts/qa/qa-report.json', json_encode([]));
        $this->write('artifacts/qa/go-no-go.json', json_encode(['verdict'=>'go']));
        $this->write('artifacts/i18n/pot-refresh.json', json_encode(['pot_entries'=>10,'domain_mismatches'=>0]));
        $this->write('artifacts/dist/manifest.json', json_encode([['path'=>'a','size'=>1,'sha256'=>'x']]));
        $this->write('artifacts/dist/sbom.json', json_encode([['name'=>'a','version'=>'1']]));
        $this->write('artifacts/dist/dist-audit.json', json_encode(['summary'=>['violations'=>0]]));
        $this->write('artifacts/qa/wporg-lint.json', json_encode([
            'readme'=>['missing'=>false,'missing_headers'=>[],'short_description'=>true,'sections'=>[]],
            'assets'=>['present'=>true,'files'=>[]]
        ]));
    }

    public function testCoveragePass(): void
    {
        $this->writeCommonArtifacts();
        $this->write('artifacts/coverage/coverage.json', json_encode([
            'lines_total'=>10,
            'lines_covered'=>6,
            'lines_pct'=>60,
            'source'=>'unit'
        ]));

        $code = 0;
        $this->execEnforcer(['--profile=rc','--enforce','--junit'], $code);
        $this->assertSame(0, $code);
        $this->assertFileExists('artifacts/ga/GA_ENFORCER.junit.xml');
    }

    public function testCoverageFail(): void
    {
        $this->writeCommonArtifacts();
        $this->write('artifacts/coverage/coverage.json', json_encode([
            'lines_total'=>10,
            'lines_covered'=>5,
            'lines_pct'=>50,
            'source'=>'unit'
        ]));

        $code = 0;
        $this->execEnforcer(['--profile=ga','--enforce','--junit'], $code);
        $this->assertSame(1, $code);
        $xml = simplexml_load_file('artifacts/ga/GA_ENFORCER.junit.xml');
        $found = false;
        foreach ($xml->testcase as $tc) {
            if ((string)$tc['name'] === 'Coverage' && isset($tc->failure)) {
                $found = true;
            }
        }
        $this->assertTrue($found, 'Coverage failure not found');
    }
}
