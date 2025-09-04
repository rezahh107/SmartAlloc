<?php
namespace SmartAlloc\Notifications;

use SmartAlloc\Database\DbPort;
use SmartAlloc\Logging\Logger;
use SmartAlloc\Perf\Stopwatch;
use SmartAlloc\Notifications\Exceptions\DlqRepositoryException;

final class DlqRepository implements DlqRepositoryInterface {
private const QUERY_BUDGET_MS = 100.0;

public function __construct( private DbPort $db, private Logger $logger ) {}

public function create( string $event_name, string $payload ): int {
$args = [
$event_name,
$payload,
0,
current_time( 'mysql', true ),
current_time( 'mysql', true ),
];
try {
$perf = Stopwatch::measure(
fn() => $this->db->exec(
'INSERT INTO {prefix}smartalloc_dlq (event_name, payload, attempts, created_at, updated_at) VALUES (%s, %s, %d, %s, %s)',
...$args
)
);
$level = $perf->durationMs > self::QUERY_BUDGET_MS ? 'warn' : 'info';
$this->logger->$level( 'DlqRepository::create', [ 'duration_ms' => $perf->durationMs, 'args' => [ 'event_name' => $event_name ] ] );
return $perf->result ? $this->db->insert_id() : 0;
} catch ( \Throwable $e ) {
$this->logger->error( 'DlqRepository::create failed', [ 'method' => __METHOD__, 'arguments' => [ 'event_name' => $event_name ], 'exception' => $e->getMessage() ] );
throw new DlqRepositoryException( 'Unable to insert DLQ record', 0, $e );
}
}

public function list( int $limit = 10, int $offset = 0 ): array {
try {
$perf = Stopwatch::measure(
fn() => $this->db->exec(
'SELECT * FROM {prefix}smartalloc_dlq WHERE attempts < %d ORDER BY created_at ASC LIMIT %d OFFSET %d',
3,
$limit,
$offset
)
);
$level = $perf->durationMs > self::QUERY_BUDGET_MS ? 'warn' : 'info';
$rows = is_array( $perf->result ) ? $perf->result : [];
$this->logger->$level( 'DlqRepository::list', [ 'duration_ms' => $perf->durationMs, 'limit' => $limit, 'offset' => $offset, 'count' => count( $rows ) ] );
return $rows;
} catch ( \Throwable $e ) {
$this->logger->error( 'DlqRepository::list failed', [ 'method' => __METHOD__, 'arguments' => [ 'limit' => $limit, 'offset' => $offset ], 'exception' => $e->getMessage() ] );
throw new DlqRepositoryException( 'Unable to fetch DLQ records', 0, $e );
}
}

public function update_attempts( int $id, int $attempts ): bool {
$args = [ $attempts, current_time( 'mysql', true ), $id ];
try {
$perf = Stopwatch::measure(
fn() => $this->db->exec(
'UPDATE {prefix}smartalloc_dlq SET attempts = %d, updated_at = %s WHERE id = %d',
...$args
)
);
$level = $perf->durationMs > self::QUERY_BUDGET_MS ? 'warn' : 'info';
$this->logger->$level( 'DlqRepository::update_attempts', [ 'duration_ms' => $perf->durationMs, 'id' => $id, 'attempts' => $attempts ] );
return (bool) $perf->result;
} catch ( \Throwable $e ) {
$this->logger->error( 'DlqRepository::update_attempts failed', [ 'method' => __METHOD__, 'arguments' => [ 'id' => $id, 'attempts' => $attempts ], 'exception' => $e->getMessage() ] );
throw new DlqRepositoryException( 'Unable to update attempts', 0, $e );
}
}

public function delete( int $id ): bool {
try {
$perf = Stopwatch::measure(
fn() => $this->db->exec(
'DELETE FROM {prefix}smartalloc_dlq WHERE id = %d',
$id
)
);
$level = $perf->durationMs > self::QUERY_BUDGET_MS ? 'warn' : 'info';
$this->logger->$level( 'DlqRepository::delete', [ 'duration_ms' => $perf->durationMs, 'id' => $id ] );
return (bool) $perf->result;
} catch ( \Throwable $e ) {
$this->logger->error( 'DlqRepository::delete failed', [ 'method' => __METHOD__, 'arguments' => [ 'id' => $id ], 'exception' => $e->getMessage() ] );
throw new DlqRepositoryException( 'Unable to delete DLQ record', 0, $e );
}
}
}
