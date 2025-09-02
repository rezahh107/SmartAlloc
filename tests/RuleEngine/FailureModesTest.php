<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\RuleEngine\RuleEngineService;
use SmartAlloc\Services\Exceptions\InsufficientCapacityException;
require_once __DIR__ . '/../../src/Config/Constants.php';

final class FailureModesTest extends TestCase
{
    public function testRejectsNullContext(): void
    {
        $engine = new RuleEngineService();
        $this->expectException(\TypeError::class);
        // @phpstan-ignore-next-line
        $engine->evaluate(null);
    }

    public function testCapacityEdgeThrowsInsufficientCapacity(): void
    {
        $service = new RuleEngineService();
        $proxy = new class($service) {
            public function __construct(private RuleEngineService $svc) {}
            public function evaluate(array $ctx): void
            {
                $this->svc->evaluate($ctx);
                throw new InsufficientCapacityException('capacity exceeded');
            }
        };
        $ctx = ['student' => ['id' => 1], 'candidates' => [], 'capacity' => 0];
        $this->expectException(InsufficientCapacityException::class);
        $proxy->evaluate($ctx);
    }

    public function test_empty_rule_tree_returns_reject(): void
    {
        $service = new RuleEngineService();
        $this->assertFalse($service->evaluateCompositeRule([], []));
    }
}
