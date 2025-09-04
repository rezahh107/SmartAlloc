<?php
declare(strict_types=1);

namespace SmartAlloc\Tests\Unit\Infrastructure\WpDb;

use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SmartAlloc\Infrastructure\Contracts\DbProxy;
use SmartAlloc\Infrastructure\WpDb\WpDlqRepository;

final class WpDlqRepositoryTest extends TestCase
{
    public function test_insert_handles_database_exception(): void
    {
        $db = $this->createMock(DbProxy::class);
        $db->method('insert')->willThrowException(new Exception('DB Error'));
        $repo = new WpDlqRepository($db, 't');
        $this->assertFalse($repo->insert('topic', [], new DateTimeImmutable()));
    }

    public function test_listRecent_returns_empty_array_on_exception(): void
    {
        $db = $this->createMock(DbProxy::class);
        $db->method('getResults')->willThrowException(new Exception('Query failed'));
        $repo = new WpDlqRepository($db, 't');
        $this->assertSame([], $repo->listRecent(10));
    }

    public function test_docblocks_are_present(): void
    {
        $reflection = new ReflectionClass(WpDlqRepository::class);
        foreach (['insert', 'listRecent', 'get', 'delete', 'count'] as $method) {
            $doc = $reflection->getMethod($method)->getDocComment();
            $this->assertNotFalse($doc, "Method {$method} missing DocBlock");
        }
    }
}

