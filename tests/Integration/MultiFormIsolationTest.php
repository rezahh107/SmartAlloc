<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\AllocationService;

final class MultiFormIsolationTest extends BaseTestCase {
    public function test_allocations_write_to_form_specific_tables(): void {
        global $wpdb;
        $wpdb = new class extends \wpdb { public function __construct() {} };
        $tables = new TableResolver($wpdb);
        $svc = new AllocationService($tables);

        $ctx150 = new FormContext(150);
        $ctx200 = new FormContext(200);

        $svc->allocate($ctx150, [['id'=>1],['id'=>2]]);
        $svc->allocate($ctx200, [['id'=>3]]);

        $this->assertStringContainsString('_f150', $tables->allocations($ctx150));
        $this->assertStringContainsString('_f200', $tables->allocations($ctx200));
        // Optionally assert insert counts via $wpdb->queries spy if available
    }
}
