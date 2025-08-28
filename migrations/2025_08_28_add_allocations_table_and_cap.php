<?php

declare(strict_types=1);

use SmartAlloc\Core\FormContext;
use SmartAlloc\Infra\DB\TableResolver;

/**
 * Run migration to create allocations table and propagate capability.
 */
function smartalloc_run_migration_2025_08_28_add_allocations_table_and_cap(): void {
    global $wpdb;
    $resolver = new TableResolver($wpdb);
    $table    = $resolver->allocations(new FormContext(0));
    $charset  = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $sql      = "
CREATE TABLE {$table} (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  mentee_id BIGINT UNSIGNED NOT NULL,
  mentor_id BIGINT UNSIGNED DEFAULT NULL,
  gf_entry_id BIGINT UNSIGNED NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending_review',
  match_score FLOAT DEFAULT NULL,
  allocated_at_utc DATETIME DEFAULT NULL,
  created_at_utc DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY  (id),
  KEY mentor_id_idx (mentor_id),
  KEY status_idx (status)
) {$charset};";
    dbDelta($sql);

    $admin = get_role('administrator');
    $admin?->add_cap('smartalloc_manage');

    foreach (wp_roles()->roles as $role_key => $role) {
        $role_obj = get_role($role_key);
        if ($role_obj && $role_obj->has_cap('manage_smartalloc')) {
            $role_obj->add_cap('smartalloc_manage');
        }
    }
}
