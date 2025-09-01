<?php
// tests/Unit/BaselineComparisonRendererTest.php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit;

use SmartAlloc\Baseline\BaselineComparisonRenderer;
use SmartAlloc\Tests\BaseTestCase;

class BaselineComparisonRendererTest extends BaseTestCase
{
    public function testRendersComparisonTable(): void
    {
        $baseline = [
            'date_persian' => '10 شهریور 1404',
            'phases' => [
                'foundation' => [
                    'tasks' => [
                        'security' => ['status' => 'completed', 'description' => 'امنیت'],
                    ],
                ],
            ],
        ];
        $renderer = new BaselineComparisonRenderer();
        $output = $renderer->render($baseline);
        $this->assertStringContainsString('🟢', $output);
        $this->assertStringContainsString('foundation', $output);
    }
}
