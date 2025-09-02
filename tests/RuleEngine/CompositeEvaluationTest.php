<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\RuleEngine\RuleEngineService;
use SmartAlloc\RuleEngine\InvalidRuleException;

final class CompositeEvaluationTest extends TestCase
{
    private RuleEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RuleEngineService();
    }

    public function test_nested_and_or_logic(): void
    {
        $rule = [
            'operator' => 'OR',
            'conditions' => [
                [
                    'operator' => 'AND',
                    'conditions' => [
                        ['field' => 'amount', 'operator' => '>', 'value' => 100],
                        ['field' => 'category', 'operator' => '=', 'value' => 'premium'],
                    ],
                ],
                ['field' => 'priority', 'operator' => '=', 'value' => 'urgent'],
            ],
        ];

        $context1 = ['amount' => 150, 'category' => 'premium', 'priority' => 'low'];
        $context2 = ['amount' => 50, 'category' => 'standard', 'priority' => 'urgent'];
        $context3 = ['amount' => 50, 'category' => 'standard', 'priority' => 'low'];

        $this->assertTrue($this->service->evaluateCompositeRule($rule, $context1));
        $this->assertTrue($this->service->evaluateCompositeRule($rule, $context2));
        $this->assertFalse($this->service->evaluateCompositeRule($rule, $context3));
    }

    public function test_invalid_operator_throws_exception(): void
    {
        $this->expectException(InvalidRuleException::class);
        $this->expectExceptionMessage('Unsupported operator: XOR');
        $rule = ['operator' => 'XOR', 'conditions' => []];
        $this->service->evaluateCompositeRule($rule, []);
    }

    public function test_invalid_comparator_throws_exception(): void
    {
        $this->expectException(InvalidRuleException::class);
        $this->expectExceptionMessage('Unsupported comparator: LIKE');
        $rule = ['field' => 'amount', 'operator' => 'LIKE', 'value' => 100];
        $this->service->evaluateCompositeRule($rule, ['amount' => 100]);
    }
}
