<?php

declare(strict_types=1);

namespace SmartAlloc\Contracts;

/**
 * Interface for event listeners
 */
interface ListenerInterface
{
    /**
     * Handle an event
     */
    public function handle(string $event, array $payload): void;
} 