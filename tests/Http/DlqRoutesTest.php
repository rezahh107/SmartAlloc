<?php
declare(strict_types=1);

namespace {
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Http\Rest\DlqController;
use SmartAlloc\Services\DlqService;

if (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/../../'); }
if (!defined('WPINC')) { define('WPINC', 'wp-includes'); }
if (!defined('SMARTALLOC_CAP')) { define('SMARTALLOC_CAP', 'manage_options'); }
if (!defined('ARRAY_A')) { define('ARRAY_A', 1); }

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(
            public string $code = '',
            public string $message = '',
            public array $data = []
        ) {}
        public function get_error_data(): array { return $this->data; }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        public function __construct(public string $method = 'GET', public string $route = '') {}
    }
}
class TestRequest extends WP_REST_Request {
    public function __construct(private array $params = []) {
        parent::__construct('POST');
    }
    public function get_param(string $key) { return $this->params[$key] ?? null; }
    public function __get($key) { return $this->params[$key] ?? null; }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct(private array $data = [], private int $status = 200) {}
        public function get_status(): int { return $this->status; }
        public function get_data(): array { return $this->data; }
    }
}

if (!function_exists('add_action')) { function add_action($h,$c,$p=10,$a=1){} }
if (!function_exists('wp_schedule_single_event')) { function wp_schedule_single_event($t,$h,$a){} }
if (!function_exists('wp_next_scheduled')) { function wp_next_scheduled($h,$a){ return false; } }
if (!function_exists('as_enqueue_async_action')) { function as_enqueue_async_action(){ return false; } }
if (!function_exists('as_next_scheduled_action')) { function as_next_scheduled_action(){ return false; } }
if (!function_exists('do_action')) { function do_action($h,$a){ if(isset($GLOBALS['__do_action'])) { ($GLOBALS['__do_action'])($h,$a); } } }
if (!function_exists('current_user_can')) { function current_user_can($cap){ return $GLOBALS['can'] ?? false; } }
if (!function_exists('get_current_user_id')) { function get_current_user_id(){ return 1; } }
if (!function_exists('get_transient')) { function get_transient($k){ global $t; return $t[$k] ?? false; } }
if (!function_exists('set_transient')) { function set_transient($k,$v,$e){ global $t; $t[$k] = $v; } }
if (!function_exists('wp_json_encode')) { function wp_json_encode($d){ return json_encode($d); } }

final class DlqRoutesTest extends BaseTestCase
{
    private function setupWpdb(): void
    {
        $GLOBALS['wpdb'] = new class {
            public string $prefix = 'wp_';
            public array $dlq = [
                ['id'=>1,'event_name'=>'e','payload'=>'{"x":1}','error_text'=>'e','attempts'=>1,'created_at'=>'2020'],
                ['id'=>2,'event_name'=>'e','payload'=>'bad','error_text'=>'e','attempts'=>1,'created_at'=>'2020'],
            ];
            private int $lastId = 0;
            public function prepare($sql,...$args){
                $params = is_array($args[0] ?? null) ? $args[0] : $args;
                if (isset($params[0])) { $this->lastId = (int)$params[0]; }
                foreach ($params as $p) {
                    $sql = preg_replace('/%d/', (string)(int)$p, $sql, 1);
                    $sql = preg_replace('/%s/', "'" . $p . "'", $sql, 1);
                }
                return $sql;
            }
            public function get_results($sql,$mode){ return $this->dlq; }
            public function get_row($sql,$mode){
                foreach ($this->dlq as $r) {
                    if ($r['id'] === $this->lastId) { return $r; }
                }
                return null;
            }
            public function delete($t,$w){
                foreach ($this->dlq as $i => $r) {
                    if ($r['id'] === $w['id']) { unset($this->dlq[$i]); }
                }
            }
            public function insert($t,$d){}
            public function query($sql){}
        };
    }

    public function testListRequiresCapability(): void
    {
        $this->setupWpdb();
        $GLOBALS['can'] = false;
        $controller = new DlqController(new DlqService());
        $resp = $controller->list(new WP_REST_Request());
        $this->assertInstanceOf(WP_Error::class, $resp);
        $this->assertSame(403, $resp->get_error_data()['status']);
    }

    public function testListReturnsItems(): void
    {
        $this->setupWpdb();
        $GLOBALS['can'] = true;
        $controller = new DlqController(new DlqService());
        $resp = $controller->list(new WP_REST_Request());
        $this->assertInstanceOf(WP_REST_Response::class, $resp);
        $this->assertSame(200, $resp->get_status());
        $data = $resp->get_data();
        $this->assertCount(2, $data);
        $this->assertArrayHasKey('payload_preview', $data[0]);
    }

    public function testRetryMissingItem(): void
    {
        $this->setupWpdb();
        $GLOBALS['can'] = true;
        $controller = new DlqController(new DlqService());
        $resp = $controller->retry(new TestRequest(['id' => 999]));
        $this->assertInstanceOf(WP_Error::class, $resp);
        $this->assertSame(404, $resp->get_error_data()['status']);
    }

    public function testRetryDispatchesAndDeletes(): void
    {
        $this->setupWpdb();
        $GLOBALS['can'] = true;
        $controller = new DlqController(new DlqService());
        $GLOBALS['ran'] = null;
        $GLOBALS['__do_action'] = function($h,$a){ $GLOBALS['ran'] = $a; };
        $resp = $controller->retry(new TestRequest(['id' => 1]));
        $this->assertInstanceOf(WP_REST_Response::class, $resp);
        $this->assertSame(200, $resp->get_status());
        $this->assertSame('e', $GLOBALS['ran']['event_name']);
        $this->assertSame(1, $GLOBALS['ran']['body']['x']);
        $this->assertCount(1, $GLOBALS['wpdb']->dlq);
    }
}
}

namespace SmartAlloc\Security {
    function get_transient($k){ return $GLOBALS['t'][$k] ?? false; }
    function set_transient($k,$v,$e){ $GLOBALS['t'][$k] = $v; }
}
namespace SmartAlloc\Http\Rest {
    function wp_json_encode($d){ return \json_encode($d); }
}
