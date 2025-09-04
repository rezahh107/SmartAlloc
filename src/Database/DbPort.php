<?php
namespace SmartAlloc\Database;

/**
 * Database abstraction.
 */
interface DbPort {
/**
 * Execute a SQL query.
 *
 * @param string $sql Prepared SQL with placeholders.
 * @param mixed ...$args Parameters for the placeholders.
 * @return mixed Query result.
 */
public function exec( string $sql, mixed ...$args );

/**
 * Get ID of last inserted row.
 */
public function insert_id(): int;
}
