<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase, WordPress.Files.FileName.InvalidClassFileName
/**
 * Debug screen for SmartAlloc monitoring.
 *
 * @package SmartAlloc\Admin
 */

declare(strict_types=1);

namespace SmartAlloc\Admin;

use SmartAlloc\Health\HealthReporter;

/**
 * Displays SmartAlloc diagnostic information.
 */
final class DebugScreen {

		/**
		 * Health reporter instance.
		 *
		 * @var HealthReporter
		 */
	private HealthReporter $health_reporter;

		/**
		 * Constructor.
		 *
		 * @param HealthReporter $health_reporter Health reporter.
		 */
	public function __construct( HealthReporter $health_reporter ) {
			$this->health_reporter = $health_reporter;
	}

		/**
		 * Register admin hooks.
		 */
	public function register_hooks(): void {
			add_action( 'admin_menu', array( $this, 'add_debug_menu' ) );
	}

		/**
		 * Add debug submenu.
		 */
	public function add_debug_menu(): void {
			add_submenu_page(
				'tools.php',
				'SmartAlloc Debug',
				'SmartAlloc Debug',
				'manage_options',
				'smartalloc-debug',
				array( $this, 'render' )
			);
	}

		/**
		 * Render the debug screen.
		 */
	public function render(): void {
			echo '<div class="wrap">';
			echo '<h1>SmartAlloc Debug Information</h1>';

			$this->render_system_info();
			$this->render_allocation_stats();
			$this->render_circuit_breaker_status();

			echo '</div>';
			$this->render_javascript();
	}

		/**
		 * Render system information section.
		 */
	private function render_system_info(): void {
			echo '<div class="smartalloc-debug-section">';
			echo '<h2>System Information</h2>';
			echo '<table class="widefat">';
			echo '<tr><td><strong>PHP Version:</strong></td><td>' . esc_html( PHP_VERSION ) . '</td></tr>';
			echo '<tr><td><strong>WordPress Version:</strong></td><td>' . esc_html( get_bloginfo( 'version' ) ) . '</td></tr>';
			echo '<tr><td><strong>SmartAlloc Version:</strong></td><td>1.0.0</td></tr>';
			echo '</table>';
			echo '</div>';
	}

		/**
		 * Render allocation statistics section.
		 */
	private function render_allocation_stats(): void {
			echo '<div class="smartalloc-debug-section">';
			echo '<h2>Allocation Statistics</h2>';
			echo '<table class="widefat">';
			echo '<tr><td><strong>Total Allocations:</strong></td><td>0</td></tr>';
			echo '<tr><td><strong>Active Allocations:</strong></td><td>0</td></tr>';
			echo '<tr><td><strong>Failed Allocations:</strong></td><td>0</td></tr>';
			echo '</table>';
			echo '</div>';
	}

		/**
		 * Render circuit breaker status section.
		 */
	public function render_circuit_breaker_status(): void {
		try {
			$health_status = $this->health_reporter->get_health_status();
			$data          = $health_status['data'];

			echo '<div class="smartalloc-debug-section smartalloc-circuit-status">';
			echo '<h2>Circuit Breaker Status</h2>';

			if ( ! $health_status['success'] ) {
				echo '<div class="notice notice-error"><p>Failed to retrieve circuit breaker status</p></div>';
				echo '</div>';
				return;
			}

			$status_class = 'status-' . $data['status'];
			$status_text  = ucfirst( $data['status'] );
			$state_text   = ucfirst( str_replace( '_', ' ', $data['circuit_state'] ) );

			echo '<div class="circuit-info">';

			echo '<div class="status-indicator ' . esc_attr( $status_class ) . '">';
			echo '<span class="status-dot"></span>';
			echo '<span class="status-text">' . esc_html( $status_text ) . '</span>';
			echo '</div>';

			echo '<table class="widefat circuit-details">';
			echo '<tr><td><strong>State:</strong></td><td>' . esc_html( $state_text ) . '</td></tr>';
			echo '<tr><td><strong>Failure Count:</strong></td><td>' . esc_html( $data['failure_count'] ) . '</td></tr>';

			if ( $data['last_failure'] ) {
								$last_failure_formatted = gmdate( 'Y-m-d H:i:s', $data['last_failure'] );
				echo '<tr><td><strong>Last Failure:</strong></td><td>' . esc_html( $last_failure_formatted ) . '</td></tr>';
			} else {
				echo '<tr><td><strong>Last Failure:</strong></td><td>None</td></tr>';
			}

			if ( $data['next_retry'] ) {
				$cooldown_seconds = $data['next_retry'] - time();
				if ( $cooldown_seconds > 0 ) {
					echo '<tr><td><strong>Cooldown:</strong></td><td>';
					echo '<span id="cooldown-timer" data-target="' . esc_attr( $data['next_retry'] ) . '">';
					echo esc_html( $this->format_countdown( $cooldown_seconds ) );
					echo '</span>';
					echo '</td></tr>';
				} else {
					echo '<tr><td><strong>Cooldown:</strong></td><td>Ready for retry</td></tr>';
				}
			} else {
				echo '<tr><td><strong>Cooldown:</strong></td><td>N/A</td></tr>';
			}

			echo '</table>';
			echo '</div>';
			echo '</div>';
		} catch ( \Exception $e ) {
			echo '<div class="smartalloc-debug-section">';
			echo '<h2>Circuit Breaker Status</h2>';
			echo '<div class="notice notice-error"><p>Error: ' . esc_html( $e->getMessage() ) . '</p></div>';
			echo '</div>';
		}
	}

		/**
		 * Format countdown string.
		 *
		 * @param int $seconds Seconds remaining.
		 * @return string
		 */
	private function format_countdown( int $seconds ): string {
		if ( $seconds <= 0 ) {
			return 'Ready';
		}

		$minutes           = (int) floor( $seconds / 60 );
		$remaining_seconds = $seconds % 60;

		if ( $minutes > 0 ) {
			return sprintf( '%dm %ds', $minutes, $remaining_seconds );
		}

		return sprintf( '%ds', $remaining_seconds );
	}

		/**
		 * Render inline styles and scripts.
		 */
	private function render_javascript(): void {
		?>
		<style>
		.smartalloc-debug-section {
			margin: 20px 0;
			background: #fff;
			border: 1px solid #ccd0d4;
			box-shadow: 0 1px 1px rgba(0,0,0,.04);
		}
		.smartalloc-debug-section h2 {
			margin: 0;
			padding: 8px 12px;
			background: #f1f1f1;
			border-bottom: 1px solid #ccd0d4;
		}
		.circuit-info {
			padding: 15px;
		}
		.status-indicator {
			display: flex;
			align-items: center;
			margin-bottom: 15px;
			font-weight: bold;
		}
		.status-dot {
			width: 12px;
			height: 12px;
			border-radius: 50%;
			margin-right: 8px;
		}
		.status-healthy .status-dot { background-color: #46b450; }
		.status-degraded .status-dot { background-color: #ffb900; }
		.status-down .status-dot { background-color: #dc3232; }
		.status-healthy .status-text { color: #46b450; }
		.status-degraded .status-text { color: #ffb900; }
		.status-down .status-text { color: #dc3232; }
		.circuit-details td {
			padding: 8px 12px;
		}
		#cooldown-timer {
			font-family: monospace;
			font-weight: bold;
		}
		</style>
		<script>
		jQuery(document).ready(function($) {
			function updateCountdown() {
				const timer = $('#cooldown-timer');
				if (timer.length && timer.data('target')) {
					const target = parseInt(timer.data('target'));
					const now = Math.floor(Date.now() / 1000);
					const remaining = target - now;

					if (remaining <= 0) {
						timer.text('Ready for retry');
						timer.css('color', '#46b450');
					} else {
						const minutes = Math.floor(remaining / 60);
						const seconds = remaining % 60;
						if (minutes > 0) {
							timer.text(minutes + 'm ' + seconds + 's');
						} else {
							timer.text(seconds + 's');
						}
					}
				}
			}
			updateCountdown();
			setInterval(updateCountdown, 1000);
			setTimeout(function() {
				location.reload();
			}, 30000);
		});
		</script>
		<?php
	}
}

