<?php

/**
 * Circuit Breaker Callback Exception
 *
 * @package SmartAlloc
 */

declare(strict_types=1);

namespace SmartAlloc\Exceptions;

use Exception;

/**
 * Circuit Breaker Callback Exception
 *
 * Thrown when a circuit breaker callback fails during execution.
 */
class CircuitBreakerCallbackException extends Exception
{
    /**
     * Original exception
     *
     * @var \Throwable|null
     */
    private ?\Throwable $originalException;

    /**
     * Constructor
     *
     * @param string          $message            Exception message.
     * @param \Throwable|null $originalException  Original exception.
     * @param int             $code               Exception code.
     * @param \Throwable|null $previous           Previous exception.
     */
    public function __construct(
        string $message = 'Circuit breaker callback failed',
        ?\Throwable $originalException = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $this->originalException = $originalException;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get original exception
     *
     * @return \Throwable|null Original exception.
     */
    public function getOriginalException(): ?\Throwable
    {
        return $this->originalException;
    }
}
