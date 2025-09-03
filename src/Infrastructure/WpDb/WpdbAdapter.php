<?php

declare(strict_types=1);

namespace SmartAlloc\Infrastructure\WpDb;

use SmartAlloc\Infrastructure\Contracts\DbProxy;

final class WpdbAdapter implements DbProxy
{
    /** @param \wpdb|object $wpdb */
    public function __construct(private $wpdb) {}

    public static function fromGlobals(): self
    {
        /** @var \wpdb|object $wpdb */
        $wpdb = $GLOBALS['wpdb'];
        return new self($wpdb);
    }

    public function getPrefix(): string
    {
        return $this->wpdb->prefix;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): void
    {
        $this->wpdb->insert($table, $data); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /** @param array<int|string,mixed> $params */
    public function getResults(string $sql, array $params = []): array
    {
        $prepared = $this->wpdb->prepare($sql, $params);
        return $this->wpdb->get_results($prepared, ARRAY_A) ?: [];
    }

    /** @param array<int|string,mixed> $params */
    public function getRow(string $sql, array $params = []): ?array
    {
        $prepared = $this->wpdb->prepare($sql, $params);
        return $this->wpdb->get_row($prepared, ARRAY_A) ?: null;
    }

    /** @param array<string,mixed> $where */
    public function delete(string $table, array $where): void
    {
        $this->wpdb->delete($table, $where);
    }

    /** @param array<int|string,mixed> $params */
    public function getVar(string $sql, array $params = []): ?string
    {
        $prepared = $this->wpdb->prepare($sql, $params);
        $var = $this->wpdb->get_var($prepared);
        return $var !== null ? (string) $var : null;
    }
}

