<?php

/**
 * Integration test for single-path GF submission with IdempotencyGuard
 *
 * @package SmartAlloc\Tests\Integration\GF
 */

declare(strict_types=1);

namespace SmartAlloc\Tests\Integration\GF;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Bootstrap;
use SmartAlloc\Infra\GF\HookBootstrap;
use SmartAlloc\Infra\GF\IdempotencyGuard;
use SmartAlloc\Services\Cache;
use SmartAlloc\Container;

class AfterSubmissionSinglePathTest extends TestCase
{
    private HookBootstrap $hookBootstrap;
    private IdempotencyGuard $guard;
    private Cache $cache;
    private int $processCount = 0;

    public function setUp(): void
    {
        parent::setUp();

        $this->clearHook('gform_after_submission');
        $this->clearHook('gform_after_submission_150');
        $this->clearHook('smartalloc/event');

        $this->cache = new Cache();
        $this->guard = new IdempotencyGuard($this->cache);
        $container   = new Container();
        $container->set(Cache::class, fn() => $this->cache);
        $container->set(IdempotencyGuard::class, fn() => $this->guard);
        $ref  = new \ReflectionClass(Bootstrap::class);
        $prop = $ref->getProperty('container');
        $prop->setAccessible(true);
        $prop->setValue(null, $container);

        /** @phpstan-ignore-next-line */
        \Patchwork\replace(
            'SmartAlloc\\Infra\\GF\\SabtSubmissionHandler::handle',
            function (array $entry, array $form): void {
                do_action('smartalloc/event', 'AllocationProcessed', []);
            }
        );

        $this->hookBootstrap = new HookBootstrap();
        $this->hookBootstrap->register();

        add_action('smartalloc/event', [$this, 'trackProcessing'], 10, 2);
        $this->processCount = 0;
    }

    public function tearDown(): void
    {
        $this->clearHook('gform_after_submission');
        $this->clearHook('gform_after_submission_150');
        $this->clearHook('smartalloc/event');
        $this->cache->flushAllForTests();
        parent::tearDown();
    }

    public function trackProcessing($event, $payload): void
    {
        $this->processCount++;
    }

    public function testOnlyForm150HookRegistered(): void
    {
        global $_wp_actions;

        $this->assertArrayNotHasKey('gform_after_submission', $_wp_actions);
        $this->assertArrayHasKey('gform_after_submission_150', $_wp_actions);
    }

    public function testSingleSubmissionProcessesOnce(): void
    {
        $entry = ['id' => 123];
        $form  = ['id' => '150'];

        do_action('gform_after_submission_150', $entry, $form);
        $this->assertSame(1, $this->processCount);
        $this->assertTrue($this->guard->has_processed(150, 123));

        do_action('gform_after_submission_150', $entry, $form);
        $this->assertSame(1, $this->processCount);

        $entry['id'] = 124;
        do_action('gform_after_submission_150', $entry, $form);
        $this->assertSame(2, $this->processCount);
    }

    public function testNoGenericHookProcessing(): void
    {
        $entry = ['id' => 789, 'form_id' => '151'];
        $form  = ['id' => '151'];

        do_action('gform_after_submission', $entry, $form);
        $this->assertSame(0, $this->processCount);

        do_action('gform_after_submission_151', $entry, $form);
        $this->assertSame(0, $this->processCount);
    }

    public function testIdempotencyTtlBehavior(): void
    {
        $entry = ['id' => 555];
        $form  = ['id' => '150'];

        do_action('gform_after_submission_150', $entry, $form);
        $this->assertSame(1, $this->processCount);

        Bootstrap::container()->get(Cache::class)->l1Del('gf:entry:150:555');

        do_action('gform_after_submission_150', $entry, $form);
        $this->assertSame(2, $this->processCount);
    }

    private function clearHook(string $hook): void
    {
        global $_wp_actions, $_wp_filters;
        unset($_wp_actions[$hook], $_wp_filters[$hook]);
    }
}
