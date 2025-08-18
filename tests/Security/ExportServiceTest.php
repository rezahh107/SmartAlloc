<?php

use PHPUnit\Framework\TestCase;
use SmartAlloc\Infra\Export\ExporterService;

final class ExportServiceTest extends TestCase
{
    /**
     * @dataProvider invalidIdProvider
     */
    public function testInvalidIds(string $input): void
    {
        $service = new ExporterService();
        $this->expectException(\InvalidArgumentException::class);
        $service->exportData($input);
    }

    /**
     * @return array<int, array{0:string}>
     */
    public function invalidIdProvider(): array
    {
        return [
            ['1; DROP TABLE exports;'],
            ['0'],
            ['-1'],
            [''],
            ['abc'],
        ];
    }

    public function testValidIdReturnsData(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE exports (id INTEGER PRIMARY KEY, name TEXT)');
        $stmt = $pdo->prepare('INSERT INTO exports (id, name) VALUES (1, :name)');
        $stmt->execute([':name' => 'test']);

        $service = new ExporterService($pdo);
        $result = $service->exportData('1');

        $this->assertSame([
            ['id' => 1, 'name' => 'test'],
        ], $result);
    }
}

