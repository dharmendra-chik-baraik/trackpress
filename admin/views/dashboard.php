<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap trackpress-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="trackpress-stats-container">
        <div class="trackpress-stat-box">
            <h3><?php _e('Today\'s Activity', 'trackpress'); ?></h3>
            <div class="stat-numbers">
                <div class="stat-item">
                    <span class="stat-label"><?php _e('User Logs:', 'trackpress'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['today_users_logs']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Visitor Logs:', 'trackpress'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['today_visitors_logs']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Admin Actions:', 'trackpress'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['today_admin_logs']); ?></span>
                </div>
            </div>
        </div>
        
        <div class="trackpress-stat-box">
            <h3><?php _e('Total Records', 'trackpress'); ?></h3>
            <div class="stat-numbers">
                <div class="stat-item">
                    <span class="stat-label"><?php _e('User Logs:', 'trackpress'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['total_users_logs']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Visitor Logs:', 'trackpress'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['total_visitors_logs']); ?></span>
                </div>
                <div class="stat-item">
                    <span class="stat-label"><?php _e('Admin Logs:', 'trackpress'); ?></span>
                    <span class="stat-value"><?php echo esc_html($stats['total_admin_logs']); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <div class="trackpress-recent-activity">
        <div class="recent-section">
            <h3><?php _e('Recent User Activity', 'trackpress'); ?></h3>
            <?php if (!empty($recent_users)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('User', 'trackpress'); ?></th>
                            <th><?php _e('Action', 'trackpress'); ?></th>
                            <th><?php _e('Page', 'trackpress'); ?></th>
                            <th><?php _e('IP Address', 'trackpress'); ?></th>
                            <th><?php _e('Time', 'trackpress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_users as $log) : 
                            $action_details = maybe_unserialize($log->action_details);
                            $page_title = is_array($action_details) && isset($action_details['page_title']) 
                                ? $action_details['page_title'] 
                                : basename($log->page_url);
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($log->user_login); ?></strong><br>
                                <small><?php echo esc_html($log->user_email); ?></small>
                            </td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $log->action_type))); ?></td>
                            <td>
                                <a href="<?php echo esc_url($log->page_url); ?>" target="_blank" title="<?php echo esc_attr($page_title); ?>">
                                    <?php echo esc_html(wp_trim_words($page_title, 5)); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($log->ip_address); ?></td>
                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No user activity recorded yet.', 'trackpress'); ?></p>
            <?php endif; ?>
            <p><a href="<?php echo admin_url('admin.php?page=trackpress-users'); ?>" class="button"><?php _e('View All User Logs', 'trackpress'); ?></a></p>
        </div>
        
        <div class="recent-section">
            <h3><?php _e('Recent Visitor Activity', 'trackpress'); ?></h3>
            <?php if (!empty($recent_visitors)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Visitor', 'trackpress'); ?></th>
                            <th><?php _e('Action', 'trackpress'); ?></th>
                            <th><?php _e('Page', 'trackpress'); ?></th>
                            <th><?php _e('IP Address', 'trackpress'); ?></th>
                            <th><?php _e('Device', 'trackpress'); ?></th>
                            <th><?php _e('Time', 'trackpress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_visitors as $log) : 
                            $action_details = maybe_unserialize($log->action_details);
                            $page_title = is_array($action_details) && isset($action_details['page_title']) 
                                ? $action_details['page_title'] 
                                : basename($log->page_url);
                        ?>
                        <tr>
                            <td><?php echo esc_html(substr($log->visitor_hash, 0, 8) . '...'); ?></td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $log->action_type))); ?></td>
                            <td>
                                <a href="<?php echo esc_url($log->page_url); ?>" target="_blank" title="<?php echo esc_attr($page_title); ?>">
                                    <?php echo esc_html(wp_trim_words($page_title, 5)); ?>
                                </a>
                            </td>
                            <td><?php echo esc_html($log->ip_address); ?></td>
                            <td><?php echo esc_html($log->device_type); ?><br><small><?php echo esc_html($log->browser); ?></small></td>
                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No visitor activity recorded yet.', 'trackpress'); ?></p>
            <?php endif; ?>
            <p><a href="<?php echo admin_url('admin.php?page=trackpress-visitors'); ?>" class="button"><?php _e('View All Visitor Logs', 'trackpress'); ?></a></p>
        </div>
        
        <div class="recent-section">
            <h3><?php _e('Recent Admin Actions', 'trackpress'); ?></h3>
            <?php if (!empty($recent_admin)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Admin User', 'trackpress'); ?></th>
                            <th><?php _e('Action', 'trackpress'); ?></th>
                            <th><?php _e('Details', 'trackpress'); ?></th>
                            <th><?php _e('Object', 'trackpress'); ?></th>
                            <th><?php _e('Time', 'trackpress'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_admin as $log) : ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($log->user_login); ?></strong><br>
                                <small><?php echo esc_html($log->user_role); ?></small>
                            </td>
                            <td><?php echo esc_html(ucwords(str_replace('_', ' ', $log->action_type))); ?></td>
                            <td><?php echo esc_html(wp_trim_words($log->action_details, 10)); ?></td>
                            <td>
                                <?php if ($log->object_type && $log->object_name) : ?>
                                    <?php echo esc_html($log->object_type); ?>: <?php echo esc_html($log->object_name); ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date_i18n('Y-m-d H:i:s', strtotime($log->created_at))); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php _e('No admin actions recorded yet.', 'trackpress'); ?></p>
            <?php endif; ?>
            <p><a href="<?php echo admin_url('admin.php?page=trackpress-admin'); ?>" class="button"><?php _e('View All Admin Logs', 'trackpress'); ?></a></p>
        </div>
    </div>
</div>

<style>
.trackpress-dashboard {
    max-width: 1200px;
}
.trackpress-stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin: 20px 0;
}
.trackpress-stat-box {
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
    padding: 20px;
}
.trackpress-stat-box h3 {
    margin-top: 0;
    border-bottom: 1px solid #f0f0f0;
    padding-bottom: 10px;
}
.stat-numbers {
    display: grid;
    gap: 10px;
}
.stat-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
    border-bottom: 1px solid #f5f5f5;
}
.stat-item:last-child {
    border-bottom: none;
}
.stat-label {
    font-weight: 500;
}
.stat-value {
    font-weight: bold;
    font-size: 1.2em;
    color: #0073aa;
}
.trackpress-recent-activity {
    margin-top: 30px;
}
.recent-section {
    margin-bottom: 30px;
    padding: 20px;
    background: #fff;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}
.recent-section h3 {
    margin-top: 0;
}
</style>