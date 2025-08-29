<?php

declare(strict_types=1);

namespace SmartAlloc\Bootstrap;

use SmartAlloc\Notifications\RetryingMailer;

final class NotificationsBootstrap
{
    public static function init(): void
    {
        $mailFn = static function (array $p): bool {
            return function_exists('wp_mail')
                ? (bool) wp_mail($p['to'], $p['subject'], $p['message'], $p['headers'], $p['attachments'])
                : false;
        };

        $scheduleFn = static function (int $ts, string $hook, array $args): bool {
            return function_exists('wp_schedule_single_event')
                ? (bool) wp_schedule_single_event($ts, $hook, $args)
                : false;
        };

        (new RetryingMailer($mailFn, $scheduleFn))->register();
    }
}
