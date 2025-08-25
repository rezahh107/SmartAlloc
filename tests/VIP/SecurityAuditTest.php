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

    public function test_role_gates()
    {
        $admin = $this->factory->user->create_and_get(['role' => 'administrator']);
        wp_set_current_user($admin->ID);
        foreach (['/smartalloc/v1/metrics','/smartalloc/v1/export'] as $ep) {
            $res = rest_do_request(new WP_REST_Request('GET', $ep));
            $this->assertNotEquals(403, $res->get_status(), "Admin should access $ep");
        }

        $subscriber = $this->factory->user->create_and_get(['role' => 'subscriber']);
        wp_set_current_user($subscriber->ID);
        foreach (['/smartalloc/v1/metrics','/smartalloc/v1/export'] as $ep) {
            $res = rest_do_request(new WP_REST_Request('GET', $ep));
            $this->assertEquals(403, $res->get_status(), "Subscriber must be blocked for $ep");
        }
    }
}
