<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use SmartAlloc\Bootstrap;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Domain\Allocation\AllocationStatus;
use SmartAlloc\Infra\Repository\AllocationsRepository;
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
        private AllocationsRepository $repository
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
            $c->get(AllocationsRepository::class)
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

        // Idempotency check
        $existing = $this->repository->findByEntryId($entryId);
        if ($existing) {
            do_action('smartalloc/event', 'AllocationProcessed', [
                'entry_id' => $entryId,
                'status' => $existing['status'],
                'mentor_id' => $existing['mentor_id'],
            ]);
            return;
        }

        $map = $this->mapper->mapEntry($entry);
        if (!($map['ok'] ?? false)) {
            $this->repository->save($entryId, AllocationStatus::REJECT, null, null, $map['code'] ?? 'unknown');

            $this->logger->warning('sabt.reject', ['entry_id' => $entryId]);
            do_action('smartalloc/event', 'AllocationProcessed', [
                'entry_id' => $entryId,
                'status' => AllocationStatus::REJECT,
                'mentor_id' => null,
            ]);
            return;
        }

        $student = $map['student'];
        $student['id'] = $entryId;

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
        $status = AllocationStatus::REJECT;
        $candidates = null;
        $reason = null;

        if (($result['committed'] ?? false) && $score >= 0.90) {
            $status = AllocationStatus::AUTO;
        } elseif ($score >= 0.80 && $score < 0.90) {
            $status = AllocationStatus::MANUAL;
            if (!empty($result['candidates']) && is_array($result['candidates'])) {
                $candidates = array_slice($result['candidates'], 0, 5);
            }
            $mentorId = null;
        } else {
            $status = AllocationStatus::REJECT;
            if (!empty($result['reason'])) {
                $reason = (string) $result['reason'];
            }
            $mentorId = null;
        }

        $this->repository->save($entryId, $status, $mentorId, $candidates, $reason);

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
