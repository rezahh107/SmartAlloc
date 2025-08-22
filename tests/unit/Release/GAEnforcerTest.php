<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Release;

use PHPUnit\Framework\TestCase;

class GAEnforcerTest extends TestCase
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
        @mkdir('artifacts/ga', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->files) as $f) {
            if (is_file($f)) {
                unlink($f);
            }
        }
        @unlink('artifacts/ga/GA_ENFORCER.json');
        @unlink('artifacts/ga/GA_ENFORCER.txt');
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

    private function execute(array $args, ?int &$exit = null): void
    {
        $cmd = 'php scripts/ga-enforcer.php ' . implode(' ', array_map('escapeshellarg', $args));
        $exit = null;
        exec($cmd, $_, $exit);
    }

    public function testPass(): void
    {
        $this->write('scripts/.ga-enforce.json', json_encode([
            'rest_permission_violations' => 0,
            'sql_prepare_violations' => 0,
            'secrets_findings' => 0,
            'license_denied' => 0,
            'i18n_domain_mismatches' => 0,
            'coverage_min_lines_pct' => 0,
            'require_manifest' => true,
            'require_sbom' => true,
            'version_mismatch_fatal' => false,
            'dist_audit_max_errors' => 999,
            'wporg_lint_max_warnings' => 999,
        ]));
        $this->write('artifacts/qa/qa-report.json', json_encode([
            'coverage_percent' => 100,
            'rest_permission_violations' => 0,
            'sql_prepare_violations' => 0,
        ]));
        $this->write('artifacts/qa/rest-violations.json', json_encode([]));
        $this->write('artifacts/qa/sql-violations.json', json_encode([]));
        $this->write('artifacts/qa/secrets.json', json_encode([]));
        $this->write('artifacts/qa/licenses.json', json_encode(['summary' => ['denied' => 0]]));
        $this->write('artifacts/i18n/pot-refresh.json', json_encode(['pot_entries' => 10, 'domain_mismatch' => 0]));
        $this->write('artifacts/schema/schema-validate.json', json_encode(['count' => 0]));
        $this->write('artifacts/dist/manifest.json', '{}');
        $this->write('artifacts/dist/sbom.json', '{}');

        $code = 0;
        $this->execute(['--enforce'], $code);
        $this->assertSame(0, $code);
    }

    public function testFailOnRestViolation(): void
    {
        $this->write('scripts/.ga-enforce.json', json_encode([
            'rest_permission_violations' => 0,
            'sql_prepare_violations' => 0,
            'secrets_findings' => 0,
            'license_denied' => 0,
            'i18n_domain_mismatches' => 0,
            'coverage_min_lines_pct' => 0,
            'require_manifest' => true,
            'require_sbom' => true,
            'version_mismatch_fatal' => false,
            'dist_audit_max_errors' => 999,
            'wporg_lint_max_warnings' => 999,
        ]));

        $this->write('artifacts/qa/qa-report.json', json_encode([
            'coverage_percent' => 100,
            'rest_permission_violations' => 1,
            'sql_prepare_violations' => 0,
        ]));
        $this->write('artifacts/qa/rest-violations.json', json_encode(['foo']));
        $this->write('artifacts/qa/sql-violations.json', json_encode([]));
        $this->write('artifacts/qa/secrets.json', json_encode([]));
        $this->write('artifacts/qa/licenses.json', json_encode(['summary' => ['denied' => 0]]));
        $this->write('artifacts/i18n/pot-refresh.json', json_encode(['pot_entries' => 10, 'domain_mismatch' => 0]));
        $this->write('artifacts/schema/schema-validate.json', json_encode(['count' => 0]));
        $this->write('artifacts/dist/manifest.json', '{}');
        $this->write('artifacts/dist/sbom.json', '{}');

        $code = 0;
        $this->execute(['--enforce'], $code);
        $this->assertSame(1, $code);
    }
}
