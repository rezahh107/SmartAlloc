<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GAEnforcerCoverageTest extends TestCase
{
    public function test_ga_profile_enforces_low_coverage(): void
    {
        if (getenv('RUN_ENFORCE') !== '1') {
            $this->markTestSkipped('opt-in');
        }

        $rootArtifacts = __DIR__ . '/../../../artifacts';
        @mkdir($rootArtifacts . '/coverage', 0777, true);
        @mkdir($rootArtifacts . '/dist', 0777, true);

        $cov = [
            'source' => 'json',
            'generated_at' => date('c'),
            'totals' => ['lines_total' => 100, 'lines_covered' => 70, 'pct' => 70.0],
            'files' => [],
        ];
        file_put_contents($rootArtifacts . '/coverage/coverage.json', json_encode($cov));

        $manifest = [
            'entries' => [
                ['path' => 'a.txt', 'size' => 1],
                ['path' => 'b.txt', 'sha256' => 'abc'],
            ],
        ];
        file_put_contents($rootArtifacts . '/dist/manifest.json', json_encode($manifest));

        putenv('RUN_ENFORCE');
        $cmd = PHP_BINARY . ' '
            . escapeshellarg(__DIR__ . '/../../../scripts/ga-enforcer.php')
            . ' --profile=rc --junit';
        exec($cmd, $o, $rc);
        $this->assertSame(0, $rc);
        $junit = $rootArtifacts . '/ga/GA_ENFORCER.junit.xml';
        $this->assertFileExists($junit);
        $xml = (string)file_get_contents($junit);
        $this->assertStringContainsString('<testcase name="Artifacts.Schema">', $xml);
        $this->assertStringContainsString('<skipped/>', $xml);

        putenv('RUN_ENFORCE=1');
        $cmd = PHP_BINARY . ' '
            . escapeshellarg(__DIR__ . '/../../../scripts/ga-enforcer.php')
            . ' --profile=ga --enforce --junit';
        exec($cmd, $o2, $rc2);
        $this->assertNotSame(0, $rc2);
        $xml2 = (string)file_get_contents($junit);
        $this->assertStringContainsString('<testcase name="Artifacts.Schema">', $xml2);
        $this->assertStringContainsString('<failure', $xml2);
    }
}
