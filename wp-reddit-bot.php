<?php
/**
 * Plugin Name: Reddit Traffic Bot
 * Description: Generate traffic by posting contextual comments on Reddit using AI
 * Version: 1.0.1
 * Author: Your Name
 * Text Domain: reddit-bot
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('REDDIT_BOT_VERSION', '1.0.1');
define('REDDIT_BOT_PATH', plugin_dir_path(__FILE__));
define('REDDIT_BOT_URL', plugin_dir_url(__FILE__));

// Load the activator class immediately (before activation hook)
require_once REDDIT_BOT_PATH . 'includes/class-activator.php';

// Main plugin class
class RedditBot {
    
    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        $this->load_dependencies();
        
        if (is_admin()) {
            new RedditBot_Admin();
        }
        
        new RedditBot_Cron();
    }
    
    private function load_dependencies() {
        // Core classes
        require_once REDDIT_BOT_PATH . 'includes/class-logger.php';
        require_once REDDIT_BOT_PATH . 'includes/class-queue.php';
        require_once REDDIT_BOT_PATH . 'includes/class-cron.php';
        
        // Reddit integration
        require_once REDDIT_BOT_PATH . 'reddit/class-reddit-api.php';
        require_once REDDIT_BOT_PATH . 'reddit/class-reddit-auth.php';
        require_once REDDIT_BOT_PATH . 'reddit/class-reddit-monitor.php';
        
        // AI integration
        require_once REDDIT_BOT_PATH . 'ai/class-openrouter.php';
        require_once REDDIT_BOT_PATH . 'ai/class-comment-generator.php';
        
        // Config helper
        require_once REDDIT_BOT_PATH . 'config/defaults.php';
        
        // Admin interface (only when needed)
        if (is_admin()) {
            require_once REDDIT_BOT_PATH . 'admin/class-admin.php';
        }
    }
    
    public function activate() {
        RedditBot_Activator::activate();
    }
    
    public function deactivate() {
        wp_clear_scheduled_hook('reddit_bot_process_queue');
        wp_clear_scheduled_hook('reddit_bot_monitor_posts');
    }
}

// Initialize the plugin
new RedditBot();