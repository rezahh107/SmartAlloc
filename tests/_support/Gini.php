<?php
namespace SmartAlloc\Testing\Support;

/**
 * Compute Gini coefficient for array of non-negative numbers.
 * @param array<int|float> $values
 */
function gini(array $values): float
{
    $n = count($values);
    if ($n === 0) {
        return 0.0;
    }
    sort($values);
    $cum = 0.0;
    $sum = array_sum($values);
    foreach ($values as $i => $v) {
        $cum += ($i + 1) * $v;
    }
    $gini = (2 * $cum) / ($n * $sum) - ($n + 1) / $n;
    return $gini;
}
