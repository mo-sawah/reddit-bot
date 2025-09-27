<?php
class RedditBot_Config {
    
    public static function get_setting($name, $default = '') {
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_settings';
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table WHERE setting_name = %s",
            $name
        ));
        
        return $value !== null ? $value : $default;
    }
    
    public static function update_setting($name, $value) {
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_settings';
        
        return $wpdb->replace($table, array(
            'setting_name' => $name,
            'setting_value' => $value
        ));
    }
    
    public static function get_all_settings() {
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_settings';
        
        $results = $wpdb->get_results("SELECT setting_name, setting_value FROM $table", ARRAY_A);
        $settings = array();
        
        foreach ($results as $row) {
            $settings[$row['setting_name']] = $row['setting_value'];
        }
        
        return $settings;
    }
    
    public static function get_subreddit_list() {
        $subreddits = self::get_setting('target_subreddits', '');
        return array_filter(array_map('trim', explode(',', $subreddits)));
    }
    
    public static function get_ai_models() {
        return array(
            'anthropic/claude-3-haiku' => 'Claude 3 Haiku (Fast & Cheap)',
            'anthropic/claude-3-sonnet' => 'Claude 3 Sonnet (Balanced)',
            'openai/gpt-3.5-turbo' => 'GPT-3.5 Turbo (OpenAI)',
            'openai/gpt-4' => 'GPT-4 (OpenAI)',
            'meta-llama/llama-2-70b-chat' => 'Llama 2 70B (Meta)'
        );
    }
}