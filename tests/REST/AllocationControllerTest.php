<?php

namespace SmartAlloc\Tests\REST;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\REST\Controllers\AllocationController;
use SmartAlloc\Contracts\AllocationServiceInterface;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Services\Exceptions\DuplicateAllocationException;
use SmartAlloc\Tests\BaseTestCase;

final class AllocationControllerTest extends BaseTestCase
{
    private AllocationController $controller;
    private AllocationServiceInterface $svc;
    private $cb;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $svc = new class implements AllocationServiceInterface {
            public array $keys = [];
            public ?FormContext $lastCtx = null;
            public function allocateWithContext(FormContext $ctx, array $payload): array {
                $this->lastCtx = $ctx;
                $key = $ctx->formId . ':' . ($payload['student_id'] ?? '') . ':' . ($payload['email'] ?? '');
                if (in_array($key, $this->keys, true)) {
                    throw new DuplicateAllocationException('duplicate allocation');
                }
                $this->keys[] = $key;
                return ['summary' => ['form_id' => $ctx->formId, 'count' => 1], 'allocations' => []];
            }
            public function allocate(array $payload): array { return []; }
        };
        $this->svc = $svc;
        $this->controller = new AllocationController($svc);
        $this->cb = null;
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function register(): void
    {
        $this->controller->register();
        $key = 'smartalloc/v1 /allocate/(?P<form_id>\\d+)';
        $this->cb = $GLOBALS['sa_rest_routes'][$key]['callback'];
    }

    /** @test */
    public function returns_201_on_valid_request_with_form_id_and_nonce_and_cap(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(true);
        Functions\expect('wp_verify_nonce')->with('good', 'smartalloc_allocate_200')->andReturn(true);
        $this->register();
        $req = new \WP_REST_Request(['form_id' => 200, '_wpnonce' => 'good']);
        $req->set_body(json_encode(['student_id'=>1,'email'=>'a@a.com']));
        $res = ($this->cb)($req);
        $this->assertSame(201, $res->get_status());
    }

    /** @test */
    public function returns_403_on_missing_capability(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(false);
        $this->register();
        $req = new \WP_REST_Request();
        $res = ($this->cb)($req);
        $this->assertSame(403, $res->get_status());
    }

    /** @test */
    public function returns_403_on_bad_nonce(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(true);
        Functions\expect('wp_verify_nonce')->with('bad', 'smartalloc_allocate_200')->andReturn(false);
        $this->register();
        $req = new \WP_REST_Request(['form_id' => 200, '_wpnonce' => 'bad']);
        $res = ($this->cb)($req);
        $this->assertSame(403, $res->get_status());
    }

    /** @test */
    public function returns_400_on_bad_form_id(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(true);
        Functions\expect('wp_verify_nonce')->with('n', 'smartalloc_allocate_0')->andReturn(true);
        $this->register();
        $req = new \WP_REST_Request(['form_id' => 0, '_wpnonce' => 'n']);
        $res = ($this->cb)($req);
        $this->assertSame(400, $res->get_status());
    }

    /** @test */
    public function returns_409_on_duplicate(): void
    {
        Functions\when('current_user_can')->alias(fn() => true);
        Functions\when('wp_verify_nonce')->alias(fn() => true);
        $this->register();
        $req1 = new \WP_REST_Request(['form_id' => 200, '_wpnonce' => 'n']);
        $req1->set_body(json_encode(['student_id'=>1,'email'=>'dup@a.com']));
        ($this->cb)($req1);
        $reqDup = new \WP_REST_Request(['form_id' => 200, '_wpnonce' => 'n']);
        $reqDup->set_body(json_encode(['student_id'=>1,'email'=>'dup@a.com']));
        $resDup = ($this->cb)($reqDup);
        $this->assertSame(409, $resDup->get_status());
    }

    /** @test */
    public function passes_form_id_through_to_service(): void
    {
        Functions\expect('current_user_can')->with('manage_smartalloc')->andReturn(true);
        Functions\expect('wp_verify_nonce')->andReturn(true);
        $this->register();
        $req = new \WP_REST_Request(['form_id' => 123, '_wpnonce' => 'n']);
        $req->set_body(json_encode(['student_id'=>5,'email'=>'x@a.com']));
        ($this->cb)($req);
        $this->assertSame(123, $this->svc->lastCtx->formId);
    }
}
