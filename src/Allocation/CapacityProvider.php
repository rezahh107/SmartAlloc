<?php namespace SmartAlloc\Allocation;
interface CapacityProvider { public function hasCapacity(int|string $resourceId): bool; }
final class ArrayCapacityProvider implements CapacityProvider {
    public function __construct(private array $caps) {}
    public function hasCapacity(int|string $id): bool { return (int) ($this->caps[$id] ?? 0) > 0; }
}
