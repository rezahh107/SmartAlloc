<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Infra;

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Infra\GF\HookBootstrap;
use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;

final class HookBootstrapTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testRegistersGfHooksForEnabledForms(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('gform_after_submission_150', Mockery::type('callable'), 10, 2);
        (new HookBootstrap())->register();
    }
}
