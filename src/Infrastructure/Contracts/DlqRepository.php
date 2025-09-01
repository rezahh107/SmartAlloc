<?php

declare(strict_types=1);

namespace SmartAlloc\Infrastructure\Contracts;

interface DlqRepository {
    /**
     * @param array<string,mixed> $payload
     */
    public function insert(string $topic, array $payload, \DateTimeImmutable $createdAtUtc): void;
}
