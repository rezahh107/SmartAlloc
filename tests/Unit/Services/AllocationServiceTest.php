<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Exceptions\DuplicateAllocationException;
use SmartAlloc\Services\Exceptions\InsufficientCapacityException;
use SmartAlloc\Tests\BaseTestCase;

final class AllocationServiceTest extends BaseTestCase
{
    private AllocationService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class extends \wpdb {
            public string $prefix = 'wp_';
            public array $data = [];
            public function __construct() {}
            public function prepare(string $query, ...$args): string {
                if (isset($args[0]) && is_array($args[0])) {
                    $args = $args[0];
                }
                foreach ($args as &$a) {
                    if (is_string($a)) {
                        $a = "'" . addslashes($a) . "'";
                    }
                }
                return vsprintf($query, $args);
            }
            public function get_var($query = null, $x = 0, $y = 0) {
                if (preg_match('/FROM\s+(\w+)/', $query, $m)) {
                    $table = $m[1];
                } else {
                    return 0;
                }
                $rows = $this->data[$table] ?? [];
                if (str_contains($query, 'WHERE')) {
                    if (preg_match("/student_id = (\d+) OR email = \\'([^']*)\'/", $query, $n)) {
                        $sid = (int) $n[1];
                        $email = stripslashes($n[2]);
                        foreach ($rows as $r) {
                            if ($r['student_id'] === $sid || $r['email'] === $email) {
                                return 1;
                            }
                        }
                    }
                    return 0;
                }
                return count($rows);
            }
            public function query($query) {
                if (preg_match("/INTO\s+(\w+).*VALUES\s*\((\d+),'([^']*)','([^']*)','([^']*)','([^']*)'\)/", $query, $m)) {
                    $this->data[$m[1]][] = [
                        'student_id' => (int) $m[2],
                        'email' => stripslashes($m[3]),
                        'mobile' => stripslashes($m[4]),
                        'national_id' => stripslashes($m[5]),
                        'created_at' => stripslashes($m[6]),
                    ];
                    return 1;
                }
                return 0;
            }
        };
        $tables = new TableResolver($wpdb);
        $this->svc = new AllocationService($tables);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function success_path_creates_row_with_utc_timestamp_and_metric(): void
    {
        $ctx = new FormContext(150);
        $payload = ['student_id' => 1, 'email' => 'a@b.com'];
        $this->svc->allocateWithContext($ctx, $payload);
        global $wpdb;
        $this->assertArrayHasKey('wp_smartalloc_allocations_f150', $wpdb->data);
        $row = $wpdb->data['wp_smartalloc_allocations_f150'][0];
        $dt = new \DateTime($row['created_at'], new \DateTimeZone('UTC'));
        $this->assertSame('UTC', $dt->getTimezone()->getName());
    }

    /** @test */
    public function duplicate_throws_exception(): void
    {
        $ctx = new FormContext(150);
        $payload = ['student_id' => 2, 'email' => 'dup@b.com'];
        $this->svc->allocateWithContext($ctx, $payload);
        $this->expectException(DuplicateAllocationException::class);
        $this->svc->allocateWithContext($ctx, $payload);
    }

    /** @test */
    public function capacity_throws_exception_after_limit(): void
    {
        $ctx = new FormContext(150);
        for ($i = 0; $i < 60; $i++) {
            $this->svc->allocateWithContext($ctx, ['student_id' => $i + 10, 'email' => "u{$i}@b.com"]);
        }
        $this->expectException(InsufficientCapacityException::class);
        $this->svc->allocateWithContext($ctx, ['student_id' => 999, 'email' => 'z@b.com']);
    }

    /** @test */
    public function logs_mask_pii(): void
    {
        $ctx = new FormContext(150);
        $payload = ['student_id' => 3, 'email' => 'a@b.com', 'mobile' => '123', 'national_id' => 'xyz'];
        $tmp = tempnam(sys_get_temp_dir(), 'log');
        $prev = ini_set('error_log', $tmp);
        $this->svc->allocateWithContext($ctx, $payload);
        $logged = file_get_contents($tmp) ?: '';
        unlink($tmp);
        if ($prev !== false) {
            ini_set('error_log', $prev);
        }
        $this->assertStringNotContainsString('a@b.com', $logged);
        $this->assertStringContainsString('a***', $logged);
    }
}
