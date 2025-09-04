<?php
namespace SmartAlloc\Notifications;

/**
 * Interface for DLQ storage.
 */
interface DlqRepositoryInterface {
public function create( string $event_name, string $payload ): int;
public function list( int $limit = 10, int $offset = 0 ): array;
public function update_attempts( int $id, int $attempts ): bool;
public function delete( int $id ): bool;
}
