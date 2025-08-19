<?php

declare(strict_types=1);

use Brain\Monkey; 
use Brain\Monkey\Functions;
use SmartAlloc\Http\RestController;
use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Domain\Allocation\AllocationResult;
use SmartAlloc\Container;
use SmartAlloc\Tests\BaseTestCase;

final class ManualReviewEndpointTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('get_current_user_id')->justReturn(1);
        Functions\when('current_user_can')->justReturn(true);
        $GLOBALS['sa_transients'] = [];
        Functions\when('delete_transient');
        Functions\when('sanitize_key')->alias(fn($k)=>$k);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    private function makeController($repo, $metrics): RestController
    {
        $c = new Container();
        $c->set(AllocationsRepository::class, fn() => $repo);
        $c->set(Metrics::class, fn() => $metrics);
        return new RestController($c);
    }

    public function test_approve_success(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(true);

        $repo = new class {
            public function approveManual($e,$m,$r,$n){ return new AllocationResult(['committed'=>true]); }
        };
        $metrics = new class { public array $inc=[]; public function inc($k){ $this->inc[]=$k; } };
        $controller = $this->makeController($repo, $metrics);
        $req = new WP_REST_Request(['entry'=>1,'mentor_id'=>2]);
        $req->set_header('X-WP-Nonce','a');
        $ref = new ReflectionClass($controller);
        $m = $ref->getMethod('approveManual'); $m->setAccessible(true);
        $res = $m->invoke($controller, $req);
        $this->assertInstanceOf(WP_REST_Response::class, $res);
        $this->assertSame(200, $res->get_status());
        $this->assertSame(['review_approve_total'], $metrics->inc);
    }

    public function test_approve_capacity_exceeded(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(true);

        $repo = new class {
            public function approveManual($e,$m,$r,$n){ return new AllocationResult(['committed'=>false,'reason'=>'capacity']); }
        };
        $metrics = new class { public array $inc=[]; public function inc($k){ $this->inc[]=$k; } };
        $controller = $this->makeController($repo, $metrics);
        $req = new WP_REST_Request(['entry'=>1,'mentor_id'=>2]);
        $req->set_header('X-WP-Nonce','a');
        $ref = new ReflectionClass($controller);
        $m = $ref->getMethod('approveManual'); $m->setAccessible(true);
        $res = $m->invoke($controller,$req);
        $this->assertSame(409, $res->get_status());
        $this->assertSame('capacity_exceeded', $res->get_data()['error']);
        $this->assertSame(['review_capacity_blocked'], $metrics->inc);
    }

    public function test_reject_reason_allowlist(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(true);

        $repo = new class {
            public array $called=[]; public function rejectManual($e,$r,$reason,$n){ $this->called[]=$reason; }
        };
        $metrics = new class { public array $inc=[]; public function inc($k){ $this->inc[]=$k; } };
        $controller = $this->makeController($repo, $metrics);
        $req = new WP_REST_Request(['entry'=>1,'reason'=>'duplicate']);
        $req->set_header('X-WP-Nonce','a');
        $ref = new ReflectionClass($controller);
        $m = $ref->getMethod('rejectManual'); $m->setAccessible(true);
        $res = $m->invoke($controller,$req);
        $this->assertSame(200, $res->get_status());
        $this->assertSame(['review_reject_total'], $metrics->inc);

        $reqBad = new WP_REST_Request(['entry'=>1,'reason'=>'bad']);
        $reqBad->set_header('X-WP-Nonce','a');
        $resBad = $m->invoke($controller,$reqBad);
        $this->assertSame(400, $resBad->get_status());
    }

    public function test_defer_idempotent(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(true);

        $repo = new class { public bool $once=true; public function deferManual($e,$r,$n){ if($this->once){$this->once=false; return true;} return false; } };
        $metrics = new class { public array $inc=[]; public function inc($k){ $this->inc[]=$k; } };
        $controller = $this->makeController($repo, $metrics);
        $ref = new ReflectionClass($controller); $m = $ref->getMethod('deferManual'); $m->setAccessible(true);

        $req = new WP_REST_Request(['entry'=>1]);
        $req->set_header('X-WP-Nonce','a');
        $res1 = $m->invoke($controller,$req);
        $this->assertSame(200,$res1->get_status());
        $res2 = $m->invoke($controller,$req);
        $this->assertSame(409,$res2->get_status());
        $this->assertSame(['review_defer_total'], $metrics->inc);
    }

    public function test_lock_returns_409(): void
    {
        Functions\when('wp_verify_nonce')->justReturn(true);
        $GLOBALS['sa_transients'] = ['smartalloc_review_lock_1' => '2'];
        $repo = new class {
            public function approveManual($e,$m,$r,$n){ return new AllocationResult(['committed'=>true]); }
        };
        $metrics = new class { public array $inc=[]; public function inc($k){ $this->inc[]=$k; } };
        $controller = $this->makeController($repo, $metrics);
        $req = new WP_REST_Request(['entry'=>1,'mentor_id'=>2]);
        $req->set_header('X-WP-Nonce','a');
        $ref = new ReflectionClass($controller); $m = $ref->getMethod('approveManual'); $m->setAccessible(true);
        $res = $m->invoke($controller,$req);
        $this->assertSame(409,$res->get_status());
        $this->assertSame('entry_locked',$res->get_data()['error']);
        $this->assertSame(['review_lock_hit'], $metrics->inc);
    }
}


