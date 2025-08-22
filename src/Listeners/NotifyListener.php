<?php

declare(strict_types=1);

namespace SmartAlloc\Listeners;

use SmartAlloc\Contracts\ListenerInterface;
use SmartAlloc\Container;

/**
 * Notification listener
 */
final class NotifyListener implements ListenerInterface
{
    public function __construct(private Container $container) {}

    public function handle(string $event, array $payload): void
    {
        $notificationService = $this->container->get(\SmartAlloc\Services\NotificationService::class);
        $notificationService->send([
            'event_name' => $event,
            'body'       => $payload,
        ]);
    }
}