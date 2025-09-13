<?php
declare(strict_types=1);

namespace SmartAlloc\RuleEngine;

use SmartAlloc\Allocation\ArrayCapacityProvider;
use SmartAlloc\Allocation\CapacityProvider;

require_once __DIR__ . '/../Allocation/CapacityProvider.php';
use SmartAlloc\Rules\ExternalDependencyError;

require_once __DIR__ . '/Contracts.php';

/**
 * Provides rule evaluation including nested composite logic.
 */
final class RuleEngineService implements RuleEngineContract
{
    private const MAX_DEPTH = 4;
    private const MAX_CONDITIONS = 100;
    private const TIMEOUT_SECONDS = 2.0;
    private CapacityProvider $capacity;
    private int $conditionCount = 0;
    private float $startTime = 0.0;

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
     * Evaluate a rule tree supporting nested AND/OR logic.
     *
     * A rule is either a simple condition:
     * [ 'field' => 'amount', 'operator' => '>', 'value' => 100 ]
     * or a composite node:
     * [ 'operator' => 'AND|OR', 'conditions' => [ ...child rules... ] ]
     *
     * @param array<string,mixed> $rule    Root rule or condition
     * @param array<string,mixed> $context Data used for evaluation
     * @throws InvalidRuleException When an unsupported operator or comparator is encountered
     */
    public function evaluateCompositeRule(array $rule, array $context, int $depth = 0): bool
    {
        if ($depth === 0) {
            $this->startTime = microtime(true);
            $this->conditionCount = 0;
        }

        $this->checkLimits($depth);
        $this->conditionCount++;
        if ($rule === []) {
            return false;
        }
        $op = $rule['operator'] ?? null;
        if (isset($rule['conditions'])) {
            $upper = strtoupper((string) $op);
            if (!in_array($upper, ['AND', 'OR', 'SINGLE'], true)) {
                throw new InvalidRuleException('Invalid operator: ' . $upper); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
            }
            if ($upper === 'SINGLE') {
                $child = $rule['conditions'][0] ?? [];
                return $this->evaluateCompositeRule($child, $context, $depth + 1);
            }
            // Validate conditions array
            if (!is_array($rule['conditions']) || $rule['conditions'] === []) {
                throw new InvalidRuleException('Invalid rule structure: empty conditions');
            }
            return $this->evaluateLogicalOperator($rule, $context, $depth);
        }
        if (is_string($op) && !in_array($op, ['>', '>=', '<', '<=', '=', '!='], true)) {
            $upper = strtoupper($op);
            throw new InvalidRuleException('Invalid operator: ' . $upper); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        return $this->evaluateSimpleCondition($rule, $context);
    }

    /**
     * Evaluate a composite node using its logical operator.
     *
     * @param array<string,mixed> $rule    Composite rule definition
     * @param array<string,mixed> $context Runtime context
     * @throws InvalidRuleException When the operator is not AND or OR
     */
    private function evaluateLogicalOperator(array $rule, array $context, int $depth): bool
    {
        $operator = strtoupper((string) ($rule['operator'] ?? ''));
        $conditions = is_array($rule['conditions'] ?? null) ? $rule['conditions'] : [];
        $next = $depth + 1;
        return match ($operator) {
            'AND' => $this->allConditionsTrue($conditions, $context, $next),
            'OR' => $this->anyConditionTrue($conditions, $context, $next),
            default => throw new InvalidRuleException('Invalid operator: ' . $operator), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        };
    }

    /** @param array<int,array<string,mixed>> $conditions */
    private function allConditionsTrue(array $conditions, array $context, int $depth): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->evaluateCompositeRule($condition, $context, $depth)) {
                return false;
            }
        }
        return true;
    }

    /** @param array<int,array<string,mixed>> $conditions */
    private function anyConditionTrue(array $conditions, array $context, int $depth): bool
    {
        foreach ($conditions as $condition) {
            if ($this->evaluateCompositeRule($condition, $context, $depth)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Evaluate a simple condition against the context.
     *
     * @param array<string,mixed> $rule    Condition definition
     * @param array<string,mixed> $context Runtime context
     * @throws InvalidRuleException When the comparator is unsupported
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

    private function checkLimits(int $depth): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new InvalidRuleException('Rule depth exceeded maximum: ' . self::MAX_DEPTH);
        }
        if ($this->conditionCount >= self::MAX_CONDITIONS) {
            throw new InvalidRuleException('Rule complexity exceeded');
        }
        if ($this->startTime > 0 && (microtime(true) - $this->startTime) > self::TIMEOUT_SECONDS) {
            throw new InvalidRuleException('Rule evaluation timeout');
        }
    }
}
