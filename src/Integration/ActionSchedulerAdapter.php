<?php

declare(strict_types=1);

namespace SmartAlloc\Integration;

use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\LoggerInterface;

/**
 * Action Scheduler integration for SmartAlloc
 * Provides unified async processing through EventBus
 */
final class ActionSchedulerAdapter
{
    private const HOOK_NAME = 'smartalloc_process_async_event';
    private const MAX_RETRIES = 3;
    private const DEFAULT_TIMEOUT = 300; // 5 minutes

    public function __construct(
        private EventBus $eventBus,
        private LoggerInterface $logger
    ) {}

    /**
     * Register the action scheduler integration
     */
    public function register(): void
    {
        // Register the unified handler
        add_action(self::HOOK_NAME, [$this, 'processAsyncEvent'], 10, 3);
        add_action( 'smartalloc_notify_mail', function ( $p ) {
            \SmartAlloc\Bootstrap::container()->get( \SmartAlloc\Services\NotificationService::class )->sendMail( (array) $p );
        }, 10, 1 );
        
        // Register with Action Scheduler if available
        if (class_exists('\ActionScheduler')) {
            $this->registerWithActionScheduler();
        } else {
            $this->registerWithWpCron();
        }

        $this->logger->info('action_scheduler.registered', [
            'hook' => self::HOOK_NAME,
            'provider' => class_exists('\ActionScheduler') ? 'action_scheduler' : 'wp_cron'
        ]);
    }

    public function enqueue( string $hook, array $args = [], int $delaySec = 0 ): void {
        if ( class_exists( 'ActionScheduler' ) ) {
            $delaySec > 0
                ? as_schedule_single_action( time() + $delaySec, $hook, $args, 'smartalloc' )
                : as_enqueue_async_action( $hook, $args, 'smartalloc' );
        } else {
            wp_schedule_single_event( time() + max( 1, $delaySec ), $hook, $args );
        }
    }

    /**
     * Register with Action Scheduler
     */
    private function registerWithActionScheduler(): void
    {
        // Action Scheduler is available, use it for better performance
        $this->logger->info('action_scheduler.using_action_scheduler');
    }

    /**
     * Register with WP-Cron as fallback
     */
    private function registerWithWpCron(): void
    {
        // WP-Cron fallback
        $this->logger->info('action_scheduler.using_wp_cron');
    }

    /**
     * Schedule an async event
     */
    public function schedule(string $eventName, array $payload, int $delay = 0, int $priority = 10): bool
    {
        try {
            $args = [
                'event_name' => $eventName,
                'payload' => $payload,
                'priority' => $priority,
                'timestamp' => current_time('mysql')
            ];

            if (class_exists('\ActionScheduler')) {
                return $this->scheduleWithActionScheduler($args, $delay);
            } else {
                return $this->scheduleWithWpCron($args, $delay);
            }

        } catch (\Throwable $e) {
            $this->logger->error('action_scheduler.schedule_failed', [
                'event_name' => $eventName,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Schedule with Action Scheduler
     */
    private function scheduleWithActionScheduler(array $args, int $delay): bool
    {
        try {
            $hook = self::HOOK_NAME;
            $args = [$args];
            $group = 'smartalloc';
            
            if ($delay > 0) {
                $timestamp = time() + $delay;
                \ActionScheduler::schedule_single_action($timestamp, $hook, $args, $group);
            } else {
                \ActionScheduler::schedule_single_action(time(), $hook, $args, $group);
            }

            $this->logger->debug('action_scheduler.scheduled_action_scheduler', [
                'hook' => $hook,
                'args' => $args,
                'delay' => $delay
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->logger->error('action_scheduler.action_scheduler_failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Schedule with WP-Cron
     */
    private function scheduleWithWpCron(array $args, int $delay): bool
    {
        try {
            $hook = self::HOOK_NAME;
            $timestamp = time() + $delay;
            
            wp_schedule_single_event($timestamp, $hook, [$args]);

            $this->logger->debug('action_scheduler.scheduled_wp_cron', [
                'hook' => $hook,
                'args' => $args,
                'delay' => $delay
            ]);

            return true;

        } catch (\Throwable $e) {
            $this->logger->error('action_scheduler.wp_cron_failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Process async event (unified handler)
     */
    public function processAsyncEvent(array $args): void
    {
        try {
            $eventName = $args['event_name'] ?? 'unknown';
            $payload = $args['payload'] ?? [];
            $priority = $args['priority'] ?? 10;
            $timestamp = $args['timestamp'] ?? current_time('mysql');

            $this->logger->info('action_scheduler.processing', [
                'event_name' => $eventName,
                'priority' => $priority,
                'timestamp' => $timestamp
            ]);

            // Set timeout for this event
            $timeout = apply_filters('smartalloc_async_timeout', self::DEFAULT_TIMEOUT, $eventName);
            set_time_limit($timeout);

            // Dispatch through EventBus
            $this->eventBus->dispatch($eventName, $payload);

            $this->logger->info('action_scheduler.completed', [
                'event_name' => $eventName,
                'duration' => $this->calculateDuration($timestamp)
            ]);

        } catch (\Throwable $e) {
            $this->logger->error('action_scheduler.processing_failed', [
                'event_name' => $args['event_name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-schedule for retry if possible
            $this->handleRetry($args);
        }
    }

    /**
     * Handle retry logic for failed events
     */
    private function handleRetry(array $args): void
    {
        $retryCount = $args['retry_count'] ?? 0;
        
        if ($retryCount < self::MAX_RETRIES) {
            $args['retry_count'] = $retryCount + 1;
            $delay = pow(2, $retryCount) * 60; // Exponential backoff: 1, 2, 4 minutes
            
            $this->logger->info('action_scheduler.retrying', [
                'event_name' => $args['event_name'] ?? 'unknown',
                'retry_count' => $args['retry_count'],
                'delay' => $delay
            ]);

            $this->schedule($args['event_name'], $args['payload'], $delay, $args['priority'] ?? 10);
        } else {
            $this->logger->error('action_scheduler.max_retries_exceeded', [
                'event_name' => $args['event_name'] ?? 'unknown',
                'retry_count' => $retryCount
            ]);
        }
    }

    /**
     * Calculate duration since event was scheduled
     */
    private function calculateDuration(string $timestamp): float
    {
        $scheduled = strtotime($timestamp);
        $now = current_time('timestamp');
        
        return $now - $scheduled;
    }

    /**
     * Get Action Scheduler status
     */
    public function getStatus(): array
    {
        $status = [
            'provider' => class_exists('\ActionScheduler') ? 'action_scheduler' : 'wp_cron',
            'hook_name' => self::HOOK_NAME,
            'max_retries' => self::MAX_RETRIES,
            'default_timeout' => self::DEFAULT_TIMEOUT
        ];

        if (class_exists('\ActionScheduler')) {
            $status['action_scheduler_available'] = true;
            $status['pending_actions'] = $this->getPendingActionCount();
        } else {
            $status['action_scheduler_available'] = false;
            $status['wp_cron_events'] = $this->getWpCronEvents();
        }

        return $status;
    }

    /**
     * Get pending Action Scheduler actions count
     */
    private function getPendingActionCount(): int
    {
        try {
            if (class_exists('\ActionScheduler_Store')) {
                return \ActionScheduler_Store::instance()->count_actions([
                    'hook' => self::HOOK_NAME,
                    'status' => 'pending'
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('action_scheduler.count_failed', ['error' => $e->getMessage()]);
        }
        
        return 0;
    }

    /**
     * Get WP-Cron events
     */
    private function getWpCronEvents(): array
    {
        try {
            $crons = _get_cron_array();
            $events = [];
            
            foreach ($crons as $timestamp => $cron) {
                if (isset($cron[self::HOOK_NAME])) {
                    $events[] = [
                        'timestamp' => $timestamp,
                        'count' => count($cron[self::HOOK_NAME])
                    ];
                }
            }
            
            return $events;
        } catch (\Throwable $e) {
            $this->logger->error('action_scheduler.wp_cron_events_failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Clear all scheduled events
     */
    public function clearAll(): bool
    {
        try {
            if (class_exists('\ActionScheduler')) {
                return $this->clearActionSchedulerEvents();
            } else {
                return $this->clearWpCronEvents();
            }
        } catch (\Throwable $e) {
            $this->logger->error('action_scheduler.clear_failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Clear Action Scheduler events
     */
    private function clearActionSchedulerEvents(): bool
    {
        try {
            if (class_exists('\ActionScheduler_Store')) {
                $store = \ActionScheduler_Store::instance();
                $actions = $store->query_actions([
                    'hook' => self::HOOK_NAME,
                    'per_page' => 1000
                ]);
                
                foreach ($actions as $actionId) {
                    $store->delete_action($actionId);
                }
                
                $this->logger->info('action_scheduler.cleared_action_scheduler', ['count' => count($actions)]);
                return true;
            }
        } catch (\Throwable $e) {
            $this->logger->error('action_scheduler.clear_action_scheduler_failed', ['error' => $e->getMessage()]);
        }
        
        return false;
    }

    /**
     * Clear WP-Cron events
     */
    private function clearWpCronEvents(): bool
    {
        try {
            $crons = _get_cron_array();
            $cleared = 0;
            
            foreach ($crons as $timestamp => $cron) {
                if (isset($cron[self::HOOK_NAME])) {
                    // Use public API instead of manipulating cron array directly
                    foreach ($cron[self::HOOK_NAME] as $key => $event) {
                        wp_unschedule_event($timestamp, self::HOOK_NAME, $event['args']);
                        $cleared++;
                    }
                }
            }
            
            $this->logger->info('action_scheduler.cleared_wp_cron', ['count' => $cleared]);
            return true;
            
        } catch (\Throwable $e) {
            $this->logger->error('action_scheduler.clear_wp_cron_failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
} 