<?php

declare(strict_types=1);

namespace SmartAlloc\Utils;

/**
 * Simple deterministic fuzzy matching helper.
 */
final class Fuzzy
{
    /**
     * Calculate similarity between two strings using Levenshtein distance.
     * Result is a float between 0 and 1.
     */
    public static function similarity(string $a, string $b): float
    {
        $a = mb_strtolower(trim($a));
        $b = mb_strtolower(trim($b));
        if ($a === '' || $b === '') {
            return 0.0;
        }
        $len = max(mb_strlen($a), mb_strlen($b));
        if ($len === 0) {
            return 0.0;
        }
        $dist = levenshtein($a, $b);
        $sim = 1 - ($dist / $len);
        if ($sim < 0) {
            $sim = 0.0;
        }
        if ($sim > 1) {
            $sim = 1.0;
        }
        return $sim;
    }

    /**
     * Decide fuzzy match outcome based on similarity.
     *
     * @return 'accept'|'manual'|'reject'
     */
    public static function decide(float $sim): string
    {
        $decision = $sim >= 0.90 ? 'accept' : ($sim >= 0.80 ? 'manual' : 'reject');
        if (function_exists('apply_filters')) {
            /** @psalm-suppress UndefinedFunction */
            $decision = (string) apply_filters('smartalloc/fuzzy_school_decision', $decision, $sim);
        }
        return $decision;
    }
}
