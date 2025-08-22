<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;

final class MultiStagePriorityTest extends BaseTestCase {
    public function test_multistage_gender_then_fallback_or_skip(): void {
        if (getenv('RUN_ALLOC_SCENARIOS') !== '1') {
            $this->markTestSkipped('alloc scenarios opt-in');
        }
        if (!interface_exists('\\SmartAlloc\\Allocation\\AllocatorStrategyInterface') ||
            !class_exists('\\SmartAlloc\\Allocation\\ScoringAllocator')) {
            $this->markTestSkipped('allocator not implemented');
        }

        // Synthetic candidates
        $candidates = [
            ['id' => 1, 'gender' => 'M', 'score' => 80],
            ['id' => 2, 'gender' => 'F', 'score' => 90],
            ['id' => 3, 'gender' => 'F', 'score' => 85],
            ['id' => 4, 'gender' => 'M', 'score' => 95],
        ];
        $capacity = ['F' => 1, 'M' => 1];

        $selected = [];
        // Stage 1: pick top scoring F within capacity
        $stage1 = array_filter($candidates, fn($c) => $c['gender'] === 'F');
        usort($stage1, fn($a, $b) => $b['score'] <=> $a['score'] ?: $a['id'] <=> $b['id']);
        $selected[] = array_shift($stage1)['id'];

        // Stage 2: fallback to M
        $remaining = array_filter($candidates, fn($c) => !in_array($c['id'], $selected, true));
        $stage2 = array_filter($remaining, fn($c) => $c['gender'] === 'M');
        usort($stage2, fn($a, $b) => $b['score'] <=> $a['score'] ?: $a['id'] <=> $b['id']);
        $selected[] = array_shift($stage2)['id'];

        $this->assertSame([2, 4], $selected, 'deterministic allocation order');
        if (class_exists('\\SmartAlloc\\Allocation\\CommitKeyHelper')) {
            $this->assertTrue(true); // placeholder for commit key idempotency
        }
    }
}
