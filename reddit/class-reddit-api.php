<?php
class RedditBot_API {
    
    private $client_id;
    private $client_secret;
    private $username;
    private $password;
    private $access_token;
    private $user_agent;
    
    public function __construct() {
        $this->client_id = RedditBot_Config::get_setting('reddit_client_id');
        $this->client_secret = RedditBot_Config::get_setting('reddit_client_secret');
        $this->username = RedditBot_Config::get_setting('reddit_username');
        $this->password = RedditBot_Config::get_setting('reddit_password');
        $this->user_agent = 'WordPressBotScript/1.0 by ' . $this->username;
    }
    
    public function authenticate() {
        $auth_url = 'https://www.reddit.com/api/v1/access_token';
        
        $post_data = array(
            'grant_type' => 'password',
            'username' => $this->username,
            'password' => $this->password
        );
        
        $headers = array(
            'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
            'User-Agent: ' . $this->user_agent
        );
        
        $response = $this->make_request($auth_url, $post_data, $headers);
        
        if ($response && isset($response['access_token'])) {
            $this->access_token = $response['access_token'];
            RedditBot_Logger::info('reddit_api', 'Successfully authenticated with Reddit');
            return true;
        } else {
            RedditBot_Logger::error('reddit_api', 'Failed to authenticate with Reddit: ' . print_r($response, true));
            return false;
        }
    }
    
    public function get_subreddit_posts($subreddit, $sort = 'hot', $limit = 25) {
        if (!$this->access_token && !$this->authenticate()) {
            return false;
        }
        
        $url = "https://oauth.reddit.com/r/{$subreddit}/{$sort}";
        $params = array('limit' => $limit);
        
        $headers = array(
            'Authorization: Bearer ' . $this->access_token,
            'User-Agent: ' . $this->user_agent
        );
        
        $response = $this->make_request($url . '?' . http_build_query($params), null, $headers);
        
        if ($response && isset($response['data']['children'])) {
            return $response['data']['children'];
        }
        
        return false;
    }
    
    public function post_comment($post_fullname, $comment_text) {
        if (!$this->access_token && !$this->authenticate()) {
            RedditBot_Logger::error('reddit_api', 'Cannot authenticate for comment posting');
            return false;
        }
        
        // FIXED: Use the non-OAuth URL for comment posting
        $url = 'https://www.reddit.com/api/comment';
        
        // Ensure we have the correct fullname format
        if (!strpos($post_fullname, 't3_') === 0) {
            $post_fullname = 't3_' . $post_fullname;
        }
        
        // FIXED: Use correct parameter names based on documentation
        $post_data = array(
            'thing_id' => $post_fullname,  // Correct parameter name
            'text' => $comment_text,
            'api_type' => 'json'
        );
        
        $headers = array(
            'Authorization: Bearer ' . $this->access_token,
            'User-Agent: ' . $this->user_agent,
            'Content-Type: application/x-www-form-urlencoded'
        );
        
        RedditBot_Logger::info('reddit_api', "Attempting to post comment to: $post_fullname");
        
        $response = $this->make_request($url, $post_data, $headers);
        
        // Enhanced response handling
        if ($response) {
            RedditBot_Logger::info('reddit_api', 'Raw API response: ' . print_r($response, true));
            
            if (isset($response['json'])) {
                if (isset($response['json']['errors']) && empty($response['json']['errors'])) {
                    RedditBot_Logger::info('reddit_api', "Successfully posted comment to: $post_fullname");
                    return true;
                } else {
                    $errors = isset($response['json']['errors']) ? $response['json']['errors'] : array('Unknown error');
                    RedditBot_Logger::error('reddit_api', "Reddit API errors: " . print_r($errors, true));
                    return false;
                }
            } else {
                RedditBot_Logger::error('reddit_api', "Unexpected response format: " . print_r($response, true));
                return false;
            }
        } else {
            RedditBot_Logger::error('reddit_api', "No response received for comment post to: $post_fullname");
            return false;
        }
    }
    
    public function get_post_details($post_id) {
        if (!$this->access_token && !$this->authenticate()) {
            return false;
        }
        
        // Remove t3_ prefix if present for this endpoint
        $clean_id = str_replace('t3_', '', $post_id);
        $url = "https://oauth.reddit.com/comments/{$clean_id}";
        
        $headers = array(
            'Authorization: Bearer ' . $this->access_token,
            'User-Agent: ' . $this->user_agent
        );
        
        $response = $this->make_request($url, null, $headers);
        
        if ($response && isset($response[0]['data']['children'][0])) {
            return $response[0]['data']['children'][0]['data'];
        }
        
        return false;
    }
    
    private function make_request($url, $post_data = null, $headers = array()) {
        $ch = curl_init();
        
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => $this->user_agent,
            CURLOPT_FOLLOWLOCATION => true
        ));
        
        if ($post_data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        
        // Enhanced logging
        if ($error) {
            RedditBot_Logger::error('reddit_api', "cURL error: $error");
            return false;
        }
        
        RedditBot_Logger::info('reddit_api', "HTTP Code: $http_code, URL: $url");
        
        if ($http_code >= 400) {
            RedditBot_Logger::error('reddit_api', "HTTP error: $http_code - Response: $response");
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            RedditBot_Logger::error('reddit_api', "JSON decode error: " . json_last_error_msg() . " - Raw response: $response");
            return false;
        }
        
        return $decoded;
    }
    
    public function test_connection() {
        if ($this->authenticate()) {
            $headers = array(
                'Authorization: Bearer ' . $this->access_token,
                'User-Agent: ' . $this->user_agent
            );
            
            $response = $this->make_request('https://oauth.reddit.com/api/v1/me', null, $headers);
            
            if ($response && isset($response['name'])) {
                return array('success' => true, 'username' => $response['name']);
            }
        }
        
        return array('success' => false, 'error' => 'Authentication failed');
    }
}