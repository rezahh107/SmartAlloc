<?php

declare(strict_types=1);

namespace SmartAlloc\Listeners;

use SmartAlloc\Contracts\ListenerInterface;
use SmartAlloc\Container;

/**
 * Export listener
 */
final class ExportListener implements ListenerInterface
{
    public function __construct(private Container $container) {}

    public function handle(string $event, array $payload): void
    {
        if ($event !== 'AllocationCommitted') {
            return;
        }
        
        $rows = $payload['rows'] ?? [];
        if (empty($rows)) {
            return;
        }
        
        $exportService = $this->container->get(\SmartAlloc\Services\ExportService::class);
        $exportService->exportSabt($rows);
    }
} 