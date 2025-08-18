<?php

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

$purge = (bool) get_option('smartalloc_purge_on_uninstall', false);

global $wpdb;
// Always clear transients
$pattern = $wpdb->esc_like('_transient_smartalloc_') . '%';
$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $pattern, str_replace('_transient_', '_transient_timeout_', $pattern)));

if ($purge) {
    $like = $wpdb->esc_like('smartalloc_') . '%';
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like));
}
