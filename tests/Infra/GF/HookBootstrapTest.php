<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Infra\GF;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use SmartAlloc\Infra\GF\HookBootstrap;
use SmartAlloc\Tests\BaseTestCase;

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

    public function testRegistersHooksForEnabledForms(): void
    {
        Functions\expect('add_action')
            ->once()
            ->with('gform_after_submission_150', Mockery::type('callable'), 10, 2);
        (new HookBootstrap())->register();
    }
}
