<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Services\RetryService;

final class RetryServiceTest extends BaseTestCase
{
    public function testBackoffMonotonicAndBounded(): void
    {
        mt_srand(1);
        $svc = new RetryService();
        for ($i = 1; $i <= 5; $i++) {
            $d = $svc->backoff($i);
            $base = min(60, (int) pow(2, $i - 1));
            $this->assertGreaterThanOrEqual($base, $d);
            $this->assertLessThanOrEqual($base + 3, $d);
        }
    }
}
