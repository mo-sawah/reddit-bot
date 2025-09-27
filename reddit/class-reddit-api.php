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
            RedditBot_Logger::error('reddit_api', 'Failed to authenticate with Reddit');
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
            return false;
        }
        
        $url = 'https://oauth.reddit.com/api/comment';
        
        $post_data = array(
            'thing_id' => $post_fullname,
            'text' => $comment_text,
            'api_type' => 'json'
        );
        
        $headers = array(
            'Authorization: Bearer ' . $this->access_token,
            'User-Agent: ' . $this->user_agent
        );
        
        $response = $this->make_request($url, $post_data, $headers);
        
        if ($response && isset($response['json']['errors']) && empty($response['json']['errors'])) {
            RedditBot_Logger::info('reddit_api', "Successfully posted comment to: $post_fullname");
            return true;
        } else {
            $error = isset($response['json']['errors'][0]) ? $response['json']['errors'][0] : 'Unknown error';
            RedditBot_Logger::error('reddit_api', "Failed to post comment: " . print_r($error, true));
            return false;
        }
    }
    
    public function get_post_details($post_id) {
        if (!$this->access_token && !$this->authenticate()) {
            return false;
        }
        
        $url = "https://oauth.reddit.com/comments/{$post_id}";
        
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
            CURLOPT_USERAGENT => $this->user_agent
        ));
        
        if ($post_data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            RedditBot_Logger::error('reddit_api', "cURL error: $error");
            return false;
        }
        
        if ($http_code >= 400) {
            RedditBot_Logger::error('reddit_api', "HTTP error: $http_code - Response: $response");
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            RedditBot_Logger::error('reddit_api', "JSON decode error: " . json_last_error_msg());
            return false;
        }
        
        return $decoded;
    }
    
    public function test_connection() {
        if ($this->authenticate()) {
            // Try to get user info as a test
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