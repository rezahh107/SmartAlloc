<?php
declare(strict_types=1);

namespace SmartAlloc\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Exception thrown by repository operations.
 */
final class RepositoryException extends RuntimeException
{
    private string $operation;
    /** @var array<string,mixed> */
    private array $context;

    /**
     * @param array<string,mixed> $context
     */
    public function __construct(string $message, string $operation, array $context = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
        $this->operation = $operation;
        $this->context   = $context;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * @return array<string,mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }
}
