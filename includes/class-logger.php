<?php
class RedditBot_Logger {
    
    public static function log($action, $message, $level = 'info') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_logs';
        
        $wpdb->insert($table, array(
            'action' => $action,
            'message' => $message,
            'level' => $level,
            'created_at' => current_time('mysql')
        ));
        
        // Also log to WordPress debug if enabled
        if (WP_DEBUG && WP_DEBUG_LOG) {
            error_log("Reddit Bot [$level] $action: $message");
        }
    }
    
    public static function info($action, $message) {
        self::log($action, $message, 'info');
    }
    
    public static function warning($action, $message) {
        self::log($action, $message, 'warning');
    }
    
    public static function error($action, $message) {
        self::log($action, $message, 'error');
    }
    
    public static function get_recent_logs($limit = 50) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_logs';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d",
            $limit
        ));
    }
    
    public static function cleanup_old_logs($days = 30) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_logs';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
    }
}