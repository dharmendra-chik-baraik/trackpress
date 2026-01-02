<?php if (!defined('ABSPATH'))
    exit; ?>
<div class="wrap trackpress-users">
    <h1><?php echo esc_html__('User Activity Logs', 'trackpress'); ?></h1>

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
        <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=trackpress-users&action=delete_all&table=users'), 'trackpress_action', '_wpnonce'); ?>"
            class="button button-danger"
            onclick="return confirm('<?php echo esc_js(__('Are you sure you want to delete ALL user logs? This action cannot be undone.', 'trackpress')); ?>');">
            <?php echo esc_html__('Delete All Logs', 'trackpress'); ?>
        </a>

        <form method="get" action="<?php echo admin_url('admin.php'); ?>" style="display: inline;">
            <input type="hidden" name="page" value="trackpress-users">
            <input type="text" name="search"
                placeholder="<?php echo esc_attr__('Search users, actions...', 'trackpress'); ?>"
                value="<?php echo isset($_GET['search']) ? esc_attr($_GET['search']) : ''; ?>">
            <input type="submit" class="button" value="<?php echo esc_attr__('Search', 'trackpress'); ?>">
        </form>
    </div>

    <div class="trackpress-stats-bar">
        <div class="stat-item">
            <span class="stat-label"><?php echo esc_html__('Total Logs:', 'trackpress'); ?></span>
            <span class="stat-value"><?php echo esc_html($total_items); ?></span>
        </div>
        <div class="stat-item">
            <span class="stat-label"><?php echo esc_html__('Showing:', 'trackpress'); ?></span>
            <span class="stat-value"><?php echo esc_html(min($per_page, $total_items)); ?>
                <?php echo esc_html__('per page', 'trackpress'); ?></span>
        </div>
    </div>

    <?php if (!empty($logs)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php echo esc_html__('User', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Action', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Details', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('IP Address', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Page/URL', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Time', 'trackpress'); ?></th>
                    <th><?php echo esc_html__('Actions', 'trackpress'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log):
                    $action_details = maybe_unserialize($log->action_details);
                    $page_title = is_array($action_details) && isset($action_details['page_title'])
                        ? $action_details['page_title']
                        : basename($log->page_url);
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($log->user_login); ?></strong><br>
                            <small><?php echo esc_html($log->user_email); ?></small><br>
                            <small>ID: <?php echo esc_html($log->user_id); ?></small>
                        </td>
                        <td>
                            <span class="action-badge action-<?php echo esc_attr($log->action_type); ?>">
                                <?php echo esc_html(ucwords(str_replace('_', ' ', $log->action_type))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (is_array($action_details)): ?>
                                <div class="action-details">
                                    <?php
                                    $display_details = [];
                                    foreach ($action_details as $key => $value) {
                                        if ($key !== 'page_title' && $value) {
                                            $display_details[] = '<strong>' . esc_html($key) . ':</strong> ' . esc_html($value);
                                        }
                                    }
                                    echo implode('<br>', $display_details);
                                    ?>
                                </div>
                            <?php else: ?>
                                <?php echo esc_html(wp_trim_words($log->action_details, 10)); ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html($log->ip_address); ?><br>
                            <small><?php echo esc_html($log->http_method); ?></small>
                        </td>
                        <td>
                            <a href="<?php echo esc_url($log->page_url); ?>" target="_blank"
                                title="<?php echo esc_attr($log->page_url); ?>">
                                <?php echo esc_html(wp_trim_words($page_title, 5)); ?>
                            </a>
                            <?php if ($log->referrer_url): ?>
                                <br>
                                <small title="<?php echo esc_attr($log->referrer_url); ?>">
                                    <?php echo esc_html__('From:', 'trackpress'); ?>
                                    <?php echo esc_html(wp_trim_words(basename($log->referrer_url), 3)); ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo esc_html(date_i18n('Y-m-d', strtotime($log->created_at))); ?><br>
                            <small><?php echo esc_html(date_i18n('H:i:s', strtotime($log->created_at))); ?></small>
                        </td>
                        <td>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=trackpress-users&action=delete_single&id=' . $log->id . '&table=users'), 'trackpress_action', '_wpnonce'); ?>"
                                class="button button-small"
                                onclick="return confirm('<?php echo esc_js(__('Delete this log entry?', 'trackpress')); ?>');">
                                <?php echo esc_html__('Delete', 'trackpress'); ?>
                            </a>
                            <a href="<?php echo esc_url($log->page_url); ?>" target="_blank" class="button button-small">
                                <?php echo esc_html__('Visit', 'trackpress'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($total_items / $per_page);
                if ($total_pages > 1) {
                    echo paginate_links([
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('&laquo;', 'trackpress'),
                        'next_text' => __('&raquo;', 'trackpress'),
                        'total' => $total_pages,
                        'current' => $current_page,
                    ]);
                }
                ?>
            </div>
        </div>
    <?php else: ?>
        <div class="notice notice-warning">
            <p><?php echo esc_html__('No user activity logs found.', 'trackpress'); ?></p>
        </div>
    <?php endif; ?>
</div>

<style>
    .trackpress-users .trackpress-admin-actions {
        margin: 20px 0;
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .trackpress-users .trackpress-admin-actions .button-danger {
        background: #d63638;
        border-color: #d63638;
        color: white;
    }

    .trackpress-users .trackpress-admin-actions .button-danger:hover {
        background: #b32d2e;
        border-color: #b32d2e;
    }

    .trackpress-users .trackpress-stats-bar {
        background: #f0f0f1;
        border: 1px solid #c3c4c7;
        padding: 10px 15px;
        margin: 15px 0;
        border-radius: 4px;
        display: flex;
        gap: 20px;
    }

    .trackpress-users .stat-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .trackpress-users .stat-label {
        font-weight: 500;
    }

    .trackpress-users .stat-value {
        font-weight: bold;
        color: #2271b1;
    }

    .trackpress-users .action-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: 500;
        text-transform: uppercase;
    }

    .trackpress-users .action-page_view {
        background: #e8f4fd;
        color: #135e96;
    }

    .trackpress-users .action-user_login,
    .trackpress-users .action-user_logout {
        background: #f0f6fc;
        color: #0a4b78;
    }

    .trackpress-users .action-comment_submitted {
        background: #f0f9f0;
        color: #1a531b;
    }

    .trackpress-users .action-search_query {
        background: #f9f0ff;
        color: #5a2d8a;
    }

    .trackpress-users .action-details {
        max-width: 300px;
        font-size: 12px;
        line-height: 1.4;
    }

    .trackpress-users .button-small {
        padding: 2px 8px;
        font-size: 12px;
        height: auto;
        line-height: 1.5;
        margin: 2px 0;
    }
</style>