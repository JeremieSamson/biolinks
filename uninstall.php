<?php
declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

$config_table = $wpdb->prefix . 'biolinks_config';

$page_id = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT option_value FROM $config_table WHERE option_name = %s",
    'page_id'
));

if ($page_id > 0) {
    wp_delete_post($page_id, true);
}

$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}biolinks_logs");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}biolinks_links");
$wpdb->query("DROP TABLE IF EXISTS $config_table");
