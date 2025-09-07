<?php
/**
 * Circuit Breaker implementation with transient-based state storage.
 *
 * Provides fault tolerance by monitoring failure rates and temporarily
 * blocking operations when thresholds are exceeded.
 *
 * @package SmartAlloc\Services
 * @since 1.0.0
 */

// phpcs:disable WordPress.Files.FileName,WordPress.NamingConventions.ValidVariableName,WordPress.NamingConventions.ValidFunctionName,WordPress.PHP.YodaConditions

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\ValueObjects\CircuitBreakerStatus;
use SmartAlloc\Services\Exceptions\CircuitOpenException;

/**
 * Circuit Breaker implementation with transient-based state storage.
 *
 * Available WordPress Filters:
 * - smartalloc_cb_threshold: Modify failure threshold (default: 5)
 *   Parameters: $threshold (int), $circuit_key (string)
 * - smartalloc_cb_cooldown: Modify cooldown period in seconds (default: 300)
 *   Parameters: $cooldown (int), $circuit_key (string)
 *
 * @example
 * // Set custom threshold for API circuit
 * add_filter('smartalloc_cb_threshold', function ($threshold, $key) {
 * return $key === 'api_calls' ? 10 : $threshold;
 * }, 10, 2);
 *
 * @example
 * // Set longer cooldown for database circuit
 * add_filter('smartalloc_cb_cooldown', function ($cooldown, $key) {
 * return $key === 'database' ? 600 : $cooldown;
 * }, 10, 2);
 */
final class CircuitBreaker {

	/**
	 * Default failure threshold before circuit opens.
	 */
	private const DEFAULT_THRESHOLD = 5;

	/**
	 * Default cooldown period in seconds (5 minutes).
	 */
	private const DEFAULT_COOLDOWN = 300;

	/**
	 * Base transient key for circuit breaker state.
	 */
	private const TRANSIENT_KEY = 'smartalloc_circuit_breaker';

		/**
		 * Failure threshold for this circuit.
		 *
		 * @var int
		 */
	private int $threshold;

		/**
		 * Cooldown period in seconds.
		 *
		 * @var int
		 */
	private int $cooldown;

		/**
		 * Unique transient key for this circuit instance.
		 *
		 * @var string
		 */
	private string $transientKey;

		/**
		 * Initialize circuit breaker with configurable parameters.
		 *
		 * @param string $key Circuit identifier.
		 */
	public function __construct( string $key = 'default' ) {
		$this->threshold = (int) apply_filters(
			'smartalloc_cb_threshold',
			self::DEFAULT_THRESHOLD,
			$key
		);

		$this->cooldown = (int) apply_filters(
			'smartalloc_cb_cooldown',
			self::DEFAULT_COOLDOWN,
			$key
		);

		$this->transientKey = self::TRANSIENT_KEY . '_' . $key;
	}

		/**
		 * Get current circuit breaker status with auto-recovery.
		 *
		 * @return CircuitBreakerStatus Current status object.
		 */
	public function getStatus(): CircuitBreakerStatus {
		$data = $this->retrieveOrInitializeTransient();
		$data = $this->autoRecoverIfExpired( $data );
		$data = $this->sanitizeErrorMessage( $data );

		return new CircuitBreakerStatus(
			$data['state'],
			$data['fail_count'],
			$this->threshold,
			$data['cooldown_until'],
			$data['last_error']
		);
	}

		/**
		 * Record a failure and update circuit state.
		 *
		 * @param string $error Error message.
		 * @throws CircuitOpenException When failure threshold is exceeded.
		 */
	public function recordFailure( string $error ): void {
		$status       = $this->getStatus();
		$newFailCount = $status->failCount + 1;

		$newState      = $newFailCount >= $this->threshold ? 'open' : 'closed';
		$currentTime   = function_exists( 'wp_date' ) ? (int) wp_date( 'U' ) : time();
		$cooldownUntil = $newState === 'open'
		? $currentTime + $this->cooldown
		: null;

		$this->saveState(
			$newState,
			$newFailCount,
			$cooldownUntil,
			$error
		);

		if ( $newState === 'open' ) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new CircuitOpenException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				$this->transientKey,
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				$newFailCount,
				(int) $cooldownUntil,
				'Circuit breaker opened due to failure threshold exceeded'
			);
		}
	}

		/**
		 * Record a successful operation and reset circuit state.
		 *
		 * @return void
		 */
	public function recordSuccess(): void {
		$this->saveState( 'closed', 0, null, null );
	}

		/**
		 * Guard against circuit breaker open state.
		 *
		 * @param string $context Context for error messages.
		 * @throws \RuntimeException When circuit is open.
		 */
	public function guard( string $context ): void {
		$status      = $this->getStatus();
		$currentTime = function_exists( 'wp_date' ) ? (int) wp_date( 'U' ) : time();

		if (
		$status->state === 'open' &&
		(
			$status->cooldownUntil === null ||
			$status->cooldownUntil > $currentTime
		)
		) {
            // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw new \RuntimeException(
                // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				'Circuit breaker open for ' . $context
			);
		}
	}

		/**
		 * Handle successful operation completion.
		 *
		 * @param string $context Context for the successful operation.
		 * @return void
		 */
	public function success( string $context ): void {
		unset( $context );
		$this->recordSuccess();
	}

		/**
		 * Handle operation failure.
		 *
		 * @param string     $context   Context of the operation.
		 * @param \Throwable $exception Thrown exception.
		 * @return void
		 */
	public function failure( string $context, \Throwable $exception ): void {
		unset( $context );
		$sanitized_message = $this->sanitizeMessage( $exception->getMessage() );
		$this->recordFailure( $sanitized_message );
	}

		/**
		 * Save circuit breaker state to transient storage.
		 *
		 * @param string      $state         Circuit state.
		 * @param int         $failCount     Failure count.
		 * @param int|null    $cooldownUntil Cooldown expiration timestamp.
		 * @param string|null $lastError     Last error message.
		 * @return void
		 */
	private function saveState(
		string $state,
		int $failCount,
		?int $cooldownUntil,
		?string $lastError
	): void {
		$data = array(
			'state'          => $state,
			'fail_count'     => $failCount,
			'cooldown_until' => $cooldownUntil,
			'last_error'     => $lastError,
		);

		set_transient(
			$this->transientKey,
			$data,
			$this->cooldown + 60
		);
	}

		/**
		 * Retrieve existing transient data or initialize default state.
		 *
		 * @return array Circuit data.
		 */
	private function retrieveOrInitializeTransient(): array {
		$data = get_transient( $this->transientKey );

		if ( $data === false ) {
			return array(
				'state'          => 'closed',
				'fail_count'     => 0,
				'cooldown_until' => null,
				'last_error'     => null,
			);
		}

		return $data;
	}

		/**
		 * Auto-recover circuit from open to half-open if cooldown expired.
		 *
		 * @param array $data Circuit data.
		 * @return array Updated circuit data.
		 */
	private function autoRecoverIfExpired( array $data ): array {
		if (
		$data['state'] === 'open' &&
		$data['cooldown_until'] !== null &&
		( function_exists( 'wp_date' ) ? (int) wp_date( 'U' ) : time() ) >= $data['cooldown_until']
		) {
			$data['state']          = 'half-open';
			$data['fail_count']     = 0;
			$data['cooldown_until'] = null;

			$this->saveState(
				$data['state'],
				$data['fail_count'],
				$data['cooldown_until'],
				$data['last_error']
			);
		}

		return $data;
	}

		/**
		 * Sanitize and truncate message to prevent storage bloat.
		 *
		 * @param string $message Error message.
		 * @return string Sanitized message.
		 */
	private function sanitizeMessage( string $message ): string {
			return substr( $message, 0, 100 );
	}

		/**
		 * Sanitize and truncate error message to prevent storage bloat.
		 *
		 * @param array $data Circuit data.
		 * @return array Circuit data with sanitized error message.
		 */
	private function sanitizeErrorMessage( array $data ): array {
		if ( $data['last_error'] !== null ) {
				$data['last_error'] = substr( $data['last_error'], 0, 100 );
		}

			return $data;
	}
}
