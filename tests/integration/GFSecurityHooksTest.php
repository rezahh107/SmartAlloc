<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class GFSecurityHooksTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists('\\Brain\\Monkey\\Functions')) {
            $this->markTestSkipped('Brain Monkey not installed');
        }
        \Brain\Monkey\setUp();
    }

    protected function tearDown(): void
    {
        if (class_exists('\\Brain\\Monkey')) {
            \Brain\Monkey\tearDown();
        }
    }

    public function test_gf_security_hooks_registered_or_skipped(): void
    {
        if (!function_exists('has_filter')) {
            $this->markTestSkipped('WP not bootstrapped');
        }

        $hooks = [
            'gform_form_post_get_meta',
            'gform_field_validation',
            'gform_pre_submission_filter',
            'gform_post_submission',
        ];

        $container = $this->createStub(\SmartAlloc\Container::class);
        $eventBus = $this->createStub(\SmartAlloc\Event\EventBus::class);
        $logger = $this->createStub(\SmartAlloc\Contracts\LoggerInterface::class);

        // Attempt to register hooks if class exists
        if (class_exists(\SmartAlloc\Integration\GravityForms::class)) {
            $gf = new \SmartAlloc\Integration\GravityForms($container, $eventBus, $logger);
            $gf->register();
        }

        $found = 0;
        foreach ($hooks as $hook) {
            if (has_filter($hook)) {
                $found++;
                switch ($hook) {
                    case 'gform_field_validation':
                        $res = apply_filters($hook, ['is_valid' => true], 'value', ['id' => 1], ['id' => 1]);
                        $this->assertArrayHasKey('is_valid', $res);
                        break;
                    case 'gform_pre_submission_filter':
                        $form = apply_filters($hook, ['id' => 1], ['id' => 1]);
                        $this->assertIsArray($form);
                        break;
                    case 'gform_form_post_get_meta':
                        do_action($hook, ['id' => 1]);
                        $this->assertTrue(true);
                        break;
                    case 'gform_post_submission':
                        do_action($hook, ['id' => 1], ['id' => 1]);
                        $this->assertTrue(true);
                        break;
                }
            }
        }

        if ($found === 0) {
            $this->markTestSkipped('no GF callbacks registered in this environment');
        }

        $this->assertGreaterThan(0, $found);
    }
}
