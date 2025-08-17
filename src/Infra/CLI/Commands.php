<?php

declare(strict_types=1);

namespace SmartAlloc\Infra\CLI;

use SmartAlloc\Container;
use SmartAlloc\Services\{Db, ExportService, StatsService};

/**
 * WP-CLI commands for SmartAlloc
 */
final class Commands
{
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Run database migrations
     */
    public function upgrade(): void
    {
        \WP_CLI::log('Running SmartAlloc database migrations...');
        
        try {
            Db::migrate();
            \WP_CLI::success('Database migrations completed successfully.');
        } catch (\Throwable $e) {
            \WP_CLI::error('Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Export data to Excel
     */
    public function export(): void
    {
        \WP_CLI::log('Starting export...');
        
        try {
            $exportService = $this->container->get(ExportService::class);
            $filePath = $exportService->exportSabt([]);
            \WP_CLI::success("Export completed: $filePath");
        } catch (\Throwable $e) {
            \WP_CLI::error('Export failed: ' . $e->getMessage());
        }
    }

    /**
     * Set Gravity Forms ID
     */
    public function set_form($args, $assoc_args): void
    {
        $formId = (int) ($assoc_args['id'] ?? 0);
        
        if ($formId <= 0) {
            \WP_CLI::error('Please provide a valid form ID using --id=FORM_ID');
        }
        
        update_option('smartalloc_form_id', $formId);
        update_option('smartalloc_form_id_updated_at', current_time('mysql'));
        
        \WP_CLI::success("Gravity Forms ID set to: $formId");
    }

    /**
     * Rebuild daily statistics
     */
    public function rebuild_stats(): void
    {
        \WP_CLI::log('Rebuilding daily statistics...');
        
        try {
            $statsService = $this->container->get(StatsService::class);
            $statsService->rebuildDaily();
            \WP_CLI::success('Daily statistics rebuilt successfully.');
        } catch (\Throwable $e) {
            \WP_CLI::error('Statistics rebuild failed: ' . $e->getMessage());
        }
    }

    /**
     * Show system health
     */
    public function health(): void
    {
        \WP_CLI::log('Checking system health...');
        
        try {
            $healthService = $this->container->get(\SmartAlloc\Services\HealthService::class);
            $status = $healthService->status();
            
            \WP_CLI::log('Health Status:');
            \WP_CLI::log('- Database: ' . ($status['db'] ? 'OK' : 'FAILED'));
            \WP_CLI::log('- Cache: ' . ($status['cache'] ? 'OK' : 'FAILED'));
            \WP_CLI::log('- Version: ' . $status['version']);
            \WP_CLI::log('- Time: ' . $status['time']);
            
            if (!empty($status['notes'])) {
                \WP_CLI::log('- Notes: ' . implode(', ', $status['notes']));
            }
            
            if ($status['db'] && $status['cache']) {
                \WP_CLI::success('System is healthy.');
            } else {
                \WP_CLI::warning('System has issues.');
            }
        } catch (\Throwable $e) {
            \WP_CLI::error('Health check failed: ' . $e->getMessage());
        }
    }
} 