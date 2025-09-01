<?php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;
use SmartAlloc\Tests\Support\{InvalidConfigEngine,MissingDependencyEngine,CircularRuleEngine};
final class FailureModesSkeletonTest extends TestCase{
public function test_invalid_rule_configuration():void{$e=new InvalidConfigEngine();$this->expectException(\SmartAlloc\Rules\RuleConfigError::class);$e->evaluate([]);}
public function test_missing_dependency_behavior():void{$e=new MissingDependencyEngine();$this->expectException(\SmartAlloc\Rules\ExternalDependencyError::class);$e->evaluate([]);}
public function test_circular_rule_detection():void{$e=new CircularRuleEngine();$this->expectException(\SmartAlloc\Rules\RuleConfigError::class);$e->evaluate([]);}
}
