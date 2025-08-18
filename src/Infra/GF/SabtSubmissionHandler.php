<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use SmartAlloc\Bootstrap;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Infra\Settings\Settings;
use SmartAlloc\Services\AllocationService;

/**
 * Handle Sabt form submissions from Gravity Forms.
 */
final class SabtSubmissionHandler
{
    public function __construct(
        private SabtEntryMapper $mapper,
        private AllocationService $allocator,
        private LoggerInterface $logger,
        private \wpdb $wpdb
    ) {
    }

    /**
     * WordPress hook entrypoint.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $form
     */
    public static function handle(array $entry, array $form): void
    {
        $c = Bootstrap::container();
        $handler = new self(
            new SabtEntryMapper(),
            $c->get(AllocationService::class),
            $c->get(LoggerInterface::class),
            $GLOBALS['wpdb']
        );
        $handler->process($entry, $form);
    }

    /**
     * Process a Sabt entry.
     *
     * @param array<string,mixed> $entry
     * @param array<string,mixed> $form
     */
    public function process(array $entry, array $form): void
    {
        $entryId = absint($entry['id'] ?? 0);
        if ($entryId <= 0) {
            return;
        }

        $table = $this->wpdb->prefix . 'smartalloc_allocations';

        // Idempotency check
        $existing = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT status, mentor_id FROM {$table} WHERE entry_id = %d", $entryId),
            'ARRAY_A'
        );
        if ($existing) {
            do_action('smartalloc/event', 'AllocationProcessed', [
                'entry_id' => $entryId,
                'status' => $existing['status'],
                'mentor_id' => $existing['mentor_id'] ? (int) $existing['mentor_id'] : null,
            ]);
            return;
        }

        $map = $this->mapper->mapEntry($entry);
        if (!($map['ok'] ?? false)) {
            $this->wpdb->insert($table, [
                'entry_id' => $entryId,
                'student_hash' => hash('sha256', wp_json_encode($entry), true),
                'status' => 'reject',
                'mentor_id' => null,
                'candidates' => wp_json_encode(['reason' => $map['code'] ?? 'unknown']),
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);

            $this->logger->warning('sabt.reject', ['entry_id' => $entryId]);
            do_action('smartalloc/event', 'AllocationProcessed', [
                'entry_id' => $entryId,
                'status' => 'reject',
                'mentor_id' => null,
            ]);
            return;
        }

        $student = $map['student'];
        $student['id'] = $entryId;
        $studentHash = hash('sha256', wp_json_encode($student), true);

        $mode = Settings::getAllocationMode();
        $result = [];

        try {
            if ($mode === 'rest') {
                $response = wp_remote_post(
                    rest_url('smartalloc/v1/allocate'),
                    [
                        'headers' => ['Content-Type' => 'application/json'],
                        'body' => wp_json_encode(['student' => $student]),
                        'timeout' => 5,
                    ]
                );
                if (is_wp_error($response)) {
                    throw new \RuntimeException($response->get_error_message());
                }
                $result = json_decode((string) ($response['body'] ?? ''), true)['result'] ?? [];
            } else {
                $result = $this->allocator->assign($student);
            }
        } catch (\Throwable $e) {
            $this->logger->error('sabt.allocate_error', [
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $score = (float) ($result['school_match_score'] ?? 0);
        $mentorId = isset($result['mentor_id']) ? (int) $result['mentor_id'] : null;
        $status = 'reject';
        $candidatesJson = null;

        if (($result['committed'] ?? false) && $score >= 0.90) {
            $status = 'auto';
        } elseif ($score >= 0.80 && $score < 0.90) {
            $status = 'manual';
            if (!empty($result['candidates']) && is_array($result['candidates'])) {
                $candidatesJson = wp_json_encode(array_slice($result['candidates'], 0, 5));
            }
            $mentorId = null;
        } else {
            $status = 'reject';
            if (!empty($result['reason'])) {
                $candidatesJson = wp_json_encode(['reason' => $result['reason']]);
            }
            $mentorId = null;
        }

        $this->wpdb->insert($table, [
            'entry_id' => $entryId,
            'student_hash' => $studentHash,
            'status' => $status,
            'mentor_id' => $mentorId,
            'candidates' => $candidatesJson,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ]);

        $this->logger->info('sabt.processed', [
            'entry_id' => $entryId,
            'status' => $status,
        ]);

        do_action('smartalloc/event', 'AllocationProcessed', [
            'entry_id' => $entryId,
            'status' => $status,
            'mentor_id' => $mentorId,
        ]);
    }
}
