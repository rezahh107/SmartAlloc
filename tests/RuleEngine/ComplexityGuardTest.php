<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\RuleEngine\RuleEngineService;
use SmartAlloc\RuleEngine\InvalidRuleException;

final class ComplexityGuardTest extends TestCase
{
    public function test_too_many_conditions_throws_exception(): void
    {
        $svc = new RuleEngineService();
        $conditions = [];
        for ($i = 0; $i < 101; $i++) {
            $conditions[] = ['field' => 'k' . $i, 'operator' => '=', 'value' => 1];
        }
        $rule = [ 'operator' => 'AND', 'conditions' => $conditions ];
        $this->expectException(InvalidRuleException::class);
        $this->expectExceptionMessage('Rule complexity');
        $svc->evaluateCompositeRule($rule, []);
    }
}

