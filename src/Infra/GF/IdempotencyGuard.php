<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use SmartAlloc\Services\Cache;

/**
 * Prevents duplicate Gravity Forms submission processing by caching entry IDs.
 */
final class IdempotencyGuard
{
    private const TTL = 3600; // 1 hour

    public function __construct(private Cache $cache)
    {
    }

    private function key(int $formId, int $entryId): string
    {
        return "gf:entry:{$formId}:{$entryId}";
    }

    public function hasProcessed(int $formId, int $entryId): bool
    {
        return (bool) $this->cache->l1Get($this->key($formId, $entryId));
    }

    public function markProcessed(int $formId, int $entryId): void
    {
        $this->cache->l1Set($this->key($formId, $entryId), 1, self::TTL);
    }
}
