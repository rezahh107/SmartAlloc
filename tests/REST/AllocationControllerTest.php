<?php
namespace {
    function register_rest_route($ns, $route, $args) { $GLOBALS['__rest_cb'] = $args['callback']; }
}

namespace SmartAlloc\Tests\REST;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\REST\Controllers\AllocationController;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Tests\BaseTestCase;

final class AllocationControllerTest extends BaseTestCase
{
    private AllocationController $controller;
    private $cb;

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
                if (isset($args[0]) && is_array($args[0])) { $args = $args[0]; }
                foreach ($args as &$a) { if (is_string($a)) { $a = "'" . addslashes($a) . "'"; } }
                return vsprintf($query, $args);
            }
            public function get_var($query) {
                if (preg_match('/FROM\s+(\w+)/', $query, $m)) { $table = $m[1]; } else { return 0; }
                $rows = $this->data[$table] ?? [];
                if (str_contains($query, 'WHERE')) {
                    if (preg_match("/student_id = (\d+) OR email = \\'([^']*)\'/", $query, $n)) {
                        $sid = (int) $n[1]; $email = stripslashes($n[2]);
                        foreach ($rows as $r) { if ($r['student_id'] === $sid || $r['email'] === $email) { return 1; } }
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
        $svc = new AllocationService($tables);
        $this->controller = new AllocationController($svc);
        $this->cb = null;
        Functions\when('sanitize_email')->returnArg();
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('current_time')->alias(fn($type, $gmt) => gmdate('Y-m-d H:i:s'));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function register(): void
    {
        $this->controller->register();
        $this->cb = $GLOBALS['__rest_cb'];
    }

    /** @test */
    public function returns_201_on_valid_request_with_form_id_and_nonce_and_cap(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(true);
        Functions\expect('wp_verify_nonce')->with('good', 'smartalloc_allocate_200')->andReturn(true);
        $this->register();
        $req = new \WP_REST_Request('POST', '/smartalloc/v1/allocate');
        $_POST['form_id'] = '200';
        $req->set_param('form_id', 200);
        $req->set_param('_wpnonce', 'good');
        $req->set_body(json_encode(['student_id'=>1,'email'=>'a@a.com']));
        $res = ($this->cb)($req);
        $this->assertSame(201, $res->get_status());
    }

    /** @test */
    public function returns_403_on_missing_capability(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(false);
        $this->register();
        $req = new \WP_REST_Request('POST', '/smartalloc/v1/allocate');
        $res = ($this->cb)($req);
        $this->assertSame(403, $res->get_status());
    }

    /** @test */
    public function returns_403_on_bad_nonce(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(true);
        Functions\expect('wp_verify_nonce')->with('bad', 'smartalloc_allocate_200')->andReturn(false);
        $this->register();
        $req = new \WP_REST_Request('POST', '/smartalloc/v1/allocate');
        $req->set_param('form_id', 200);
        $req->set_param('_wpnonce', 'bad');
        $res = ($this->cb)($req);
        $this->assertSame(403, $res->get_status());
    }

    /** @test */
    public function returns_409_on_duplicate_and_400_on_capacity_exceeded_mapping(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(true);
        Functions\expect('wp_verify_nonce')->andReturn(true);
        $this->register();
        $req1 = new \WP_REST_Request('POST', '/smartalloc/v1/allocate');
        $req1->set_param('form_id', 200);
        $req1->set_param('_wpnonce', 'n');
        $req1->set_body(json_encode(['student_id'=>1,'email'=>'dup@a.com']));
        ($this->cb)($req1);
        $reqDup = new \WP_REST_Request('POST', '/smartalloc/v1/allocate');
        $reqDup->set_param('form_id', 200);
        $reqDup->set_param('_wpnonce', 'n');
        $reqDup->set_body(json_encode(['student_id'=>1,'email'=>'dup@a.com']));
        $resDup = ($this->cb)($reqDup);
        $this->assertSame(409, $resDup->get_status());
        for ($i = 0; $i < 59; $i++) {
            $r = new \WP_REST_Request('POST', '/smartalloc/v1/allocate');
            $r->set_param('form_id', 200);
            $r->set_param('_wpnonce', 'n');
            $r->set_body(json_encode(['student_id'=>$i+2,'email'=>"u{$i}@a.com"]));
            ($this->cb)($r);
        }
        $reqCap = new \WP_REST_Request('POST', '/smartalloc/v1/allocate');
        $reqCap->set_param('form_id', 200);
        $reqCap->set_param('_wpnonce', 'n');
        $reqCap->set_body(json_encode(['student_id'=>999,'email'=>'z@a.com']));
        $resCap = ($this->cb)($reqCap);
        $this->assertSame(400, $resCap->get_status());
    }

    /** @test */
    public function passes_form_id_through_to_service(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(true);
        Functions\expect('wp_verify_nonce')->andReturn(true);
        $this->register();
        $req = new \WP_REST_Request('POST', '/smartalloc/v1/allocate');
        $req->set_param('form_id', 123);
        $req->set_param('_wpnonce', 'n');
        $req->set_body(json_encode(['student_id'=>5,'email'=>'x@a.com']));
        ($this->cb)($req);
        global $wpdb;
        $this->assertArrayHasKey('wp_smartalloc_allocations_f123', $wpdb->data);
    }
}
