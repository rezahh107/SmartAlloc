<?php

namespace SmartAlloc\Tests\Unit\Infrastructure;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Infra\WpDbAdapter;
use SmartAlloc\Tests\Support\WPDB_Stub;

class WpDbAdapterTest extends TestCase
{
    public function testDelegatesCalls(): void
    {
        $stub = new WPDB_Stub();
        $adapter = new WpDbAdapter($stub); // @phpstan-ignore-line
        $adapter->getVar('SELECT 1');
        $this->assertSame(['get_var', 'SELECT 1'], $stub->last_call);
    }
}
