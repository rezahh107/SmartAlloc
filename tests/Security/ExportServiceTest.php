<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SmartAlloc\Infra\Export\ExporterService;

final class ExportServiceTest extends TestCase
{
    /**
     * @dataProvider invalidIdProvider
     */
    public function testInvalidIds(int $input): void
    {
        $wpdb = $this->mockWpdb([]);
        $service = new ExporterService($wpdb);
        $this->expectException(\InvalidArgumentException::class);
        $service->exportData($input);
    }

    /**
     * @return array<int, array{0:int}>
     */
    public function invalidIdProvider(): array
    {
        return [
            [0],
            [-1],
        ];
    }

    public function testValidIdReturnsData(): void
    {
        $wpdb = $this->mockWpdb([
            ['id' => 1, 'name' => 'test'],
        ]);

        $service = new ExporterService($wpdb);
        $result  = $service->exportData(1);

        $this->assertSame([
            ['id' => 1, 'name' => 'test'],
        ], $result);
    }

    private function mockWpdb(array $results)
    {
        return new class($results) {
            public $prefix = 'wp_';
            public function __construct(private array $results) {}
            public function prepare($query, ...$args) { return $query; }
            public function get_results($sql, $mode) { return $this->results; }
        };
    }
}
