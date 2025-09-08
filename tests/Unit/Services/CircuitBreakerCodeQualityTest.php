<?php

/**
 * CircuitBreaker code quality tests.
 */

declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Services;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use SmartAlloc\Exceptions\CircuitBreakerException;
use SmartAlloc\Services\CircuitBreaker;

class CircuitBreakerCodeQualityTest extends TestCase
{
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = new NullLogger();
    }

    public function testConstructorPositiveIntegerValidation(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new CircuitBreaker(failureThreshold: 0, logger: $this->logger);
    }

    public function testNameSanitization(): void
    {
        $cb = new CircuitBreaker(name: 'test<script>', logger: $this->logger);
        $this->assertMatchesRegularExpression('/^[a-zA-Z0-9_\-.]+$/', $cb->getName());
    }

    public function testFailureMetadataCollectionAndLimit(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 100, logger: $this->logger);

        for ($i = 0; $i < 60; $i++) {
            try {
                $cb->execute(function () use ($i): void {
                    throw new \RuntimeException("Failure {$i}", $i);
                });
            } catch (\Throwable $e) {
                // expected
            }
        }

        $metadata = $cb->getFailureMetadata();
        $this->assertLessThanOrEqual(50, count($metadata));
        $messages = array_column($metadata, 'exception_message');
        $this->assertContains('Failure 59', $messages);
        $this->assertNotContains('Failure 0', $messages);
    }

    public function testDetailedCircuitOpenExceptionMessage(): void
    {
        $cb = new CircuitBreaker(failureThreshold: 1, recoveryTimeout: 3600, logger: $this->logger, name: 'open_test');

        try {
            $cb->execute(function (): void {
                throw new \RuntimeException('boom');
            });
        } catch (\Throwable $e) {
            // ignore
        }

        $this->expectException(CircuitBreakerException::class);
        $this->expectExceptionMessage('Circuit breaker "open_test" is open');

        $cb->execute(function (): void {
            // should not execute
        });
    }
}
