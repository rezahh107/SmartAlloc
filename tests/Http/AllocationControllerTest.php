<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\EventStoreInterface;
use SmartAlloc\Services\Logging;
use SmartAlloc\Tests\BaseTestCase;
use SmartAlloc\Http\Rest\AllocationController;
use SmartAlloc\Services\AllocationService;

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
        public function get_error_data(): array { return $this->data; }
        public function get_error_message(): string { return $this->message; }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private string $body = '';
        public function set_body(string $body): void { $this->body = $body; }
        public function get_body(): string { return $this->body; }
    }
}

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public function __construct(private array $data = [], private int $status = 200) {}
        public function get_data(): array { return $this->data; }
        public function get_status(): int { return $this->status; }
    }
}

final class AllocationControllerTest extends BaseTestCase
{
    private EventBus $eventBus;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        $eventStore = new class implements EventStoreInterface {
            public function insertEventIfNotExists(string $event, string $dedupeKey, array $payload): int { return 1; }
            public function startListenerRun(int $eventLogId, string $listener): int { return 1; }
            public function finishListenerRun(int $listenerRunId, string $status, ?string $error): void {}
            public function finishEvent(int $eventLogId, string $status, ?string $error, int $durationMs): void {}
        };
        $this->eventBus = new EventBus(new Logging(), $eventStore);
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testAllocateRequiresCapability(): void
    {
        Functions\expect('current_user_can')->once()->with(SMARTALLOC_CAP)->andReturn(false);

        $allocator = new class extends AllocationService {
            public function __construct() {}
        };
        $controller = new AllocationController($allocator, $this->eventBus, new Logging());
        $request = new WP_REST_Request();
        $request->set_body('{}');

        $response = $controller->handle($request);
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame(403, $response->get_error_data()['status']);
    }

    public function testAllocateInvalidPayloadReturns400(): void
    {
        Functions\expect('current_user_can')->andReturn(true);

        $allocator = new class extends AllocationService {
            public function __construct() {}
        };
        $controller = new AllocationController($allocator, $this->eventBus, new Logging());
        $request = new WP_REST_Request();
        $request->set_body('{"student": {"id": -1}}');

        $response = $controller->handle($request);
        $this->assertInstanceOf(WP_Error::class, $response);
        $this->assertSame(400, $response->get_error_data()['status']);
    }

    public function testAllocateValidPayloadReturns201(): void
    {
        Functions\expect('current_user_can')->andReturn(true);

        $allocator = new class extends AllocationService {
            public int $called = 0;
            public function __construct() {}
            public function assign(array $student): array
            {
                $this->called++;
                return ['mentor_id' => 1001, 'committed' => true];
            }
        };

        $controller = new AllocationController($allocator, $this->eventBus, new Logging());
        $request = new WP_REST_Request();
        $request->set_body(json_encode([
            'student' => [
                'id' => 1,
                'gender' => 'M',
                'group_code' => 'EX',
                'center' => 1,
                'schools' => [9000]
            ]
        ]));

        $response = $controller->handle($request);
        $this->assertInstanceOf(WP_REST_Response::class, $response);
        $this->assertSame(201, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['ok']);
        $this->assertSame(['mentor_id' => 1001, 'committed' => true], $data['result']);
        $this->assertSame(1, $allocator->called);
    }
}
