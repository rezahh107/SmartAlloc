<?php
declare(strict_types=1);

use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\{EventStoreInterface,ListenerInterface,LoggerInterface};

final class EventBusTest extends BaseTestCase
{
    public function testDuplicateDispatchAndFailureLogging(): void
    {
        $store = new class implements EventStoreInterface {
            public array $events = [];
            public array $runs = [];
            public int $nextId = 1;
            public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int {
                if (isset($this->events[$dedupeKey])) { return 0; }
                $id = $this->nextId++;
                $this->events[$dedupeKey] = $id;
                return $id;
            }
            public function startListenerRun(int $eventLogId, string $listener): int {
                $this->runs[] = ['listener' => $listener];
                return count($this->runs);
            }
            public function finishListenerRun(int $listenerRunId, string $status, ?string $error, int $durationMs): void {
                $this->runs[$listenerRunId-1]['status'] = $status;
            }
            public function finishEvent(int $eventLogId, string $status, ?string $error, int $durationMs): void {}
        };
        $logger = new class implements LoggerInterface {
            public array $errors = [];
            public function debug(string $m, array $c = []): void {}
            public function info(string $m, array $c = []): void {}
            public function warning(string $m, array $c = []): void {}
            public function error(string $m, array $c = []): void { $this->errors[] = $m; }
        };
        $bus = new EventBus($logger, $store);
        $bus->on('Evt', new class implements ListenerInterface {
            public function handle(string $event, array $payload): void { throw new RuntimeException('boom'); }
        });
        $listener = new class implements ListenerInterface {
            public bool $ran = false;
            public function handle(string $event, array $payload): void { $this->ran = true; }
        };
        $bus->on('Evt', $listener);
        $bus->dispatch('Evt', ['entry_id' => 5]);
        $this->assertTrue($listener->ran);
        $this->assertSame('failed', $store->runs[0]['status']);
        $bus->dispatch('Evt', ['entry_id' => 5]);
        $this->assertCount(2, $store->runs); // no new runs on duplicate
    }
}
