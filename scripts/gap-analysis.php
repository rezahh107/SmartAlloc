#!/usr/bin/env php
<?php
/**
 * Gap Analysis & Recommendation Engine.
 * Usage: php scripts/gap-analysis.php --target-phase=POLISH
 */

namespace SmartAlloc\Scripts;

use RuntimeException;

class BaselineException extends RuntimeException {}

class GapAnalyzer
{
    private const BASELINE_CONFIG = __DIR__ . '/../config/baseline.json';
    private const EFFORT_MATRIX   = [
        'Security'       => ['effort_per_point' => 8,  'risk' => 'HIGH'],
        'Logic'          => ['effort_per_point' => 6,  'risk' => 'MEDIUM'],
        'Performance'    => ['effort_per_point' => 10, 'risk' => 'HIGH'],
        'Observability'  => ['effort_per_point' => 4,  'risk' => 'LOW'],
        'Maintainability'=> ['effort_per_point' => 5,  'risk' => 'MEDIUM'],
    ];

    private array $baseline;

    public function __construct()
    {
        if (! is_file(self::BASELINE_CONFIG)) {
            throw new BaselineException('Baseline config missing');
        }

        $data           = json_decode(file_get_contents(self::BASELINE_CONFIG), true);
        $this->baseline = is_array($data) ? $data : [];
    }

    public function analyze(string $targetPhase): array
    {
        $phaseData = $this->baseline['phases'][$targetPhase] ?? ['metrics' => []];
        $gaps      = $phaseData['metrics'];
        $recommendations = [];
        $totalEffort     = 0;

        foreach ($gaps as $dimension => $required) {
            $effort = $required * self::EFFORT_MATRIX[$dimension]['effort_per_point'];
            $totalEffort += $effort;
            $recommendations[] = [
                'dimension'    => $dimension,
                'gap'          => $required,
                'effort_hours' => $effort,
                'priority'     => self::EFFORT_MATRIX[$dimension]['risk'],
            ];
        }

        return [
            'target_phase'        => $targetPhase,
            'total_effort_hours'  => $totalEffort,
            'recommendations'     => $recommendations,
        ];
    }
}

$options = getopt('', ['target-phase:']);
$target  = $options['target-phase'] ?? 'FOUNDATION';
$analyzer = new GapAnalyzer();
$result   = $analyzer->analyze($target);

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
