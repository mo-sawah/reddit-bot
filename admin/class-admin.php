<?php
class RedditBot_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX handlers
        add_action('wp_ajax_reddit_bot_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_reddit_bot_test_ai', array($this, 'ajax_test_ai'));
        add_action('wp_ajax_reddit_bot_force_queue', array($this, 'ajax_force_queue'));
        add_action('wp_ajax_reddit_bot_delete_queue_item', array($this, 'ajax_delete_queue_item'));
        add_action('wp_ajax_reddit_bot_retry_queue_item', array($this, 'ajax_retry_queue_item'));
        add_action('wp_ajax_reddit_bot_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_reddit_bot_get_stats', array($this, 'ajax_get_stats'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Reddit Bot',
            'Reddit Bot',
            'manage_options',
            'reddit-bot',
            array($this, 'dashboard_page'),
            'dashicons-reddit',
            30
        );
        
        add_submenu_page(
            'reddit-bot',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'reddit-bot',
            array($this, 'dashboard_page')
        );
        
        add_submenu_page(
            'reddit-bot',
            'Settings',
            'Settings',
            'manage_options',
            'reddit-bot-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'reddit-bot',
            'Queue',
            'Queue',
            'manage_options',
            'reddit-bot-queue',
            array($this, 'queue_page')
        );
        
        add_submenu_page(
            'reddit-bot',
            'Logs',
            'Logs',
            'manage_options',
            'reddit-bot-logs',
            array($this, 'logs_page')
        );
    }
    
    public function register_settings() {
        register_setting('reddit_bot_settings', 'reddit_bot_options');
    }
    
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'reddit-bot') === false) {
            return;
        }
        
        wp_enqueue_style(
            'reddit-bot-admin',
            REDDIT_BOT_URL . 'admin/css/admin.css',
            array(),
            REDDIT_BOT_VERSION
        );
        
        wp_enqueue_script(
            'reddit-bot-admin',
            REDDIT_BOT_URL . 'admin/js/admin.js',
            array('jquery'),
            REDDIT_BOT_VERSION,
            true
        );
        
        wp_localize_script('reddit-bot-admin', 'reddit_bot_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('reddit_bot_nonce')
        ));
    }
    
    public function dashboard_page() {
        $stats = $this->get_dashboard_stats();
        include REDDIT_BOT_PATH . 'admin/admin-dashboard.php';
    }
    
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $settings = RedditBot_Config::get_all_settings();
        $ai_models = RedditBot_Config::get_ai_models();
        include REDDIT_BOT_PATH . 'admin/admin-settings.php';
    }
    
    public function queue_page() {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        if ($action === 'clear_failed') {
            $this->clear_failed_queue_items();
        }
        
        $queue_stats = RedditBot_Queue::get_queue_stats();
        $recent_queue = $this->get_recent_queue_items();
        include REDDIT_BOT_PATH . 'admin/admin-queue.php';
    }
    
    public function logs_page() {
        $logs = RedditBot_Logger::get_recent_logs(100);
        include REDDIT_BOT_PATH . 'admin/admin-logs.php';
    }
    
    private function save_settings() {
        if (!wp_verify_nonce($_POST['_wpnonce'], 'reddit_bot_settings')) {
            wp_die('Security check failed');
        }
        
        $settings = array(
            'reddit_client_id',
            'reddit_client_secret', 
            'reddit_username',
            'reddit_password',
            'openrouter_api_key',
            'target_subreddits',
            'max_comments_per_hour',
            'ai_model',
            'comment_style',
            'website_url',
            'bot_enabled'
        );
        
        foreach ($settings as $setting) {
            $value = isset($_POST[$setting]) ? sanitize_text_field($_POST[$setting]) : '';
            RedditBot_Config::update_setting($setting, $value);
        }
        
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        });
    }
    
    private function get_dashboard_stats() {
        $queue_stats = RedditBot_Queue::get_queue_stats();
        $ai_stats = (new RedditBot_CommentGenerator())->get_usage_stats();
        
        return array(
            'bot_enabled' => RedditBot_Config::get_setting('bot_enabled') === '1',
            'queue_total' => $queue_stats['total'],
            'queue_pending' => $queue_stats['pending'],
            'queue_posted' => $queue_stats['posted'],
            'queue_failed' => $queue_stats['failed'],
            'ai_generated_24h' => $ai_stats['comments_generated_24h'],
            'estimated_cost_24h' => $ai_stats['estimated_cost_24h'],
            'next_monitor_run' => wp_next_scheduled('reddit_bot_monitor_posts'),
            'next_queue_run' => wp_next_scheduled('reddit_bot_process_queue')
        );
    }
    
    private function get_recent_queue_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        return $wpdb->get_results("
            SELECT * FROM $table 
            ORDER BY created_at DESC 
            LIMIT 20
        ");
    }
    
    private function clear_failed_queue_items() {
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        $deleted = $wpdb->delete($table, array('status' => 'failed'));
        
        if ($deleted > 0) {
            add_action('admin_notices', function() use ($deleted) {
                echo "<div class='notice notice-success'><p>Cleared $deleted failed queue items.</p></div>";
            });
        }
    }
    
    public function ajax_test_connection() {
        check_ajax_referer('reddit_bot_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $reddit_api = new RedditBot_API();
        $result = $reddit_api->test_connection();
        
        wp_send_json($result);
    }
    
    public function ajax_test_ai() {
        check_ajax_referer('reddit_bot_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $openrouter = new RedditBot_OpenRouter();
        $result = $openrouter->test_connection();
        
        wp_send_json($result);
    }

    public function ajax_force_queue() {
        check_ajax_referer('reddit_bot_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        try {
            $cron = new RedditBot_Cron();
            $cron->process_queue();
            wp_send_json_success('Queue processing completed');
        } catch (Exception $e) {
            wp_send_json_error('Queue processing failed: ' . $e->getMessage());
        }
    }
    
    public function ajax_delete_queue_item() {
        check_ajax_referer('reddit_bot_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $id = intval($_POST['id']);
        if (!$id) {
            wp_send_json_error('Invalid queue item ID');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_queue';
        $result = $wpdb->delete($table, array('id' => $id));
        
        if ($result) {
            RedditBot_Logger::info('admin', "Queue item $id deleted manually");
            wp_send_json_success('Queue item deleted');
        } else {
            wp_send_json_error('Failed to delete queue item');
        }
    }
    
    public function ajax_retry_queue_item() {
        check_ajax_referer('reddit_bot_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $id = intval($_POST['id']);
        if (!$id) {
            wp_send_json_error('Invalid queue item ID');
        }
        
        // Mark as pending for retry
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_queue';
        $result = $wpdb->update(
            $table,
            array('status' => 'pending'),
            array('id' => $id)
        );
        
        if ($result !== false) {
            RedditBot_Logger::info('admin', "Queue item $id marked for retry");
            wp_send_json_success('Queue item marked for retry');
        } else {
            wp_send_json_error('Failed to retry queue item');
        }
    }
    
    public function ajax_clear_logs() {
        check_ajax_referer('reddit_bot_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $deleted = RedditBot_Logger::cleanup_old_logs(30);
        RedditBot_Logger::info('admin', "Manually cleared $deleted old log entries");
        wp_send_json_success("Cleared $deleted old log entries");
    }
    
    public function ajax_get_stats() {
        check_ajax_referer('reddit_bot_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $stats = $this->get_dashboard_stats();
        wp_send_json_success($stats);
    }
    
    public function ajax_get_recent_logs() {
        check_ajax_referer('reddit_bot_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $logs = RedditBot_Logger::get_recent_logs(20);
        $html = $this->render_logs_table($logs);
        wp_send_json_success($html);
    }
    
    private function render_logs_table($logs) {
        if (empty($logs)) {
            return '<div class="no-logs"><p>No recent logs found.</p></div>';
        }
        
        ob_start();
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-time">Time</th>
                    <th scope="col" class="column-level">Level</th>
                    <th scope="col" class="column-action">Action</th>
                    <th scope="col" class="column-message">Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr class="log-row log-level-<?php echo $log->level; ?>">
                    <td class="column-time">
                        <span title="<?php echo esc_attr($log->created_at); ?>">
                            <?php echo date('M j, H:i:s', strtotime($log->created_at)); ?>
                        </span>
                    </td>
                    <td class="column-level">
                        <span class="log-level-badge level-<?php echo $log->level; ?>">
                            <?php echo ucfirst($log->level); ?>
                        </span>
                    </td>
                    <td class="column-action">
                        <code><?php echo esc_html($log->action); ?></code>
                    </td>
                    <td class="column-message">
                        <div class="log-message">
                            <?php echo esc_html($log->message); ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }
}