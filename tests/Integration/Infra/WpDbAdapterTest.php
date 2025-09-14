<?php

namespace SmartAlloc\Tests\Integration\Infra;

use SmartAlloc\Infra\WpDbAdapter;

/** @group integration */
class WpDbAdapterTest extends \SmartAlloc\Tests\BaseTestCase
{
    public function testSelectOne(): void
    {
        $wpdb    = $GLOBALS['wpdb'];
        $adapter = new WpDbAdapter($wpdb);
        $this->assertEquals('1', $adapter->fetchOne('SELECT 1'));
    }
}
