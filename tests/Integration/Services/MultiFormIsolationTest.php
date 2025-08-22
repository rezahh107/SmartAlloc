<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Tests\BaseTestCase;

final class MultiFormIsolationTest extends BaseTestCase
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
            public function get_var($query) {
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
        parent::tearDown();
    }

    /** @test */
    public function allocations_are_isolated_per_form(): void
    {
        global $wpdb;
        $this->svc->allocateWithContext(new FormContext(150), ['student_id' => 1, 'email' => 'a@a.com']);
        $this->svc->allocateWithContext(new FormContext(200), ['student_id' => 1, 'email' => 'a@a.com']);
        $this->assertCount(1, $wpdb->data['wp_smartalloc_allocations_f150']);
        $this->assertCount(1, $wpdb->data['wp_smartalloc_allocations_f200']);
        $ts = $wpdb->data['wp_smartalloc_allocations_f150'][0]['created_at'];
        $dt = new \DateTime($ts, new \DateTimeZone('UTC'));
        $this->assertSame('UTC', $dt->getTimezone()->getName());
    }
}
