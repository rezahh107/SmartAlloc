<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Exponential backoff with jitter helper.
 */

final class RetryService {

        public function __construct(
                private ?object $scheduler = null,
                private int $maxDelay = 60,
                private int $jitter = 3
        ) {
        }

	/**
	 * Calculate delay seconds for given attempt (1-based).
	 */
	public function backoff( int $attempt ): int {
		$base   = (int) pow( 2, max( 0, $attempt - 1 ) );
		$base   = min( $this->maxDelay, $base );
		$jitter = mt_rand( 0, $this->jitter );
		return $base + $jitter;
	}

        public function enqueue( string $hook, array $args = array(), int $delaySec = 0 ): void {
                if ( $this->scheduler === null || ! method_exists( $this->scheduler, 'enqueue' ) ) {
                        return;
                }
		if ( isset( $args[0] ) && is_array( $args[0] ) ) {
			$args[0]['_attempt'] = ( $args[0]['_attempt'] ?? 1 ) + 1;
		}
		$this->scheduler->enqueue( $hook, $args, $delaySec );
	}
}
