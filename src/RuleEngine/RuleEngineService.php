<?php declare(strict_types=1);
namespace SmartAlloc\RuleEngine;
use SmartAlloc\Allocation\CapacityProvider;
use SmartAlloc\Allocation\ArrayCapacityProvider;
use SmartAlloc\Rules\ExternalDependencyError;
require_once __DIR__ . '/Contracts.php';
final class RuleEngineService implements RuleEngineContract {
    public function __construct(?CapacityProvider $capacity = null) { $this->capacity = $capacity ?? new ArrayCapacityProvider([]); }
    public function evaluate(array $studentCtx): EvaluationResult {
        $score = (float) ($studentCtx['school_fuzzy'] ?? 0.0);
        $decision = 'reject'; $reasons = ['school_match_low'];
        if ($score >= 0.90) { $decision = 'auto'; $reasons = []; }
        elseif ($score >= 0.80) { $decision = 'manual'; $reasons = ['school_match_borderline']; }
        $r = new EvaluationResult($decision); $r->scores['school_fuzzy'] = $score; $r->reasons = $reasons;
        $flag = apply_filters('smartalloc_rule_cap_check', SMARTALLOC_RULE_CAP_CHECK);
        if ($flag) { $mentorId = $studentCtx['mentor_id'] ?? 0; if (!$this->capacity->hasCapacity($mentorId)) { throw new ExternalDependencyError('NO_CAPACITY'); } }
        return $r;
    }
}
