<?php
/**
 * Simple performance scenario runner.
 *
 * @package SmartAlloc\Perf
 */

declare(strict_types=1);

namespace SmartAlloc\Perf;

/**
 * Executes scenarios with time budgets and calculates a score.
 */
class ScenarioRunner
{
    /**
     * Results keyed by scenario name.
     *
     * @var array<string, array{time:float,budget:int,passed:bool}>
     */
    private array $results = [];

    /**
     * Run a scenario and record its timing.
     *
     * @param string   $name   Scenario identifier.
     * @param callable $fn     Scenario function.
     * @param int      $budget Time budget in milliseconds.
     *
     * @return array{time:float,budget:int,passed:bool}
     */
    public function run(string $name, callable $fn, int $budget): array
    {
        $start = hrtime(true);
        $fn();
        $duration = (hrtime(true) - $start) / 1_000_000;
        $passed = $duration <= $budget;
        return $this->results[$name] = [
            'time'   => $duration,
            'budget' => $budget,
            'passed' => $passed,
        ];
    }

    /**
     * Get performance score out of 20.
     */
    public function score(): int
    {
        if (!$this->results) {
            return 0;
        }
        $total  = count($this->results);
        $passed = count(array_filter($this->results, static fn($r) => $r['passed']));
        return (int) round(20 * $passed / $total);
    }

    /**
     * Retrieve results for all scenarios.
     *
     * @return array<string, array{time:float,budget:int,passed:bool}>
     */
    public function results(): array
    {
        return $this->results;
    }
}
