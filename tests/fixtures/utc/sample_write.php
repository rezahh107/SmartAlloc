<?php
// Write pattern example
$wpdb->insert('wp_smartalloc_logs', ['message' => 'Test', 'created_at' => current_time('mysql')]);
