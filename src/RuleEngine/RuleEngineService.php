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
        // TODO: Add capacity check when available
        // TODO: Add mentor validation when available
        return $r;
    }
}
