<?php

declare(strict_types=1);

use SmartAlloc\Http\Rest\WebhookController;
use SmartAlloc\Tests\BaseTestCase;
use Brain\Monkey\Functions;

final class WebhookReplayTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['sa_transients'] = [];
        $GLOBALS['sa_options']['smartalloc_settings'] = ['webhook_secret' => 'abc'];
        Functions\when('__')->returnArg();
    }

    public function test_valid_request_ok(): void
    {
        $controller = new WebhookController();
        $req = new WP_REST_Request();
        $req->set_body('{}');
        $req->set_header('Content-Type', 'application/json');
        $req->set_header('X-SmartAlloc-Timestamp', (string) time());
        $req->set_header('X-SmartAlloc-Signature', hash_hmac('sha256', '{}', 'abc'));
        $req->set_header('X-Request-Id', 'r1');
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
        $res = $controller->handle($req);
        $this->assertSame(200, $res->get_status());
    }

    public function test_replay_rejected(): void
    {
        $controller = new WebhookController();
        $req = new WP_REST_Request();
        $body = '{}';
        $sig = hash_hmac('sha256', $body, 'abc');
        $req->set_body($body);
        $req->set_header('Content-Type', 'application/json');
        $req->set_header('X-SmartAlloc-Timestamp', (string) time());
        $req->set_header('X-SmartAlloc-Signature', $sig);
        $req->set_header('X-Request-Id', 'r2');
        $_SERVER['REMOTE_ADDR'] = '1.1.1.1';
        $controller->handle($req);
        $res = $controller->handle($req);
        $this->assertInstanceOf(WP_Error::class, $res);
        $this->assertSame(409, $res->get_error_data()['status']);
    }

    public function test_rate_limit_rejected(): void
    {
        $controller = new WebhookController();
        $req = new WP_REST_Request();
        $body = '{}';
        $sig = hash_hmac('sha256', $body, 'abc');
        $req->set_body($body);
        $req->set_header('Content-Type', 'application/json');
        $req->set_header('X-SmartAlloc-Timestamp', (string) time());
        $req->set_header('X-SmartAlloc-Signature', $sig);
        $req->set_header('X-Request-Id', 'r3');
        $_SERVER['REMOTE_ADDR'] = '2.2.2.2';
        $rateKey = 'smartalloc_rl_' . md5('abc|2.2.2.2');
        $GLOBALS['sa_transients'][$rateKey] = 60;
        $res = $controller->handle($req);
        $this->assertInstanceOf(WP_Error::class, $res);
        $this->assertSame(429, $res->get_error_data()['status']);
    }
}
