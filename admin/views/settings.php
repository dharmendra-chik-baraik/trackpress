<?php if (!defined('ABSPATH')) exit; 
$settings = \TrackPress\Admin\Admin_Pages::get_settings();
$user_roles = wp_roles()->get_names();
?>
<div class="wrap trackpress-settings">
    <h1><?php echo esc_html__('TrackPress Settings', 'trackpress'); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('trackpress_settings_nonce'); ?>
        
        <div class="trackpress-settings-container">
            <div class="trackpress-settings-main">
                <h2 class="title"><?php echo esc_html__('Tracking Settings', 'trackpress'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="track_logged_in"><?php echo esc_html__('Track Logged-in Users', 'trackpress'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="track_logged_in" name="track_logged_in" value="1" <?php checked($settings['track_logged_in'], 1); ?>>
                            <p class="description"><?php echo esc_html__('Track activities of logged-in users', 'trackpress'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="track_visitors"><?php echo esc_html__('Track Visitors', 'trackpress'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="track_visitors" name="track_visitors" value="1" <?php checked($settings['track_visitors'], 1); ?>>
                            <p class="description"><?php echo esc_html__('Track activities of visitors (non-logged-in users)', 'trackpress'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="track_admin"><?php echo esc_html__('Track Admin Actions', 'trackpress'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="track_admin" name="track_admin" value="1" <?php checked($settings['track_admin'], 1); ?>>
                            <p class="description"><?php echo esc_html__('Track actions performed in WordPress admin area', 'trackpress'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label><?php echo esc_html__('Skip Tracking for Roles', 'trackpress'); ?></label>
                        </th>
                        <td>
                            <fieldset>
                                <?php foreach ($user_roles as $role_key => $role_name) : ?>
                                <label>
                                    <input type="checkbox" name="skip_roles[]" value="<?php echo esc_attr($role_key); ?>" 
                                           <?php checked(in_array($role_key, $settings['skip_roles'])); ?>>
                                    <?php echo esc_html($role_name); ?>
                                </label><br>
                                <?php endforeach; ?>
                            </fieldset>
                            <p class="description"><?php echo esc_html__('Users with these roles will not be tracked', 'trackpress'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="cleanup_days"><?php echo esc_html__('Auto-cleanup Days', 'trackpress'); ?></label>
                        </th>
                        <td>
                            <input type="number" id="cleanup_days" name="cleanup_days" value="<?php echo esc_attr($settings['cleanup_days']); ?>" min="0" max="365" class="small-text">
                            <p class="description"><?php echo esc_html__('Delete logs older than this many days (0 = never delete)', 'trackpress'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <h2 class="title"><?php echo esc_html__('Exclusion Settings', 'trackpress'); ?></h2>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="exclude_pages"><?php echo esc_html__('Exclude Pages', 'trackpress'); ?></label>
                        </th>
                        <td>
                            <textarea id="exclude_pages" name="exclude_pages" rows="5" class="large-text code"><?php echo esc_textarea(is_array($settings['exclude_pages']) ? implode("\n", $settings['exclude_pages']) : $settings['exclude_pages']); ?></textarea>
                            <p class="description"><?php echo esc_html__('Enter URLs or paths to exclude from tracking (one per line). Example: /wp-admin/, /wp-login.php', 'trackpress'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="exclude_ips"><?php echo esc_html__('Exclude IP Addresses', 'trackpress'); ?></label>
                        </th>
                        <td>
                            <textarea id="exclude_ips" name="exclude_ips" rows="5" class="large-text code"><?php echo esc_textarea(is_array($settings['exclude_ips']) ? implode("\n", $settings['exclude_ips']) : $settings['exclude_ips']); ?></textarea>
                            <p class="description"><?php echo esc_html__('Enter IP addresses to exclude from tracking (one per line). Example: 127.0.0.1, 192.168.1.1', 'trackpress'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
            
            <div class="trackpress-settings-sidebar">
                <div class="postbox">
                    <h3 class="hndle"><?php echo esc_html__('Quick Actions', 'trackpress'); ?></h3>
                    <div class="inside">
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=trackpress'); ?>" class="button button-primary">
                                <?php echo esc_html__('View Dashboard', 'trackpress'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=trackpress-users'); ?>" class="button">
                                <?php echo esc_html__('User Logs', 'trackpress'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=trackpress-visitors'); ?>" class="button">
                                <?php echo esc_html__('Visitor Logs', 'trackpress'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo admin_url('admin.php?page=trackpress-admin'); ?>" class="button">
                                <?php echo esc_html__('Admin Logs', 'trackpress'); ?>
                            </a>
                        </p>
                    </div>
                </div>
                
                <div class="postbox">
                    <h3 class="hndle"><?php echo esc_html__('Database Info', 'trackpress'); ?></h3>
                    <div class="inside">
                        <?php
                        global $wpdb;
                        $tables = [
                            $wpdb->prefix . 'trackpress_users_logs',
                            $wpdb->prefix . 'trackpress_visitors_logs',
                            $wpdb->prefix . 'trackpress_admin_logs'
                        ];
                        
                        foreach ($tables as $table) {
                            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table");
                            $size = $wpdb->get_var("SELECT ROUND((data_length + index_length) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "' AND table_name = '$table'");
                            
                            echo '<p><strong>' . esc_html(str_replace($wpdb->prefix, '', $table)) . ':</strong><br>';
                            echo esc_html__('Records:', 'trackpress') . ' ' . esc_html($count) . '<br>';
                            echo esc_html__('Size:', 'trackpress') . ' ' . ($size ? esc_html($size) . ' MB' : esc_html__('N/A', 'trackpress'));
                            echo '</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="postbox">
                    <h3 class="hndle"><?php echo esc_html__('Export Logs', 'trackpress'); ?></h3>
                    <div class="inside">
                        <p><?php echo esc_html__('Export logs in CSV format:', 'trackpress'); ?></p>
                        <p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=trackpress-settings&export=users'), 'trackpress_export', '_wpnonce'); ?>" class="button">
                                <?php echo esc_html__('Export User Logs', 'trackpress'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=trackpress-settings&export=visitors'), 'trackpress_export', '_wpnonce'); ?>" class="button">
                                <?php echo esc_html__('Export Visitor Logs', 'trackpress'); ?>
                            </a>
                        </p>
                        <p>
                            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=trackpress-settings&export=admin'), 'trackpress_export', '_wpnonce'); ?>" class="button">
                                <?php echo esc_html__('Export Admin Logs', 'trackpress'); ?>
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="trackpress_save_settings" class="button-primary" value="<?php echo esc_attr__('Save Settings', 'trackpress'); ?>">
        </p>
    </form>
</div>

<style>
.trackpress-settings-container {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 20px;
    margin: 20px 0;
}

.trackpress-settings-main {
    background: #fff;
    padding: 20px;
    border: 1px solid #ccd0d4;
    border-radius: 4px;
}

.trackpress-settings-sidebar .postbox {
    margin-bottom: 20px;
    padding: 20px;
}

.trackpress-settings-sidebar .postbox .inside {
    padding: 10px;
}

.trackpress-settings-sidebar .button {
    width: 100%;
    margin-bottom: 5px;
    text-align: center;
}

.trackpress-settings-sidebar .button-primary {
    background: #2271b1;
    border-color: #2271b1;
    color: white;
}

@media (max-width: 1200px) {
    .trackpress-settings-container {
        grid-template-columns: 1fr;
    }
}
</style>