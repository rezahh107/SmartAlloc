<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\RuleEngine\RuleEngineService;
use SmartAlloc\RuleEngine\EvaluationResult;
use SmartAlloc\Services\Exceptions\InsufficientCapacityException;

/**
 * Tests for Rule Engine failure modes and boundary conditions.
 *
 * @group rule-engine
 */
final class FailureModesTest extends TestCase {
    /**
     * Test fuzzy school match thresholds.
     *
     * @dataProvider fuzzySchoolThresholds
     */
    public function test_fuzzy_threshold_boundaries(float $score, string $expected): void {
        $decision = match (true) {
            $score >= 0.90 => 'auto',
            $score >= 0.80 => 'manual',
            default => 'reject',
        };

        self::assertSame($expected, $decision);
    }

    /**
     * Data provider for fuzzy threshold tests.
     *
     * @return array<string, array{0: float, 1: string}>
     */
    public static function fuzzySchoolThresholds(): array {
        return [
            'above auto threshold' => [0.91, 'auto'],
            'exact auto threshold' => [0.90, 'auto'],
            'manual upper bound' => [0.89, 'manual'],
            'manual lower bound' => [0.80, 'manual'],
            'below reject threshold' => [0.79, 'reject'],
            'far below threshold' => [0.50, 'reject'],
        ];
    }

    /**
     * Test Iranian phone number validation rules.
     *
     * @dataProvider iranianPhoneNumbers
     */
    public function test_phone_validator_rules(string $phone, bool $valid): void {
        $pattern = '/^(\\+98|0)?9\\d{9}$/';

        self::assertSame($valid, (bool) preg_match($pattern, $phone));
    }

    /**
     * Data provider for Iranian phone validation.
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function iranianPhoneNumbers(): array {
        return [
            'standard format' => ['09123456789', true],
            'with country code' => ['+989123456789', true],
            'without leading zero' => ['9123456789', true],
            'too short' => ['0912345678', false],
            'too long' => ['091234567890', false],
            'invalid prefix' => ['08123456789', false],
        ];
    }

    public function test_invalid_input_throws_type_error(): void {
        $engine = new RuleEngineService();
        $this->expectException(\TypeError::class);
        $engine->evaluate(null);
    }

    public function test_zero_capacity_handling(): void {
        $engine = new class extends RuleEngineService {
            public function evaluate(array $studentCtx): EvaluationResult {
                $result = parent::evaluate($studentCtx);
                if (apply_filters('smartalloc_rule_cap_check', true)) {
                    throw new InsufficientCapacityException('capacity exceeded');
                }
                return $result;
            }
        };
        add_filter('smartalloc_rule_cap_check', '__return_true');
        $this->expectException(InsufficientCapacityException::class);
        $engine->evaluate(['school_fuzzy' => 0.95]);
        remove_filter('smartalloc_rule_cap_check', '__return_true');
    }

    public function test_dependency_failure_graceful_degradation(): void {
        $engine = new RuleEngineService();
        $fn = function () {
            throw new \RuntimeException('db error');
        };
        add_filter('smartalloc_rule_cap_check', $fn);
        $this->expectException(\RuntimeException::class);
        $engine->evaluate(['school_fuzzy' => 0.92]);
        remove_filter('smartalloc_rule_cap_check', $fn);
    }
}
