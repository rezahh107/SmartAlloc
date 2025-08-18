<?php

declare(strict_types=1);

namespace SmartAlloc\Http\Rest;

use SmartAlloc\Contracts\LoggerInterface;
use SmartAlloc\Domain\Allocation\StudentAllocator;
use WP_Error;
use WP_REST_Request;

/**
 * REST controller handling allocation requests.
 *
 * @phpcs:ignoreFile
 */
final class AllocationController
{
    public function __construct(
        private StudentAllocator $allocator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Register REST route for allocation.
     */
    public function register_routes(): void
    {
        add_action('rest_api_init', function (): void {
            register_rest_route(
                'smartalloc/v1',
                '/allocate',
                [
                    'methods'  => 'POST',
                    'permission_callback' => function (): bool {
                        return current_user_can('manage_smartalloc');
                    },
                    'callback' => [$this, 'handle'],
                ]
            );
        });
    }

    /**
     * Handle allocation request.
     *
     * @return array<string,mixed>|WP_Error
     */
    public function handle(WP_REST_Request $request)
    {
        $raw = $request->get_body();
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return new WP_Error('smartalloc_error', 'Invalid JSON', ['status' => 400]);
        }

        $idRaw   = $data['id'] ?? null;
        $nameRaw = $data['name'] ?? '';
        $roleRaw = $data['role'] ?? '';

        if (!is_scalar($idRaw) || !is_numeric((string) $idRaw)) {
            return new WP_Error('smartalloc_error', 'Invalid id', ['status' => 400]);
        }
        $id = absint((string) $idRaw);
        if ($id <= 0) {
            return new WP_Error('smartalloc_error', 'Invalid id', ['status' => 400]);
        }

        $name = sanitize_text_field((string) $nameRaw);
        if ($name === '') {
            return new WP_Error('smartalloc_error', 'Missing name', ['status' => 400]);
        }

        $role    = sanitize_text_field((string) $roleRaw);
        $allowed = ['student', 'teacher'];
        if (!in_array($role, $allowed, true)) {
            return new WP_Error('smartalloc_error', 'Invalid role', ['status' => 400]);
        }

        $payload = [
            'id'   => $id,
            'name' => $name,
            'role' => $role,
        ];

        try {
            $result = $this->allocator->allocate($payload)->to_array();
            $this->logger->info('allocation', [
                'request_id' => uniqid('alloc_', true),
                'count'      => 1,
            ]);
            return $result;
        } catch (\Throwable $e) {
            return new WP_Error('smartalloc_error', 'Allocation failed', ['status' => 400]);
        }
    }
}
