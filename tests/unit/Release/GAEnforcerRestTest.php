<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Release;

use PHPUnit\Framework\TestCase;

final class GAEnforcerRestTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        putenv('RUN_ENFORCE');
        $this->dir = sys_get_temp_dir() . '/ga-rest-' . uniqid();
        mkdir($this->dir, 0777, true);
        mkdir($this->dir . '/scripts', 0777, true);
        mkdir($this->dir . '/artifacts/security', 0777, true);
        mkdir($this->dir . '/artifacts/coverage', 0777, true);
        mkdir($this->dir . '/artifacts/schema', 0777, true);
        mkdir($this->dir . '/artifacts/ga', 0777, true);
    }

    protected function tearDown(): void
    {
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $path = $item->getPathname();
            if ($item->isDir()) {
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($this->dir);
    }

    private function write(string $rel, string $content): void
    {
        $path = $this->dir . '/' . $rel;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
    }

    public function test_rest_permissions_junit(): void
    {
        // copy main scripts
        foreach (['ga-enforcer.php','coverage-import.php','artifact-schema-validate.php'] as $s) {
            $src = dirname(__DIR__, 3) . '/scripts/' . $s;
            $this->write('scripts/' . $s, file_get_contents($src));
        }
        // stub scan-rest-permissions
        $stub = <<<'PHPSTUB'
<?php
$root = dirname(__DIR__);
@mkdir($root . '/artifacts/security', 0777, true);
$data = [
  'generated_at_utc' => '2025-01-01T00:00:00Z',
  'routes' => [],
  'warnings' => [
    ['file'=>'x.php','line'=>1,'route'=>'foo/v1/mut','type'=>'mutating_missing_capability','fingerprint'=>'f']
  ],
  'summary' => ['routes'=>1,'warnings'=>1,'mutating_warnings'=>1,'readonly_warnings'=>0]
];
file_put_contents($root . '/artifacts/security/rest-permissions.json', json_encode($data));
PHPSTUB;
        $this->write('scripts/scan-rest-permissions.php', $stub);
        // stub scan-sql-prepare
        $this->write('scripts/scan-sql-prepare.php', "<?php\nexit(0);\n");
        $this->write('artifacts/security/sql-prepare.json', json_encode(['counts'=>['violations'=>0,'allowlisted'=>0], 'violations'=>[]]));
        // stub qa-report
        $this->write('scripts/qa-report.php', "<?php\nexit(0);\n");
        // coverage & schema
        $this->write('artifacts/coverage/coverage.json', json_encode(['totals'=>['lines'=>['pct'=>100]] ]));
        $this->write('artifacts/schema/schema-validate.json', json_encode(['count'=>0]));
        // config
        $config = [
            'rest_permission_violations'=>0,
            'sql_prepare_violations'=>0,
            'secrets_findings'=>0,
            'license_denied'=>0,
            'i18n_domain_mismatches'=>0,
            'coverage_pct_min'=>0,
            'schema_warnings'=>0,
            'require_manifest'=>false,
            'require_sbom'=>false,
            'version_mismatch_fatal'=>false,
            'dist_audit_max_errors'=>0,
            'wporg_lint_max_warnings'=>0,
            'pot_min_entries'=>0
        ];
        $this->write('scripts/.ga-enforce.json', json_encode($config));

        // RC run: skipped
        $cmd = 'php ' . escapeshellarg($this->dir . '/scripts/ga-enforcer.php') . ' --profile=rc --junit';
        exec($cmd, $out, $code);
        $this->assertSame(0, $code);
        $xml = simplexml_load_file($this->dir . '/artifacts/ga/GA_ENFORCER.junit.xml');
        $case = $xml->xpath('//testcase[@name="REST.Permissions"]')[0];
        $this->assertTrue(isset($case->skipped));

        // GA enforce: failure
        $cmd = 'php ' . escapeshellarg($this->dir . '/scripts/ga-enforcer.php') . ' --profile=ga --enforce --junit';
        exec($cmd, $out, $code);
        $this->assertSame(1, $code);
        $xml = simplexml_load_file($this->dir . '/artifacts/ga/GA_ENFORCER.junit.xml');
        $case = $xml->xpath('//testcase[@name="REST.Permissions"]')[0];
        $this->assertTrue(isset($case->failure));
    }
}
