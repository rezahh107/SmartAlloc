#!/usr/bin/env php
<?php
/**
 * Baseline Comparison Tool.
 * Usage: php scripts/baseline-compare.php --from=2024-09-01 --to=current
 */

namespace SmartAlloc\Scripts;

use RuntimeException;

class BaselineException extends RuntimeException {}

class BaselineComparator
{
    private const METRICS_DIR = __DIR__ . '/../metrics/';

    public function compare(string $from, string $to): array
    {
        $fromMetrics = $this->loadMetrics($from);
        $toMetrics   = $this->loadMetrics($to);

        $comparison = [
            'period'       => ['from' => $from, 'to' => $to],
            'improvements' => [],
            'regressions'  => [],
            'unchanged'    => [],
        ];

        foreach ($fromMetrics as $dimension => $fromValue) {
            if (in_array($dimension, ['phase_gate_status', 'overall_score'], true)) {
                continue;
            }
            $toValue = $toMetrics[$dimension] ?? 0;
            $delta   = $toValue - $fromValue;

            if ($delta > 0) {
                $comparison['improvements'][$dimension] = $delta;
            } elseif ($delta < 0) {
                $comparison['regressions'][$dimension] = $delta;
            } else {
                $comparison['unchanged'][] = $dimension;
            }
        }

        if (isset($fromMetrics['overall_score'], $toMetrics['overall_score'])) {
            $delta = $toMetrics['overall_score'] - $fromMetrics['overall_score'];
            if ($delta > 0) {
                $comparison['improvements']['overall_score'] = $delta;
            } elseif ($delta < 0) {
                $comparison['regressions']['overall_score'] = $delta;
            } else {
                $comparison['unchanged'][] = 'overall_score';
            }
        }

        if (isset($fromMetrics['phase_gate_status'], $toMetrics['phase_gate_status'])) {
            $comparison['phase_gate'] = [
                'from'      => $fromMetrics['phase_gate_status'],
                'to'        => $toMetrics['phase_gate_status'],
                'regressed' => $fromMetrics['phase_gate_status'] === 'PASS'
                    && $toMetrics['phase_gate_status'] !== 'PASS',
            ];
        }

        return $comparison;
    }

    private function loadMetrics(string $name): array
    {
        $file = self::METRICS_DIR . $name . '.json';
        if (! is_file($file)) {
            throw new BaselineException("Metrics file {$name} not found");
        }

        $data = json_decode(file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }
}

$options = getopt('', ['from:', 'to:']);
$from    = $options['from'] ?? 'start';
$to      = $options['to'] ?? 'current';
$comp    = new BaselineComparator();
$result  = $comp->compare($from, $to);

echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
