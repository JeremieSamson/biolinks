<?php
declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$biolinks_config_table = $wpdb->prefix . 'biolinks_config';
$biolinks_links_table  = $wpdb->prefix . 'biolinks_links';
$biolinks_logs_table   = $wpdb->prefix . 'biolinks_logs';

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
$biolinks_page_id = (int) $wpdb->get_var(
    $wpdb->prepare("SELECT option_value FROM {$biolinks_config_table} WHERE option_name = %s", 'page_id') // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
);

if ($biolinks_page_id > 0) {
    wp_delete_post($biolinks_page_id, true);
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query("DROP TABLE IF EXISTS {$biolinks_logs_table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query("DROP TABLE IF EXISTS {$biolinks_links_table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query("DROP TABLE IF EXISTS {$biolinks_config_table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
