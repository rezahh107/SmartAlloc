<?php

declare(strict_types=1);

namespace SmartAlloc\Exceptions;

class ThrottleException extends \Exception
{
    protected int $retryAfter;

    public function __construct(string $message = 'Rate limit exceeded', int $retryAfter = 60, int $code = 429, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
