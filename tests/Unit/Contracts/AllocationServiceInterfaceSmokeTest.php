<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Contracts;

use SmartAlloc\Tests\BaseTestCase;

final class AllocationServiceInterfaceSmokeTest extends BaseTestCase
{
    /** @test */
    public function interface_exists_and_has_expected_methods(): void
    {
        $iface = 'SmartAlloc\\Contracts\\AllocationServiceInterface';
        $this->assertTrue(interface_exists($iface), 'AllocationServiceInterface must exist');

        $ref = new \ReflectionClass($iface);
        $this->assertTrue($ref->hasMethod('allocate'), 'allocate() method missing');
        $this->assertTrue($ref->hasMethod('allocateWithContext'), 'allocateWithContext() method missing');
    }
}

