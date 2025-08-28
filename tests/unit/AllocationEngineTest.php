<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Allocation\AllocationEngine;

final class AllocationEngineTest extends BaseTestCase {
    public function test_run_inserts_allocation(): void {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->expects($this->once())->method('prepare')->willReturn('SQL');
        $wpdb->expects($this->once())->method('query');
        $engine = new AllocationEngine($wpdb);
        $result = $engine->run([123]);
        $this->assertTrue($result->has(123));
    }
}
