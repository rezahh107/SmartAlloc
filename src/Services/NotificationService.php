<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Notification service with circuit breaker support
 */
final class NotificationService
{
    public function __construct(
        private CircuitBreaker $circuitBreaker,
        private Logging $logger
    ) {}

    /**
     * Send notification
     */
    public function send(array $payload): void
    {
        $name = 'notify';
        
        try {
            $this->circuitBreaker->guard($name);
            
            // TODO: Implement actual notification logic
            // This could be email, SMS, webhook, etc.
            
            $this->circuitBreaker->success($name);
            $this->logger->info('notify.success', ['payload' => $payload]);
        } catch (\Throwable $e) {
            $this->circuitBreaker->failure($name);
            $this->logger->error('notify.error', ['error' => $e->getMessage()]);
        }
    }
} 