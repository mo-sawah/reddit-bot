<?php
class RedditBot_Cron {
    
    public function __construct() {
        add_action('reddit_bot_monitor_posts', array($this, 'monitor_posts'));
        add_action('reddit_bot_process_queue', array($this, 'process_queue'));
        add_filter('cron_schedules', array($this, 'add_custom_schedules'));
    }
    
    public function add_custom_schedules($schedules) {
        $schedules['reddit_bot_15min'] = array(
            'interval' => 900, // 15 minutes
            'display' => __('Every 15 Minutes (Reddit Bot)')
        );
        
        return $schedules;
    }
    
    public function monitor_posts() {
        // Check if bot is enabled
        if (!$this->is_bot_enabled()) {
            return;
        }
        
        RedditBot_Logger::info('cron', 'Starting post monitoring');
        
        try {
            $monitor = new RedditBot_Monitor();
            $new_posts = $monitor->find_relevant_posts();
            
            foreach ($new_posts as $post) {
                $this->generate_and_queue_comment($post);
            }
            
            RedditBot_Logger::info('cron', 'Post monitoring completed. Found ' . count($new_posts) . ' relevant posts');
            
        } catch (Exception $e) {
            RedditBot_Logger::error('cron', 'Monitor posts failed: ' . $e->getMessage());
        }
    }
    
    public function process_queue() {
        // Check if bot is enabled
        if (!$this->is_bot_enabled()) {
            return;
        }
        
        RedditBot_Logger::info('cron', 'Processing comment queue');
        
        try {
            $max_per_run = $this->get_max_comments_per_run();
            $pending_comments = RedditBot_Queue::get_pending_comments($max_per_run);
            
            if (empty($pending_comments)) {
                return;
            }
            
            $reddit_api = new RedditBot_API();
            $posted_count = 0;
            
            foreach ($pending_comments as $comment) {
                if ($this->check_rate_limit()) {
                    $result = $reddit_api->post_comment($comment->post_id, $comment->comment_text);
                    
                    if ($result) {
                        RedditBot_Queue::mark_as_posted($comment->id);
                        $posted_count++;
                        RedditBot_Logger::info('cron', "Posted comment to post: {$comment->post_id}");
                        
                        // Add delay between posts
                        sleep(rand(60, 180)); // 1-3 minutes
                        
                    } else {
                        RedditBot_Queue::mark_as_failed($comment->id, 'Failed to post comment');
                    }
                } else {
                    RedditBot_Logger::warning('cron', 'Rate limit reached, stopping queue processing');
                    break;
                }
            }
            
            RedditBot_Logger::info('cron', "Queue processing completed. Posted $posted_count comments");
            
        } catch (Exception $e) {
            RedditBot_Logger::error('cron', 'Process queue failed: ' . $e->getMessage());
        }
    }
    
    private function generate_and_queue_comment($post) {
        try {
            $generator = new RedditBot_CommentGenerator();
            $comment_text = $generator->generate_comment($post);
            
            if ($comment_text) {
                RedditBot_Queue::add_to_queue(
                    $post['id'],
                    $post['url'], 
                    $post['title'],
                    $comment_text
                );
            }
            
        } catch (Exception $e) {
            RedditBot_Logger::error('cron', 'Failed to generate comment for post ' . $post['id'] . ': ' . $e->getMessage());
        }
    }
    
    private function is_bot_enabled() {
        return $this->get_setting('bot_enabled') === '1';
    }
    
    private function check_rate_limit() {
        $max_per_hour = intval($this->get_setting('max_comments_per_hour'));
        
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        $posted_last_hour = $wpdb->get_var("
            SELECT COUNT(*) FROM $table 
            WHERE status = 'posted' AND posted_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        
        return $posted_last_hour < $max_per_hour;
    }
    
    private function get_max_comments_per_run() {
        $max_per_hour = intval($this->get_setting('max_comments_per_hour'));
        return min($max_per_hour, 3); // Never more than 3 per run
    }
    
    private function get_setting($name) {
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_settings';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_name = %s",
            $name
        ));
    }
}