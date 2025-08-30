<?php
declare(strict_types=1);

namespace SmartAlloc\RuleEngine;

require_once __DIR__ . '/Contracts.php';

final class RuleEngineService implements RuleEngineContract
{
    public function evaluate(array $studentCtx): EvaluationResult
    {
        $score    = (float) ($studentCtx['school_fuzzy'] ?? 0.0);
        $decision = 'reject';
        $reasons  = ['school_match_low'];
        if ($score >= 0.90) {
            $decision = 'auto';
            $reasons  = [];
        } elseif ($score >= 0.80) {
            $decision = 'manual';
            $reasons  = ['school_match_borderline'];
        }
        $r = new EvaluationResult($decision);
        $r->scores['school_fuzzy'] = $score;
        $r->reasons = $reasons;
        // NEW: capacity check with filter override
        $flag = apply_filters('smartalloc_rule_cap_check', SMARTALLOC_RULE_CAP_CHECK);
        if ($flag) {
            $capacityOk = true; // placeholder until provider injected
            if (!$capacityOk && class_exists('\\SmartAlloc\\Services\\Exceptions\\InsufficientCapacityException')) {
                throw new \SmartAlloc\Services\Exceptions\InsufficientCapacityException(
                    'Capacity check failed (flag ON).'
                );
            }
        }
        // TODO: Add mentor validation when available
        return $r;
    }
}
