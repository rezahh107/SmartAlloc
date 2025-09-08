<?php

/**
 * Circuit Breaker Exception
 *
 * @package SmartAlloc\Exceptions
 * @since 1.0.0
 */

declare(strict_types=1);

namespace SmartAlloc\Exceptions;

use Exception;

/**
 * Thrown when circuit breaker prevents operation execution.
 */
class CircuitBreakerException extends Exception
{
    public function __construct(
        string $message = 'Circuit breaker exception',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
