<?php

declare(strict_types=1);

namespace SmartAlloc\Exceptions;

use Exception;
use Throwable;

final class CircuitBreakerCallbackException extends Exception
{
    private ?Throwable $originalException;
    private string $callbackType;
    private string $circuitName;

    public function __construct(
        string $message = 'Circuit breaker callback failed',
        string $callbackType = 'unknown',
        string $circuitName = 'default',
        ?Throwable $originalException = null,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        $this->callbackType = $callbackType;
        $this->circuitName = $circuitName;
        $this->originalException = $originalException;
        parent::__construct($message, $code, $previous);
    }

    public function getOriginalException(): ?Throwable
    {
        return $this->originalException;
    }

    public function getCallbackType(): string
    {
        return $this->callbackType;
    }

    public function getCircuitName(): string
    {
        return $this->circuitName;
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return [
            'callback_type' => $this->callbackType,
            'circuit_name'  => $this->circuitName,
            'original_exception' => $this->originalException ? [
                'type'    => $this->originalException::class,
                'message' => $this->originalException->getMessage(),
                'code'    => $this->originalException->getCode(),
                'file'    => $this->originalException->getFile(),
                'line'    => $this->originalException->getLine(),
            ] : null,
        ];
    }
}
