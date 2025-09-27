<?php
class RedditBot_OpenRouter {
    
    private $api_key;
    private $base_url;
    private $default_model;
    
    public function __construct() {
        $this->api_key = RedditBot_Config::get_setting('openrouter_api_key');
        $this->base_url = 'https://openrouter.ai/api/v1';
        $this->default_model = RedditBot_Config::get_setting('ai_model', 'anthropic/claude-3-haiku');
    }
    
    public function generate_completion($prompt, $model = null, $max_tokens = 300) {
        if (!$this->api_key) {
            RedditBot_Logger::error('openrouter', 'API key not configured');
            return false;
        }
        
        $model = $model ?: $this->default_model;
        
        $data = array(
            'model' => $model,
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
            'top_p' => 0.9
        );
        
        $headers = array(
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'HTTP-Referer: ' . get_site_url(),
            'X-Title: Reddit Bot WordPress Plugin'
        );
        
        $response = $this->make_request('/chat/completions', $data, $headers);
        
        if ($response && isset($response['choices'][0]['message']['content'])) {
            $content = trim($response['choices'][0]['message']['content']);
            RedditBot_Logger::info('openrouter', "Generated completion using model: $model");
            return $content;
        }
        
        RedditBot_Logger::error('openrouter', 'Failed to generate completion');
        return false;
    }
    
    public function test_connection() {
        if (!$this->api_key) {
            return array('success' => false, 'message' => 'API key not configured');
        }
        
        $test_prompt = "Say 'Hello' in exactly one word.";
        
        $result = $this->generate_completion($test_prompt, null, 10);
        
        if ($result) {
            return array('success' => true, 'message' => 'OpenRouter connection successful', 'response' => $result);
        }
        
        return array('success' => false, 'message' => 'Failed to connect to OpenRouter API');
    }
    
    public function get_available_models() {
        $headers = array(
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json'
        );
        
        $response = $this->make_request('/models', null, $headers, 'GET');
        
        if ($response && isset($response['data'])) {
            return $response['data'];
        }
        
        return false;
    }
    
    public function estimate_cost($prompt, $model = null) {
        $model = $model ?: $this->default_model;
        
        // Rough token estimation (1 token ≈ 4 characters)
        $estimated_tokens = strlen($prompt) / 4;
        
        // Basic cost estimates per 1K tokens (these change frequently)
        $model_costs = array(
            'anthropic/claude-3-haiku' => 0.00025,
            'anthropic/claude-3-sonnet' => 0.003,
            'openai/gpt-3.5-turbo' => 0.0015,
            'openai/gpt-4' => 0.03,
            'meta-llama/llama-2-70b-chat' => 0.0007
        );
        
        $cost_per_1k = isset($model_costs[$model]) ? $model_costs[$model] : 0.001;
        
        return ($estimated_tokens / 1000) * $cost_per_1k;
    }
    
    private function make_request($endpoint, $data = null, $headers = array(), $method = 'POST') {
        $url = $this->base_url . $endpoint;
        
        $ch = curl_init();
        
        $curl_options = array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => true
        );
        
        if ($method === 'POST' && $data) {
            $curl_options[CURLOPT_POST] = true;
            $curl_options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
        
        curl_setopt_array($ch, $curl_options);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            RedditBot_Logger::error('openrouter', "cURL error: $error");
            return false;
        }
        
        if ($http_code >= 400) {
            RedditBot_Logger::error('openrouter', "HTTP error: $http_code - Response: $response");
            return false;
        }
        
        $decoded = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            RedditBot_Logger::error('openrouter', "JSON decode error: " . json_last_error_msg());
            return false;
        }
        
        return $decoded;
    }
}