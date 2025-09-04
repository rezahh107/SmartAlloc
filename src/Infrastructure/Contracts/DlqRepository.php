<?php

declare(strict_types=1);

namespace SmartAlloc\Infrastructure\Contracts;

interface DlqRepository {
    /**
     * Insert a DLQ record.
     *
     * @param string                 $topic       Topic name.
     * @param array<string,mixed>    $payload     Payload data.
     * @param \DateTimeImmutable     $createdAtUtc Creation time in UTC.
     *
     * @return bool True on success, false on failure.
     */
    public function insert(string $topic, array $payload, \DateTimeImmutable $createdAtUtc): bool;

    /**
     * Retrieve recent DLQ records.
     *
     * @param int $limit Maximum number of records to fetch.
     *
     * @return array<int,array<string,mixed>> Records array.
     */
    public function listRecent(int $limit): array;

    /**
     * Retrieve a DLQ record by ID.
     *
     * @param int $id Record identifier.
     *
     * @return array<string,mixed>|null Record data or null when missing.
     */
    public function get(int $id): ?array;

    /**
     * Delete a DLQ record.
     *
     * @param int $id Record identifier.
     *
     * @return bool True on success, false on failure.
     */
    public function delete(int $id): bool;

    /**
     * Count DLQ records.
     *
     * @return int Number of records.
     */
    public function count(): int;
}
