<?php
class RedditBot_Queue {

    // Add this method to the RedditBot_Queue class
    public static function mark_as_pending($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        return $wpdb->update($table, 
            array('status' => 'pending'),
            array('id' => $id)
        );
    }
    
    public static function add_to_queue($post_id, $post_url, $post_title, $comment_text) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        $result = $wpdb->insert($table, array(
            'post_id' => $post_id,
            'post_url' => $post_url,
            'post_title' => $post_title,
            'comment_text' => $comment_text,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ));
        
        if ($result) {
            RedditBot_Logger::info('queue', "Added comment to queue for post: $post_id");
            return $wpdb->insert_id;
        } else {
            RedditBot_Logger::error('queue', "Failed to add comment to queue for post: $post_id");
            return false;
        }
    }
    
    public static function get_pending_comments($limit = 5) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE status = 'pending' ORDER BY created_at ASC LIMIT %d",
            $limit
        ));
    }
    
    public static function mark_as_posted($id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        return $wpdb->update($table, 
            array(
                'status' => 'posted',
                'posted_at' => current_time('mysql')
            ),
            array('id' => $id)
        );
    }
    
    public static function mark_as_failed($id, $error_message = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        $result = $wpdb->update($table, 
            array('status' => 'failed'),
            array('id' => $id)
        );
        
        if ($error_message) {
            RedditBot_Logger::error('queue', "Comment ID $id failed: $error_message");
        }
        
        return $result;
    }
    
    public static function get_queue_stats() {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        $stats = $wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'posted' THEN 1 ELSE 0 END) as posted,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM $table
        ", ARRAY_A);
        
        return $stats;
    }
    
    public static function cleanup_old_entries($days = 7) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table WHERE status IN ('posted', 'failed') AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
            $days
        ));
        
        if ($deleted > 0) {
            RedditBot_Logger::info('queue', "Cleaned up $deleted old queue entries");
        }
        
        return $deleted;
    }
}