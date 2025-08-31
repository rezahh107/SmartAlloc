<?php
// phpcs:ignoreFile
declare(strict_types=1);

namespace SmartAlloc\Rules;

final class FailureMapper
{
    public static function fromException(\Throwable $e): RuleEngineResult
    {
        return match (true) {
            $e instanceof InvalidInput => new RuleEngineResult(
                RuleEngineResult::FAIL,
                AllocationFailureCode::INVALID_INPUT,
                ['message' => $e->getMessage()]
            ),
            $e instanceof RuleConfigError => new RuleEngineResult(
                RuleEngineResult::FAIL,
                AllocationFailureCode::RULE_CONFIG
            ),
            $e instanceof RuleTimeout => new RuleEngineResult(
                RuleEngineResult::RETRYABLE,
                AllocationFailureCode::TIMEOUT
            ),
            $e instanceof ExternalDependencyError => new RuleEngineResult(
                RuleEngineResult::RETRYABLE,
                AllocationFailureCode::EXTERNAL_DEP
            ),
            default => new RuleEngineResult(
                RuleEngineResult::FAIL,
                'UNKNOWN',
                ['message' => $e->getMessage()]
            ),
        };
    }
}
