<?php

declare(strict_types=1);

namespace SmartAlloc\Notifications;

interface MailerInterface
{
    /**
     * Send mail with retry capability.
     *
     * @param array{to:string,subject:string,message:string,headers?:array,attachments?:array} $payload
     * @param int $attempt Current attempt number
     *
     * @return bool True if sent or retry scheduled, false on final failure
     */
    public function send(array $payload, int $attempt = 1): bool;

    /**
     * Register WordPress hooks.
     */
    public function register(): void;
}
