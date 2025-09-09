<?php
// phpcs:ignoreFile

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use SmartAlloc\Bootstrap;
use SmartAlloc\Infra\GF\SabtSubmissionHandler;
use SmartAlloc\Infra\GF\IdempotencyGuard;

final class HookBootstrap {

    /**
     * Register form-specific Gravity Forms hooks
     */
    public function register(): void {
        add_action( 'gform_after_submission_150', array( $this, 'handleForm150' ), 10, 2 );
    }

    /**
     * Handle form 150 submission with idempotency guard
     *
     * @param array $entry
     * @param array $form
     */
    public function handleForm150( $entry, $form ): void {
        $guard   = Bootstrap::container()->get( IdempotencyGuard::class );
        $entryId = (int) $entry['id'];

        if ( $guard->hasProcessed( 150, $entryId ) ) {
            return;
        }

        $guard->markProcessed( 150, $entryId );
        SabtSubmissionHandler::handle( $entry, $form );
    }
}
