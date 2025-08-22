<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\RetryService;

final class RetryServiceTest extends TestCase
{
    public function testBackoffMonotonicAndBounded(): void
    {
        mt_srand(1);
        $svc = new RetryService();
        $delays = [];
        for ($i = 1; $i <= 5; $i++) {
            $delays[$i] = $svc->backoff($i);
        }
        $this->assertLessThan($delays[2], $delays[3]);
        $this->assertLessThan($delays[3], $delays[4]);
        foreach ($delays as $i => $d) {
            $base = min(60, (int) pow(2, $i - 1));
            $this->assertGreaterThanOrEqual($base, $d);
            $this->assertLessThanOrEqual($base + 3, $d);
        }
    }
}
