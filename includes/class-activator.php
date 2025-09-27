<?php
class RedditBot_Activator {
    
    public static function activate() {
        self::create_tables();
        self::set_default_settings();
        self::schedule_cron_jobs();
    }
    
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Settings table
        $settings_table = $wpdb->prefix . 'reddit_bot_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            setting_name varchar(255) NOT NULL,
            setting_value longtext,
            PRIMARY KEY (setting_name)
        ) $charset_collate;";
        
        // Queue table
        $queue_table = $wpdb->prefix . 'reddit_bot_queue';
        $queue_sql = "CREATE TABLE $queue_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            post_id varchar(50) NOT NULL,
            post_url varchar(500) NOT NULL,
            post_title text,
            comment_text text,
            status enum('pending','posted','failed') DEFAULT 'pending',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            posted_at datetime NULL,
            PRIMARY KEY (id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Logs table
        $logs_table = $wpdb->prefix . 'reddit_bot_logs';
        $logs_sql = "CREATE TABLE $logs_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            action varchar(100) NOT NULL,
            message text,
            level enum('info','warning','error') DEFAULT 'info',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY level (level),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($settings_sql);
        dbDelta($queue_sql);
        dbDelta($logs_sql);
    }
    
    private static function set_default_settings() {
        $defaults = array(
            'reddit_client_id' => '',
            'reddit_client_secret' => '',
            'reddit_username' => '',
            'reddit_password' => '',
            'openrouter_api_key' => '',
            'target_subreddits' => 'wordpress,webdev,entrepreneur',
            'max_comments_per_hour' => '5',
            'ai_model' => 'anthropic/claude-3-haiku',
            'comment_style' => 'helpful',
            'website_url' => get_site_url(),
            'bot_enabled' => '0'
        );
        
        foreach ($defaults as $key => $value) {
            self::update_setting($key, $value);
        }
    }
    
    private static function update_setting($name, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_settings';
        
        $wpdb->replace($table, array(
            'setting_name' => $name,
            'setting_value' => $value
        ));
    }
    
    private static function schedule_cron_jobs() {
        if (!wp_next_scheduled('reddit_bot_monitor_posts')) {
            wp_schedule_event(time(), 'hourly', 'reddit_bot_monitor_posts');
        }
        
        if (!wp_next_scheduled('reddit_bot_process_queue')) {
            wp_schedule_event(time(), 'reddit_bot_15min', 'reddit_bot_process_queue');
        }
    }
}