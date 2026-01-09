<?php if (!defined('ABSPATH'))
    exit; ?>
<?php
// Helper functions for this view only
function trackpress_get_action_severity($action_type)
{
    $important_actions = [
        'user_deleted',
        'post_deleted',
        'plugin_activated',
        'plugin_deactivated',
        'theme_changed',
        'option_updated',
        'menu_deleted',
        'comment_deleted',
        'term_deleted',
        'media_deleted'
    ];

    $warning_actions = [
        'post_trashed',
        'comment_trashed',
        'user_profile_updated'
    ];

    if (in_array($action_type, $important_actions)) {
        return 'high';
    } elseif (in_array($action_type, $warning_actions)) {
        return 'medium';
    } else {
        return 'low';
    }
}

function trackpress_get_edit_url($object_type, $object_id)
{
    switch ($object_type) {
        case 'post':
            return admin_url('post.php?post=' . $object_id . '&action=edit');
        case 'user':
            return admin_url('user-edit.php?user_id=' . $object_id);
        case 'attachment':
            return admin_url('post.php?post=' . $object_id . '&action=edit');
        case 'comment':
            return admin_url('comment.php?action=editcomment&c=' . $object_id);
        case 'term':
            // Need to get taxonomy from somewhere - for now return null
            return null;
        default:
            return null;
    }
}
ob_start();
?>
<div class="wrap trackpress-admin-logs">
    <h1><?php echo esc_html__('Admin Actions Logs', 'trackpress'); ?></h1>
    
    <?php if (isset($_GET['deleted']) && $_GET['deleted'] === '1'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('Log entry deleted successfully.', 'trackpress'); ?></p>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['deleted_all']) && $_GET['deleted_all'] === '1'): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html__('All logs deleted successfully.', 'trackpress'); ?></p>
        </div>
    <?php endif; ?>
    <div class="trackpress-admin-actions">
        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=trackpress-admin&action=delete_all&table=admin'), 'trackpress_action', '_wpnonce'); ?>"
            class="button button-danger"
            onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete ALL admin logs? This action cannot be undone.', 'trackpress')); ?>');">
            <?php echo esc_html__('Delete All Logs', 'trackpress'); ?>
        </a>

        <form method="get" action="<?php echo admin_url('admin.php'); ?>" style="display: inline;">
            <input type="hidden" name="page" value="trackpress-admin">
            <input type="text" name="search"
                placeholder="<?php echo esc_attr__('Search users, actions...', 'trackpress'); ?>"
                value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>">
            <input type="submit" class="button" value="<?php echo esc_attr__('Search', 'trackpress'); ?>">
        </form>

        <div class="view-switcher">
            <a href="<?php echo admin_url('admin.php?page=trackpress-admin&view=all'); ?>"
                class="button <?php echo (!isset($_GET['view']) || $_GET['view'] === 'all') ? 'button-primary' : ''; ?>">
                <?php echo esc_html__('All Actions', 'trackpress'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=trackpress-admin&view=important'); ?>"
                class="button <?php echo (isset($_GET['view']) && $_GET['view'] === 'important') ? 'button-primary' : ''; ?>">
                <?php echo esc_html__('Important Only', 'trackpress'); ?>
            </a>
        </div>
    </div>

    <div class="trackpress-stats-bar">
        <?php
        $admin_stats = \TrackPress\Admin_Tracker::get_admin_activity_summary(7);
        ?>
        <div class="stat-item">
            <span class="stat-label"><?php echo esc_html__('Total Logs:', 'trackpress'); ?></span>
            <span class="stat-value"><?php echo esc_html($total_items); ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label"><?php echo esc_html__('Last 7 Days:', 'trackpress'); ?></span>
            <span class="stat-value"><?php echo esc_html($admin_stats['total_actions']); ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label"><?php echo esc_html__('Active Admins:', 'trackpress'); ?></span>
            <span class="stat-value"><?php echo esc_html(count($admin_stats['actions_by_user'])); ?></span>
        </div>
    </div>

    <?php if (!empty($logs)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('Admin User', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Action Type', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Details', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Object', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('IP Address', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Admin Page', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Time', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Actions', 'trackpress'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($log->user_login); ?></strong><br>
                            <small><?php echo esc_html(ucfirst($log->user_role)); ?></small><br>
                            <small>ID: <?php echo esc_html($log->user_id); ?></small>
                        </td>
                        <td>
                            <span
                                class="action-badge severity-<?php echo esc_attr(trackpress_get_action_severity($log->action_type)); ?>">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $log->action_type))); ?>
                            </span>
                        </td>
                        <td>
                            <div class="action-details">
                                <?php echo esc_html(wp_trim_words($log->action_details, 15)); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($log->object_type && $log->object_name): ?>
                                <strong><?php echo esc_html(ucfirst($log->object_type)); ?>:</strong><br>
                                <?php echo esc_html($log->object_name); ?>
                                <?php if ($log->object_id): ?>
                                    <br><small>ID: <?php echo esc_html($log->object_id); ?></small>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($log->ip_address); ?><br>
                            <small><?php echo esc_html($log->http_method); ?></small>
                        </td>
                        <td>
                            <a href="<?php echo esc_url(admin_url($log->admin_page)); ?>" target="_blank">
                                <?php echo esc_html(basename($log->admin_page)); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo esc_html(date_i18n('Y-m-d', strtotime($log->created_at))); ?><br>
                            <small><?php echo esc_html(date_i18n('H:i:s', strtotime($log->created_at))); ?></small>
                        </td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=trackpress-admin&action=delete_single&id=' . $log->id . '&table=admin'), 'trackpress_action', '_wpnonce'); ?>"
                                class="button button-small"
                                onclick="return confirm('<?php echo esc_js(__('Delete this log entry?', 'trackpress')); ?>');">
                                <?php echo esc_html__('Delete', 'trackpress'); ?>
                            </a>
                            <?php if ($log->object_type && $log->object_id):
                                $edit_url = trackpress_get_edit_url($log->object_type, $log->object_id);
                                if ($edit_url): ?>
                                    <a href="<?php echo esc_url($edit_url); ?>" class="button button-small" target="_blank">
                                        <?php echo esc_html__('Edit', 'trackpress'); ?>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                // Make sure $per_page is defined before this point
                $per_page = isset($per_page) ? $per_page : 20;
                $total_pages = ceil($total_items / $per_page);
                if ($total_pages > 1) {
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;', 'trackpress'),
                        'next_text' => __('&raquo;', 'trackpress'),
                        'total' => $total_pages,
                        'current' => isset($current_page) ? $current_page : 1,
                    ]);
                }
                ?>
            </div>
        </div>
    <?php else: ?>
        <div class="notice notice-warning">
            <p><?php echo esc_html__('No admin action logs found.', 'trackpress'); ?></p>
        </div>
    <?php endif;
    ob_end_flush();
    ?>
</div>

<style>
    .trackpress-admin-logs .trackpress-admin-actions {
        margin: 20px 0;
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }

    .trackpress-admin-logs .trackpress-admin-actions .button-danger {
        background: #d63638;
        border-color: #d63638;
        color: white;
    }

    .trackpress-admin-logs .trackpress-admin-actions .button-danger:hover {
        background: #b32d2e;
        border-color: #b32d2e;
    }

    .trackpress-admin-logs .view-switcher {
        display: flex;
        gap: 5px;
        margin-left: auto;
    }

    .trackpress-admin-logs .trackpress-stats-bar {
        background: #f0f0f1;
        border: 1px solid #c3c4c7;
        padding: 10px 15px;
        margin: 15px 0;
        border-radius: 4px;
        display: flex;
        gap: 20px;
    }

    .trackpress-admin-logs .stat-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .trackpress-admin-logs .stat-label {
        font-weight: 500;
    }

    .trackpress-admin-logs .stat-value {
        font-weight: bold;
        color: #2271b1;
    }

    .trackpress-admin-logs .action-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
    }

    .trackpress-admin-logs .severity-high {
        background: #fef0f1;
        color: #8a2d2d;
        border: 1px solid #e0b4b4;
    }

    .trackpress-admin-logs .severity-medium {
        background: #fff8e5;
        color: #8a6d2d;
        border: 1px solid #e0d4b4;
    }

    .trackpress-admin-logs .severity-low {
        background: #f0f6fc;
        color: #0a4b78;
        border: 1px solid #b4d0e0;
    }

    .trackpress-admin-logs .action-details {
        max-width: 300px;
        font-size: 12px;
        line-height: 1.4;
    }

    .trackpress-admin-logs .button-small {
        padding: 2px 8px;
        font-size: 12px;
        height: auto;
        line-height: 1.5;
        margin: 2px 0;
    }
</style>