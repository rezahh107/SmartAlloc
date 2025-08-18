<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Infra\Settings;

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Infra\Settings\Settings;
use SmartAlloc\Tests\BaseTestCase;

final class SettingsTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $GLOBALS['sa_options'] = ['smartalloc_settings' => []];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_defaults_are_returned_when_option_missing(): void
    {
        $this->assertSame(0.90, Settings::getFuzzyAutoThreshold());
        $this->assertSame(0.80, Settings::getFuzzyManualMin());
        $this->assertSame(0.89, Settings::getFuzzyManualMax());
        $this->assertSame(60, Settings::getDefaultCapacity());
        $this->assertSame('direct', Settings::getAllocationMode());
        $this->assertSame([], Settings::getPostalCodeAliases());
        $this->assertSame(0, Settings::getExportRetentionDays());
    }

    public function test_validation_rejects_invalid_threshold_ranges(): void
    {
        $input = [
            'fuzzy_manual_min' => 0.9,
            'fuzzy_manual_max' => 0.95,
            'fuzzy_auto_threshold' => 0.93,
        ];
        $sanitized = Settings::sanitize($input);
        $this->assertSame(0.90, $sanitized['fuzzy_auto_threshold']);
        $this->assertSame(0.80, $sanitized['fuzzy_manual_min']);
        $this->assertSame(0.89, $sanitized['fuzzy_manual_max']);
    }

    public function test_allocation_mode_accepts_only_direct_or_rest(): void
    {
        $sanitized = Settings::sanitize(['allocation_mode' => 'rest']);
        $this->assertSame('rest', $sanitized['allocation_mode']);

        $sanitized = Settings::sanitize(['allocation_mode' => 'invalid']);
        $this->assertSame('direct', $sanitized['allocation_mode']);
    }
}

