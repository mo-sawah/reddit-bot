<div class="wrap">
    <h1>Reddit Bot Settings</h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('reddit_bot_settings'); ?>
        
        <table class="form-table">
            <!-- Bot Control -->
            <tr>
                <th scope="row">
                    <label for="bot_enabled">Bot Status</label>
                </th>
                <td>
                    <label>
                        <input type="checkbox" name="bot_enabled" id="bot_enabled" value="1" 
                               <?php checked($settings['bot_enabled'], '1'); ?>>
                        Enable Bot
                    </label>
                    <p class="description">
                        When enabled, the bot will automatically monitor Reddit and post comments.
                    </p>
                </td>
            </tr>
            
            <!-- Reddit API Settings -->
            <tr>
                <th colspan="2">
                    <h2>Reddit API Configuration</h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="reddit_client_id">Client ID</label>
                </th>
                <td>
                    <input type="text" name="reddit_client_id" id="reddit_client_id" 
                           value="<?php echo esc_attr($settings['reddit_client_id']); ?>" 
                           class="regular-text" autocomplete="off">
                    <p class="description">Your Reddit app Client ID</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="reddit_client_secret">Client Secret</label>
                </th>
                <td>
                    <input type="password" name="reddit_client_secret" id="reddit_client_secret" 
                           value="<?php echo esc_attr($settings['reddit_client_secret']); ?>" 
                           class="regular-text" autocomplete="off">
                    <p class="description">Your Reddit app Client Secret</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="reddit_username">Username</label>
                </th>
                <td>
                    <input type="text" name="reddit_username" id="reddit_username" 
                           value="<?php echo esc_attr($settings['reddit_username']); ?>" 
                           class="regular-text" autocomplete="off">
                    <p class="description">Your Reddit username</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="reddit_password">Password</label>
                </th>
                <td>
                    <input type="password" name="reddit_password" id="reddit_password" 
                           value="<?php echo esc_attr($settings['reddit_password']); ?>" 
                           class="regular-text" autocomplete="off">
                    <p class="description">Your Reddit password</p>
                </td>
            </tr>
            
            <tr>
                <th></th>
                <td>
                    <button type="button" id="test-reddit-connection" class="button">
                        Test Reddit Connection
                    </button>
                    <span id="reddit-test-result"></span>
                </td>
            </tr>
            
            <!-- AI Settings -->
            <tr>
                <th colspan="2">
                    <h2>AI Configuration (OpenRouter)</h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="openrouter_api_key">API Key</label>
                </th>
                <td>
                    <input type="password" name="openrouter_api_key" id="openrouter_api_key" 
                           value="<?php echo esc_attr($settings['openrouter_api_key']); ?>" 
                           class="regular-text" autocomplete="off">
                    <p class="description">Your OpenRouter API key</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="ai_model">AI Model</label>
                </th>
                <td>
                    <select name="ai_model" id="ai_model">
                        <?php foreach ($ai_models as $model_id => $model_name): ?>
                        <option value="<?php echo esc_attr($model_id); ?>" 
                                <?php selected($settings['ai_model'], $model_id); ?>>
                            <?php echo esc_html($model_name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Choose the AI model for comment generation</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="comment_style">Comment Style</label>
                </th>
                <td>
                    <select name="comment_style" id="comment_style">
                        <option value="helpful" <?php selected($settings['comment_style'], 'helpful'); ?>>Helpful & Supportive</option>
                        <option value="casual" <?php selected($settings['comment_style'], 'casual'); ?>>Casual & Friendly</option>
                        <option value="expert" <?php selected($settings['comment_style'], 'expert'); ?>>Expert & Technical</option>
                        <option value="encouraging" <?php selected($settings['comment_style'], 'encouraging'); ?>>Encouraging & Positive</option>
                    </select>
                </td>
            </tr>
            
            <tr>
                <th></th>
                <td>
                    <button type="button" id="test-ai-connection" class="button">
                        Test AI Connection
                    </button>
                    <span id="ai-test-result"></span>
                </td>
            </tr>
            
            <!-- Targeting Settings -->
            <tr>
                <th colspan="2">
                    <h2>Targeting Configuration</h2>
                </th>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="target_subreddits">Target Subreddits</label>
                </th>
                <td>
                    <input type="text" name="target_subreddits" id="target_subreddits" 
                           value="<?php echo esc_attr($settings['target_subreddits']); ?>" 
                           class="large-text">
                    <p class="description">
                        Comma-separated list of subreddits to monitor (e.g., wordpress,webdev,entrepreneur)
                    </p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="website_url">Website URL</label>
                </th>
                <td>
                    <input type="url" name="website_url" id="website_url" 
                           value="<?php echo esc_attr($settings['website_url']); ?>" 
                           class="regular-text">
                    <p class="description">Your website URL to include in comments</p>
                </td>
            </tr>
            
            <tr>
                <th scope="row">
                    <label for="max_comments_per_hour">Rate Limit</label>
                </th>
                <td>
                    <input type="number" name="max_comments_per_hour" id="max_comments_per_hour" 
                           value="<?php echo esc_attr($settings['max_comments_per_hour']); ?>" 
                           min="1" max="20" class="small-text">
                    comments per hour
                    <p class="description">
                        Maximum comments to post per hour (recommended: 3-5 to avoid being flagged)
                    </p>
                </td>
            </tr>
        </table>
        
        <?php submit_button(); ?>
    </form>
    
    <!-- Help Section -->
    <div class="reddit-bot-help">
        <h2>Need Help Setting Up?</h2>
        <div class="help-section">
            <h3>Reddit API Setup</h3>
            <ol>
                <li>Go to <a href="https://www.reddit.com/prefs/apps" target="_blank">Reddit Apps</a></li>
                <li>Click "Create App" or "Create Another App"</li>
                <li>Choose "script" as the app type</li>
                <li>Fill in name and description</li>
                <li>Set redirect URI to: http://localhost:8080</li>
                <li>Copy the Client ID and Secret</li>
            </ol>
        </div>
        
        <div class="help-section">
            <h3>OpenRouter API Setup</h3>
            <ol>
                <li>Go to <a href="https://openrouter.ai" target="_blank">OpenRouter.ai</a></li>
                <li>Sign up for an account</li>
                <li>Add credits to your account</li>
                <li>Generate an API key</li>
                <li>Copy the API key above</li>
            </ol>
        </div>
    </div>
</div>