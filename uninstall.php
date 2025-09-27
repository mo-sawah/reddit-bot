<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove database tables
global $wpdb;

$tables = array(
    $wpdb->prefix . 'reddit_bot_settings',
    $wpdb->prefix . 'reddit_bot_queue',
    $wpdb->prefix . 'reddit_bot_logs'
);

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Clear scheduled cron jobs
wp_clear_scheduled_hook('reddit_bot_process_queue');
wp_clear_scheduled_hook('reddit_bot_monitor_posts');

// Remove any cached data
wp_cache_flush();

// Log the uninstall
error_log('Reddit Bot Plugin: Successfully uninstalled and cleaned up');