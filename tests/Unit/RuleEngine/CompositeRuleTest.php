<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\RuleEngine\RuleEngineService;

final class CompositeRuleTest extends TestCase
{
    public function test_and_operator_requires_all_conditions_true(): void
    {
        $svc = new RuleEngineService();
        $rule = [
            'operator' => 'AND',
            'conditions' => [
                ['field' => 'age', 'operator' => '>', 'value' => 18],
                ['field' => 'status', 'operator' => '=', 'value' => 'active'],
            ],
        ];
        $this->assertTrue($svc->evaluateCompositeRule($rule, ['age' => 20, 'status' => 'active']));
        $this->assertFalse($svc->evaluateCompositeRule($rule, ['age' => 20, 'status' => 'inactive']));
    }

    public function test_or_operator_requires_any_condition_true(): void
    {
        $svc = new RuleEngineService();
        $rule = [
            'operator' => 'OR',
            'conditions' => [
                ['field' => 'age', 'operator' => '>', 'value' => 18],
                ['field' => 'status', 'operator' => '=', 'value' => 'active'],
            ],
        ];
        $this->assertTrue($svc->evaluateCompositeRule($rule, ['age' => 20, 'status' => 'inactive']));
        $this->assertFalse($svc->evaluateCompositeRule($rule, ['age' => 17, 'status' => 'inactive']));
    }
}
