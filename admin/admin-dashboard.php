<div class="wrap">
    <h1>Reddit Bot Dashboard</h1>
    
    <div class="reddit-bot-dashboard">
        <!-- Status Cards -->
        <div class="status-cards">
            <div class="status-card <?php echo $stats['bot_enabled'] ? 'enabled' : 'disabled'; ?>">
                <h3>Bot Status</h3>
                <p class="status-text">
                    <?php echo $stats['bot_enabled'] ? 'ENABLED' : 'DISABLED'; ?>
                </p>
                <p class="status-desc">
                    <?php if ($stats['bot_enabled']): ?>
                        Bot is actively monitoring and posting
                    <?php else: ?>
                        Bot is currently paused
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="status-card">
                <h3>Queue Status</h3>
                <p class="status-number"><?php echo $stats['queue_pending']; ?></p>
                <p class="status-desc">Comments pending</p>
                <small>
                    Total: <?php echo $stats['queue_total']; ?> | 
                    Posted: <?php echo $stats['queue_posted']; ?> | 
                    Failed: <?php echo $stats['queue_failed']; ?>
                </small>
            </div>
            
            <div class="status-card">
                <h3>AI Usage (24h)</h3>
                <p class="status-number"><?php echo $stats['ai_generated_24h']; ?></p>
                <p class="status-desc">Comments generated</p>
                <small>Est. Cost: $<?php echo $stats['estimated_cost_24h']; ?></small>
            </div>
        </div>
        
        <!-- Schedule Info -->
        <div class="schedule-info">
            <h3>Next Scheduled Runs</h3>
            <table class="widefat">
                <tr>
                    <td><strong>Post Monitoring:</strong></td>
                    <td>
                        <?php 
                        if ($stats['next_monitor_run']) {
                            echo date('Y-m-d H:i:s', $stats['next_monitor_run']);
                        } else {
                            echo 'Not scheduled';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td><strong>Queue Processing:</strong></td>
                    <td>
                        <?php 
                        if ($stats['next_queue_run']) {
                            echo date('Y-m-d H:i:s', $stats['next_queue_run']);
                        } else {
                            echo 'Not scheduled';
                        }
                        ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>Quick Actions</h3>
            <p>
                <a href="<?php echo admin_url('admin.php?page=reddit-bot-settings'); ?>" class="button button-primary">
                    Configure Settings
                </a>
                <a href="<?php echo admin_url('admin.php?page=reddit-bot-queue'); ?>" class="button">
                    View Queue
                </a>
                <a href="<?php echo admin_url('admin.php?page=reddit-bot-logs'); ?>" class="button">
                    View Logs
                </a>
            </p>
            
            <?php if (!$stats['bot_enabled']): ?>
            <div class="notice notice-warning">
                <p><strong>Bot is disabled.</strong> Go to Settings to enable the bot and configure your API keys.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Activity -->
        <div class="recent-activity">
            <h3>Recent Activity</h3>
            <?php
            $recent_logs = RedditBot_Logger::get_recent_logs(10);
            if ($recent_logs): ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Action</th>
                            <th>Message</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_logs as $log): ?>
                        <tr>
                            <td><?php echo date('H:i:s', strtotime($log->created_at)); ?></td>
                            <td><?php echo esc_html($log->action); ?></td>
                            <td><?php echo esc_html(substr($log->message, 0, 80)); ?><?php echo strlen($log->message) > 80 ? '...' : ''; ?></td>
                            <td><span class="log-level log-<?php echo $log->level; ?>"><?php echo ucfirst($log->level); ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No recent activity.</p>
            <?php endif; ?>
        </div>
    </div>
</div>