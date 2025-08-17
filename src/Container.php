<?php

declare(strict_types=1);

namespace SmartAlloc;

/**
 * Simple Dependency Injection Container
 * PSR-11 compatible with lazy loading support
 */
final class Container
{
    /** @var array<string, callable> */
    private array $definitions = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Register a service definition
     */
    public function set(string $id, callable $factory): void
    {
        $this->definitions[$id] = $factory;
    }

    /**
     * Get a service instance
     */
    public function get(string $id): mixed
    {
        if (!isset($this->instances[$id])) {
            if (!isset($this->definitions[$id])) {
                throw new \RuntimeException("Service not found: $id");
            }
            $this->instances[$id] = ($this->definitions[$id])();
        }
        return $this->instances[$id];
    }

    /**
     * Check if a service is registered
     */
    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->definitions[$id]);
    }
} 