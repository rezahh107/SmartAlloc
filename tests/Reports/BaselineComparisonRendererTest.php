<?php
// phpcs:ignoreFile
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SmartAlloc\Reports\BaselineComparisonRenderer;
final class BaselineComparisonRendererTest extends TestCase
{
    public function test_renders_comparison_table(): void
    {
        $baseline = [
            'date_persian' => '10 شهریور 1404',
            'phases' => [
                'foundation' => [
                    'tasks' => [
                        'security' => [
                            'status' => 'completed',
                            'description' => 'امنیت',
                        ],
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
