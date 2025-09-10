<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName,WordPress.Files.FileName.NotHyphenatedLowercase

/**
 * Gravity Forms idempotency guard.
 *
 * @package SmartAlloc\Infra\GF
 */

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use SmartAlloc\Services\Cache;

/**
 * Prevents duplicate Gravity Forms submission processing by caching entry IDs.
 */
final class IdempotencyGuard {
    /**
     * TTL in seconds for cache entries (1 hour).
     *
     * @var int
     */
    private const TTL = 3600;

    /**
     * Constructor.
     *
     * @param Cache $cache Cache instance.
     */
    public function __construct( private Cache $cache ) {}

    /**
     * Generate cache key for form and entry.
     *
     * @param int $form_id  Form ID.
     * @param int $entry_id Entry ID.
     * @return string Cache key.
     */
    private function key( int $form_id, int $entry_id ): string {
        return "gf:entry:{$form_id}:{$entry_id}";
    }

    /**
     * Check if the entry has already been processed.
     *
     * @param int $form_id  Form ID.
     * @param int $entry_id Entry ID.
     * @return bool True if processed.
     */
    public function has_processed( int $form_id, int $entry_id ): bool {
        return (bool) $this->cache->l1Get( $this->key( $form_id, $entry_id ) );
    }

    /**
     * Mark the entry as processed.
     *
     * @param int $form_id  Form ID.
     * @param int $entry_id Entry ID.
     * @return void
     */
    public function mark_processed( int $form_id, int $entry_id ): void {
        $this->cache->l1Set( $this->key( $form_id, $entry_id ), 1, self::TTL );
    }
}
