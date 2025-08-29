<?php

declare(strict_types=1);

namespace SmartAlloc\Notifications;

interface MailerInterface
{
    /**
     * Sends an email payload or schedules a retry.
     *
     * @param array<string,mixed> $payload Email arguments.
     * @param int                 $attempt Current attempt number.
     *
     * @return bool True if delivered or scheduled, false on final failure.
     */
    public function send(array $payload, int $attempt = 1): bool;

    /** Registers WP-Cron hook for retries. */
    public function register(): void;
}
