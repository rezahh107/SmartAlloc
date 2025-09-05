<?php // phpcs:ignore WordPress.Files.FileName.NotHyphenatedLowercase

/**
 * Mailer interface for SmartAlloc.
 *
 * @package SmartAlloc
 */

declare(strict_types=1);

namespace SmartAlloc\Notifications;

/**
 * Interface for email sending functionality.
 */
interface MailerInterface {
	/**
	 * Send mail with retry capability.
	 *
	 * @param array{to:string,subject:string,message:string,headers?:array,attachments?:array} $payload Payload.
	 * @param int                                                                              $attempt                                      Current attempt number.
	 *
	 * @return bool True if sent or retry scheduled, false on final failure.
	 */
	public function send( array $payload, int $attempt = 1 ): bool;

	/**
	 * Register WordPress hooks.
	 */
	public function register(): void;
}
