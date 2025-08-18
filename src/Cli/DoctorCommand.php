<?php

declare(strict_types=1);

namespace SmartAlloc\Cli;

use SmartAlloc\Infra\Settings\Settings;

final class DoctorCommand
{
    /**
     * Run diagnostic checks.
     *
     * @param array<int,string> $args
     * @param array<string,string> $assoc_args
     */
    public function __invoke(array $args, array $assoc_args): void
    {
        $results = $this->runChecks();
        if (($assoc_args['format'] ?? '') === 'json') {
            if (class_exists('WP_CLI')) {
                \WP_CLI::print_value($results, ['format' => 'json']);
            } else {
                echo wp_json_encode($results);
            }
            return;
        }

        foreach ($results as $check) {
            $status = $check['status'] ? 'PASS' : 'FAIL';
            $line = sprintf('%s: %s', $check['label'], $status);
            if (class_exists('WP_CLI')) {
                \WP_CLI::line($line);
            } else {
                echo $line . PHP_EOL;
            }
        }
    }

    /**
     * @return array<int,array{label:string,status:bool}>
     */
    public function runChecks(): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'smartalloc_allocations';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        $index = $exists && $wpdb->get_var("SHOW INDEX FROM {$table} WHERE Key_name='mentor_id'");

        $uploads = wp_upload_dir();
        $writable = is_writable($uploads['basedir'] ?? sys_get_temp_dir());
        $cron = (bool) wp_next_scheduled('smartalloc_retention_daily');
        $settings = is_array(Settings::sanitize((array) get_option('smartalloc_settings', [])));
        $routes = rest_get_server()->get_routes();
        $rest = isset($routes['/smartalloc/v1/health']);

        return [
            ['label' => __('DB tables', 'smartalloc'), 'status' => (bool) $exists],
            ['label' => __('DB indices', 'smartalloc'), 'status' => (bool) $index],
            ['label' => __('Uploads writable', 'smartalloc'), 'status' => $writable],
            ['label' => __('Cron scheduled', 'smartalloc'), 'status' => $cron],
            ['label' => __('Settings parsable', 'smartalloc'), 'status' => $settings],
            ['label' => __('REST routes', 'smartalloc'), 'status' => $rest],
        ];
    }
}
