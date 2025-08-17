<?php

declare(strict_types=1);

namespace SmartAlloc\Event;

/**
 * Event key generator for deduplication
 */
final class EventKey
{
    /**
     * Generate a deduplication key for an event
     */
    public static function make(string $event, array $payload, string $version = 'v1'): string
    {
        $entry = $payload['entry_id'] ?? ($payload['id'] ?? uniqid('', true));
        return $event . ':' . $entry . ':' . $version;
    }
} 