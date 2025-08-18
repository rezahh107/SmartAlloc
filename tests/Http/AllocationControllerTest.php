<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use SmartAlloc\Http\Rest\AllocationController;
use SmartAlloc\Domain\Allocation\StudentAllocator;
use SmartAlloc\Domain\Allocation\AllocationResult;
use SmartAlloc\Contracts\LoggerInterface;

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
        public function get_error_data(): array { return $this->data; }
        public function get_error_message(): string { return $this->message; }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private string $body = '';
        public function set_body(string $body): void { $this->body = $body; }
        public function get_body(): string { return $this->body; }
    }
}

final class AllocationControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * @dataProvider requestProvider
     */
    public function testHandle(string $body, bool $expectError, int $calls): void
    {
        Functions\expect('current_user_can')->andReturn(true);

        $allocator = new class extends StudentAllocator {
            public int $called = 0;
            public function __construct() {}
            public function allocate(array $studentData): AllocationResult
            {
                $this->called++;
                return new AllocationResult([
                    'allocated' => true,
                    'student_id' => $studentData['id'],
                ]);
            }
        };

        $logger = new class implements LoggerInterface {
            public function debug(string $message, array $context = []): void {}
            public function info(string $message, array $context = []): void {}
            public function warning(string $message, array $context = []): void {}
            public function error(string $message, array $context = []): void {}
        };

        $controller = new AllocationController($allocator, $logger);
        $request    = new WP_REST_Request();
        $request->set_body($body);

        $result = $controller->handle($request);
        $this->assertSame($calls, $allocator->called);

        if ($expectError) {
            $this->assertInstanceOf(WP_Error::class, $result);
            $this->assertSame(400, $result->get_error_data()['status']);
        } else {
            $this->assertIsArray($result);
            $this->assertSame(['allocated' => true, 'student_id' => 1], $result);
        }
    }

    public function requestProvider(): array
    {
        return [
            'invalid json' => ['{', true, 0],
            'missing fields' => [json_encode(['id' => 1]), true, 0],
            'sql injection' => [json_encode(['id' => '1; DROP TABLE', 'name' => 'Bob', 'role' => 'student']), true, 0],
            'valid payload' => [json_encode(['id' => 1, 'name' => 'Bob', 'role' => 'student']), false, 1],
        ];
    }
}
