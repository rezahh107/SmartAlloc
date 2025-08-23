<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\REST;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\REST\Controllers\ExportController;
use SmartAlloc\Tests\BaseTestCase;

final class ExportControllerTest extends BaseTestCase
{
    private ExportController $controller;
    private $cb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $this->controller = new ExportController();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function register(): void
    {
        $this->controller->register();
        $key = 'smartalloc/v1 /export';
        $this->cb = $GLOBALS['sa_rest_routes'][$key]['callback'];
    }

    /** @test */
    public function returns_403_when_cap_missing(): void
    {
        Functions\expect('current_user_can')->with('smartalloc_manage')->andReturn(false);
        $this->register();
        $req = new \WP_REST_Request();
        $res = ($this->cb)($req);
        $this->assertSame(403, $res->get_status());
    }

    /** @test */
    public function returns_403_on_bad_nonce(): void
    {
        Functions\expect('current_user_can')->with('smartalloc_manage')->andReturn(true);
        Functions\expect('wp_verify_nonce')->with('bad', 'smartalloc_export_5')->andReturn(false);
        $this->register();
        $req = new \WP_REST_Request(['form_id' => 5, '_wpnonce' => 'bad']);
        $res = ($this->cb)($req);
        $this->assertSame(403, $res->get_status());
    }

    /** @test */
    public function exports_and_logs_on_success(): void
    {
        Functions\expect('current_user_can')->with('smartalloc_manage')->andReturn(true);
        Functions\expect('wp_verify_nonce')->with('good', 'smartalloc_export_5')->andReturn(true);
        $this->register();
        global $wpdb;
        $wpdb->queries = [];
        $req = new \WP_REST_Request(['form_id' => 5, '_wpnonce' => 'good']);
        $res = ($this->cb)($req);
        $this->assertSame(200, $res->get_status());
        $data = $res->get_data();
        $this->assertTrue($data['ok']);
        $insert = array_filter($wpdb->queries, fn($q) => str_contains($q, 'smartalloc_export_log'));
        $this->assertNotEmpty($insert);
    }
}

