<?php
namespace TrackPress\Admin;

class Admin_Pages
{

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_init', [$this, 'init_settings']);
        add_filter('plugin_action_links_' . TRACKPRESS_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);
        add_action('admin_init', [$this, 'handle_table_actions']);
    }

    public function add_admin_menus()
    {
        $capability = 'manage_options';

        // Main menu
        add_menu_page(
            'TrackPress',
            'TrackPress',
            $capability,
            'trackpress',
            [$this, 'render_dashboard_page'],
            'dashicons-chart-area',
            30
        );

        // Submenus
        add_submenu_page(
            'trackpress',
            'User Tracking',
            'User Logs',
            $capability,
            'trackpress-users',
            [$this, 'render_users_page']
        );

        add_submenu_page(
            'trackpress',
            'Visitor Tracking',
            'Visitor Logs',
            $capability,
            'trackpress-visitors',
            [$this, 'render_visitors_page']
        );

        add_submenu_page(
            'trackpress',
            'Admin Actions',
            'Admin Logs',
            $capability,
            'trackpress-admin',
            [$this, 'render_admin_page']
        );

        add_submenu_page(
            'trackpress',
            'TrackPress Settings',
            'Settings',
            $capability,
            'trackpress-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_dashboard_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'trackpress'));
        }

        $stats = \TrackPress\Database::get_stats();

        // Get recent activities
        $recent_users = \TrackPress\Database::get_user_logs(5);
        $recent_visitors = \TrackPress\Database::get_visitor_logs(5);
        $recent_admin = \TrackPress\Database::get_admin_logs(5);

        include TRACKPRESS_PLUGIN_DIR . 'admin/views/dashboard.php';
    }

    public function render_users_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'trackpress'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'trackpress_users_logs';

        // Handle actions
        //$this->handle_table_actions();

        // Get logs with pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Get logs
        $logs = \TrackPress\Database::get_user_logs($per_page, $offset);

        include TRACKPRESS_PLUGIN_DIR . 'admin/views/users-tracking.php';
    }

    public function render_visitors_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'trackpress'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'trackpress_visitors_logs';

        // Handle actions
        //$this->handle_table_actions();

        // Get logs with pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Get logs
        $logs = \TrackPress\Database::get_visitor_logs($per_page, $offset);

        include TRACKPRESS_PLUGIN_DIR . 'admin/views/visitors-tracking.php';
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'trackpress'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'trackpress_admin_logs';

        // Handle actions
        //$this->handle_table_actions();

        // Get logs with pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;

        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Get logs
        $logs = \TrackPress\Database::get_admin_logs($per_page, $offset);

        include TRACKPRESS_PLUGIN_DIR . 'admin/views/admin-tracking.php';
    }

    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'trackpress'));
        }

        // Save settings if form submitted
        if (isset($_POST['trackpress_save_settings'])) {
            check_admin_referer('trackpress_settings_nonce');
            $this->save_settings();
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully.', 'trackpress') . '</p></div>';
        }

        include TRACKPRESS_PLUGIN_DIR . 'admin/views/settings.php';
    }

    public function handle_table_actions()
    {  // MUST be public, not private
        // Only process actions for TrackPress pages
        if (!isset($_GET['page']) || strpos($_GET['page'], 'trackpress') !== 0) {
            return;
        }

        if (!isset($_GET['action']) || !isset($_GET['_wpnonce'])) {
            return;
        }

        $action = sanitize_text_field($_GET['action']);
        $nonce = sanitize_text_field($_GET['_wpnonce']);

        if (!wp_verify_nonce($nonce, 'trackpress_action')) {
            wp_die(__('Security check failed.', 'trackpress'));
        }

        global $wpdb;

        switch ($action) {
            case 'delete_single':
                if (isset($_GET['id']) && isset($_GET['table'])) {
                    $id = intval($_GET['id']);
                    $table = sanitize_text_field($_GET['table']);

                    if (in_array($table, ['users', 'visitors', 'admin'])) {
                        $table_name = $wpdb->prefix . 'trackpress_' . $table . '_logs';
                        $wpdb->delete($table_name, ['id' => $id], ['%d']);

                        // Get current page
                        $page = isset($_GET['page']) ? $_GET['page'] : 'trackpress-admin';

                        // Build redirect URL with success message
                        $redirect_args = [
                            'page' => $page,
                            'deleted' => '1'
                        ];

                        // Keep other parameters
                        $keep_params = ['paged', 'search', 'view'];
                        foreach ($keep_params as $param) {
                            if (isset($_GET[$param]) && !empty($_GET[$param])) {
                                $redirect_args[$param] = $_GET[$param];
                            }
                        }

                        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
                        exit;
                    }
                }
                break;

            case 'delete_all':
                if (isset($_GET['table'])) {
                    $table = sanitize_text_field($_GET['table']);

                    if (in_array($table, ['users', 'visitors', 'admin'])) {
                        $table_name = $wpdb->prefix . 'trackpress_' . $table . '_logs';
                        $wpdb->query("TRUNCATE TABLE $table_name");

                        // Get current page
                        $page = isset($_GET['page']) ? $_GET['page'] : 'trackpress-admin';

                        // Build redirect URL with success message
                        $redirect_args = [
                            'page' => $page,
                            'deleted_all' => '1'
                        ];

                        wp_safe_redirect(add_query_arg($redirect_args, admin_url('admin.php')));
                        exit;
                    }
                }
                break;
        }
    }

    private function save_settings()
    {
        $settings = [
            'cleanup_days' => isset($_POST['cleanup_days']) ? intval($_POST['cleanup_days']) : 30,
            'skip_roles' => isset($_POST['skip_roles']) ? array_map('sanitize_text_field', $_POST['skip_roles']) : [],
            'track_logged_in' => isset($_POST['track_logged_in']) ? 1 : 0,
            'track_visitors' => isset($_POST['track_visitors']) ? 1 : 0,
            'track_admin' => isset($_POST['track_admin']) ? 1 : 0,
            'exclude_pages' => isset($_POST['exclude_pages']) ? sanitize_textarea_field($_POST['exclude_pages']) : '',
            'exclude_ips' => isset($_POST['exclude_ips']) ? sanitize_textarea_field($_POST['exclude_ips']) : '',
        ];

        update_option('trackpress_settings', $settings);

        // Update cleanup schedule if days changed
        $old_settings = get_option('trackpress_settings', []);
        if (isset($old_settings['cleanup_days']) && $old_settings['cleanup_days'] != $settings['cleanup_days']) {
            wp_clear_scheduled_hook('trackpress_cleanup_old_logs');
            if ($settings['cleanup_days'] > 0) {
                wp_schedule_event(time(), 'daily', 'trackpress_cleanup_old_logs');
            }
        }
    }

    public function init_settings()
    {
        register_setting('trackpress_settings', 'trackpress_settings');
    }

    public function add_plugin_action_links($links)
    {
        $settings_link = '<a href="' . admin_url('admin.php?page=trackpress-settings') . '">' . __('Settings', 'trackpress') . '</a>';
        $logs_link = '<a href="' . admin_url('admin.php?page=trackpress') . '">' . __('Dashboard', 'trackpress') . '</a>';
        array_unshift($links, $settings_link, $logs_link);
        return $links;
    }

    public static function get_settings()
    {
        $defaults = [
            'cleanup_days' => 30,
            'skip_roles' => ['administrator'],
            'track_logged_in' => 1,
            'track_visitors' => 1,
            'track_admin' => 1,
            'exclude_pages' => "/wp-admin/\n/wp-login.php\n/wp-cron.php",
            'exclude_ips' => "127.0.0.1\n::1",
        ];

        $settings = get_option('trackpress_settings', []);
        return wp_parse_args($settings, $defaults);
    }
}