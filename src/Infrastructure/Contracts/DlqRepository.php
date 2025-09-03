<?php

declare(strict_types=1);

namespace SmartAlloc\Infrastructure\Contracts;

interface DlqRepository {
    /**
     * @param array<string,mixed> $payload
     */
    public function insert(string $topic, array $payload, \DateTimeImmutable $createdAtUtc): void;

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listRecent(int $limit): array;

    /**
     * @return array<string,mixed>|null
     */
    public function get(int $id): ?array;

    public function delete(int $id): void;

    public function count(): int;
}
