<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Http\Rest\ExportsListController;
use SmartAlloc\Infra\Export\ExporterService;
use SmartAlloc\Tests\BaseTestCase;


final class ExportsListControllerTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(true);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_lists_exports(): void
    {
        $service = new class extends ExporterService {
            public function __construct() {}
            public function getRecent(int $limit = 20): array {
                return [['filename'=>'f.xlsx','size'=>10,'checksum'=>'abc','rows'=>1,'created_at'=>'2024-01-01','status'=>'Valid']];
            }
        };
        $controller = new ExportsListController($service);
        $response = $controller->handle(new WP_REST_Request(['limit'=>1]));
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertSame('f.xlsx', $data[0]['filename']);
        $this->assertSame('Valid', $data[0]['status']);
    }
}
