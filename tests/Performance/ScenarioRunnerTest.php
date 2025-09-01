<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Performance;

use SmartAlloc\Perf\ScenarioRunner;
use SmartAlloc\Tests\BaseTestCase;

final class ScenarioRunnerTest extends BaseTestCase
{
    public function testSlowScenarioReducesScore(): void
    {
        $runner = new ScenarioRunner();
        $runner->run('fast', fn() => usleep(10000), 50);
        $runner->run('slow', fn() => usleep(100000), 30);
        $results = $runner->results();
        $this->assertTrue($results['fast']['passed']);
        $this->assertFalse($results['slow']['passed']);
        $this->assertLessThan(20, $runner->score());
    }
}
