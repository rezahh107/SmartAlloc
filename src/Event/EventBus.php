<?php

declare(strict_types=1);

namespace SmartAlloc\Event;

use SmartAlloc\Contracts\{ListenerInterface, LoggerInterface, EventStoreInterface};

/**
 * Enhanced Event Bus with retry, timeout, priority, and bridge support
 */
final class EventBus
{
    /** @var array<string, array<int, ListenerInterface[]>> */
    private array $listeners = [];

    /** @var array<string, callable> */
    private array $bridges = [];

    public function __construct(
        private LoggerInterface $logger,
        private EventStoreInterface $eventStore,
        private int $maxRetries = 3,
        private int $defaultTimeout = 30
    ) {}

    /**
     * Register a listener for an event with priority
     */
    public function on(string $event, ListenerInterface $listener, int $priority = 10): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        if (!isset($this->listeners[$event][$priority])) {
            $this->listeners[$event][$priority] = [];
        }
        
        $this->listeners[$event][$priority][] = $listener;
    }

    /**
     * Register a bridge for WordPress actions
     */
    public function bridge(string $action, string $event): void
    {
        $this->bridges[$action] = $event;
        
        // Hook into WordPress action
        add_action($action, function(...$args) use ($event) {
            $this->dispatch($event, $args);
        });
    }

    /**
     * Dispatch an event to all registered listeners with retry and timeout
     */
    public function dispatch(string $event, array $payload, string $version = 'v1'): void
    {
        $key = EventKey::make($event, $payload, $version);
        $eventId = $this->eventStore->insertEventIfNotExists($event, $key, $payload);
        
        if ($eventId === 0) {
            $this->logger->info('event.duplicate', ['event' => $event, 'key' => $key]);
            return; // Deduplication hit
        }

        $this->logger->info('event.start', ['event' => $event, 'key' => $key]);
        $startTime = microtime(true);

        // Get listeners sorted by priority
        $sortedListeners = $this->getSortedListeners($event);
        
        $failed = false;
        foreach ($sortedListeners as $listener) {
            $listenerName = get_class($listener);
            $listenerRunId = $this->eventStore->startListenerRun($eventId, $listenerName);

            $lStart = microtime(true);
            try {
                $this->executeListenerWithRetry($listener, $event, $payload);
                $lDuration = (int) round((microtime(true) - $lStart) * 1000);
                $this->eventStore->finishListenerRun($listenerRunId, 'completed', null, $lDuration);
                $this->logger->info('listener.success', [
                    'event' => $event,
                    'listener' => $listenerName,
                ]);
            } catch (\Throwable $e) {
                $lDuration = (int) round((microtime(true) - $lStart) * 1000);
                $failed = true;
                $this->eventStore->finishListenerRun($listenerRunId, 'failed', $e->getMessage(), $lDuration);
                $this->logger->error('listener.error', [
                    'event' => $event,
                    'listener' => $listenerName,
                    'error' => $e->getMessage()
                ]);
                // Continue with other listeners
            }
        }

        $duration = (int) round((microtime(true) - $startTime) * 1000);
        $this->eventStore->finishEvent(
            $eventId,
            $failed ? 'failed' : 'completed',
            $failed ? 'listener_failed' : null,
            $duration
        );
        
        // Trigger WordPress action for compatibility
        do_action('smartalloc/event', $event, $payload, $version);
    }

    /**
     * Execute listener with retry logic and timeout
     */
    private function executeListenerWithRetry(
        ListenerInterface $listener,
        string $event,
        array $payload
    ): void {
        $listenerName = get_class($listener);
        $attempts = 0;
        $lastError = null;

        while ($attempts < $this->maxRetries) {
            $attempts++;

            try {
                $this->setTimeout($this->defaultTimeout);
                $listener->handle($event, $payload);
                return; // Success
            } catch (\Throwable $e) {
                $lastError = $e;

                if ($attempts < $this->maxRetries) {
                    $this->logger->warning('listener.retry', [
                        'event' => $event,
                        'listener' => $listenerName,
                        'attempt' => $attempts,
                        'max_retries' => $this->maxRetries,
                        'error' => $e->getMessage()
                    ]);

                    $waitTime = min(30, pow(2, $attempts - 1));
                    sleep($waitTime);
                }
            }
        }

        // All retries failed
        throw new \RuntimeException(
            "Listener {$listenerName} failed after {$this->maxRetries} attempts. Last error: " . $lastError->getMessage()
        );
    }

    /**
     * Get listeners sorted by priority
     */
    private function getSortedListeners(string $event): array
    {
        if (!isset($this->listeners[$event])) {
            return [];
        }
        
        $sorted = [];
        ksort($this->listeners[$event]); // Sort by priority (ascending)
        
        foreach ($this->listeners[$event] as $priority => $listeners) {
            foreach ($listeners as $listener) {
                $sorted[] = $listener;
            }
        }
        
        return $sorted;
    }

    /**
     * Set execution timeout
     */
    private function setTimeout(int $seconds): void
    {
        if (function_exists('set_time_limit')) {
            set_time_limit($seconds);
        }
        
        if (function_exists('ini_set')) {
            ini_set('max_execution_time', (string) $seconds);
        }
    }

    /**
     * Get event statistics
     */
    public function getStats(): array
    {
        $stats = [];
        
        foreach ($this->listeners as $event => $priorities) {
            $stats[$event] = [
                'total_listeners' => 0,
                'priorities' => []
            ];
            
            foreach ($priorities as $priority => $listeners) {
                $stats[$event]['total_listeners'] += count($listeners);
                $stats[$event]['priorities'][$priority] = count($listeners);
            }
        }
        
        return $stats;
    }

    /**
     * Get bridge information
     */
    public function getBridges(): array
    {
        return $this->bridges;
    }

    /**
     * Clear all listeners for an event
     */
    public function clear(string $event): void
    {
        unset($this->listeners[$event]);
    }

    /**
     * Clear all bridges
     */
    public function clearBridges(): void
    {
        $this->bridges = [];
    }

    /**
     * Check if event has listeners
     */
    public function hasListeners(string $event): bool
    {
        return isset($this->listeners[$event]) && !empty($this->listeners[$event]);
    }

    /**
     * Get listener count for an event
     */
    public function getListenerCount(string $event): int
    {
        if (!isset($this->listeners[$event])) {
            return 0;
        }
        
        $count = 0;
        foreach ($this->listeners[$event] as $priority => $listeners) {
            $count += count($listeners);
        }
        
        return $count;
    }
} 