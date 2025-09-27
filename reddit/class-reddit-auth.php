<?php
class RedditBot_Auth {
    
    public static function validate_credentials($client_id, $client_secret, $username, $password) {
        $test_api = new RedditBot_API_Test($client_id, $client_secret, $username, $password);
        return $test_api->test_connection();
    }
    
    public static function get_auth_url() {
        // For future OAuth flow implementation
        $client_id = RedditBot_Config::get_setting('reddit_client_id');
        $redirect_uri = admin_url('admin.php?page=reddit-bot-settings&action=oauth');
        $state = wp_create_nonce('reddit_oauth');
        
        $params = array(
            'client_id' => $client_id,
            'response_type' => 'code',
            'state' => $state,
            'redirect_uri' => $redirect_uri,
            'duration' => 'permanent',
            'scope' => 'submit,read'
        );
        
        return 'https://www.reddit.com/api/v1/authorize?' . http_build_query($params);
    }
    
    public static function generate_reddit_app_instructions() {
        return array(
            'steps' => array(
                '1. Go to https://www.reddit.com/prefs/apps',
                '2. Click "Create App" or "Create Another App"',
                '3. Choose "script" as the app type',
                '4. Fill in the form:',
                '   - Name: Your WordPress Bot',
                '   - Description: WordPress traffic generation bot',
                '   - Redirect URI: http://localhost:8080 (not used for script apps)',
                '5. Click "Create app"',
                '6. Copy the Client ID (under the app name)',
                '7. Copy the Client Secret'
            ),
            'notes' => array(
                'The Client ID is the random string under your app name',
                'The Client Secret is the longer string labeled "secret"',
                'Keep these credentials secure and never share them publicly',
                'Use your regular Reddit username and password'
            )
        );
    }
}

// Temporary test class for validation
class RedditBot_API_Test {
    private $client_id;
    private $client_secret;
    private $username;
    private $password;
    
    public function __construct($client_id, $client_secret, $username, $password) {
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        $this->username = $username;
        $this->password = $password;
    }
    
    public function test_connection() {
        $auth_url = 'https://www.reddit.com/api/v1/access_token';
        
        $post_data = array(
            'grant_type' => 'password',
            'username' => $this->username,
            'password' => $this->password
        );
        
        $headers = array(
            'Authorization: Basic ' . base64_encode($this->client_id . ':' . $this->client_secret),
            'User-Agent: TestBot/1.0'
        );
        
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $auth_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($post_data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 15
        ));
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            $data = json_decode($response, true);
            if (isset($data['access_token'])) {
                return array('success' => true, 'message' => 'Credentials are valid');
            }
        }
        
        return array('success' => false, 'message' => 'Invalid credentials or API error');
    }
}