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
        if (isset($payload['dedupe_key']) && is_string($payload['dedupe_key'])) {
            return $payload['dedupe_key'];
        }
        $entry = (string)($payload['entry_id'] ?? '0');
        return $event . ':' . $entry . ':' . $version;
    }
}
