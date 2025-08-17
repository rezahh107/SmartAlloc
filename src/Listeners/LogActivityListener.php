<?php

declare(strict_types=1);

namespace SmartAlloc\Listeners;

use SmartAlloc\Contracts\ListenerInterface;
use SmartAlloc\Container;

/**
 * Activity logging listener
 */
final class LogActivityListener implements ListenerInterface
{
    public function __construct(private Container $container) {}

    public function handle(string $event, array $payload): void
    {
        $logger = $this->container->get(\SmartAlloc\Services\Logging::class);
        $logger->info('activity', ['event' => $event, 'payload' => $payload]);
    }
} 