<?php
/**
 * Version synchronization check script
 * Ensures all version numbers are consistent across the plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH') && !defined('CLI_SCRIPT')) {
    define('CLI_SCRIPT', true);
}

// Get plugin directory
$plugin_dir = dirname(__DIR__);
$plugin_file = $plugin_dir . '/smart-alloc.php';

// Read plugin file
if (!file_exists($plugin_file)) {
    echo "ERROR: Plugin file not found: $plugin_file\n";
    exit(1);
}

$plugin_content = file_get_contents($plugin_file);

// Extract version from plugin header
if (!preg_match('/Version:\s*([0-9]+\.[0-9]+\.[0-9]+)/', $plugin_content, $matches)) {
    echo "ERROR: Version not found in plugin header\n";
    exit(1);
}
$plugin_version = $matches[1];

// Extract version from constants
if (!preg_match("/define\('SMARTALLOC_VERSION',\s*'([0-9]+\.[0-9]+\.[0-9]+)'\)/", $plugin_content, $matches)) {
    echo "ERROR: SMARTALLOC_VERSION constant not found\n";
    exit(1);
}
$constant_version = $matches[1];

// Read composer.json
$composer_file = $plugin_dir . '/composer.json';
if (!file_exists($composer_file)) {
    echo "ERROR: composer.json not found\n";
    exit(1);
}

$composer_data = json_decode(file_get_contents($composer_file), true);
if (!$composer_data || !isset($composer_data['version'])) {
    echo "ERROR: Version not found in composer.json\n";
    exit(1);
}
$composer_version = $composer_data['version'];

// Check if all versions match
if ($plugin_version !== $constant_version || $plugin_version !== $composer_version) {
    echo "ERROR: Version mismatch detected!\n";
    echo "Plugin header: $plugin_version\n";
    echo "SMARTALLOC_VERSION: $constant_version\n";
    echo "composer.json: $composer_version\n";
    exit(1);
}

echo "✓ All versions are synchronized: $plugin_version\n";
exit(0); 