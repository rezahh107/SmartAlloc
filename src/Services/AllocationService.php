<?php

declare(strict_types=1);

namespace SmartAlloc\Services;

use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;
use SmartAlloc\Services\DbSafe;
use SmartAlloc\Services\Exceptions\DuplicateAllocationException;
use SmartAlloc\Services\Exceptions\InsufficientCapacityException;
use SmartAlloc\Services\Exceptions\InvalidFormContextException;

final class AllocationService
{
    public function __construct(private TableResolver $tables) {}

    /**
     * Legacy entrypoint targeting form 150.
     *
     * @param array<string,mixed> $payload
     * @return array{summary:array, allocations:array<int,array>}
     */
    public function allocate(array $payload): array
    {
        return $this->allocateWithContext(new FormContext(150), $payload);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array{summary:array, allocations:array<int,array>}
     *
     * @throws InsufficientCapacityException
     * @throws DuplicateAllocationException
     * @throws InvalidFormContextException
     */
    public function allocateWithContext(FormContext $ctx, array $payload): array
    {
        if ($ctx->formId <= 0) {
            throw new InvalidFormContextException('invalid form id');
        }

        global $wpdb;
        $table = $this->tables->allocations($ctx);

        $studentId  = isset($payload['student_id']) ? (int) $payload['student_id'] : 0;
        $email      = isset($payload['email']) ? sanitize_email((string) $payload['email']) : '';
        $mobile     = isset($payload['mobile']) ? sanitize_text_field((string) $payload['mobile']) : '';
        $nationalId = isset($payload['national_id']) ? sanitize_text_field((string) $payload['national_id']) : '';

        $dupSql = DbSafe::mustPrepare(
            "SELECT COUNT(1) FROM {$table} WHERE student_id = %d OR email = %s",
            [$studentId, $email]
        );
        $exists = (int) $wpdb->get_var($dupSql);
        if ($exists > 0) {
            $this->log('duplicate', $ctx, $email, $mobile, $nationalId);
            throw new DuplicateAllocationException('duplicate allocation');
        }

        $cntSql = DbSafe::mustPrepare("SELECT COUNT(1) FROM {$table}", []);
        $count = (int) $wpdb->get_var($cntSql);
        if ($count >= 60) {
            $this->log('capacity', $ctx, $email, $mobile, $nationalId);
            throw new InsufficientCapacityException('capacity exceeded');
        }

        $now = current_time('mysql', 1);
        $insertSql = DbSafe::mustPrepare(
            "INSERT INTO {$table} (student_id,email,mobile,national_id,created_at) VALUES (%d,%s,%s,%s,%s)",
            [$studentId, $email, $mobile, $nationalId, $now]
        );
        $wpdb->query($insertSql);

        $this->log('success', $ctx, $email, $mobile, $nationalId);
        if (function_exists('do_action')) {
            do_action('smartalloc_metric', 'allocation_created', ['form_id' => $ctx->formId]);
        }

        return [
            'summary'     => ['form_id' => $ctx->formId, 'count' => 1],
            'allocations' => [['student_id' => $studentId]],
        ];
    }

    private function mask(string $v): string
    {
        if ($v === '') {
            return '';
        }
        return substr($v, 0, 1) . '***';
    }

    private function log(string $outcome, FormContext $ctx, string $email, string $mobile, string $nationalId): void
    {
        $log = [
            'event'       => 'allocation',
            'form_id'     => $ctx->formId,
            'outcome'     => $outcome,
            'ts'          => current_time('mysql', 1),
            'email'       => $this->mask($email),
            'mobile'      => $this->mask($mobile),
            'national_id' => $this->mask($nationalId),
        ];
        if (function_exists('error_log')) {
            error_log(json_encode($log));
        }
    }
}
