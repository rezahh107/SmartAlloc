<?php

declare(strict_types=1);

namespace SmartAlloc\Listeners;

use SmartAlloc\Contracts\ListenerInterface;
use SmartAlloc\Container;

/**
 * Auto-assignment listener
 */
final class AutoAssignListener implements ListenerInterface
{
    public function __construct(private Container $container) {}

    public function handle(string $event, array $payload): void
    {
        $allocationService = $this->container->get(\SmartAlloc\Services\AllocationService::class);
        
        $student = $payload['student'] ?? $payload;
        $result = $allocationService->assign($student);
        
        if (!empty($result['committed'])) {
            do_action('smartalloc/event', 'MentorAssigned', [
                'student' => $payload,
                'mentor_id' => $result['mentor_id']
            ]);
        }
    }
} 