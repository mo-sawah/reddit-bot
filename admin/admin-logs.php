<div class="wrap">
    <h1>Activity Logs</h1>
    
    <!-- Log Filters -->
    <div class="log-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="reddit-bot-logs">
            
            <select name="level">
                <option value="">All Levels</option>
                <option value="info" <?php selected($_GET['level'] ?? '', 'info'); ?>>Info</option>
                <option value="warning" <?php selected($_GET['level'] ?? '', 'warning'); ?>>Warning</option>
                <option value="error" <?php selected($_GET['level'] ?? '', 'error'); ?>>Error</option>
            </select>
            
            <select name="action">
                <option value="">All Actions</option>
                <option value="reddit_api" <?php selected($_GET['action'] ?? '', 'reddit_api'); ?>>Reddit API</option>
                <option value="openrouter" <?php selected($_GET['action'] ?? '', 'openrouter'); ?>>OpenRouter AI</option>
                <option value="cron" <?php selected($_GET['action'] ?? '', 'cron'); ?>>Cron Jobs</option>
                <option value="queue" <?php selected($_GET['action'] ?? '', 'queue'); ?>>Queue</option>
                <option value="monitor" <?php selected($_GET['action'] ?? '', 'monitor'); ?>>Monitoring</option>
            </select>
            
            <input type="submit" class="button" value="Filter">
            <a href="<?php echo admin_url('admin.php?page=reddit-bot-logs'); ?>" class="button">Clear</a>
            
            <span style="float: right;">
                <button type="button" id="clear-logs" class="button button-secondary">
                    Clear Old Logs (30+ days)
                </button>
                <button type="button" id="refresh-logs" class="button">
                    Refresh
                </button>
            </span>
        </form>
    </div>
    
    <!-- Logs Table -->
    <div class="logs-table">
        <?php if (!empty($logs)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-time">Time</th>
                    <th scope="col" class="column-level">Level</th>
                    <th scope="col" class="column-action">Action</th>
                    <th scope="col" class="column-message">Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr class="log-row log-level-<?php echo $log->level; ?>">
                    <td class="column-time">
                        <span title="<?php echo esc_attr($log->created_at); ?>">
                            <?php echo date('M j, H:i:s', strtotime($log->created_at)); ?>
                        </span>
                    </td>
                    <td class="column-level">
                        <span class="log-level-badge level-<?php echo $log->level; ?>">
                            <?php echo ucfirst($log->level); ?>
                        </span>
                    </td>
                    <td class="column-action">
                        <code><?php echo esc_html($log->action); ?></code>
                    </td>
                    <td class="column-message">
                        <div class="log-message">
                            <?php echo esc_html($log->message); ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php else: ?>
        <div class="no-logs">
            <p>No logs found. Logs will appear here when the bot starts running.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Log Legend -->
    <div class="log-legend">
        <h3>Log Levels</h3>
        <ul>
            <li><span class="log-level-badge level-info">Info</span> - Normal operation messages</li>
            <li><span class="log-level-badge level-warning">Warning</span> - Issues that don't stop operation</li>
            <li><span class="log-level-badge level-error">Error</span> - Problems that prevent normal operation</li>
        </ul>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-refresh logs every 30 seconds if on logs page
    setInterval(function() {
        if (window.location.href.indexOf('reddit-bot-logs') > -1) {
            // Only refresh if no filters are applied
            var urlParams = new URLSearchParams(window.location.search);
            if (!urlParams.get('level') && !urlParams.get('action')) {
                $('#refresh-logs').click();
            }
        }
    }, 30000);
    
    // Refresh logs
    $('#refresh-logs').click(function() {
        location.reload();
    });
    
    // Clear old logs
    $('#clear-logs').click(function() {
        if (confirm('Clear all logs older than 30 days?')) {
            $(this).prop('disabled', true).text('Clearing...');
            
            $.post(ajaxurl, {
                action: 'reddit_bot_clear_logs',
                nonce: '<?php echo wp_create_nonce('reddit_bot_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Old logs cleared successfully.');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }).always(function() {
                $('#clear-logs').prop('disabled', false).text('Clear Old Logs (30+ days)');
            });
        }
    });
    
    // Highlight recent errors
    $('.log-level-error').each(function() {
        var logTime = $(this).find('.column-time span').attr('title');
        var logDate = new Date(logTime);
        var now = new Date();
        var diffMinutes = (now - logDate) / (1000 * 60);
        
        // Highlight errors from last 30 minutes
        if (diffMinutes < 30) {
            $(this).addClass('recent-error');
        }
    });
});
</script>