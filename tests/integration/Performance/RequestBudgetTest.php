<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RequestBudgetTest extends TestCase
{
    public function test_request_budget_opt_in(): void
    {
        if (getenv('RUN_PERFORMANCE_TESTS') !== '1') {
            $this->markTestSkipped('performance tests opt-in');
        }
        if (!function_exists('get_num_queries')) {
            $this->markTestSkipped('WP not bootstrapped / wp-browser missing');
        }

        $queries = function_exists('get_num_queries') ? get_num_queries() : -1;
        if ($queries === -1 || !defined('SAVEQUERIES') || SAVEQUERIES !== true) {
            $this->markTestSkipped('SAVEQUERIES not enabled');
        }

        $mem = memory_get_peak_usage(true);
        $this->assertLessThan(50, $queries, 'query budget exceeded');
        $this->assertLessThan(32 * 1024 * 1024, $mem, 'memory budget exceeded');
    }
}
