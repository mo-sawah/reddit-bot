<?php
class RedditBot_CommentGenerator {
    
    private $openrouter;
    private $website_url;
    private $comment_style;
    
    public function __construct() {
        $this->openrouter = new RedditBot_OpenRouter();
        $this->website_url = RedditBot_Config::get_setting('website_url', get_site_url());
        $this->comment_style = RedditBot_Config::get_setting('comment_style', 'helpful');
    }
    
    public function generate_comment($post) {
        $prompt = $this->build_prompt($post);
        
        if (!$prompt) {
            RedditBot_Logger::error('comment_generator', 'Failed to build prompt for post: ' . $post['id']);
            return false;
        }
        
        $comment = $this->openrouter->generate_completion($prompt, null, 400);
        
        if ($comment) {
            $formatted_comment = $this->format_comment($comment, $post);
            RedditBot_Logger::info('comment_generator', 'Generated comment for post: ' . $post['id']);
            return $formatted_comment;
        }
        
        return false;
    }
    
    private function build_prompt($post) {
        $style_instructions = $this->get_style_instructions();
        $website_context = $this->get_website_context();
        
        $prompt = "You are helping someone write a helpful Reddit comment. Here's the context:

POST TITLE: {$post['title']}
SUBREDDIT: r/{$post['subreddit']}
POST CONTENT: " . (trim($post['selftext']) ?: '[No additional content]') . "

YOUR WEBSITE: {$this->website_url}
WEBSITE CONTEXT: {$website_context}

INSTRUCTIONS:
{$style_instructions}

Write a helpful, engaging comment that:
1. Directly addresses the post topic
2. Provides genuine value to the discussion
3. Naturally includes your website link when relevant
4. Sounds conversational and authentic
5. Is 2-4 sentences long
6. Follows Reddit etiquette

DO NOT:
- Be overly promotional
- Use corporate language
- Include multiple links
- Write generic responses
- Violate subreddit rules

Comment:";

        return $prompt;
    }
    
    private function get_style_instructions() {
        $styles = array(
            'helpful' => 'Be genuinely helpful and supportive. Share experience and practical advice.',
            'casual' => 'Use casual, friendly language. Be conversational and relatable.',
            'expert' => 'Demonstrate expertise while remaining approachable. Share technical insights.',
            'encouraging' => 'Be positive and encouraging. Motivate and inspire others.',
            'question_focused' => 'Ask thoughtful follow-up questions. Engage in deeper discussion.'
        );
        
        return isset($styles[$this->comment_style]) ? $styles[$this->comment_style] : $styles['helpful'];
    }
    
    private function get_website_context() {
        // Get basic info about the website
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');
        
        // Get recent post titles for context
        $recent_posts = get_posts(array(
            'numberposts' => 5,
            'post_status' => 'publish'
        ));
        
        $post_topics = array();
        foreach ($recent_posts as $wp_post) {
            $post_topics[] = $wp_post->post_title;
        }
        
        $context = "$site_name - $site_description. ";
        if (!empty($post_topics)) {
            $context .= "Recent topics: " . implode(', ', array_slice($post_topics, 0, 3));
        }
        
        return $context;
    }
    
    private function format_comment($comment, $post) {
        // Clean up the comment
        $comment = trim($comment);
        
        // Remove any quotes or markdown that might break formatting
        $comment = str_replace(array('`', '**', '__'), '', $comment);
        
        // Ensure the website link is properly formatted
        if (strpos($comment, $this->website_url) === false && $this->should_include_link($post)) {
            $comment = $this->add_link_naturally($comment);
        }
        
        // Add a subtle signature if appropriate
        if (rand(1, 3) === 1) { // 33% chance
            $comment .= "\n\nHope this helps!";
        }
        
        return $comment;
    }
    
    private function should_include_link($post) {
        // Don't include links in every comment
        if (rand(1, 4) !== 1) { // Only 25% chance
            return false;
        }
        
        // Check if the post topic is relevant to our website
        $relevant_keywords = array('wordpress', 'website', 'blog', 'seo', 'tutorial', 'guide', 'help');
        $post_text = strtolower($post['title'] . ' ' . $post['selftext']);
        
        foreach ($relevant_keywords as $keyword) {
            if (strpos($post_text, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function add_link_naturally($comment) {
        $link_phrases = array(
            "I wrote about this on my blog: {$this->website_url}",
            "You might find this helpful: {$this->website_url}",
            "I have a detailed guide here: {$this->website_url}",
            "Check out this resource: {$this->website_url}",
            "More info on my site: {$this->website_url}"
        );
        
        $phrase = $link_phrases[array_rand($link_phrases)];
        
        return $comment . "\n\n" . $phrase;
    }
    
    public function test_generation() {
        $test_post = array(
            'id' => 'test_123',
            'title' => 'Need help with WordPress SEO',
            'selftext' => 'I am struggling to improve my website ranking. Any tips?',
            'subreddit' => 'wordpress'
        );
        
        return $this->generate_comment($test_post);
    }
    
    public function get_usage_stats() {
        global $wpdb;
        $table = $wpdb->prefix . 'reddit_bot_logs';
        
        // Count AI generations in the last 24 hours
        $recent_generations = $wpdb->get_var("
            SELECT COUNT(*) FROM $table 
            WHERE action = 'comment_generator' 
            AND level = 'info' 
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        
        // Estimate cost (rough calculation)
        $estimated_cost = $recent_generations * 0.001; // ~$0.001 per comment
        
        return array(
            'comments_generated_24h' => intval($recent_generations),
            'estimated_cost_24h' => round($estimated_cost, 4)
        );
    }
}