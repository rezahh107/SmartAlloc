<?php

declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class FlowPerksIntegrationTest extends BaseTestCase
{
    public function test_flow_perks_loops_and_conditional_routing_or_skip(): void
    {
        self::markTestSkipped('TODO: Requires Gravity Flow + Gravity Perks to test loops and conditional routing.');
    }

    public function test_flow_assignee_changes_or_skip(): void
    {
        self::markTestSkipped('TODO: Needs Gravity Flow environment to simulate assignee changes.');
    }
}
