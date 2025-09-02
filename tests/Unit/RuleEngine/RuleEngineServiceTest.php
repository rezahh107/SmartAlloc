<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\RuleEngine\{RuleEngineService,InvalidRuleException};

final class RuleEngineServiceTest extends TestCase
{
    private RuleEngineService $svc;

    protected function setUp(): void
    {
        $this->svc = new RuleEngineService();
    }

    public function test_and_all_pass(): void
    {
        $rule=['operator'=>'AND','conditions'=>[
            ['field'=>'a','operator'=>'>','value'=>1],
            ['field'=>'b','operator'=>'=','value'=>'x'],
        ]];
        $this->assertTrue($this->svc->evaluateCompositeRule($rule,['a'=>2,'b'=>'x']));
    }

    public function test_and_one_fails(): void
    {
        $rule=['operator'=>'AND','conditions'=>[
            ['field'=>'a','operator'=>'>','value'=>1],
            ['field'=>'b','operator'=>'=','value'=>'x'],
        ]];
        $this->assertFalse($this->svc->evaluateCompositeRule($rule,['a'=>0,'b'=>'x']));
    }

    public function test_or_one_pass(): void
    {
        $rule=['operator'=>'OR','conditions'=>[
            ['field'=>'a','operator'=>'>','value'=>1],
            ['field'=>'b','operator'=>'=','value'=>'x'],
        ]];
        $this->assertTrue($this->svc->evaluateCompositeRule($rule,['a'=>0,'b'=>'x']));
    }

    public function test_or_all_fail(): void
    {
        $rule=['operator'=>'OR','conditions'=>[
            ['field'=>'a','operator'=>'>','value'=>1],
            ['field'=>'b','operator'=>'=','value'=>'x'],
        ]];
        $this->assertFalse($this->svc->evaluateCompositeRule($rule,['a'=>0,'b'=>'y']));
    }

    public function test_nested_exceeds_max_depth(): void
    {
        $rule=['operator'=>'AND','conditions'=>[]];
        $this->expectException(InvalidRuleException::class);
        $this->expectExceptionMessage('Max depth exceeded');
        $this->svc->evaluateCompositeRule($rule,[],4);
    }

    public function test_invalid_operator_throws_exception(): void
    {
        $rule=['operator'=>'XOR','conditions'=>[]];
        $this->expectException(InvalidRuleException::class);
        $this->expectExceptionMessage('Invalid operator: XOR');
        $this->svc->evaluateCompositeRule($rule,[]);
    }
}

