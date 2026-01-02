<?php
namespace TrackPress\Admin;

class Admin_Pages
{
    private $plugin_data;
    private $remote_version;

    public function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_init', [$this, 'init_settings']);
        add_filter('plugin_action_links_' . TRACKPRESS_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);
        add_action('admin_init', [$this, 'handle_table_actions']);

        // Initialize plugin data
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        $this->plugin_data = get_plugin_data(TRACKPRESS_PLUGIN_FILE);

        // Check for updates periodically
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update']);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);

        // Add update notice
        add_action('admin_notices', [$this, 'update_notice']);
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
            'TrackPress - About',
            'About',
            $capability,
            'trackpress-about',
            [$this, 'render_about_page']
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
        $about_link = '<a href="' . admin_url('admin.php?page=trackpress-about') . '">' . __('About', 'trackpress') . '</a>';
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

    // updates functionality 

    // Render About page
    public function render_about_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'trackpress'));
        }

        $current_version = $this->plugin_data['Version'];
        $remote_info = $this->get_remote_info();
        $is_update_available = $remote_info && version_compare($remote_info->version, $current_version, '>');
        
        // Get plugin features
        $features = [
            [
                'title' => 'User Activity Tracking',
                'description' => 'Track and log all user activities including logins, logouts, and profile updates.',
                'icon' => 'dashicons-admin-users'
            ],
            [
                'title' => 'Visitor Tracking',
                'description' => 'Monitor anonymous visitors with detailed information about their visits.',
                'icon' => 'dashicons-visibility'
            ],
            [
                'title' => 'Admin Actions Logging',
                'description' => 'Keep records of all administrative actions for security and accountability.',
                'icon' => 'dashicons-shield'
            ],
            [
                'title' => 'Advanced Filtering',
                'description' => 'Filter logs by date, user, IP address, and specific actions.',
                'icon' => 'dashicons-filter'
            ],
            [
                'title' => 'Data Cleanup',
                'description' => 'Automatically clean up old logs based on customizable retention periods.',
                'icon' => 'dashicons-trash'
            ],
            [
                'title' => 'Export Capabilities',
                'description' => 'Export logs in various formats for analysis and reporting.',
                'icon' => 'dashicons-download'
            ],
            [
                'title' => 'Role-Based Tracking',
                'description' => 'Configure which user roles to track or exclude from tracking.',
                'icon' => 'dashicons-groups'
            ],
            [
                'title' => 'Real-time Dashboard',
                'description' => 'Get real-time insights with comprehensive statistics and charts.',
                'icon' => 'dashicons-chart-line'
            ]
        ];

        // Developer information
        $developers = [
            [
                'name' => 'Dharmendra Chik Baraik',
                'role' => 'Lead Developer',
                'contact' => 'https://github.com/dharmendra-chik-baraik'
            ]
        ];

        include TRACKPRESS_PLUGIN_DIR . 'admin/views/about.php';
    }

    // Get remote version information from GitHub
    private function get_remote_info()
    {
        $transient_key = 'trackpress_remote_info';
        $remote_info = get_transient($transient_key);

        if (false === $remote_info) {
            // First try local version.json
            $version_file = TRACKPRESS_PLUGIN_DIR . 'version.json';
            
            if (file_exists($version_file)) {
                $local_data = json_decode(file_get_contents($version_file));
                if ($local_data) {
                    $remote_info = $local_data;
                    set_transient($transient_key, $remote_info, 12 * HOUR_IN_SECONDS);
                    return $remote_info;
                }
            }

            // Fallback to GitHub API
            $github_api_url = 'https://api.github.com/repos/dharmendra-chik-baraik/trackpress/releases/latest';
            
            $response = wp_remote_get($github_api_url, [
                'timeout' => 10,
                'headers' => [
                    'Accept' => 'application/vnd.github.v3+json'
                ]
            ]);

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $github_data = json_decode(wp_remote_retrieve_body($response));
                
                if ($github_data) {
                    $remote_info = new \stdClass();
                    $remote_info->version = ltrim($github_data->tag_name, 'v');
                    $remote_info->last_updated = $github_data->published_at;
                    $remote_info->download_url = $github_data->zipball_url;
                    $remote_info->changelog = ['Latest' => [$github_data->body]];
                    
                    set_transient($transient_key, $remote_info, 12 * HOUR_IN_SECONDS);
                }
            }
        }

        return $remote_info;
    }

    // Check for updates
    public function check_for_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote_info = $this->get_remote_info();
        
        if ($remote_info && version_compare($remote_info->version, $this->plugin_data['Version'], '>')) {
            $plugin_slug = plugin_basename(TRACKPRESS_PLUGIN_FILE);
            
            $obj = new \stdClass();
            $obj->slug = dirname($plugin_slug);
            $obj->new_version = $remote_info->version;
            $obj->url = 'https://github.com/dharmendra-chik-baraik/trackpress';
            $obj->package = $remote_info->download_url;
            $obj->tested = isset($remote_info->tested) ? $remote_info->tested : '';
            $obj->requires = isset($remote_info->requires) ? $remote_info->requires : '';
            $obj->requires_php = isset($remote_info->requires_php) ? $remote_info->requires_php : '';
            
            $transient->response[$plugin_slug] = $obj;
        }

        return $transient;
    }

    // Plugin information for update modal
    public function plugin_info($false, $action, $response)
    {
        if ($action !== 'plugin_information') {
            return false;
        }

        $plugin_slug = plugin_basename(TRACKPRESS_PLUGIN_FILE);
        
        if (empty($response->slug) || $response->slug !== dirname($plugin_slug)) {
            return false;
        }

        $remote_info = $this->get_remote_info();
        
        if (!$remote_info) {
            return false;
        }

        $response = new \stdClass();
        $response->slug = dirname($plugin_slug);
        $response->plugin_name = $this->plugin_data['Name'];
        $response->name = $this->plugin_data['Name'];
        $response->version = $remote_info->version;
        $response->author = $this->plugin_data['Author'];
        $response->author_profile = 'https://github.com/dharmendra-chik-baraik';
        $response->homepage = 'https://github.com/dharmendra-chik-baraik/trackpress';
        $response->requires = isset($remote_info->requires) ? $remote_info->requires : '';
        $response->tested = isset($remote_info->tested) ? $remote_info->tested : '';
        $response->requires_php = isset($remote_info->requires_php) ? $remote_info->requires_php : '';
        $response->downloaded = 0;
        $response->last_updated = $remote_info->last_updated;
        $response->sections = [
            'description' => $this->plugin_data['Description'],
            'changelog' => $this->format_changelog($remote_info->changelog)
        ];
        $response->download_link = $remote_info->download_url;
        $response->banners = [
            'low' => TRACKPRESS_PLUGIN_URL . 'assets/banner-772x250.png',
            'high' => TRACKPRESS_PLUGIN_URL . 'assets/banner-1544x500.png'
        ];

        return $response;
    }

    // Format changelog for display
    private function format_changelog($changelog)
    {
        if (is_array($changelog)) {
            $output = '';
            foreach ($changelog as $version => $changes) {
                $output .= '<h4>' . esc_html($version) . '</h4>';
                $output .= '<ul>';
                foreach ($changes as $change) {
                    $output .= '<li>' . esc_html($change) . '</li>';
                }
                $output .= '</ul>';
            }
            return $output;
        }
        return '<p>' . esc_html($changelog) . '</p>';
    }

    // Update notice in admin
    public function update_notice()
    {
        $remote_info = $this->get_remote_info();
        
        if ($remote_info && version_compare($remote_info->version, $this->plugin_data['Version'], '>')) {
            ?>
            <div class="notice notice-warning is-dismissible">
                <p>
                    <strong><?php echo esc_html($this->plugin_data['Name']); ?>:</strong>
                    <?php printf(
                        __('A new version (%s) is available. <a href="%s">View update details</a> or <a href="%s">update now</a>.', 'trackpress'),
                        esc_html($remote_info->version),
                        admin_url('admin.php?page=trackpress-about'),
                        admin_url('update-core.php')
                    ); ?>
                </p>
            </div>
            <?php
        }
    }
}