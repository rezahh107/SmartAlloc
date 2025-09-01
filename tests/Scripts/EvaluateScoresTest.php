<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Scripts;

use SmartAlloc\Tests\BaseTestCase;

final class EvaluateScoresTest extends BaseTestCase
{
    /**
     * @dataProvider dimensionProvider
     */
    public function test_ci_failure_when_dimension_below_threshold(string $dimension, string $envVar): void
    {
        $tmp = sys_get_temp_dir() . '/sa_eval_' . uniqid();
        mkdir($tmp, 0777, true);
        $root = dirname(__DIR__, 2);
        symlink($root . '/vendor', $tmp . '/vendor');
        symlink($root . '/src', $tmp . '/src');
        symlink($root . '/tests', $tmp . '/tests');
        $context = [
            'current_scores' => [
                'security' => 25,
                'logic' => 25,
                'performance' => 25,
                'readability' => 25,
                'goal' => 25,
                'weighted_percent' => 95.0,
            ],
        ];
        $context['current_scores'][$dimension] = 10;
        file_put_contents($tmp . '/ai_context.json', json_encode($context));
        $cwd = getcwd();
        chdir($tmp);
        putenv($envVar . '=20');
        exec('bash ' . escapeshellarg($root . '/scripts/evaluate_scores.sh'), $o, $s);
        exec('bash ' . escapeshellarg($root . '/scripts/fail_on_ci_failure.sh'), $o2, $s2);
        chdir($cwd);

        $this->assertSame(0, $s);
        $this->assertSame(1, $s2);
    }

    public function dimensionProvider(): array
    {
        return [
            ['logic', 'LOGIC_MIN'],
            ['performance', 'PERFORMANCE_MIN'],
            ['readability', 'READABILITY_MIN'],
            ['goal', 'GOAL_MIN'],
        ];
    }
}
