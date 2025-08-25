<?php
namespace SmartAlloc\Tests\VIP;

use WP_UnitTestCase;
use WP_REST_Request;

class SecurityAuditTest extends WP_UnitTestCase
{
    public function test_all_rest_endpoints_have_permission_callback()
    {
        $routes = rest_get_server()->get_routes();
        foreach ($routes as $route => $handlers) {
            if (strpos($route, '/smartalloc/v1/') !== 0) continue;
            $this->assertIsArray($handlers, "Handlers missing for $route");
            $hasPerm = false;
            foreach ($handlers as $h) {
                if (isset($h['permission_callback'])) { $hasPerm = true; break; }
            }
            $this->assertTrue($hasPerm, "permission_callback missing for $route");
        }
    }

    public function test_admin_can_access_protected_endpoints()
    {
        $user = $this->factory->user->create_and_get(['role' => 'administrator']);
        wp_set_current_user($user->ID);
        $endpoints = ['/smartalloc/v1/metrics', '/smartalloc/v1/export'];
        foreach ($endpoints as $ep) {
            $req = new WP_REST_Request('GET', $ep);
            $res = rest_do_request($req);
            $this->assertNotEquals(403, $res->get_status(), "Admin should access $ep");
        }
    }

    public function test_subscriber_cannot_access_protected_endpoints()
    {
        $user = $this->factory->user->create_and_get(['role' => 'subscriber']);
        wp_set_current_user($user->ID);
        $endpoints = ['/smartalloc/v1/metrics', '/smartalloc/v1/export'];
        foreach ($endpoints as $ep) {
            $req = new WP_REST_Request('GET', $ep);
            $res = rest_do_request($req);
            $this->assertEquals(403, $res->get_status(), "Subscriber should be blocked for $ep");
        }
    }
}
