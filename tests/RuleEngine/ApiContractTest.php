<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\RuleEngine\RuleEngineService;
use SmartAlloc\RuleEngine\EvaluationResult;

final class ApiContractTest extends TestCase
{
    private RuleEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RuleEngineService();
    }

    /**
     * @dataProvider thresholdProvider
     */
    public function test_decision_thresholds(float $score, string $expected): void
    {
        $result = $this->service->evaluate(['school_fuzzy' => $score]);

        $this->assertSame($expected, $result->decision);
        $this->assertSame($score, $result->scores['school_fuzzy']);
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function thresholdProvider(): array
    {
        return [
            'auto threshold' => [0.90, 'auto'],
            'above auto'     => [0.95, 'auto'],
            'manual upper'   => [0.89, 'manual'],
            'manual lower'   => [0.80, 'manual'],
            'reject'         => [0.79, 'reject'],
        ];
    }

    public function test_result_has_utc_timestamp(): void
    {
        $result = $this->service->evaluate(['school_fuzzy' => 0.85]);

        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/',
            $result->ts_utc
        );
    }
}
