<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class GAEnforcerDistTest extends BaseTestCase
{
    private string $origReadme = '';

    protected function setUp(): void
    {
        putenv('RUN_ENFORCE');
        $this->origReadme = (string)file_get_contents('readme.txt');
        @mkdir('artifacts/ga', 0777, true);
        @mkdir('artifacts/dist', 0777, true);
    }

    protected function tearDown(): void
    {
        file_put_contents('readme.txt', $this->origReadme);
        @unlink('scripts/.ga-enforce.json');
    }

    public function test_dist_manifest_warnings_gate(): void
    {
        $broken = preg_replace('/^Stable tag:.*$/m', 'Stable tag:', $this->origReadme);
        file_put_contents('readme.txt', (string)$broken);
        $config = [
            'rest_permission_violations' => 999,
            'sql_prepare_violations' => 999,
            'secrets_findings' => 999,
            'license_denied' => 999,
            'i18n_domain_mismatches' => 999,
            'coverage_pct_min' => 0,
            'schema_warnings' => 999,
            'pot_min_entries' => 0,
            'dist_audit_max_errors' => 999,
            'wporg_lint_max_warnings' => 999,
            'dist_manifest_warnings' => 0,
            'require_manifest' => false,
            'require_sbom' => false,
            'version_mismatch_fatal' => false,
        ];
        file_put_contents('scripts/.ga-enforce.json', json_encode($config));

        exec('php scripts/ga-enforcer.php --profile=rc --junit', $_, $rc);
        $this->assertSame(0, $rc);
        $xml = (string)file_get_contents('artifacts/ga/GA_ENFORCER.junit.xml');
        $this->assertStringContainsString('<testcase name="Dist.Manifest"', $xml);
        $this->assertStringContainsString('<skipped', $xml);

        exec('RUN_ENFORCE=1 php scripts/ga-enforcer.php --profile=ga --enforce --junit', $_, $rc2);
        $this->assertNotSame(0, $rc2);
        $xml = (string)file_get_contents('artifacts/ga/GA_ENFORCER.junit.xml');
        $this->assertStringContainsString('<testcase name="Dist.Manifest"', $xml);
        $this->assertStringContainsString('<failure', $xml);
    }
}
