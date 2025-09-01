<?php
declare(strict_types=1);

namespace SmartAlloc\RuleEngine;

use SmartAlloc\Allocation\ArrayCapacityProvider;
use SmartAlloc\Allocation\CapacityProvider;

require_once __DIR__ . '/../Allocation/CapacityProvider.php';
use SmartAlloc\Rules\ExternalDependencyError;

require_once __DIR__ . '/Contracts.php';

final class RuleEngineService implements RuleEngineContract
{
    private CapacityProvider $capacity;

    public function __construct(?CapacityProvider $capacity = null)
    {
        $this->capacity = $capacity ?? new ArrayCapacityProvider([]);
    }

    /** @param array<string,mixed> $studentCtx */
    public function evaluate(array $studentCtx): EvaluationResult
    {
        $score = (float) ($studentCtx['school_fuzzy'] ?? 0.0);
        $decision = 'reject';
        $reasons = ['school_match_low'];
        if ($score >= 0.90) {
            $decision = 'auto';
            $reasons = [];
        } elseif ($score >= 0.80) {
            $decision = 'manual';
            $reasons = ['school_match_borderline'];
        }
        $r = new EvaluationResult($decision);
        $r->scores['school_fuzzy'] = $score;
        $r->reasons = $reasons;
        $flag = apply_filters('smartalloc_rule_cap_check', SMARTALLOC_RULE_CAP_CHECK);
        if ($flag) {
            $mentorId = $studentCtx['mentor_id'] ?? 0;
            if (!$this->capacity->hasCapacity($mentorId)) {
                throw new ExternalDependencyError('NO_CAPACITY');
            }
        }
        return $r;
    }

    /**
     * @param array<string,mixed> $rule
     * @param array<string,mixed> $context
     */
    public function evaluateCompositeRule(array $rule, array $context): bool
    {
        if (isset($rule['operator']) && isset($rule['conditions'])) {
            return $this->evaluateLogicalOperator($rule, $context);
        }
        return $this->evaluateSimpleCondition($rule, $context);
    }

    /**
     * @param array<string,mixed> $rule
     * @param array<string,mixed> $context
     */
    private function evaluateLogicalOperator(array $rule, array $context): bool
    {
        $operator = strtoupper((string) ($rule['operator'] ?? ''));
        $conditions = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [];
        return match ($operator) {
            'AND' => $this->allConditionsTrue($conditions, $context),
            'OR' => $this->anyConditionTrue($conditions, $context),
            default => throw new InvalidRuleException("Unsupported operator: {$operator}"), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        };
    }

    /** @param array<int,array<string,mixed>> $conditions */
    private function allConditionsTrue(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateCompositeRule($condition, $context)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<int,array<string,mixed>> $conditions */
    private function anyConditionTrue(array $conditions, array $context): bool
    {
        foreach ($conditions as $condition) {
            if ($this->evaluateCompositeRule($condition, $context)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string,mixed> $rule
     * @param array<string,mixed> $context
     */
    private function evaluateSimpleCondition(array $rule, array $context): bool
    {
        $field = (string) ($rule['field'] ?? '');
        $op = (string) ($rule['operator'] ?? '');
        $value = $rule['value'] ?? null;
        $current = $context[$field] ?? null;
        return match ($op) {
            '>' => $current > $value,
            '>=' => $current >= $value,
            '<' => $current < $value,
            '<=' => $current <= $value,
            '=' => $current === $value,
            '!=' => $current !== $value,
            default => throw new InvalidRuleException("Unsupported comparator: {$op}"), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        };
    }
}
