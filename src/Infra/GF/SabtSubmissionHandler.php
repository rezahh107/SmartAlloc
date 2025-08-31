<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\GF;

use SmartAlloc\Bootstrap;
use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Domain\Allocation\AllocationStatus;
use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Infra\Settings\Settings;
use SmartAlloc\Contracts\AllocationServiceInterface;
use SmartAlloc\Services\ServiceContainer;
use SmartAlloc\Support\{Stopwatch, PerfSamples, PerfBudget, RuleEngineResult, FailureMapper};

/**
 * Handle Sabt form submissions from Gravity Forms.
 */
final class SabtSubmissionHandler
{
    private AllocationServiceInterface $allocator;

    public function __construct(
        private SabtEntryMapper $mapper,
        ?AllocationServiceInterface $allocator,
        private LoggerInterface $logger,
        private AllocationsRepository $repository
    ) {
        $this->allocator = $allocator ?: ServiceContainer::allocation();
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
            ServiceContainer::allocation(),
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
        unset($form); // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
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
                        'body'    => wp_json_encode(['student' => $student]),
                        'timeout' => 5, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout
                    ]
                );
                if (is_wp_error($response)) {
                    throw new \RuntimeException($response->get_error_message());
                }
                $alloc = json_decode((string) ($response['body'] ?? ''), true)['result'] ?? [];
            } else {
                [$alloc, $ms] = Stopwatch::time(fn () => $this->allocator->allocate($student));
                PerfSamples::add('allocate_ms_p95', $ms);
                PerfBudget::enforce('allocate_ms_p95');
            }
            $result = new RuleEngineResult(RuleEngineResult::OK, 'Allocation successful', ['allocation' => $alloc]);
        } catch (\Throwable $e) {
            $result = FailureMapper::from($e);
        }

        if ($result instanceof RuleEngineResult && $result->status !== RuleEngineResult::OK) {
            $this->logger->error('sabt.allocate_error', [
                'entry_id' => $entryId,
                'error'    => $result->message,
            ]);
            return;
        }

        $alloc = $result instanceof RuleEngineResult ? $result->meta['allocation'] : $result;

        $score = (float) ($alloc['school_match_score'] ?? 0);
        $mentorId = isset($alloc['mentor_id']) ? (int) $alloc['mentor_id'] : null;
        $status = AllocationStatus::REJECT;
        $candidates = null;
        $reason = null;

        $auto = Settings::getFuzzyAutoThreshold();
        $min  = Settings::getFuzzyManualMin();
        $max  = Settings::getFuzzyManualMax();

        if (($alloc['committed'] ?? false) && $score >= $auto) {
            $status = AllocationStatus::AUTO;
        } elseif ($score >= $min && $score <= $max) {
            $status = AllocationStatus::MANUAL;
            if (!empty($alloc['candidates']) && is_array($alloc['candidates'])) {
                $candidates = array_slice($alloc['candidates'], 0, 5);
            }
            $mentorId = null;
        } else {
            $status = AllocationStatus::REJECT;
            if (!empty($alloc['reason'])) {
                $reason = (string) $alloc['reason'];
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
