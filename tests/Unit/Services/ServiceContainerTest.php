<?php
declare(strict_types=1);

namespace SmartAlloc\Services {
    if (!function_exists(__NAMESPACE__ . '\\apply_filters')) {
        function apply_filters($tag, $value) {
            return $GLOBALS['__smartalloc_apply_filters_return'] ?? $value;
        }
    }
}

namespace SmartAlloc\Tests\Unit\Services {

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\ServiceContainer;
use SmartAlloc\Contracts\AllocationServiceInterface;
use SmartAlloc\Core\FormContext;

final class ServiceContainerTest extends BaseTestCase
{
    protected function tearDown(): void
    {
        if (method_exists(ServiceContainer::class, 'reset')) {
            ServiceContainer::reset();
        }
        unset($GLOBALS['__smartalloc_apply_filters_return']);
        parent::tearDown();
    }

    /** @test */
    public function returns_injected_allocation_service_when_set(): void
    {
        $fake = new class implements AllocationServiceInterface {
            public function allocateWithContext(FormContext $ctx, array $payload): array { return ['ok' => true]; }
            public function allocate(array $payload): array { return ['legacy' => true]; }
        };

        ServiceContainer::setAllocation($fake);
        $resolved = ServiceContainer::allocation();

        $this->assertSame($fake, $resolved, 'ServiceContainer must return the injected allocation service');
        $this->assertIsArray($resolved->allocate(['x' => 1]));
    }

    /** @test */
    public function resolves_from_filter_when_available(): void
    {
        $fake = new class implements AllocationServiceInterface {
            public function allocateWithContext(FormContext $ctx, array $payload): array { return ['ok' => true]; }
            public function allocate(array $payload): array { return ['legacy' => true]; }
        };

        $GLOBALS['__smartalloc_apply_filters_return'] = $fake;

        $resolved = ServiceContainer::allocation();
        $this->assertInstanceOf(AllocationServiceInterface::class, $resolved);
        $this->assertSame($fake, $resolved);
    }
}

}
