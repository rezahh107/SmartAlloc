#!/usr/bin/env php
<?php
/**
 * SmartAlloc Baseline Verification Tool.
 * Usage: php scripts/baseline-check.php --current-phase=EXPANSION
 */

namespace SmartAlloc\Scripts;

use RuntimeException;

class BaselineException extends RuntimeException {}

class BaselineChecker
{
    private const BASELINE_CONFIG = __DIR__ . '/../config/baseline.json';
    private const METRICS_FILE    = __DIR__ . '/../metrics/current.json';
    private const WEIGHTS = [
        'Security'       => 0.2,
        'Logic'          => 0.2,
        'Performance'    => 0.2,
        'Observability'  => 0.2,
        'Maintainability'=> 0.2,
    ];

    private array $baseline;
    private array $currentMetrics;

    public function __construct()
    {
        if (! is_file(self::BASELINE_CONFIG)) {
            throw new BaselineException('Baseline config missing');
        }

        $data            = json_decode(file_get_contents(self::BASELINE_CONFIG), true);
        $this->baseline  = is_array($data) ? $data : [];
        $this->currentMetrics = $this->loadMetrics();
    }

    public function verifyPhase(string $phase): array
    {
        $phaseData = $this->baseline['phases'][$phase] ?? ['metrics' => []];
        $gaps      = [];

        foreach ($phaseData['metrics'] as $dimension => $required) {
            $current = $this->currentMetrics[$dimension] ?? 0;
            if ($current < $required) {
                $gaps[$dimension] = [
                    'required' => $required,
                    'current'  => $current,
                    'gap'      => $required - $current,
                ];
            }
        }

        $overallScore    = $this->calculateScore($phaseData['metrics']);
        $completionTarget = $phaseData['completion_target'] ?? 0;
        $status           = ($overallScore >= $completionTarget) ? 'PASS' : 'FAIL';

        return [
            'phase'             => $phase,
            'valid'             => empty($gaps),
            'gaps'              => $gaps,
            'overall_progress'  => $this->calculateProgress($phaseData['metrics']),
            'overall_score'     => $overallScore,
            'phase_gate_status' => $status,
        ];
    }

    private function loadMetrics(): array
    {
        if (! is_file(self::METRICS_FILE)) {
            return [];
        }

        $data = json_decode(file_get_contents(self::METRICS_FILE), true);
        return is_array($data) ? $data : [];
    }

    private function calculateProgress(array $requirements): float
    {
        $requiredTotal = array_sum($requirements);
        if ($requiredTotal <= 0) {
            return 0.0;
        }

        $current = 0;
        foreach ($requirements as $dimension => $required) {
            $current += min($this->currentMetrics[$dimension] ?? 0, $required);
        }

        return round(($current / $requiredTotal) * 100, 2);
    }

    private function calculateScore(array $requirements): float
    {
        $totalWeight = array_sum(self::WEIGHTS);
        if ($totalWeight <= 0) {
            return 0.0;
        }

        $score = 0.0;
        foreach ($requirements as $dimension => $required) {
            $weight  = self::WEIGHTS[$dimension] ?? 0;
            $current = min($this->currentMetrics[$dimension] ?? 0, $required);
            if ($required > 0 && $weight > 0) {
                $score += ($current / $required) * $weight;
            }
        }

        return round(($score / $totalWeight) * 100, 2);
    }
}

$options = getopt('', ['current-phase:']);
$phase   = $options['current-phase'] ?? 'FOUNDATION';
$checker = new BaselineChecker();
$result  = $checker->verifyPhase($phase);

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
exit($result['valid'] ? 0 : 1);
