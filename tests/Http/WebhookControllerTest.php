<?php

declare(strict_types=1);

use SmartAlloc\Http\Rest\WebhookController;
use SmartAlloc\Tests\BaseTestCase;
use Brain\Monkey\Functions;

final class WebhookControllerTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Functions\when('__')->returnArg();
    }

    public function test_valid_signature(): void
    {
        $GLOBALS['sa_options']['smartalloc_settings'] = ['webhook_secret' => 'sek', 'enable_incoming_webhook' => 1];
        $c = new WebhookController();
        $req = new WP_REST_Request();
        $req->set_body('{"a":1}');
        $ts = time();
        $sig = hash_hmac('sha256', $req->get_body(), 'sek');
        $req->set_header('Content-Type', 'application/json');
        $req->set_header('X-SmartAlloc-Timestamp', (string)$ts);
        $req->set_header('X-SmartAlloc-Signature', $sig);
        $req->set_header('X-Request-Id', 't1');
        $res = $c->handle($req);
        $this->assertInstanceOf(WP_REST_Response::class, $res);
        $this->assertSame(200, $res->get_status());
    }

    public function test_invalid_signature(): void
    {
        $GLOBALS['sa_options']['smartalloc_settings'] = ['webhook_secret' => 'sek', 'enable_incoming_webhook' => 1];
        $c = new WebhookController();
        $req = new WP_REST_Request();
        $req->set_body('{}');
        $req->set_header('Content-Type', 'application/json');
        $req->set_header('X-SmartAlloc-Timestamp', (string)time());
        $req->set_header('X-SmartAlloc-Signature', 'bad');
        $req->set_header('X-Request-Id', 't2');
        $res = $c->handle($req);
        $this->assertInstanceOf(WP_Error::class, $res);
        $this->assertSame('invalid_signature', $res->get_error_code());
    }
}
