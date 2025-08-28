<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Allocation\AllocationEngine;

final class AllocationEngineTest extends BaseTestCase {
    public function test_run_inserts_allocation(): void {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->expects($this->once())->method('prepare')->willReturn('SQL');
        $wpdb->expects($this->once())->method('query')->willReturn(1);
        $engine = new AllocationEngine($wpdb);
        $result = $engine->run([123]);
        $this->assertTrue($result->has(123));
    }

    public function test_run_throws_on_db_failure(): void {
        $wpdb = $this->createMock(\wpdb::class);
        $wpdb->expects($this->once())->method('prepare')->willReturn('SQL');
        $wpdb->expects($this->once())->method('query')->willReturn(false);
        $engine = new AllocationEngine($wpdb);
        $this->expectException(\SmartAlloc\Allocation\Exceptions\AllocationException::class);
        $engine->run([123]);
    }
}
