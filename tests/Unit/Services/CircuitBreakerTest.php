<?php

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\CircuitBreaker;
use SmartAlloc\Exceptions\CircuitBreakerException;
use SmartAlloc\Exceptions\CircuitBreakerCallbackException;
use Psr\Log\AbstractLogger;

class CircuitBreakerTest extends TestCase
{
    public function testInitialization(): void
    {
        $cb = new CircuitBreaker(5, 60);
        $this->assertTrue($cb->isClosed());
        $this->assertEquals(0, $cb->getFailureCount());
        $this->assertEquals(5, $cb->getFailureThreshold());
    }

    public function testSuccessfulOperation(): void
    {
        $cb = new CircuitBreaker();
        $result = $cb->execute(fn ($v) => $v * 2, 5);
        $this->assertEquals(10, $result);
        $this->assertTrue($cb->isClosed());
    }

    public function testCircuitOpensAfterThreshold(): void
    {
        $cb = new CircuitBreaker(1, 60);
        try {
            $cb->execute(function (): void {
                throw new \Exception('fail');
            });
        } catch (\Exception $e) {
        }
        $this->expectException(CircuitBreakerException::class);
        $cb->execute(fn () => 'success');
    }

    public function testResetFunctionality(): void
    {
        $cb = new CircuitBreaker(1, 60);
        try {
            $cb->execute(function (): void {
                throw new \Exception('fail');
            });
        } catch (\Exception $e) {
        }
        $this->assertTrue($cb->isOpen());
        $cb->reset();
        $this->assertTrue($cb->isClosed());
    }

    /**
     * Test callback exception is thrown and logged
     */
    public function testCallbackExceptionIsThrownAndLogged(): void
    {
        $this->expectException(CircuitBreakerCallbackException::class);

        $logger = new class extends AbstractLogger {
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => $message, 'context' => $context];
            }

            public function hasError(string $message): bool
            {
                foreach ($this->records as $record) {
                    if ($record['level'] === 'error' && $record['message'] === $message) {
                        return true;
                    }
                }
                return false;
            }
        };

        $failingCallback = function (): void {
            throw new \RuntimeException('Test callback failure');
        };

        $circuitBreaker = new CircuitBreaker(
            failureThreshold: 1,
            recoveryTimeout: 1,
            halfOpenCallback: $failingCallback,
            logger: $logger
        );

        try {
            $circuitBreaker->execute(function (): void {
                throw new \Exception('Operation failed');
            });
        } catch (\Exception $e) {
            // Expected
        }

        sleep(2);

        $circuitBreaker->execute(function () {
            return 'success';
        });

        $this->assertTrue($logger->hasError('Circuit breaker callback failed'));

        $errorRecords = array_filter($logger->records, function ($record) {
            return $record['level'] === 'error';
        });

        $this->assertNotEmpty($errorRecords);
        $this->assertStringContainsString(
            'Test callback failure',
            $errorRecords[0]['context']['exception_message']
        );
    }
}
