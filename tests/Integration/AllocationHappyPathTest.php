<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\ScoringAllocator;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\EventStoreInterface;
use SmartAlloc\Domain\Allocation\AllocationResult;

if (!class_exists('WP_Error')) { class WP_Error { public function __construct(public string $code = '', public string $message = '', public array $data = []) {} public function get_error_data(): array { return $this->data; } } }

final class AllocationHappyPathTest extends BaseTestCase
{
    public function testStudentAllocatedAndEventsEmitted(): void
    {
        $this->markTestSkipped('Legacy allocation path unsupported');
    }
}
