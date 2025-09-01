<?php declare(strict_types=1);
namespace SmartAlloc\Tests\Support;
use SmartAlloc\RuleEngine\{EvaluationResult,RuleEngineContract};
use SmartAlloc\Rules\{RuleConfigError,ExternalDependencyError};
final class InvalidConfigEngine implements RuleEngineContract{public function evaluate(array $s):EvaluationResult{throw new RuleConfigError('missing thresholds');}}
final class MissingDependencyEngine implements RuleEngineContract{public function evaluate(array $s):EvaluationResult{throw new ExternalDependencyError('null provider');}}
final class CircularRuleEngine implements RuleEngineContract{public function evaluate(array $s):EvaluationResult{throw new RuleConfigError('circular rule');}}
