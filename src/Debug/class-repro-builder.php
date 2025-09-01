<?php
/**
 * Reproduction case builder for debugging SmartAlloc issues.
 *
 * Generates test cases and blueprints for reproducing allocation problems
 * in isolated environments. Complies with WordPress coding standards.
 *
 * @package SmartAlloc\Debug
 * @since 2.0.0
 */

declare(strict_types=1);

namespace SmartAlloc\Debug;

use SmartAlloc\Debug\RedactionAdapter as Redaction_Adapter;
use SmartAlloc\Infra\Metrics\MetricsCollector as Metrics_Collector;
use InvalidArgumentException;

/**
 * Builds reproduction cases for debugging allocation issues.
 *
 * Creates isolated test environments with sanitized data for debugging
 * complex allocation scenarios without exposing sensitive information.
 *
 * @since 2.0.0
 */
final class Repro_Builder {

	/**
	 * Redaction adapter for sanitizing sensitive data.
	 *
	 * @since 2.0.0
	 * @var Redaction_Adapter
	 */
	private Redaction_Adapter $redactor;

	/**
	 * Metrics collector for performance tracking.
	 *
	 * @since 2.0.0
	 * @var Metrics_Collector
	 */
	private Metrics_Collector $metrics;

	/**
	 * Test directory path for generated cases.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private string $test_dir;

	/**
	 * Blueprint directory path for e2e scenarios.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private string $bp_dir;

	/**
	 * WordPress filesystem instance.
	 *
	 * @since 2.0.0
	 * @var \WP_Filesystem_Base|null
	 */
	private $filesystem;

	/**
	 * Constructor.
	 *
	 * Initializes the reproduction builder with optional dependencies.
	 * Sets up WordPress filesystem API for secure file operations.
	 *
	 * @since 2.0.0
	 *
	 * @param Redaction_Adapter|null $redactor Optional redaction adapter.
	 * @param Metrics_Collector|null $metrics  Optional metrics collector.
	 * @param string|null            $test_dir Optional test directory path.
	 * @param string|null            $bp_dir   Optional blueprint directory path.
	 */
	public function __construct(
		?Redaction_Adapter $redactor = null,
		?Metrics_Collector $metrics = null,
		?string $test_dir = null,
		?string $bp_dir = null
	) {
		$this->redactor = $redactor ?? new Redaction_Adapter();
		$this->metrics  = $metrics ?? new Metrics_Collector();
		$this->test_dir = $test_dir ?? $this->get_default_test_dir();
		$this->bp_dir   = $bp_dir ?? $this->get_default_bp_dir();

		$this->init_filesystem();
	}

	/**
	 * Initialize WordPress filesystem API.
	 *
	 * @since 2.0.0
	 *
	 * @throws InvalidArgumentException If filesystem cannot be initialized.
	 */
	private function init_filesystem(): void {
		global $wp_filesystem;

		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( empty( $wp_filesystem ) ) {
			throw new InvalidArgumentException( 'WordPress filesystem could not be initialized' );
		}

		$this->filesystem = $wp_filesystem;
	}

	/**
	 * Get default test directory path.
	 *
	 * @since 2.0.0
	 *
	 * @return string Normalized test directory path.
	 */
	private function get_default_test_dir(): string {
		$path = dirname( __DIR__, 1 ) . '/../tests/Debug/Repro';
		return wp_normalize_path( $path );
	}

	/**
	 * Get default blueprint directory path.
	 *
	 * @since 2.0.0
	 *
	 * @return string Normalized blueprint directory path.
	 */
	private function get_default_bp_dir(): string {
		$path = dirname( __DIR__, 1 ) . '/../e2e/blueprints';
		return wp_normalize_path( $path );
	}

	/**
	 * Create reproduction test case.
	 *
	 * Generates a complete test case with sanitized data and blueprint
	 * for reproducing allocation issues in isolated environment.
	 *
	 * @since 2.0.0
	 *
	 * @param array $context Allocation context data to reproduce.
	 * @return bool True on success, false on failure.
	 */
	public function create_test_case( array $context ): bool {
		if ( method_exists( $this->metrics, 'start_timer' ) ) {
			$this->metrics->start_timer( 'repro_creation' );
		}

		try {
			if ( ! $this->ensure_directory( $this->test_dir ) ) {
				return false;
			}

			$sanitized_context = $this->redactor->redact( $context );

			$test_file = $this->test_dir . '/test_case_' . time() . '.json';
			$test_data = wp_json_encode( $sanitized_context, JSON_PRETTY_PRINT );

			if ( false === $test_data ) {
				return false;
			}

			$success = $this->filesystem->put_contents(
				$test_file,
				$test_data . "\n",
				FS_CHMOD_FILE
			);

			if ( $success ) {
				$this->create_blueprint( $sanitized_context );
			}

			return (bool) $success;

		} finally {
			if ( method_exists( $this->metrics, 'stop_timer' ) ) {
				$this->metrics->stop_timer( 'repro_creation' );
			}
		}
	}

	/**
	 * Create e2e test blueprint.
	 *
	 * @since 2.0.0
	 *
	 * @param array $context Sanitized context data.
	 * @return bool True on success, false on failure.
	 */
	private function create_blueprint( array $context ): bool {
		if ( ! $this->ensure_directory( $this->bp_dir ) ) {
			return false;
		}

		$blueprint = array(
			'version'     => '1.0',
			'description' => 'Auto-generated reproduction blueprint',
			'context'     => $context,
			'timestamp'   => current_time( 'mysql' ),
		);

		$bp_file = $this->bp_dir . '/repro_' . time() . '.json';
		$bp_data = wp_json_encode( $blueprint, JSON_PRETTY_PRINT );

		if ( false === $bp_data ) {
			return false;
		}

		return (bool) $this->filesystem->put_contents(
			$bp_file,
			$bp_data . "\n",
			FS_CHMOD_FILE
		);
	}

	/**
	 * Ensure directory exists with proper permissions.
	 *
	 * @since 2.0.0
	 *
	 * @param string $dir Directory path to create.
	 * @return bool True if directory exists or was created successfully.
	 */
	private function ensure_directory( string $dir ): bool {
		if ( $this->filesystem->is_dir( $dir ) ) {
			return true;
		}

		return $this->filesystem->mkdir( $dir, FS_CHMOD_DIR );
	}

	/**
	 * Get test directory path.
	 *
	 * @since 2.0.0
	 *
	 * @return string Test directory path.
	 */
	public function get_test_dir(): string {
		return $this->test_dir;
	}

	/**
	 * Get blueprint directory path.
	 *
	 * @since 2.0.0
	 *
	 * @return string Blueprint directory path.
	 */
	public function get_bp_dir(): string {
		return $this->bp_dir;
	}

	/**
	 * Encode data using WordPress JSON encoder.
	 *
	 * @since 2.0.0
	 *
	 * @param mixed $data Data to encode.
	 * @return string|false JSON string on success, false on failure.
	 */
	public function encode_data( $data ) {
		return wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}

	/**
	 * Clean up test files older than specified days.
	 *
	 * @since 2.0.0
	 *
	 * @param int $days Number of days to keep files.
	 * @return int Number of files cleaned up.
	 */
	public function cleanup_old_files( int $days = 7 ): int {
		$cutoff_time = time() - ( $days * DAY_IN_SECONDS );
		$cleaned     = 0;

		$test_files = $this->filesystem->dirlist( $this->test_dir );
		if ( is_array( $test_files ) ) {
			foreach ( $test_files as $file => $info ) {
				if ( 'f' === $info['type'] && $info['lastmodunix'] < $cutoff_time ) {
					if ( $this->filesystem->delete( $this->test_dir . '/' . $file ) ) {
						++$cleaned;
					}
				}
			}
		}

		$bp_files = $this->filesystem->dirlist( $this->bp_dir );
		if ( is_array( $bp_files ) ) {
			foreach ( $bp_files as $file => $info ) {
				if ( 'f' === $info['type'] && $info['lastmodunix'] < $cutoff_time ) {
					if ( $this->filesystem->delete( $this->bp_dir . '/' . $file ) ) {
						++$cleaned;
					}
				}
			}
		}

		return $cleaned;
	}
}
