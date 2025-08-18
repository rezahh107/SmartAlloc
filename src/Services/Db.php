<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

/**
 * Database service with migrations and helper methods
 *
 * @note QueryBuilder now builds WHERE clauses using prepared statements
 *       to avoid SQL injection.
 */
class Db
{
    private \wpdb $wpdb;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Run database migrations
     */
    public static function migrate(): void
    {
        if (function_exists('current_user_can') && !current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $prefix = $wpdb->prefix;

        $sql = [];

        // Event logging tables
        $sql[] = "CREATE TABLE {$prefix}salloc_event_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            event_name VARCHAR(100) NOT NULL,
            dedup_key VARCHAR(191) NOT NULL,
            payload_json LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_dedup (dedup_key),
            INDEX(event_name),
            INDEX(created_at)
        ) $charset";

        $sql[] = "CREATE TABLE {$prefix}salloc_export_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(255) NOT NULL,
            rows_total INT UNSIGNED DEFAULT 0,
            rows_failed INT UNSIGNED DEFAULT 0,
            duration_ms INT UNSIGNED DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX(created_at)
        ) $charset";

        $sql[] = "CREATE TABLE {$prefix}salloc_export_errors (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            export_id BIGINT UNSIGNED NOT NULL,
            row_idx INT UNSIGNED NOT NULL,
            sheet VARCHAR(64) NOT NULL,
            error_code VARCHAR(64) NOT NULL,
            error_detail TEXT NULL,
            KEY idx_export (export_id)
        ) $charset";

        $sql[] = "CREATE TABLE {$prefix}salloc_circuit_breakers (
            name VARCHAR(100) NOT NULL PRIMARY KEY,
            state ENUM('closed','open','half') NOT NULL DEFAULT 'closed',
            opened_at DATETIME NULL,
            meta_json LONGTEXT NULL
        ) $charset";

        $sql[] = "CREATE TABLE {$prefix}salloc_stats_daily (
            day DATE NOT NULL PRIMARY KEY,
            exports_ok INT UNSIGNED DEFAULT 0,
            exports_fail INT UNSIGNED DEFAULT 0,
            allocations_committed INT UNSIGNED DEFAULT 0
        ) $charset";

        $sql[] = "CREATE TABLE {$prefix}smartalloc_counters (
            id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
            current_date CHAR(10) NOT NULL,
            daily_counter INT NOT NULL DEFAULT 0,
            batch_counter INT NOT NULL DEFAULT 0,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_id (id)
        ) $charset";

        // Export registry
        $dbVersion = $wpdb->get_var('SELECT VERSION()');
        $filtersType = ($dbVersion && version_compare($dbVersion, '5.7', '>=')) ? 'JSON' : 'LONGTEXT';
        $sql[] = "CREATE TABLE {$prefix}smartalloc_exports (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            path TEXT NOT NULL,
            filters {$filtersType} NULL,
            size BIGINT NOT NULL DEFAULT 0,
            checksum CHAR(64) NULL,
            created_at DATETIME NOT NULL,
            INDEX created_at (created_at)
        ) $charset";

        // Allocation results table
        $sql[] = "CREATE TABLE {$prefix}smartalloc_allocations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entry_id BIGINT UNSIGNED NOT NULL,
            student_hash VARBINARY(32) NOT NULL,
            status VARCHAR(16) NOT NULL,
            mentor_id BIGINT UNSIGNED NULL,
            candidates LONGTEXT NULL,
            reviewer_id BIGINT UNSIGNED NULL,
            review_notes TEXT NULL,
            reviewed_at DATETIME NULL,
            reason_code VARCHAR(64) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY entry_id (entry_id),
            KEY mentor_id (mentor_id),
            KEY status (status),
            KEY reviewed_at (reviewed_at),
            KEY created_at (created_at)
        ) ENGINE=InnoDB $charset";

        // Execute migrations
        foreach ($sql as $query) {
            dbDelta($query);
        }

        $table = $prefix . 'smartalloc_allocations';

        $col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'status'");
        if ($col && isset($col[0]->Type) && str_contains(strtolower((string) $col[0]->Type), 'enum')) {
            $res = $wpdb->query("ALTER TABLE {$table} MODIFY status VARCHAR(16) NOT NULL");
            if ($res === false) {
                error_log('SmartAlloc migration WARN: unable to alter status column: ' . $wpdb->last_error);
            }
        }

        $col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE 'candidates'");
        if ($col && isset($col[0]->Type)) {
            $type = strtolower((string) $col[0]->Type);
            if ($type !== 'longtext') {
                $res = $wpdb->query("ALTER TABLE {$table} MODIFY candidates LONGTEXT NULL");
                if ($res === false) {
                    error_log('SmartAlloc migration WARN: unable to alter candidates column: ' . $wpdb->last_error);
                }
            }
        }

        $columns = [
            'reviewer_id BIGINT NULL',
            'review_notes TEXT NULL',
            'reviewed_at DATETIME NULL',
            'reason_code VARCHAR(64) NULL',
        ];

        foreach ($columns as $definition) {
            [$name] = explode(' ', $definition);
            $col = $wpdb->get_results("SHOW COLUMNS FROM {$table} LIKE '{$name}'");
            if (!$col) {
                $res = $wpdb->query("ALTER TABLE {$table} ADD {$definition}");
                if ($res === false) {
                    error_log('SmartAlloc migration WARN: unable to add column ' . $name . ': ' . $wpdb->last_error);
                }
            }
        }

        $idx = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = 'reviewed_at'");
        if (!$idx) {
            $res = $wpdb->query("ALTER TABLE {$table} ADD KEY reviewed_at (reviewed_at)");
            if ($res === false) {
                error_log('SmartAlloc migration WARN: unable to add reviewed_at index: ' . $wpdb->last_error);
            }
        }
    }

    /**
     * Start a database transaction
     */
    public function startTransaction(): void
    {
        $this->wpdb->query('START TRANSACTION');
    }

    /**
     * Commit a database transaction
     */
    public function commit(): void
    {
        $this->wpdb->query('COMMIT');
    }

    /**
     * Rollback a database transaction
     */
    public function rollback(): void
    {
        $this->wpdb->query('ROLLBACK');
    }

    /**
     * Execute a query and return results
     */
    public function query(string $sql, array $params = []): array
    {
        $prepared = $params ? $this->wpdb->prepare($sql, ...$params) : $sql;
        $results = $this->wpdb->get_results($prepared, ARRAY_A);
        
        if ($results === null && $this->wpdb->last_error) {
            throw new \RuntimeException('Database query error: ' . $this->wpdb->last_error);
        }
        
        return $results ?? [];
    }

    /**
     * Execute a query and return affected rows count
     */
    public function exec(string $sql, array $params = []): int
    {
        $prepared = $params ? $this->wpdb->prepare($sql, ...$params) : $sql;
        $result = $this->wpdb->query($prepared);
        
        if ($result === false) {
            throw new \RuntimeException('Database execution error: ' . $this->wpdb->last_error);
        }
        
        return (int) $this->wpdb->rows_affected;
    }

    /**
     * Insert a record and return the insert ID
     */
    public function insert(string $table, array $data): int
    {
        $result = $this->wpdb->insert($table, $data);
        
        if ($result === false) {
            throw new \RuntimeException('Database insert error: ' . $this->wpdb->last_error);
        }
        
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Update records and return affected rows count
     */
    public function update(string $table, array $data, array $where): int
    {
        $result = $this->wpdb->update($table, $data, $where);
        
        if ($result === false) {
            throw new \RuntimeException('Database update error: ' . $this->wpdb->last_error);
        }
        
        return (int) $this->wpdb->rows_affected;
    }

    /**
     * Bulk insert multiple rows
     */
    public function bulkInsert(string $table, array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $columns = array_keys($rows[0]);
        $values = [];
        $placeholders = [];

        // Get table structure to determine proper placeholders
        $tableStructure = $this->getTableStructure($table);
        $columnTypes = [];
        
        foreach ($tableStructure as $column) {
            $columnTypes[$column['Field']] = $this->getPlaceholderForType($column['Type']);
        }

        foreach ($rows as $row) {
            $rowPlaceholders = [];
            foreach ($columns as $column) {
                $values[] = $row[$column] ?? null;
                // Use appropriate placeholder based on column type
                $rowPlaceholders[] = $columnTypes[$column] ?? '%s';
            }
            $placeholders[] = '(' . implode(', ', $rowPlaceholders) . ')';
        }

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            $table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $prepared = $this->wpdb->prepare($sql, ...$values);
        $result = $this->wpdb->query($prepared);

        if ($result === false) {
            throw new \RuntimeException('Database bulk insert error: ' . $this->wpdb->last_error);
        }

        return (int) $this->wpdb->rows_affected;
    }

    /**
     * Get appropriate placeholder for MySQL column type
     */
    private function getPlaceholderForType(string $type): string
    {
        $type = strtolower($type);
        
        if (strpos($type, 'int') !== false) {
            return '%d';
        } elseif (strpos($type, 'decimal') !== false || strpos($type, 'float') !== false || strpos($type, 'double') !== false) {
            return '%f';
        } else {
            return '%s';
        }
    }

    /**
     * Check if table exists
     */
    public function tableExists(string $table): bool
    {
        $result = $this->wpdb->get_var($this->wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));

        return $result !== null;
    }

    /**
     * Get table structure
     */
    public function getTableStructure(string $table): array
    {
        $result = $this->wpdb->get_results($this->wpdb->prepare(
            "DESCRIBE %s",
            $table
        ), 'ARRAY_A');

        return $result ?: [];
    }

    /**
     * Simple query builder for complex queries
     */
    public function queryBuilder(): QueryBuilder
    {
        return new QueryBuilder($this->wpdb);
    }

    /**
     * Execute raw SQL with error handling
     */
    public function rawQuery(string $sql): mixed
    {
        $result = $this->wpdb->query($sql);
        
        if ($result === false) {
            throw new \RuntimeException('Database raw query error: ' . $this->wpdb->last_error);
        }
        
        return $result;
    }

    /**
     * Get last insert ID
     */
    public function getLastInsertId(): int
    {
        return (int) $this->wpdb->insert_id;
    }

    /**
     * Get affected rows count
     */
    public function getAffectedRows(): int
    {
        return (int) $this->wpdb->rows_affected;
    }

    /**
     * Get last error
     */
    public function getLastError(): string
    {
        return $this->wpdb->last_error;
    }

    /**
     * Check database connection
     */
    public function isConnected(): bool
    {
        try {
            $this->query('SELECT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}

/**
 * Simple Query Builder for complex queries
 */
class QueryBuilder
{
    private \wpdb $wpdb;
    private string $table;
    private array $select = ['*'];
    private array $where = [];
    private array $params = [];
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(\wpdb $wpdb)
    {
        $this->wpdb = $wpdb;
    }

    public function from(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    public function select(array $columns): self
    {
        $this->select = $columns;
        return $this;
    }

    public function where(string $column, string $operator, $value): self
    {
        $placeholder = is_numeric($value) ? '%d' : '%s';
        $this->where[] = compact('column', 'operator', 'placeholder');
        $this->params[] = $value;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = compact('column', 'direction');
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildQuery();
        return $this->wpdb->get_results($sql, 'ARRAY_A') ?: [];
    }

    public function first(): ?array
    {
        $this->limit = 1;
        $results = $this->get();
        return $results[0] ?? null;
    }

    public function count(): int
    {
        $originalSelect = $this->select;
        $this->select = ['COUNT(*) as count'];
        
        $result = $this->first();
        $this->select = $originalSelect;
        
        return (int) ($result['count'] ?? 0);
    }

    private function buildQuery(): string
    {
        $sql = "SELECT " . implode(', ', $this->select) . " FROM {$this->table}";

        if (!empty($this->where)) {
            $sql .= " WHERE " . $this->buildWhereClause();
        }

        if (!empty($this->orderBy)) {
            $sql .= " ORDER BY " . $this->buildOrderByClause();
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        if (!empty($this->params)) {
            $sql = $this->wpdb->prepare($sql, $this->params);
        }

        return $sql;
    }

    private function buildWhereClause(): string
    {
        $clauses = [];
        foreach ($this->where as $condition) {
            $clauses[] = "{$condition['column']} {$condition['operator']} {$condition['placeholder']}";
        }
        return implode(' AND ', $clauses);
    }

    private function buildOrderByClause(): string
    {
        $clauses = [];
        foreach ($this->orderBy as $order) {
            $clauses[] = "{$order['column']} {$order['direction']}";
        }
        return implode(', ', $clauses);
    }
} 