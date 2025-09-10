<?php

/**
 * Circuit Breaker Service for SmartAlloc
 *
 * @package SmartAlloc\Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Interfaces\CircuitBreakerInterface;
use SmartAlloc\Exceptions\CircuitBreakerException;
use SmartAlloc\Exceptions\CircuitBreakerCallbackException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use InvalidArgumentException;

/**
 * Circuit Breaker implementation providing fault tolerance.
 */
class CircuitBreaker implements CircuitBreakerInterface
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private string $state = self::STATE_CLOSED;
    private int $failureThreshold;
    private int $recoveryTimeout;
    private int $failureCount = 0;
    private ?int $lastFailureTime = null;
    /**
     * Half-open state callback.
     *
     * @var callable|null
     */
    private $halfOpenCallback;
    private int $halfOpenSuccessCount = 0;
    private int $halfOpenSuccessThreshold;
    private LoggerInterface $logger;
    private string $name;

    public function __construct(
        int $failureThreshold = 5,
        int $recoveryTimeout = 60,
        $halfOpenCallback = null,
        int $halfOpenSuccessThreshold = 3,
        ?LoggerInterface $logger = null,
        string $name = 'default'
    ) {
        $this->validateCallbackParameter($halfOpenCallback, 'halfOpenCallback');

        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
        $this->halfOpenCallback = $halfOpenCallback;
        $this->halfOpenSuccessThreshold = $halfOpenSuccessThreshold;
        $this->logger = $logger ?? new NullLogger();
        $this->name = $name;
    }

    /**
     * Guard an operation.
     *
     * @throws CircuitBreakerException When circuit is open.
     */
    public function guard(string $name): void
    {
        if ($this->state === self::STATE_OPEN) {
            throw new CircuitBreakerException("Circuit '{$name}' is open");
        }
    }

    /**
     * Record a successful call.
     */
    public function success(string $name): void
    {
        $this->failureCount = 0;
        $this->state        = self::STATE_CLOSED;
    }

    /**
     * Record a failure and maybe open the circuit.
     */
    public function failure(string $name, \Throwable $e): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();
        if ($this->failureCount >= $this->failureThreshold) {
            $this->state = self::STATE_OPEN;
        }
        $this->logger->warning('Circuit failure', ['name' => $name, 'error' => $e->getMessage()]);
    }

    /**
     * Update circuit breaker configuration.
     */
    public function updateConfig(int $failureThreshold, int $recoveryTimeout, $callback = null): void
    {
        $this->validateCallbackParameter($callback, 'callback');

        $this->failureThreshold = $failureThreshold;
        $this->recoveryTimeout = $recoveryTimeout;
        $this->halfOpenCallback = $callback;

        $this->logger->info('Circuit breaker configuration updated');
    }

    /**
     * Set half-open callback with validation.
     */
    public function setHalfOpenCallback($callback): void
    {
        $this->validateCallbackParameter($callback, 'callback');

        $this->halfOpenCallback = $callback;
    }

    /**
     * Get current half-open callback.
     */
    public function getHalfOpenCallback(): ?callable
    {
        return $this->halfOpenCallback;
    }

    /**
     * Validate callback parameter with detailed error messages.
     *
     * @param mixed  $callback
     * @param string $parameterName
     */
    private function validateCallbackParameter($callback, string $parameterName): void
    {
        if ($callback === null) {
            return;
        }

        if (is_array($callback)) {
            if (count($callback) !== 2) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Parameter "%s" array must contain exactly 2 elements [object/class, method], %d given.',
                        $parameterName,
                        count($callback)
                    )
                );
            }

            [$classOrObject, $method] = $callback;

            if (!is_string($method)) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Parameter "%s" array method name must be string, %s given.',
                        $parameterName,
                        gettype($method)
                    )
                );
            }

            if (is_object($classOrObject)) {
                if (!is_callable([$classOrObject, $method])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Parameter "%s" method "%s" does not exist on object of class "%s".',
                            $parameterName,
                            $method,
                            get_class($classOrObject)
                        )
                    );
                }
            } elseif (is_string($classOrObject)) {
                if (!class_exists($classOrObject)) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Parameter "%s" class "%s" does not exist.',
                            $parameterName,
                            $classOrObject
                        )
                    );
                }

                if (!is_callable([$classOrObject, $method])) {
                    throw new InvalidArgumentException(
                        sprintf(
                            'Parameter "%s" static method "%s" does not exist on class "%s".',
                            $parameterName,
                            $method,
                            $classOrObject
                        )
                    );
                }
            } else {
                throw new InvalidArgumentException(
                    sprintf(
                        'Parameter "%s" array first element must be object or class name (string), %s given.',
                        $parameterName,
                        gettype($classOrObject)
                    )
                );
            }
        }

        if (!is_callable($callback)) {
            $type = gettype($callback);
            if ($type === 'object') {
                $type = get_class($callback);
            }
            if ($type === 'array') {
                $type = 'array';
            }
            throw new InvalidArgumentException(
                sprintf(
                    'Parameter "%s" must be callable or null, %s given. '
                    . 'Valid callable types: function name (string), closure, '
                    . 'array [object, method], array [class, static_method], '
                    . 'invokable object, or null.',
                    $parameterName,
                    $type
                )
            );
        }
    }

    /**
     * Check if callback can be invoked safely.
     */
    private function isCallbackInvokable(callable $callback): bool
    {
        try {
            if (is_array($callback)) {
                $reflection = new \ReflectionMethod($callback[0], $callback[1]);
                return $reflection->isPublic();
            }

            if (is_string($callback)) {
                if (function_exists($callback)) {
                    return true;
                }
                if (strpos($callback, '::') !== false) {
                    [$class, $method] = explode('::', $callback, 2);
                    if (class_exists($class) && method_exists($class, $method)) {
                        $ref = new \ReflectionMethod($class, $method);
                        return $ref->isStatic() && $ref->isPublic();
                    }
                }
                return false;
            }

            if (is_object($callback)) {
                if ($callback instanceof \Closure) {
                    return true;
                }
                return method_exists($callback, '__invoke');
            }
            return false;
        } catch (\ReflectionException $e) {
            $this->logger->warning('Callback reflection failed', [
                'circuit_breaker' => $this->name,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Execute a callback with exception handling.
     *
     * @throws CircuitBreakerCallbackException
     */
    private function executeCallback(callable $callback, string $callbackType)
    {
        try {
            return $callback();
        } catch (\Throwable $exception) {
            $this->logger->error('Circuit breaker callback failed', [
                'callback_type' => $callbackType,
                'exception' => get_class($exception),
            ]);

            throw new CircuitBreakerCallbackException(
                sprintf(
                    'Circuit breaker "%s" callback "%s" failed: %s',
                    $this->name,
                    $callbackType,
                    $exception->getMessage()
                ),
                $exception,
                0,
                $exception
            );
        }
    }

    /**
     * Execute an operation guarded by the circuit breaker.
     *
     * @param callable $operation Operation to execute.
     * @param mixed    ...$args   Arguments for operation.
     * @return mixed
     * @throws CircuitBreakerException When circuit is open.
     */
    public function execute(callable $operation, ...$args)
    {
        if ($this->isOpen()) {
            if ($this->shouldAttemptReset()) {
                $this->transitionToHalfOpen();
            } else {
                throw new CircuitBreakerException('Circuit breaker is open. Operation blocked.');
            }
        }

        try {
            $result = $operation(...$args);
            $this->onSuccess();
            return $result;
        } catch (\Throwable $exception) {
            $this->onFailure();
            throw $exception;
        }
    }

    /**
     * Determine if circuit is open.
     */
    public function isOpen(): bool
    {
        return $this->state === self::STATE_OPEN;
    }

    /**
     * Determine if circuit is closed.
     */
    public function isClosed(): bool
    {
        return $this->state === self::STATE_CLOSED;
    }

    /**
     * Determine if circuit is half-open.
     */
    public function isHalfOpen(): bool
    {
        return $this->state === self::STATE_HALF_OPEN;
    }

    /**
     * Get current state.
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * Get failure count.
     */
    public function getFailureCount(): int
    {
        return $this->failureCount;
    }

    /**
     * Get failure threshold.
     */
    public function getFailureThreshold(): int
    {
        return $this->failureThreshold;
    }

    /**
     * Reset breaker to closed state.
     */
    public function reset(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->lastFailureTime = null;
        $this->halfOpenSuccessCount = 0;
    }

    /**
     * Force breaker open.
     */
    public function forceOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->lastFailureTime = time();
    }

    private function onSuccess(): void
    {
        if ($this->isHalfOpen()) {
            $this->halfOpenSuccessCount++;
            if ($this->halfOpenSuccessCount >= $this->halfOpenSuccessThreshold) {
                $this->transitionToClosed();
            }
        } else {
            $this->failureCount = 0;
        }
    }

    private function onFailure(): void
    {
        $this->failureCount++;
        $this->lastFailureTime = time();
        if ($this->failureCount >= $this->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    private function shouldAttemptReset(): bool
    {
        return $this->lastFailureTime !== null
            && (time() - $this->lastFailureTime) >= $this->recoveryTimeout;
    }

    private function transitionToClosed(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->halfOpenSuccessCount = 0;
        $this->lastFailureTime = null;
    }

    private function transitionToOpen(): void
    {
        $this->state = self::STATE_OPEN;
        $this->halfOpenSuccessCount = 0;
    }

    /**
     * Transition to half-open state.
     */
    private function transitionToHalfOpen(): void
    {
        $this->state = self::STATE_HALF_OPEN;
        $this->halfOpenSuccessCount = 0;

        $this->logger->info('Circuit breaker transitioning to half-open');

        if ($this->halfOpenCallback !== null) {
            if (!$this->isCallbackInvokable($this->halfOpenCallback)) {
                $this->logger->error('Half-open callback is not invokable at runtime');

                throw new CircuitBreakerCallbackException(
                    sprintf('Circuit breaker "%s" half-open callback is not invokable', $this->name)
                );
            }

            $this->executeCallback($this->halfOpenCallback, 'half_open');
        }
    }

    /**
     * Return statistics about the breaker.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $stats = [
            'name' => $this->name,
            'state' => $this->state,
            'failure_count' => $this->failureCount,
            'failure_threshold' => $this->failureThreshold,
            'recovery_timeout' => $this->recoveryTimeout,
            'last_failure_time' => $this->lastFailureTime,
            'half_open_success_count' => $this->halfOpenSuccessCount,
            'half_open_success_threshold' => $this->halfOpenSuccessThreshold,
            'has_callback' => $this->halfOpenCallback !== null,
        ];

        if ($this->halfOpenCallback !== null) {
            $stats['callback_info'] = [
                'type' => $this->getCallbackTypeDescription($this->halfOpenCallback),
                'is_invokable' => $this->isCallbackInvokable($this->halfOpenCallback),
            ];
        }

        return $stats;
    }

    /**
     * Describe callback type.
     */
    private function getCallbackTypeDescription(callable $callback): string
    {
        if ($callback instanceof \Closure) {
            return 'closure';
        }
        if (is_string($callback)) {
            return strpos($callback, '::') !== false ? 'static_method_string' : 'function_string';
        }
        if (is_array($callback)) {
            return is_object($callback[0]) ? 'instance_method_array' : 'static_method_array';
        }
        if (is_object($callback)) {
            return 'invokable_object';
        }
        return 'unknown';
    }
}
