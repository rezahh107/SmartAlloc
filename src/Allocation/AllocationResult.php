<?php

declare(strict_types=1);

namespace SmartAlloc\Allocation;

final class AllocationResult {
    /**
     * @var array<int,string>
     */
    private array $status = [];

    /**
     * @var array<int,string>
     */
    private array $errors = [];

    public function add(int $entry_id, string $status): void {
        $this->status[$entry_id] = $status;
    }

    public function add_error(int $entry_id, string $error): void {
        $this->errors[$entry_id] = $error;
    }

    public function has(int $entry_id): bool {
        return isset($this->status[$entry_id]);
    }

    /**
     * @return array{status:array<int,string>,errors:array<int,string>}
     */
    public function to_array(): array {
        return [
            'status' => $this->status,
            'errors' => $this->errors,
        ];
    }
}
