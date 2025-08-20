<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

use SmartAlloc\Contracts\{EventStoreInterface, LoggerInterface};
use SmartAlloc\Event\EventBus;
use SmartAlloc\Event\EventKey;

final class DedupeKeyTest extends TestCase {
    public function test_duplicate_event_is_ignored_or_skip(): void {
        if (getenv('RUN_DEDUPE_TESTS') !== '1') {
            $this->markTestSkipped('dedupe tests opt-in');
        }
        if (!class_exists(EventBus::class) || !class_exists(EventKey::class)) {
            $this->markTestSkipped('EventBus or EventKey missing');
        }

        $store = new class implements EventStoreInterface {
            public array $events = [];
            public int $lastInsert = -1;
            public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int {
                if (isset($this->events[$dedupeKey])) {
                    $this->lastInsert = 0;
                    return 0;
                }
                $this->events[$dedupeKey] = $payload;
                $this->lastInsert = 1;
                return 1;
            }
            public function startListenerRun(int $eventId, string $listener): int { return 1; }
            public function finishListenerRun(int $listenerRunId, string $status, ?string $error = null): void {}
            public function finishEvent(int $eventId, string $status, ?string $error, int $duration): void {}
        };

        $logger = new class implements LoggerInterface {
            public function info(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
            public function debug(string $message, array $context = []): void {}
        };

        $bus = new EventBus($logger, $store);
        $payload = ['entry_id' => 1];
        $bus->dispatch('MentorAssigned', $payload);
        $this->assertSame(1, $store->lastInsert);
        $this->assertCount(1, $store->events);

        $bus->dispatch('MentorAssigned', $payload);
        $this->assertSame(0, $store->lastInsert);
        $this->assertCount(1, $store->events, 'duplicate should be ignored');
    }
}
