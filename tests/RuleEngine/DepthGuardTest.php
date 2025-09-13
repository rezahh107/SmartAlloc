<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\RuleEngine\RuleEngineService;
use SmartAlloc\RuleEngine\InvalidRuleException;

final class DepthGuardTest extends TestCase
{
    public function test_depth_exceeds_throws_exception(): void
    {
        $svc = new RuleEngineService();
        $deepRule = $this->nested(5); // deeper than MAX_DEPTH=4
        $this->expectException(InvalidRuleException::class);
        $this->expectExceptionMessage('Rule depth exceeded');
        $svc->evaluateCompositeRule($deepRule, []);
    }

    private function nested(int $depth): array
    {
        if ($depth <= 1) {
            return ['field' => 'x', 'operator' => '=', 'value' => 1];
        }
        return [
            'operator' => 'AND',
            'conditions' => [ $this->nested($depth - 1) ]
        ];
    }
}

