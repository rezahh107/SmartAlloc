<?php
// phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName,WordPress.Files.FileName.NotHyphenatedLowercase

/**
 * Gravity Forms hook bootstrapper.
 *
 * @package SmartAlloc\Infra\GF
 */

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use SmartAlloc\Bootstrap;
use SmartAlloc\Infra\GF\IdempotencyGuard;
use SmartAlloc\Infra\GF\SabtSubmissionHandler;

/**
 * Registers Gravity Forms hooks for specific forms.
 */
final class HookBootstrap {
	/**
	 * Register form-specific Gravity Forms hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'gform_after_submission_150', array( $this, 'handle_form_150' ), 10, 2 );
	}

	/**
	 * Handle form 150 submission with idempotency guard.
	 *
	 * @param array $entry Entry data.
	 * @param array $form  Form data.
	 *
	 * @return void
	 */
	public function handle_form_150( $entry, $form ): void {
		$guard    = Bootstrap::container()->get( IdempotencyGuard::class );
		$entry_id = (int) $entry['id'];

		if ( $guard->has_processed( 150, $entry_id ) ) {
			return;
		}

		$guard->mark_processed( 150, $entry_id );
		SabtSubmissionHandler::handle( $entry, $form );
	}
}
