<?php

declare(strict_types=1);

namespace SmartAlloc\Contracts;

/**
 * Interface for event storage
 */
interface EventStoreInterface
{
    /**
     * Insert an event if it doesn't already exist (deduplication)
     * Returns event ID or 0 if duplicate
     */
    public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int;

    /**
     * Start a listener run
     */
    public function startListenerRun(int $eventLogId, string $listener): int;

    /**
     * Finish a listener run
     */
    public function finishListenerRun(int $listenerRunId, string $status, ?string $error, int $durationMs): void;

    /**
     * Finish an event
     */
    public function finishEvent(int $eventLogId, string $status, ?string $error, int $durationMs): void;
} 