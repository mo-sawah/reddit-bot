<div class="wrap">
    <h1>Comment Queue Management</h1>
    
    <!-- Queue Statistics -->
    <div class="queue-stats">
        <div class="stats-grid">
            <div class="stat-box">
                <h3><?php echo $queue_stats['total']; ?></h3>
                <p>Total Comments</p>
            </div>
            <div class="stat-box pending">
                <h3><?php echo $queue_stats['pending']; ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-box posted">
                <h3><?php echo $queue_stats['posted']; ?></h3>
                <p>Posted</p>
            </div>
            <div class="stat-box failed">
                <h3><?php echo $queue_stats['failed']; ?></h3>
                <p>Failed</p>
            </div>
        </div>
    </div>
    
    <!-- Queue Actions -->
    <div class="queue-actions">
        <h2>Queue Actions</h2>
        <p>
            <?php if ($queue_stats['failed'] > 0): ?>
            <a href="<?php echo admin_url('admin.php?page=reddit-bot-queue&action=clear_failed'); ?>" 
               class="button button-secondary" 
               onclick="return confirm('Clear all failed queue items?')">
                Clear Failed Items (<?php echo $queue_stats['failed']; ?>)
            </a>
            <?php endif; ?>
            
            <button type="button" id="force-queue-run" class="button">
                Force Queue Processing
            </button>
            
            <button type="button" id="refresh-queue" class="button">
                Refresh
            </button>
        </p>
    </div>
    
    <!-- Recent Queue Items -->
    <div class="queue-list">
        <h2>Recent Queue Items</h2>
        
        <?php if (!empty($recent_queue)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col" class="column-id">ID</th>
                    <th scope="col" class="column-post">Post</th>
                    <th scope="col" class="column-comment">Comment Preview</th>
                    <th scope="col" class="column-status">Status</th>
                    <th scope="col" class="column-created">Created</th>
                    <th scope="col" class="column-posted">Posted</th>
                    <th scope="col" class="column-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_queue as $item): ?>
                <tr class="queue-item status-<?php echo $item->status; ?>">
                    <td class="column-id">
                        <?php echo $item->id; ?>
                    </td>
                    <td class="column-post">
                        <strong>
                            <a href="<?php echo esc_url($item->post_url); ?>" target="_blank">
                                <?php echo esc_html(substr($item->post_title, 0, 50)); ?>
                                <?php echo strlen($item->post_title) > 50 ? '...' : ''; ?>
                            </a>
                        </strong>
                        <br>
                        <small><?php echo esc_html($item->post_id); ?></small>
                    </td>
                    <td class="column-comment">
                        <div class="comment-preview">
                            <?php echo esc_html(substr($item->comment_text, 0, 100)); ?>
                            <?php echo strlen($item->comment_text) > 100 ? '...' : ''; ?>
                        </div>
                        <button type="button" class="button-link view-full-comment" 
                                data-comment="<?php echo esc_attr($item->comment_text); ?>">
                            View Full
                        </button>
                    </td>
                    <td class="column-status">
                        <span class="status-badge status-<?php echo $item->status; ?>">
                            <?php echo ucfirst($item->status); ?>
                        </span>
                    </td>
                    <td class="column-created">
                        <?php echo date('M j, H:i', strtotime($item->created_at)); ?>
                    </td>
                    <td class="column-posted">
                        <?php if ($item->posted_at): ?>
                            <?php echo date('M j, H:i', strtotime($item->posted_at)); ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                    <td class="column-actions">
                        <?php if ($item->status === 'pending'): ?>
                            <button type="button" class="button-link delete-queue-item" 
                                    data-id="<?php echo $item->id; ?>">
                                Remove
                            </button>
                        <?php elseif ($item->status === 'failed'): ?>
                            <button type="button" class="button-link retry-queue-item" 
                                    data-id="<?php echo $item->id; ?>">
                                Retry
                            </button>
                            |
                            <button type="button" class="button-link delete-queue-item" 
                                    data-id="<?php echo $item->id; ?>">
                                Delete
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php else: ?>
        <div class="no-items">
            <p>No queue items found. The bot will add items here when it finds relevant posts to comment on.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Comment Modal -->
<div id="comment-modal" class="reddit-bot-modal" style="display: none;">
    <div class="modal-content">
        <span class="modal-close">&times;</span>
        <h3>Full Comment</h3>
        <div id="modal-comment-text"></div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // View full comment modal
    $('.view-full-comment').click(function() {
        var comment = $(this).data('comment');
        $('#modal-comment-text').text(comment);
        $('#comment-modal').show();
    });
    
    // Close modal
    $('.modal-close, #comment-modal').click(function(e) {
        if (e.target === this) {
            $('#comment-modal').hide();
        }
    });
    
    // Force queue run
    $('#force-queue-run').click(function() {
        if (confirm('Force process the comment queue now?')) {
            $(this).prop('disabled', true).text('Processing...');
            
            $.post(ajaxurl, {
                action: 'reddit_bot_force_queue',
                nonce: '<?php echo wp_create_nonce('reddit_bot_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    alert('Queue processing started. Check logs for results.');
                    location.reload();
                } else {
                    alert('Error: ' + response.data);
                }
            }).always(function() {
                $('#force-queue-run').prop('disabled', false).text('Force Queue Processing');
            });
        }
    });
    
    // Refresh page
    $('#refresh-queue').click(function() {
        location.reload();
    });
    
    // Delete queue item
    $('.delete-queue-item').click(function() {
        var id = $(this).data('id');
        var row = $(this).closest('tr');
        
        if (confirm('Delete this queue item?')) {
            $.post(ajaxurl, {
                action: 'reddit_bot_delete_queue_item',
                id: id,
                nonce: '<?php echo wp_create_nonce('reddit_bot_nonce'); ?>'
            }, function(response) {
                if (response.success) {
                    row.fadeOut();
                } else {
                    alert('Error: ' + response.data);
                }
            });
        }
    });
    
    // Retry queue item
    $('.retry-queue-item').click(function() {
        var id = $(this).data('id');
        var row = $(this).closest('tr');
        
        $.post(ajaxurl, {
            action: 'reddit_bot_retry_queue_item',
            id: id,
            nonce: '<?php echo wp_create_nonce('reddit_bot_nonce'); ?>'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.data);
            }
        });
    });
});
</script>