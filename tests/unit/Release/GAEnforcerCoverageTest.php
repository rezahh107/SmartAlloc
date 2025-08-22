<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class GAEnforcerCoverageTest extends BaseTestCase
{
    public function test_artifacts_schema_junit_behavior(): void
    {
        $rootArtifacts = __DIR__ . '/../../../artifacts';
        if (is_dir($rootArtifacts)) {
            exec('rm -rf ' . escapeshellarg($rootArtifacts));
        }
        @mkdir($rootArtifacts . '/coverage', 0777, true);
        @mkdir($rootArtifacts . '/dist', 0777, true);
        @mkdir($rootArtifacts . '/i18n', 0777, true);

        $cov = [
            'source' => 'json',
            'generated_at' => date('c'),
            'totals' => ['lines_total' => 100, 'lines_covered' => 85, 'pct' => 85.0],
            'files' => [],
        ];
        file_put_contents($rootArtifacts . '/coverage/coverage.json', json_encode($cov));

        $pot = ['pot_entries' => 10, 'domain_mismatch' => 0];
        file_put_contents($rootArtifacts . '/i18n/pot-refresh.json', json_encode($pot));
        file_put_contents($rootArtifacts . '/dist/sbom.json', '{}');
        file_put_contents($rootArtifacts . '/dist/dist-audit.json', json_encode(['summary' => ['violations' => 0]]));
        @mkdir($rootArtifacts . '/qa', 0777, true);
        file_put_contents($rootArtifacts . '/qa/wporg-lint.json', json_encode(['readme' => [], 'assets' => []]));

        $clean = [
            'entries' => [
                ['path' => 'a.txt', 'sha256' => str_repeat('a', 64), 'size' => 1],
            ],
        ];
        file_put_contents($rootArtifacts . '/dist/manifest.json', json_encode($clean));
        $stubScripts = ['scan-sql-prepare.php','dist-manifest.php'];
        $backups = [];
        foreach ($stubScripts as $s) {
            $orig = __DIR__ . '/../../../scripts/' . $s;
            $bak = $orig . '.bak';
            $backups[$orig] = $bak;
            rename($orig, $bak);
            file_put_contents($orig, "<?php\nexit(0);\n");
        }

        putenv('RUN_ENFORCE');
        $cmd = PHP_BINARY . ' '
            . escapeshellarg(__DIR__ . '/../../../scripts/ga-enforcer.php')
            . ' --profile=rc --junit';
        exec($cmd, $o, $rc);
        $this->assertSame(0, $rc);
        $junit = $rootArtifacts . '/ga/GA_ENFORCER.junit.xml';
        $this->assertFileExists($junit);
        $xml = (string)file_get_contents($junit);
        $this->assertMatchesRegularExpression('/<testcase name="Artifacts\.Schema">\s*<skipped\/>/s', $xml);

        $bad = [
            'files' => [
                ['path' => 'a.txt', 'sha256' => str_repeat('a', 64), 'size' => 1],
            ],
        ];
        file_put_contents($rootArtifacts . '/dist/manifest.json', json_encode($bad));

        putenv('RUN_ENFORCE=1');
        $cmd = PHP_BINARY . ' '
            . escapeshellarg(__DIR__ . '/../../../scripts/ga-enforcer.php')
            . ' --profile=ga --enforce --junit';
        exec($cmd, $o2, $rc2);
        $this->assertNotSame(0, $rc2);
        $xml2 = (string)file_get_contents($junit);
        $this->assertMatchesRegularExpression('/<testcase name="Artifacts\.Schema">\s*<failure/s', $xml2);
        $this->assertDoesNotMatchRegularExpression('/<testcase name="Artifacts\.Schema">\s*<skipped/s', $xml2);

        $report = json_decode((string)file_get_contents($rootArtifacts . '/ga/GA_ENFORCER.json'), true);
        $this->assertGreaterThan(0, $report['signals']['schema_warnings'] ?? 0);
        putenv('RUN_ENFORCE');
        foreach ($backups as $orig => $bak) { rename($bak, $orig); }
    }
}
