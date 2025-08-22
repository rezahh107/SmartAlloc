<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Release;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\{AllocationService,Logging,Metrics,ScoringAllocator,DlqService,EventStoreWp};
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\EventStoreInterface;

final class GAEnforcerSqlPrepareTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        putenv('RUN_ENFORCE');
        $this->dir = sys_get_temp_dir() . '/ga-sql-' . uniqid();
        mkdir($this->dir, 0777, true);
        mkdir($this->dir . '/scripts', 0777, true);
        mkdir($this->dir . '/artifacts/security', 0777, true);
        mkdir($this->dir . '/artifacts/ga', 0777, true);
        mkdir($this->dir . '/artifacts/coverage', 0777, true);
        mkdir($this->dir . '/artifacts/schema', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rm($this->dir);
    }

    private function rm(string $path): void
    {
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }
        @rmdir($path);
    }

    private function write(string $relative, string $content): void
    {
        $path = $this->dir . '/' . $relative;
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($path, $content);
    }

    public function test_sql_prepare_junit_behaviour(): void
    {
        $scripts = ['ga-enforcer.php', 'coverage-import.php', 'artifact-schema-validate.php'];
        foreach ($scripts as $s) {
            $src = dirname(__DIR__, 3) . '/scripts/' . $s;
            $this->write('scripts/' . $s, file_get_contents($src));
        }
        // stub scanner
        $this->write('scripts/scan-sql-prepare.php', "<?php\nexit(0);\n");

        $report = [
            'generated_at_utc' => '2025-01-01T00:00:00Z',
            'total_files_scanned' => 1,
            'violations' => [
                ['file' => 'bad.php', 'line' => 1, 'call' => '$wpdb->query', 'sql_preview' => 'SELECT 1', 'fingerprint' => 'x', 'allowlisted' => false],
            ],
            'counts' => ['violations' => 1, 'allowlisted' => 0],
        ];
        $this->write('artifacts/security/sql-prepare.json', json_encode($report));
        $this->write('artifacts/schema/schema-validate.json', json_encode(['count' => 0]));

        $cmd = 'php ' . escapeshellarg($this->dir . '/scripts/ga-enforcer.php') . ' --profile=rc --junit';
        exec($cmd, $out, $code);
        $this->assertSame(0, $code);
        $xml = simplexml_load_file($this->dir . '/artifacts/ga/GA_ENFORCER.junit.xml');
        $case = $xml->xpath('//testcase[@name="SQL.Prepare"]')[0];
        $this->assertTrue(isset($case->skipped));
        $this->assertStringContainsString('violations=1', (string)$case->skipped['message']);

        $cmd = 'php ' . escapeshellarg($this->dir . '/scripts/ga-enforcer.php') . ' --profile=ga --enforce --junit';
        exec($cmd, $out, $code);
        $this->assertSame(1, $code);
        $xml = simplexml_load_file($this->dir . '/artifacts/ga/GA_ENFORCER.junit.xml');
        $case = $xml->xpath('//testcase[@name="SQL.Prepare"]')[0];
        $this->assertTrue(isset($case->failure));
        $this->assertStringContainsString('bad.php:1', (string)$case->failure['message']);
    }

    public function test_hot_paths_require_prepared_sql(): void
    {
        global $wpdb;
        $wpdb = new class {
            public string $prefix = 'wp_';
            public int $rows_affected = 1;
            public function prepare($sql, $params) { return $sql; }
            public function query($sql) { }
            public function get_results($sql, $mode = null) { return []; }
            public function insert($t, $d) { }
            public function get_row($sql, $mode = null) { return null; }
        };
        $logger = new Logging();
        $metrics = new Metrics();
        $store = new class implements EventStoreInterface {
            public function insertEventIfNotExists(string $e,string $k,array $p): int { return 1; }
            public function startListenerRun(int $e,string $l): int { return 1; }
            public function finishListenerRun(int $i,string $s,?string $er,int $d): void {}
            public function finishEvent(int $i,string $s,?string $e,int $d): void {}
        };
        $bus = new EventBus($logger, $store);

        $alloc = new AllocationService($logger, $bus, $metrics, new ScoringAllocator());
        try {
            $alloc->assign(['id' => 1, 'gender' => 'M', 'center' => 'C']);
            $this->fail('Allocation did not enforce prepare');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SQL', $e->getMessage());
        }

        $dlq = new DlqService();
        try {
            $dlq->listRecent();
            $this->fail('DLQ did not enforce prepare');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SQL', $e->getMessage());
        }

        $store2 = new EventStoreWp();
        try {
            $store2->insertEventIfNotExists('Evt', 'k', []);
            $this->fail('Event store did not enforce prepare');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('SQL', $e->getMessage());
        }
    }
}
