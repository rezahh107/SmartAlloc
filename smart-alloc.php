<?php
/*
Plugin Name: SmartAlloc
Description: Event-driven student support allocation with Gravity Forms + Exporter.
Version: 1.0.0-rc.2
Author: رضا هاشمی حسینی
Text Domain: smartalloc
Domain Path: /languages
Requires at least: 6.3
Tested up to: 6.4
Requires PHP: 8.1
Update URI: false
*/

/**
 * Main plugin bootstrap.
 *
 * @note Admin menu and page titles are now localized.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constants
define('SMARTALLOC_VERSION', '1.0.0-rc.1');
define('SMARTALLOC_DB_VERSION', '1.0.0');
define('SMARTALLOC_CAP', 'manage_smartalloc');
define('SMARTALLOC_UPLOAD_DIR', 'smart-alloc');

// PHP version check
if (version_compare(PHP_VERSION, '8.1.0', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>SmartAlloc requires PHP 8.1+.</p></div>';
    });
    return;
}

// WordPress version check
if (version_compare(get_bloginfo('version'), '6.3', '<')) {
    add_action('admin_notices', function () {
        echo '<div class="notice notice-error"><p>SmartAlloc requires WordPress 6.3+.</p></div>';
    });
    return;
}

// PSR-4 Autoloader (no Composer)
spl_autoload_register(function ($class) {
    $prefix   = 'SmartAlloc\\';
    $base_dir = __DIR__ . '/src/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file           = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (is_readable($file)) {
        require_once $file;
    }
});

// Lazy-load Composer autoloader on demand to conserve memory.
spl_autoload_register(function ($class) {
    static $loaded = false;
    if ($loaded || str_starts_with($class, 'SmartAlloc\\')) {
        return;
    }
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        $loaded = true;
        require_once $autoload;
    }
}, true, true);

// Activation hook
register_activation_hook(__FILE__, function (bool $network_wide) {
    SmartAlloc\Bootstrap::activate($network_wide);
});

// Load textdomain and initialize
add_action('plugins_loaded', function () {
    load_plugin_textdomain('smartalloc', false, dirname(plugin_basename(__FILE__)) . '/languages');
    SmartAlloc\Bootstrap::init();

    // Set container in AdminController
    SmartAlloc\Http\Admin\AdminController::setContainer(SmartAlloc\Bootstrap::container());
      \SmartAlloc\Cron\RetentionTasks::register();
      \SmartAlloc\Cron\ExportRetention::register();
      (new \SmartAlloc\Http\Rest\WebhookController())->register_routes();
      \SmartAlloc\Infra\GF\HookBootstrap::registerEnabledForms();

      add_action('rest_api_init', function() {
          $container = \SmartAlloc\Bootstrap::container();
          $controller = new \SmartAlloc\REST\Controllers\AllocationController(
              $container->get(\SmartAlloc\Services\AllocationService::class),
              $container->get(\SmartAlloc\Infra\DB\TableResolver::class)
          );
          $controller->register();
      });
  });

// Run migrations on admin init
add_action('admin_init', ['SmartAlloc\\Infra\\Upgrade\\MigrationRunner', 'maybeRun']);

// WP-CLI Commands Registration
  if (defined('WP_CLI') && WP_CLI) {
      require_once __DIR__ . '/src/Infra/CLI/Commands.php';
      WP_CLI::add_command('smartalloc', \SmartAlloc\Infra\CLI\Commands::class);
    require_once __DIR__ . '/src/Cli/ExportCommand.php';
    require_once __DIR__ . '/src/Cli/AllocateCommand.php';
    require_once __DIR__ . '/src/Cli/ReviewCommand.php';
    require_once __DIR__ . '/src/Cli/DoctorCommand.php';
    require_once __DIR__ . '/src/Cli/DebugCommand.php';
    require_once __DIR__ . '/src/Cli/GFCommand.php';
    WP_CLI::add_command('smartalloc export', \SmartAlloc\Cli\ExportCommand::class);
    WP_CLI::add_command('smartalloc allocate', \SmartAlloc\Cli\AllocateCommand::class);
    WP_CLI::add_command('smartalloc review', \SmartAlloc\Cli\ReviewCommand::class);
    WP_CLI::add_command('smartalloc doctor', \SmartAlloc\Cli\DoctorCommand::class);
    WP_CLI::add_command('smartalloc debug pack', \SmartAlloc\Cli\DebugCommand::class);
      WP_CLI::add_command('smartalloc gf', \SmartAlloc\Cli\GFCommand::class);
      require_once __DIR__ . '/src/CLI/Commands.php';
      WP_CLI::add_command('smartalloc run', \SmartAlloc\CLI\Commands::class);
  }

// Persian Admin Menu
add_action('admin_menu', function () {
    add_menu_page(
        esc_html__('مدیریت تخصیص هوشمند', 'smartalloc'),
        esc_html__('مدیریت تخصیص هوشمند', 'smartalloc'),
        SMARTALLOC_CAP,
        'smartalloc-dashboard',
        function () { SmartAlloc\Http\Admin\AdminController::dashboard(); },
        'dashicons-groups',
        30
    );

    add_submenu_page(
        'smartalloc-dashboard',
        esc_html__('داشبورد', 'smartalloc'),
        esc_html__('داشبورد', 'smartalloc'),
        SMARTALLOC_CAP,
        'smartalloc-dashboard',
        function () { SmartAlloc\Http\Admin\AdminController::dashboard(); }
    );

    add_submenu_page(
        'smartalloc-dashboard',
        esc_html__('تنظیمات', 'smartalloc'),
        esc_html__('تنظیمات', 'smartalloc'),
        SMARTALLOC_CAP,
        'smartalloc-settings',
        function () { SmartAlloc\Http\Admin\AdminController::settings(); }
    );

    add_submenu_page(
        'smartalloc-dashboard',
        esc_html__('گزارش‌ها', 'smartalloc'),
        esc_html__('گزارش‌ها', 'smartalloc'),
        SMARTALLOC_CAP,
        'smartalloc-reports',
        function () { SmartAlloc\Http\Admin\AdminController::reports(); }
    );

    add_submenu_page(
        'smartalloc-dashboard',
        esc_html__('لاگ‌ها', 'smartalloc'),
        esc_html__('لاگ‌ها', 'smartalloc'),
        SMARTALLOC_CAP,
        'smartalloc-logs',
        function () { SmartAlloc\Http\Admin\AdminController::logs(); }
    );
});

add_action('admin_menu', ['SmartAlloc\\Admin\\Menu', 'register']);
add_action('admin_menu', ['SmartAlloc\\Admin\\FormsScreen', 'register']);
add_action('admin_init', ['SmartAlloc\\Admin\\Pages\\SettingsPage', 'register']);
add_action('admin_post_smartalloc_export_generate', ['SmartAlloc\\Admin\\Actions\\ExportGenerateAction', 'handle']);
add_action('admin_post_smartalloc_export_download', ['SmartAlloc\\Admin\\Actions\\ExportDownloadAction', 'handle']);
add_action('admin_post_smartalloc_reports_csv', ['SmartAlloc\\Admin\\Pages\\ReportsPage', 'downloadCsv']);
add_action('admin_post_smartalloc_enable_form', ['SmartAlloc\\Admin\\FormsScreen', 'handleEnable']);
add_action('admin_post_smartalloc_disable_form', ['SmartAlloc\\Admin\\FormsScreen', 'handleDisable']);
add_action('admin_post_smartalloc_generate_gf_json', ['SmartAlloc\\Admin\\FormsScreen', 'handleGenerateJson']);
add_action('wp_ajax_smartalloc_manual_approve', ['SmartAlloc\\Admin\\Actions\\ManualApproveAction', 'handle']);
add_action('wp_ajax_smartalloc_manual_assign', ['SmartAlloc\\Admin\\Actions\\ManualAssignAction', 'handle']);
add_action('wp_ajax_smartalloc_manual_reject', ['SmartAlloc\\Admin\\Actions\\ManualRejectAction', 'handle']);
add_action('wp_ajax_smartalloc_manual_candidates', ['SmartAlloc\\Admin\\Actions\\ManualAssignAction', 'candidates']);

add_action('gform_after_submission_150', [\SmartAlloc\Infra\GF\SabtSubmissionHandler::class, 'handle'], 10, 2);
