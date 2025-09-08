<?php
// phpcs:ignoreFile
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
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

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
		 * Circuit breaker name.
		 *
		 * @var string
		 */
	private string $name;

		/**
		 * Logger instance.
		 *
		 * @var LoggerInterface
		 */
	private LoggerInterface $logger;

		/**
		 * Failure metadata entries.
		 *
		 * @var array<int, array<string, mixed>>
		 */
	private array $failureMetadata = array();

		/**
		 * Maximum failure metadata entries.
		 *
		 * @var int
		 */
	private int $maxFailureMetadataEntries;

		/**
		 * Initialize circuit breaker with configurable parameters.
		 *
		 * @param string $key Circuit identifier.
		 */
	public function __construct( string $key = 'default', ?LoggerInterface $logger = null, int $maxFailureMetadataEntries = 100 ) {
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

			$this->transientKey              = self::TRANSIENT_KEY . '_' . $key;
			$this->name                      = $key;
			$this->logger                    = $logger ?? new NullLogger();
			$this->maxFailureMetadataEntries = $maxFailureMetadataEntries;
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
				 * @param string     $operationName Operation identifier.
				 * @param \Throwable $exception     Thrown exception.
				 * @return void
				 */
	public function failure( string $operationName, \Throwable $exception ): void {
		$status      = $this->getStatus();
		$failureData = array(
			'timestamp'            => time(),
			'microtime'            => microtime( true ),
			'operation_name'       => $operationName,
			'exception_type'       => get_class( $exception ),
			'exception_message'    => $exception->getMessage(),
			'exception_code'       => $exception->getCode(),
			'exception_file'       => $exception->getFile(),
			'exception_line'       => $exception->getLine(),
			'stack_trace'          => $exception->getTraceAsString(),
			'circuit_state_before' => $status->state,
			'failure_count_before' => $status->failCount,
		);

		if ( $exception->getPrevious() ) {
				$p                                 = $exception->getPrevious();
				$failureData['previous_exception'] = array(
					'type'    => get_class( $p ),
					'message' => $p->getMessage(),
					'code'    => $p->getCode(),
				);
		}

		$this->addFailureMetadata( $failureData );
		$this->logger->warning(
			'Circuit breaker failure recorded',
			array(
				'circuit_breaker'   => $this->name,
				'operation'         => $operationName,
				'exception_type'    => $failureData['exception_type'],
				'exception_message' => $failureData['exception_message'],
				'failure_count'     => $status->failCount + 1,
				'threshold'         => $this->threshold,
				'current_state'     => $status->state,
			)
		);

		$this->persistFailureMetadata( $failureData );

		$sanitized_message = $this->sanitizeMessage( $exception->getMessage() );
		$this->recordFailure( $sanitized_message );

		if ( $this->getStatus()->state === 'open' ) {
				$this->logger->error(
					'Circuit breaker opened due to failure threshold',
					array(
						'circuit_breaker'  => $this->name,
						'failure_count'    => $this->getStatus()->failCount,
						'threshold'        => $this->threshold,
						'recovery_timeout' => $this->cooldown,
					)
				);
		}
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

	public function execute( callable $operation, ...$args ) {
			$this->guard( $this->name );
		try {
				$result = $operation( ...$args );
				$this->success( $this->name );
				return $result;
		} catch ( \Throwable $e ) {
				$this->failure( $this->name, $e );
				throw $e;
		}
	}

	private function addFailureMetadata( array $data ): void {
			$this->failureMetadata[] = $data;
		if ( count( $this->failureMetadata ) > $this->maxFailureMetadataEntries ) {
				$this->failureMetadata = array_slice( $this->failureMetadata, -$this->maxFailureMetadataEntries );
		}
	}

	private function persistFailureMetadata( array $data ): void {
		if ( function_exists( 'set_transient' ) ) {
				$day  = defined( 'DAY_IN_SECONDS' ) ? DAY_IN_SECONDS : 86400;
				$week = defined( 'WEEK_IN_SECONDS' ) ? WEEK_IN_SECONDS : 604800;
				$key  = 'smartalloc_circuit_failure_' . $this->name . '_' . $data['timestamp'];
				set_transient( $key, $data, $day );
				$summary_key = 'smartalloc_circuit_summary_' . $this->name;
				$summary     = get_transient( $summary_key );
			if ( ! is_array( $summary ) ) {
				$summary = array(
					'total_failures' => 0,
					'last_failure'   => null,
					'failure_types'  => array(),
				);
			}
				++$summary['total_failures'];
				$summary['last_failure']        = $data['timestamp'];
				$t                              = $data['exception_type'];
				$summary['failure_types'][ $t ] = ( $summary['failure_types'][ $t ] ?? 0 ) + 1;
				set_transient( $summary_key, $summary, $week );
		}
	}

	public function getFailureMetadata( ?int $limit = null ): array {
		if ( $limit === null ) {
				return $this->failureMetadata;
		}

			return array_slice( $this->failureMetadata, -$limit );
	}

	public function getFailureSummary(): ?array {
		if ( function_exists( 'get_transient' ) ) {
				$s = get_transient( 'smartalloc_circuit_summary_' . $this->name );
				return $s !== false ? $s : null;
		}

			return null;
	}

	public function clearFailureMetadata( bool $includePersistent = false ): void {
			$this->failureMetadata = array();
		if ( $includePersistent && function_exists( 'delete_transient' ) ) {
				delete_transient( 'smartalloc_circuit_summary_' . $this->name );
		}
	}

        public function getStatistics(): array {
                        $status = $this->getStatus();
                        $stats  = array(
                                'name'                   => $this->name,
				'state'                  => $status->state,
				'failure_count'          => $status->failCount,
				'failure_threshold'      => $this->threshold,
				'recovery_timeout'       => $this->cooldown,
				'last_failure_time'      => $status->cooldownUntil,
				'total_metadata_entries' => count( $this->failureMetadata ),
			);

                        if ( $this->failureMetadata ) {
                                        $stats['recent_failures'] = $this->getFailureMetadata( 5 );
                        }

			$summary = $this->getFailureSummary();
			if ( $summary !== null ) {
					$stats['persistent_summary'] = $summary;
			}

			return $stats;
	}
}
