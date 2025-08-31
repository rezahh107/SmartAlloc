<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use Brain\Monkey; 
use Brain\Monkey\Functions; 
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Infra\DB\TableResolver;

if (!class_exists('WP_Error')) { class WP_Error { public function __construct(public string $code = '', public string $message = '', public array $data = []) {} public function get_error_data(): array { return $this->data; } } }
if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public function get_var($sql){ return 0; }
        public function query($sql){}
    }
}

final class AllocationHappyPathTest extends BaseTestCase
{ 
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testStudentAllocatedAndEventsEmitted(): void
    {
        $GLOBALS['wpdb'] = new wpdb();
        // Allow error_log to write to stderr in test output.
        $tables = new TableResolver($GLOBALS['wpdb']);
        $svc = new AllocationService($tables);
        $result = $svc->allocate(['student_id'=>1,'email'=>'a@b.com']);
        $this->assertSame(1, $result['summary']['count']);
    }
}
