<?php
/**
 * Run performance scenarios with budgets.
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use SmartAlloc\Perf\ScenarioRunner;

$runner = new ScenarioRunner();

$scenarios = [
    'fast' => array( 'budget' => 50, 'fn' => fn() => usleep(20000) ),
    'slow' => array( 'budget' => 30, 'fn' => fn() => usleep(50000) ),
];

foreach ($scenarios as $name => $data) {
    $result         = $runner->run($name, $data['fn'], $data['budget']);
    $scenario_status = $result['passed'] ? 'PASS' : 'FAIL';
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    printf("%s: %.2fms (budget %d) %s\n", $name, $result['time'], $data['budget'], $scenario_status);
}

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
printf("Score: %d\n", $runner->score());
