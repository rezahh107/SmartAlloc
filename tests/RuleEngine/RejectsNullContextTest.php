<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\RuleEngine\RuleEngineService;

final class RejectsNullContextTest extends TestCase
{
    public function test_rejects_null_context(): void
    {
        $engine = new RuleEngineService();
        $this->expectException(\TypeError::class);
        $engine->evaluate(null);
    }
}
