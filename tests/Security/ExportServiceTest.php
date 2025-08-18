<?php

declare(strict_types=1);

use SmartAlloc\Infra\Export\ExporterService;
use SmartAlloc\Tests\BaseTestCase;

final class ExportServiceTest extends BaseTestCase
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

    public function testInvalidTypeThrowsTypeError(): void
    {
        $service = new ExporterService($this->mockWpdb([]));
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line */
        $service->exportData('abc');
    }

    public function testUsesTablePrefix(): void
    {
        $wpdb = $this->mockWpdb([]);
        $service = new ExporterService($wpdb);
        $service->exportData(1);
        $this->assertStringContainsString($wpdb->prefix . 'exports', $wpdb->lastSql);
    }

    private function mockWpdb(array $results)
    {
        return new class($results) {
            public $prefix = 'wp_';
            public string $lastSql = '';
            public function __construct(private array $results) {}
            public function prepare($query, ...$args) { $this->lastSql = $query; return $query; }
            public function get_results($sql, $mode) { $this->lastSql = $sql; return $this->results; }
        };
    }
}
