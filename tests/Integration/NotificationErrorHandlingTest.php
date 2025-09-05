<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\{NotificationService, CircuitBreaker, Logging, Metrics, DlqService};
use SmartAlloc\Infrastructure\Contracts\DlqRepository;
use SmartAlloc\Exceptions\RepositoryException;

final class NotificationErrorHandlingTest extends TestCase
{
    public function test_notification_service_handles_dlq_repository_errors(): void
    {
        $repo = $this->createMock(DlqRepository::class);
        $repo->method('insert')->willThrowException(
            new RepositoryException('DB error', 'dlq_push')
        );

        $dlq     = new DlqService($repo);
        $service = new NotificationService(new CircuitBreaker(), new Logging(), new Metrics(), null, $dlq);

        $GLOBALS['filters']['smartalloc_notify_transport'] = fn() => false;
        $this->expectException(RepositoryException::class);
        try {
            $service->handle([
                'event_name' => 'email',
                'body'       => ['message' => 'test'],
                '_attempt'   => 5,
            ]);
        } finally {
            unset($GLOBALS['filters']['smartalloc_notify_transport']);
        }
    }
}
