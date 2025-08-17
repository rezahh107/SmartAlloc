<?php

namespace SmartAlloc\Tests;

use PHPUnit\Framework\TestCase;
use SmartAlloc\Services\Db;

class DbBulkInsertTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        $this->db = new Db();
    }

    public function testBulkInsert(): void
    {
        $table = 'test_table';
        $rows = [
            ['name' => 'Test 1', 'value' => 100],
            ['name' => 'Test 2', 'value' => 200],
            ['name' => 'Test 3', 'value' => 300]
        ];
        
        $result = $this->db->bulkInsert($table, $rows);
        
        $this->assertTrue($result);
    }

    public function testTableExistenceCheck(): void
    {
        $existingTable = 'wp_posts'; // WordPress core table
        $nonExistingTable = 'non_existing_table';
        
        $this->assertTrue($this->db->tableExists($existingTable));
        $this->assertFalse($this->db->tableExists($nonExistingTable));
    }

    public function testTableStructure(): void
    {
        $table = 'wp_posts';
        $structure = $this->db->getTableStructure($table);
        
        $this->assertIsArray($structure);
        $this->assertNotEmpty($structure);
    }

    public function testQueryBuilder(): void
    {
        $queryBuilder = $this->db->queryBuilder();
        
        $this->assertInstanceOf(\SmartAlloc\Services\QueryBuilder::class, $queryBuilder);
    }

    public function testQueryBuilderSelect(): void
    {
        $queryBuilder = $this->db->queryBuilder();
        
        $query = $queryBuilder
            ->select('*')
            ->from('wp_posts')
            ->where('post_status', '=', 'publish')
            ->orderBy('post_date', 'DESC')
            ->limit(10)
            ->build();
        
        $this->assertStringContainsString('SELECT *', $query);
        $this->assertStringContainsString('FROM wp_posts', $query);
        $this->assertStringContainsString('WHERE post_status =', $query);
        $this->assertStringContainsString('ORDER BY post_date DESC', $query);
        $this->assertStringContainsString('LIMIT 10', $query);
    }

    public function testRawQuery(): void
    {
        $sql = "SELECT COUNT(*) as count FROM wp_posts WHERE post_status = 'publish'";
        $result = $this->db->rawQuery($sql);
        
        $this->assertIsArray($result);
    }

    public function testLastInsertId(): void
    {
        $id = $this->db->getLastInsertId();
        
        $this->assertIsInt($id);
    }

    public function testAffectedRows(): void
    {
        $rows = $this->db->getAffectedRows();
        
        $this->assertIsInt($rows);
        $this->assertGreaterThanOrEqual(0, $rows);
    }

    public function testConnectionStatus(): void
    {
        $isConnected = $this->db->isConnected();
        
        $this->assertIsBool($isConnected);
    }
} 