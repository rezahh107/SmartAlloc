<?php

declare(strict_types=1);

namespace SmartAlloc;

use SmartAlloc\Services\{
    Db, Cache, Logging, CircuitBreaker, Metrics, CounterService,
    AllocationService, CrosswalkService, ExportService, NotificationService,
    StatsService, HealthService, EventStoreWp
};
use SmartAlloc\Infra\Repository\AllocationsRepository;
use SmartAlloc\Event\EventBus;
use SmartAlloc\Contracts\{LoggerInterface, EventStoreInterface};
use SmartAlloc\Http\RestController;
use SmartAlloc\Integration\{GravityForms, ActionSchedulerAdapter};

/**
 * Main Bootstrap class for SmartAlloc plugin
 * Handles initialization, DI container setup, and service wiring.
 *
 * @note Event listeners now wired to `StudentSubmitted` as the initial
 *       event in the allocation chain.
 */
final class Bootstrap
{
    private static ?Container $container = null;

    /**
     * Initialize the plugin
     */
    public static function init(): void
    {
        if (self::$container) {
            return; // Already initialized
        }

        self::$container = new Container();

        // Register base services
        self::registerBaseServices();

        // Register domain services
        self::registerDomainServices();

        // Register REST API
        (new RestController(self::$container))->register_routes();

        // Register integrations
        self::registerIntegrations();

        // Wire event listeners
        self::wireListeners();
    }

    /**
     * Get the DI container instance
     */
    public static function container(): Container
    {
        if (!self::$container) {
            throw new \RuntimeException('Bootstrap not initialized. Call Bootstrap::init() first.');
        }
        return self::$container;
    }

    /**
     * Plugin activation hook
     */
    public static function activate(): void
    {
        // Run database migrations
        Services\Db::migrate();

        // Create upload directory
        $upload = wp_upload_dir();
        $upload_dir = trailingslashit($upload['basedir']) . SMARTALLOC_UPLOAD_DIR;
        wp_mkdir_p($upload_dir);

        // Set default options
        if (!get_option('smartalloc_form_id')) {
            update_option('smartalloc_form_id', 150);
        }

        // Flush rewrite rules for REST API
        flush_rewrite_rules();
    }

    /**
     * Plugin uninstall hook
     */
    public static function uninstall(): void
    {
        // Note: We don't automatically delete data on uninstall
        // This should be done manually by the user if needed
    }

    /**
     * Register base infrastructure services
     */
    private static function registerBaseServices(): void
    {
        $c = self::$container;

        // Database and caching
        $c->set(Db::class, fn() => new Db());
        $c->set(Cache::class, fn() => new Cache());

        // Logging and monitoring
        $c->set(Logging::class, fn() => new Logging());
        $c->set(LoggerInterface::class, fn() => $c->get(Logging::class));
        $c->set(Metrics::class, fn() => new Metrics());
        $c->set(CircuitBreaker::class, fn() => new CircuitBreaker());

        // Event system
        $c->set(EventStoreInterface::class, fn() => new EventStoreWp());
        $c->set(EventBus::class, fn() => new EventBus(
            $c->get(LoggerInterface::class),
            $c->get(EventStoreInterface::class)
        ));

        // Async processing - will be created directly in registerIntegrations
    }

    /**
     * Register domain/business logic services
     */
    private static function registerDomainServices(): void
    {
        $c = self::$container;

        // Core business services
        $c->set(CounterService::class, fn() => new CounterService(
            $c->get(Db::class),
            $c->get(Logging::class)
        ));

        $c->set(CrosswalkService::class, fn() => new CrosswalkService(
            $c->get(Db::class),
            $c->get(Cache::class),
            $c->get(Logging::class)
        ));

        $c->set(AllocationService::class, fn() => new AllocationService(
            $c->get(Db::class),
            $c->get(CrosswalkService::class),
            $c->get(Logging::class),
            $c->get(Metrics::class),
            $c->get(EventBus::class)
        ));

        $c->set(ExportService::class, fn() => new ExportService(
            $c->get(Logging::class),
            $c->get(Metrics::class)
        ));

        $c->set(NotificationService::class, fn() => new NotificationService(
            $c->get(CircuitBreaker::class),
            $c->get(Logging::class)
        ));

        $c->set(StatsService::class, fn() => new StatsService(
            $c->get(Db::class),
            $c->get(Logging::class)
        ));

        $c->set(HealthService::class, fn() => new HealthService(
            $c->get(Db::class),
            $c->get(Cache::class)
        ));

        $c->set(AllocationsRepository::class, fn() => new AllocationsRepository(
            $c->get(LoggerInterface::class),
            $GLOBALS['wpdb']
        ));
    }

    /**
     * Register external integrations
     */
    private static function registerIntegrations(): void
    {
        $c = self::$container;

        // Action Scheduler integration
        $as = new ActionSchedulerAdapter($c->get(EventBus::class), $c->get(LoggerInterface::class));
        $as->register();

        // Gravity Forms integration
        (new GravityForms($c, $c->get(EventBus::class), $c->get(LoggerInterface::class)))->register();
    }

    /**
     * Wire event listeners to the event bus
     */
    private static function wireListeners(): void
    {
        $bus = self::$container->get(EventBus::class);

        // Auto-assignment listener
        $bus->on('StudentSubmitted', new \SmartAlloc\Listeners\AutoAssignListener(self::$container));

        // Activity logging listener
        $bus->on('StudentSubmitted', new \SmartAlloc\Listeners\LogActivityListener(self::$container));

        // Notification listener
        $bus->on('MentorAssigned', new \SmartAlloc\Listeners\NotifyListener(self::$container));

        // Export listener
        $bus->on('AllocationCommitted', new \SmartAlloc\Listeners\ExportListener(self::$container));
    }
} 