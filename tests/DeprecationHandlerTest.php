<?php

declare(strict_types=1);

namespace SmartAlloc\Tests;

class DeprecationHandlerTest extends BaseTestCase
{
    public function testDeprecatedConvertsToException(): void
    {
        $this->expectException(\ErrorException::class);
        trigger_error('controlled', E_USER_DEPRECATED);
    }
}
