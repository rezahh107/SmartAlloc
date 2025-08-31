<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Contracts\AllocationServiceInterface;
use SmartAlloc\Core\FormContext;
use SmartAlloc\Services\ServiceContainer;
use SmartAlloc\Tests\BaseTestCase;

final class ServiceContainerTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        if (method_exists(ServiceContainer::class, 'reset')) {
            ServiceContainer::reset();
        }
    }
    protected function tearDown(): void
    {
        Monkey\tearDown();
        if (method_exists(ServiceContainer::class, 'reset')) {
            ServiceContainer::reset();
        }
        parent::tearDown();
    }

    /** @test */
    public function returns_injected_allocation_service_when_set(): void
    {
        $fake = new class implements AllocationServiceInterface {
            public function allocateWithContext(FormContext $ctx, array $payload): array { return ['ok' => true, 'form' => $ctx->formId ?? null]; }
            public function allocate(array $payload): array { return ['legacy' => true]; }
        };

        ServiceContainer::setAllocation($fake);
        $resolved = ServiceContainer::allocation();

        $this->assertSame($fake, $resolved, 'ServiceContainer must return the injected allocation service');
        $this->assertIsArray($resolved->allocate(['x' => 1]));
    }

}

