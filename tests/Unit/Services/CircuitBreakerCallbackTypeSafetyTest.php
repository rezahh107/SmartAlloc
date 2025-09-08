<?php

declare(strict_types=1);

// phpcs:disable

namespace SmartAlloc\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\CircuitBreaker;
use Psr\Log\AbstractLogger;
use InvalidArgumentException;

class TestCallbackClass
{
    public function publicMethod(): string
    {
        return 'public';
    }

    public static function staticMethod(): string
    {
        return 'static';
    }

    /** @phpstan-ignore-next-line */
    private function privateMethod(): string
    {
        return 'private';
    }
}

class InvokableTestClass
{
    public function __invoke(): string
    {
        return 'invokable';
    }
}

class SimpleTestLogger extends AbstractLogger
{
    public array $info = [];

    public function log($level, $message, array $context = []): void
    {
        if ($level === 'info') {
            $this->info[] = $message;
        }
    }

    public function hasInfo(string $message): bool
    {
        return in_array($message, $this->info, true);
    }
}

class CircuitBreakerCallbackTypeSafetyTest extends TestCase {
    private SimpleTestLogger $logger;

    protected function setUp(): void
    {
        $this->logger = new SimpleTestLogger();
    }

    public static function validCallbacksProvider(): array {
        $instance = new TestCallbackClass();
        return [
            'null' => [null],
            'closure' => [fn() => null],
            'function' => ['strlen'],
            'static array' => [[TestCallbackClass::class, 'staticMethod']],
            'instance array' => [[$instance, 'publicMethod']],
            'invokable' => [new InvokableTestClass()],
            'static string' => [TestCallbackClass::class . '::staticMethod'],
        ];
    }

    /**
     * @dataProvider validCallbacksProvider
     */
    public function testValidCallbackTypesInConstructor($callback): void {
        $cb = new CircuitBreaker(halfOpenCallback: $callback, logger: $this->logger);
        $this->assertSame($callback, $cb->getHalfOpenCallback());
    }

    public static function invalidCallbacksProvider(): array {
        return [
            ['non_existent_function', 'string'],
            [123, 'integer'],
            [new \stdClass(), 'stdClass'],
        ];
    }

    /**
     * @dataProvider invalidCallbacksProvider
     */
    public function testInvalidCallbacksInConstructor($callback, string $type): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter "halfOpenCallback" must be callable or null, ' . $type . ' given');
        new CircuitBreaker(halfOpenCallback: $callback, logger: $this->logger);
    }

    public function testUpdateConfigWithValidCallbacks(): void {
        $cb = new CircuitBreaker(logger: $this->logger);
        $cb->updateConfig(10, 120, null);
        $this->assertNull($cb->getHalfOpenCallback());
        $closure = fn() => null;
        $cb->updateConfig(15, 180, $closure);
        $this->assertSame($closure, $cb->getHalfOpenCallback());
        $cb->updateConfig(20, 240, 'strlen');
        $this->assertEquals('strlen', $cb->getHalfOpenCallback());
        $this->assertTrue($this->logger->hasInfo('Circuit breaker configuration updated'));
    }

    public function testUpdateConfigWithInvalidCallback(): void {
        $cb = new CircuitBreaker(logger: $this->logger);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Parameter "callback" must be callable or null, boolean given');
        $cb->updateConfig(10, 120, true);
    }

    public function testSetHalfOpenCallback(): void {
        $cb = new CircuitBreaker(logger: $this->logger);
        $closure = fn() => null;
        $cb->setHalfOpenCallback($closure);
        $this->assertSame($closure, $cb->getHalfOpenCallback());
        $cb->setHalfOpenCallback(null);
        $this->assertNull($cb->getHalfOpenCallback());
        $this->expectException(InvalidArgumentException::class);
        $cb->setHalfOpenCallback('invalid_function');
    }

    public function testDetailedArrayValidation(): void {
        try {
            new CircuitBreaker(halfOpenCallback: ['only_one'], logger: $this->logger);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('array must contain exactly 2 elements', $e->getMessage());
        }
        try {
            new CircuitBreaker(halfOpenCallback: [TestCallbackClass::class, 123], logger: $this->logger);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('method name must be string', $e->getMessage());
        }
        try {
            new CircuitBreaker(halfOpenCallback: ['NonExistentClass', 'method'], logger: $this->logger);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('class "NonExistentClass" does not exist', $e->getMessage());
        }
        try {
            new CircuitBreaker(halfOpenCallback: [TestCallbackClass::class, 'nonExistent'], logger: $this->logger);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('static method "nonExistent" does not exist', $e->getMessage());
        }
        $instance = new TestCallbackClass();
        try {
            new CircuitBreaker(halfOpenCallback: [$instance, 'privateMethod'], logger: $this->logger);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('method "privateMethod" does not exist', $e->getMessage());
        }
    }

    public function testCallbackExecution(): void {
        $executed = false;
        $cb = new CircuitBreaker(
            failureThreshold: 1,
            recoveryTimeout: 1,
            halfOpenCallback: function () use (&$executed) { $executed = true; },
            halfOpenSuccessThreshold: 1,
            logger: $this->logger
        );
        try {
            $cb->execute(fn() => throw new \Exception('fail'));
        } catch (\Exception $e) {
        }
        sleep(2);
        $cb->execute(fn() => 'ok');
        $this->assertTrue($executed);
    }

    public function testStatisticsIncludeCallbackInfo(): void {
        $cb = new CircuitBreaker(halfOpenCallback: fn() => null, logger: $this->logger);
        $stats = $cb->getStatistics();
        $this->assertTrue($stats['has_callback']);
        $this->assertEquals('closure', $stats['callback_info']['type']);
        $this->assertTrue($stats['callback_info']['is_invokable']);
    }

    public function testCallbackTypeDescriptions(): void {
        $cb = new CircuitBreaker(halfOpenCallback: fn() => null, logger: $this->logger);
        $this->assertEquals('closure', $cb->getStatistics()['callback_info']['type']);
        $cb = new CircuitBreaker(halfOpenCallback: 'strlen', logger: $this->logger);
        $this->assertEquals('function_string', $cb->getStatistics()['callback_info']['type']);
        $cb = new CircuitBreaker(halfOpenCallback: TestCallbackClass::class . '::staticMethod', logger: $this->logger);
        $this->assertEquals('static_method_string', $cb->getStatistics()['callback_info']['type']);
        $instance = new TestCallbackClass();
        $cb = new CircuitBreaker(halfOpenCallback: [$instance, 'publicMethod'], logger: $this->logger);
        $this->assertEquals('instance_method_array', $cb->getStatistics()['callback_info']['type']);
        $cb = new CircuitBreaker(halfOpenCallback: [TestCallbackClass::class, 'staticMethod'], logger: $this->logger);
        $this->assertEquals('static_method_array', $cb->getStatistics()['callback_info']['type']);
        $cb = new CircuitBreaker(halfOpenCallback: new InvokableTestClass(), logger: $this->logger);
        $this->assertEquals('invokable_object', $cb->getStatistics()['callback_info']['type']);
    }

    public function testErrorMessagesAreDescriptive(): void {
        try {
            new CircuitBreaker(halfOpenCallback: ['InvalidClass', 'method'], logger: $this->logger);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('Parameter "halfOpenCallback"', $e->getMessage());
            $this->assertStringContainsString('class "InvalidClass" does not exist', $e->getMessage());
        }
        try {
            new CircuitBreaker(halfOpenCallback: 42, logger: $this->logger);
            $this->fail();
        } catch (InvalidArgumentException $e) {
            $msg = $e->getMessage();
            $this->assertStringContainsString('Parameter "halfOpenCallback"', $msg);
            $this->assertStringContainsString('must be callable or null', $msg);
            $this->assertStringContainsString('integer given', $msg);
            $this->assertStringContainsString('Valid callable types', $msg);
        }
    }
}
