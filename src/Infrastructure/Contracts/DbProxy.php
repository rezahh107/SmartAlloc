<?php
declare(strict_types=1);

namespace SmartAlloc\Infrastructure\Contracts;

interface DbProxy {
    public function insert(string $table, array $data): void;
    public function getResults(string $sql, array $params = []): array;
    public function getRow(string $sql, array $params = []): ?array;
    public function delete(string $table, array $where): void;
    public function getVar(string $sql, array $params = []): ?string;
    public function getPrefix(): string;
}

