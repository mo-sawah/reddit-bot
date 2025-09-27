<?php
/**
 * Plugin Name: Reddit Traffic Bot
 * Description: Generate traffic by posting contextual comments on Reddit using AI
 * Version: 1.0.2
 * Author: Your Name
 * Text Domain: reddit-bot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('REDDIT_BOT_VERSION', '1.0.2');
define('REDDIT_BOT_PATH', plugin_dir_path(__FILE__));
define('REDDIT_BOT_URL', plugin_dir_url(__FILE__));

// Check if activator file exists before including it
$activator_file = REDDIT_BOT_PATH . 'includes/class-activator.php';
if (!file_exists($activator_file)) {
    wp_die('Reddit Bot Error: Activator file not found at: ' . $activator_file);
}
require_once $activator_file;

// Main plugin class
class RedditBot {
    
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        $this->load_dependencies();
        
        if (is_admin()) {
            new RedditBot_Admin();
        }
        
        new RedditBot_Cron();
    }
    
    private function load_dependencies() {
        $files = array(
            'includes/class-logger.php',
            'includes/class-queue.php', 
            'includes/class-cron.php',
            'reddit/class-reddit-api.php',
            'reddit/class-reddit-auth.php',
            'reddit/class-reddit-monitor.php',
            'ai/class-openrouter.php',
            'ai/class-comment-generator.php',
            'config/defaults.php'
        );
        
        foreach ($files as $file) {
            $filepath = REDDIT_BOT_PATH . $file;
            if (file_exists($filepath)) {
                require_once $filepath;
            } else {
                error_log("Reddit Bot: Missing file: $filepath");
            }
        }
        
        if (is_admin()) {
            $admin_file = REDDIT_BOT_PATH . 'admin/class-admin.php';
            if (file_exists($admin_file)) {
                require_once $admin_file;
            }
        }
    }
    
    public function activate() {
        if (!class_exists('RedditBot_Activator')) {
            wp_die('Reddit Bot Error: Activator class not loaded properly.');
        }
        RedditBot_Activator::activate();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('reddit_bot_process_queue');
        wp_clear_scheduled_hook('reddit_bot_monitor_posts');
    }
}

// Initialize the plugin
new RedditBot();