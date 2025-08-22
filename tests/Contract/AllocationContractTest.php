<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\AllocationService;
use SmartAlloc\Services\Logging;
use SmartAlloc\Services\Metrics;
use SmartAlloc\Services\ScoringAllocator;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\EventStoreInterface;
use SmartAlloc\Domain\Allocation\AllocationResult;
use SmartAlloc\Services\ErrorResponse;
require_once __DIR__.'/../Helpers/AssertArrayShape.php';
use SmartAlloc\Tests\Helpers\AssertArrayShape;

if (!class_exists('WP_Error')) {
    class WP_Error {
        public function __construct(public string $code = '', public string $message = '', public array $data = []) {}
        public function get_error_data(): array { return $this->data; }
        public function get_error_code(): string { return $this->code; }
        public function get_error_message(): string { return $this->message; }
    }
}

if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public int $rows_affected = 0;
        public array $mentors = [
            2 => ['mentor_id'=>2,'gender'=>'M','center'=>1,'group_code'=>'G1','capacity'=>2,'assigned'=>1,'active'=>1],
        ];
        public function get_results($sql,$mode){ return array_values($this->mentors); }
        public function query(string $sql){ $this->rows_affected = 1; }
        public function insert(string $t, array $d){}
        public function prepare(string $sql, ...$args): string {
            $params = is_array($args[0] ?? null) ? $args[0] : $args;
            foreach ($params as $p) {
                $sql = preg_replace('/%d/', (string) (int) $p, $sql, 1);
                $sql = preg_replace('/%s/', "'" . $p . "'", $sql, 1);
                $sql = preg_replace('/%f/', (string) (float) $p, $sql, 1);
            }
            return $sql;
        }
    }
}

final class AllocationContractTest extends TestCase
{
    use AssertArrayShape;

    private function makeService(): AllocationService
    {
        $wpdb = new wpdb();
        $GLOBALS['wpdb'] = $wpdb;

        $logger = new Logging();
        $eventStore = new class implements EventStoreInterface {
            public array $events = [];
            public function insertEventIfNotExists(string $e,string $k,array $p): int { $this->events[]=$e; return count($this->events); }
            public function startListenerRun(int $e,string $l): int {return 1;}
            public function finishListenerRun(int $i,string $s,?string $er,int $d): void {}
            public function finishEvent(int $i,string $s,?string $e,int $d): void {}
        };
        $bus = new EventBus($logger,$eventStore);
        $metrics = new Metrics();
        return new AllocationService($logger,$bus,$metrics,new ScoringAllocator(),$wpdb);
    }

    public static function allocationInputProvider(): array
    {
        return [
            'valid fixture' => [include __DIR__.'/../fixtures/allocation/valid.php', true],
            'missing field' => [include __DIR__.'/../fixtures/allocation/invalid_missing.php', false],
            'extra field' => [include __DIR__.'/../fixtures/allocation/invalid_extra.php', false],
            'invalid gender' => [[ 'student_id'=>1,'center_id'=>1,'gender'=>'X'], false],
        ];
    }

    /** @dataProvider allocationInputProvider */
    public function test_allocation_input_contract(array $payload, bool $ok): void
    {
        $service = $this->makeService();
        $ref = new ReflectionClass($service);
        $method = $ref->getMethod('validateInput');
        $method->setAccessible(true);
        if (!$ok) {
            $this->expectException(InvalidArgumentException::class);
        }
        $normalized = $method->invoke($service,$payload);
        if ($ok) {
            self::assertSame('M', $normalized['gender']);
            self::assertIsInt($normalized['student_id']);
            self::assertIsInt($normalized['center_id']);
        }
    }

    public function test_allocation_output_contract(): void
    {
        $service = $this->makeService();
        $payload = include __DIR__.'/../fixtures/allocation/valid.php';
        $result = $service->assign($payload);
        $this->assertInstanceOf(AllocationResult::class,$result);
        $data = $result->to_array();
        self::assertArrayShape([
            'mentor_id' => 'int',
            'committed' => 'bool',
            'metadata' => 'array',
        ], $data);
        $this->assertGreaterThan(0,$data['mentor_id']);
        $this->assertTrue($data['committed']);
        self::assertArrayShape([
            'score'=>'float',
            'selected_strategy'=>'string',
            'tie_breaker'=>'string',
        ], $data['metadata']);

        // no capacity path
        $GLOBALS['wpdb']->mentors = []; // empty mentors
        $result2 = $service->assign($payload);
        $data2 = $result2->to_array();
        self::assertArrayShape([
            'mentor_id' => 'int',
            'committed' => 'bool',
            'metadata' => 'array',
        ], $data2);
        $this->assertSame(0,$data2['mentor_id']);
        $this->assertFalse($data2['committed']);
    }

    public function test_error_response_contract(): void
    {
        $err = new WP_Error('no_capacity','No mentors',['student_id'=>99]);
        $arr = ErrorResponse::from($err);
        self::assertArrayShape([
            'error'=>'array',
        ], $arr);
        self::assertArrayShape([
            'code'=>'string',
            'message'=>'string',
            'details'=>'array',
        ], $arr['error']);
        $this->assertSame('NO_CAPACITY',$arr['error']['code']);
        $this->assertSame('***',$arr['error']['details']['student_id']);

        $ex = new InvalidArgumentException('bad');
        $arr2 = ErrorResponse::from($ex);
        $this->assertSame('INVALID_INPUT',$arr2['error']['code']);

        $ex2 = new RuntimeException('boom');
        $arr3 = ErrorResponse::from($ex2);
        $this->assertSame('INTERNAL_ERROR',$arr3['error']['code']);
    }
}
