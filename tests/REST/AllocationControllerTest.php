<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\REST;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\REST\Controllers\AllocationController;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Infra\DB\TableResolver;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class AllocationControllerTest extends BaseTestCase {
    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
        global $wpdb;
        $wpdb = new class extends \wpdb {
            public function __construct() {}
        };
        $wpdb->prefix = 'wp_';
    }

    protected function tearDown(): void {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_allocate_endpoint_uses_form_context(): void {
        Functions\expect('current_user_can')->with('smartalloc_manage')->andReturn(true);
        $tables = new TableResolver($GLOBALS['wpdb']);
        $svc = new AllocationService($tables);
        $controller = new AllocationController($svc, $tables);

        $cb = null;
        Functions\when('register_rest_route')->alias(function($ns, $route, $args) use (&$cb) { $cb = $args['callback']; });
        $controller->register();

        $req = new \WP_REST_Request('POST', '/smartalloc/v1/allocate');
        $_POST['form_id'] = '150';
        $req->set_param('form_id', 150);
        $res = $cb($req);
        $this->assertSame(200, $res->get_status());
        $data = $res->get_data();
        $this->assertSame(150, $data['summary']['form_id']);
    }
}
