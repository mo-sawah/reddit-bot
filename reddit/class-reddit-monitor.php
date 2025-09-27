<?php
class RedditBot_Monitor {
    
    private $reddit_api;
    private $target_subreddits;
    private $keywords;
    
    public function __construct() {
        $this->reddit_api = new RedditBot_API();
        $this->target_subreddits = RedditBot_Config::get_subreddit_list();
        $this->keywords = $this->get_target_keywords();
    }
    
    public function find_relevant_posts() {
        $relevant_posts = array();
        
        foreach ($this->target_subreddits as $subreddit) {
            $posts = $this->reddit_api->get_subreddit_posts($subreddit, 'hot', 25);
            
            if ($posts) {
                foreach ($posts as $post_wrapper) {
                    $post = $post_wrapper['data'];
                    
                    if ($this->is_post_relevant($post) && $this->is_post_suitable($post)) {
                        $relevant_posts[] = array(
                            'id' => $post['name'], // Reddit fullname (e.g., t3_abc123)
                            'url' => 'https://reddit.com' . $post['permalink'],
                            'title' => $post['title'],
                            'selftext' => $post['selftext'],
                            'subreddit' => $post['subreddit'],
                            'score' => $post['score'],
                            'num_comments' => $post['num_comments'],
                            'created_utc' => $post['created_utc']
                        );
                    }
                }
                
                // Small delay between subreddit requests
                sleep(2);
            }
        }
        
        RedditBot_Logger::info('monitor', "Found " . count($relevant_posts) . " relevant posts across " . count($this->target_subreddits) . " subreddits");
        
        return $relevant_posts;
    }
    
    private function is_post_relevant($post) {
        $title_lower = strtolower($post['title']);
        $content_lower = strtolower($post['selftext']);
        $combined_text = $title_lower . ' ' . $content_lower;
        
        // Check for target keywords
        foreach ($this->keywords as $keyword) {
            if (strpos($combined_text, strtolower($keyword)) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function is_post_suitable($post) {
        // Skip if post is too old (more than 24 hours)
        if ((time() - $post['created_utc']) > 86400) {
            return false;
        }
        
        // Skip if post has too many comments already (likely saturated)
        if ($post['num_comments'] > 100) {
            return false;
        }
        
        // Skip if post score is too low (likely not engaging)
        if ($post['score'] < 5) {
            return false;
        }
        
        // Skip if we've already processed this post
        if ($this->already_processed($post['name'])) {
            return false;
        }
        
        // Skip certain post types
        if ($this->is_post_type_excluded($post)) {
            return false;
        }
        
        return true;
    }
    
    private function is_post_type_excluded($post) {
        // Skip deleted/removed posts
        if ($post['removed_by_category'] || $post['author'] === '[deleted]') {
            return true;
        }
        
        // Skip if it's just a link with no discussion potential
        if (empty($post['selftext']) && !empty($post['url']) && !$this->is_discussion_link($post['url'])) {
            return true;
        }
        
        // Skip NSFW content
        if ($post['over_18']) {
            return true;
        }
        
        return false;
    }
    
    private function is_discussion_link($url) {
        // URLs that typically generate discussion
        $discussion_domains = array('youtube.com', 'github.com', 'stackoverflow.com', 'medium.com');
        
        foreach ($discussion_domains as $domain) {
            if (strpos($url, $domain) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function already_processed($post_name) {
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_queue';
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE post_id = %s",
            $post_name
        ));
        
        return $exists > 0;
    }
    
    private function get_target_keywords() {
        // Default keywords based on website niche
        $default_keywords = array(
            'wordpress', 'website', 'blog', 'seo', 'web development',
            'help', 'advice', 'recommendations', 'how to', 'tutorial'
        );
        
        // You could make this configurable in settings
        $custom_keywords = RedditBot_Config::get_setting('target_keywords', '');
        
        if ($custom_keywords) {
            $additional = array_map('trim', explode(',', $custom_keywords));
            $default_keywords = array_merge($default_keywords, $additional);
        }
        
        return array_unique($default_keywords);
    }
}