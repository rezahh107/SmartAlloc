<?php
declare(strict_types=1);

namespace SmartAlloc\RuleEngine;

final class EvaluationResult
{
    /** @var 'auto'|'manual'|'reject' */
    public string $decision;
    /** @var array<string,string> */
    public array $reasons = [];
    /** @var array<string,float> */
    public array $scores = [];
    public string $ts_utc;
    public function __construct(string $decision)
    {
        $this->decision = $decision;
        $this->ts_utc   = gmdate('c');
    }
}

interface RuleEngineContract
{
    /** @param array<string,mixed> $studentCtx */
    public function evaluate(array $studentCtx): EvaluationResult;
}

final class RuleEngineException extends \RuntimeException
{
}
