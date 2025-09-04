<?php
use PHPUnit\Framework\TestCase;
use SmartAlloc\Notifications\DlqRepository;
use SmartAlloc\Notifications\Exceptions\DlqRepositoryException;
use SmartAlloc\Logging\Logger;
use SmartAlloc\Database\DbPort;

final class DlqRepositoryTest extends TestCase {
public function test_create_returns_insert_id_and_logs(): void {
$db = new class implements DbPort {
public string $sql = '';
public array $args = [];
public function exec( string $sql, mixed ...$args ) { $this->sql = $sql; $this->args = $args; return 1; }
public function insert_id(): int { return 7; }
};
$logger = new Logger();
$repo = new DlqRepository( $db, $logger );
$id = $repo->create( 'test', '{}' );
$this->assertSame( 7, $id );
$this->assertStringContainsString( 'INSERT INTO', $db->sql );
$this->assertSame( 'info', $logger->records[0]['level'] );
}

public function test_list_with_offset(): void {
$db = new class implements DbPort {
public function exec( string $sql, mixed ...$args ) { return [ [ 'id' => 1 ], [ 'id' => 2 ] ]; }
public function insert_id(): int { return 0; }
};
$logger = new Logger();
$repo = new DlqRepository( $db, $logger );
$rows = $repo->list( 2, 5 );
$this->assertCount( 2, $rows );
$this->assertSame( 'info', $logger->records[0]['level'] );
}

public function test_create_wraps_exceptions(): void {
$db = new class implements DbPort {
public function exec( string $sql, mixed ...$args ) { throw new \RuntimeException( 'fail' ); }
public function insert_id(): int { return 0; }
};
$logger = new Logger();
$repo = new DlqRepository( $db, $logger );
$this->expectException( DlqRepositoryException::class );
try {
$repo->create( 'test', '{}' );
} finally {
$this->assertNotEmpty( $logger->records );
$this->assertSame( 'error', $logger->records[0]['level'] );
}
}
}
