<?php
namespace TrackPress\Admin;

use WP_Error;

class Admin_Pages
{
    private $plugin_data;
    private $remote_version;

    public function __construct()
    {
        // Initialize plugin data IMMEDIATELY in constructor
        $this->init_plugin_data_early();

        // Register ALL update hooks in constructor (not admin_init)
        add_filter('pre_set_site_transient_update_plugins', [$this, 'check_for_update'], 10, 1);
        add_filter('plugins_api', [$this, 'plugin_info'], 20, 3);
        add_action('admin_notices', [$this, 'update_notice']);

        // Other hooks
        add_action('admin_menu', [$this, 'add_admin_menus']);
        add_action('admin_init', [$this, 'init_settings']);
        add_action('admin_init', [$this, 'handle_table_actions']);

        // Plugin action links - Use plugins_loaded with priority
        // add_action('plugins_loaded', function () {
            if (defined('TRACKPRESS_PLUGIN_BASENAME')) {
                add_filter('plugin_action_links_' . TRACKPRESS_PLUGIN_BASENAME, [$this, 'add_plugin_action_links']);
                error_log('TrackPress: Action links filter registered via plugins_loaded');
            }
        // });
    }

    /**
     * Initialize plugin data EARLY (in constructor)
     */
    private function init_plugin_data_early()
    {
        // Load the function if not available
        if (!function_exists('get_plugin_data')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        // Get plugin data
        if (defined('TRACKPRESS_PLUGIN_FILE')) {
            $this->plugin_data = get_plugin_data(TRACKPRESS_PLUGIN_FILE);

            // Debug log
            if (defined('WP_DEBUG') && WP_DEBUG && $this->plugin_data) {
                error_log('TrackPress: Plugin data initialized in constructor - Version: ' . $this->plugin_data['Version']);
            }
        } else {
            error_log('TrackPress ERROR: TRACKPRESS_PLUGIN_FILE not defined!');
        }
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
            'TrackPress - About Us',
            'About Us',
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

        // Debug page (hidden, for testing only)
        add_submenu_page(
            '', // Hidden from menu
            'TrackPress Debug',
            'Debug Page',
            $capability,
            'trackpress-debug',
            [$this, 'render_debug_page']
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
    {
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
        error_log('TrackPress: Adding plugin action links...');
        $settings_link = '<a href="' . admin_url('admin.php?page=trackpress-settings') . '">' . __('Settings', 'trackpress') . '</a>';
        $logs_link = '<a href="' . admin_url('admin.php?page=trackpress') . '">' . __('Dashboard', 'trackpress') . '</a>';
        $about_link = '<a href="' . admin_url('admin.php?page=trackpress-about') . '">' . __('About', 'trackpress') . '</a>';
        array_unshift($links, $settings_link, $logs_link, $about_link);
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

    // ============================================
    // UPDATE FUNCTIONALITY
    // ============================================

    // Render About page
    public function render_about_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'trackpress'));
        }

        $current_version = $this->plugin_data['Version'] ?? '1.0.0';
        $remote_info = $this->get_remote_info();
        $is_update_available = $remote_info && version_compare($remote_info->version, $current_version, '>');

        // Get plugin features
        $features = [
            [
                'title' => __('User Activity Tracking', 'trackpress'),
                'description' => __('Track and log all user activities including logins, logouts, and profile updates.', 'trackpress'),
                'icon' => 'dashicons-admin-users'
            ],
            [
                'title' => __('Visitor Tracking', 'trackpress'),
                'description' => __('Monitor anonymous visitors with detailed information about their visits.', 'trackpress'),
                'icon' => 'dashicons-visibility'
            ],
            [
                'title' => __('Admin Actions Logging', 'trackpress'),
                'description' => __('Keep records of all administrative actions for security and accountability.', 'trackpress'),
                'icon' => 'dashicons-shield'
            ],
            [
                'title' => __('Advanced Filtering', 'trackpress'),
                'description' => __('Filter logs by date, user, IP address, and specific actions.', 'trackpress'),
                'icon' => 'dashicons-filter'
            ],
            [
                'title' => __('Data Cleanup', 'trackpress'),
                'description' => __('Automatically clean up old logs based on customizable retention periods.', 'trackpress'),
                'icon' => 'dashicons-trash'
            ],
            [
                'title' => __('Export Capabilities', 'trackpress'),
                'description' => __('Export logs in various formats for analysis and reporting.', 'trackpress'),
                'icon' => 'dashicons-download'
            ],
            [
                'title' => __('Role-Based Tracking', 'trackpress'),
                'description' => __('Configure which user roles to track or exclude from tracking.', 'trackpress'),
                'icon' => 'dashicons-groups'
            ],
            [
                'title' => __('Real-time Dashboard', 'trackpress'),
                'description' => __('Get real-time insights with comprehensive statistics and charts.', 'trackpress'),
                'icon' => 'dashicons-chart-line'
            ]
        ];

        // Developer information
        $developers = [
            [
                'name' => 'Dharmendra Chik Baraik',
                'role' => 'Lead Developer',
                'website' => 'https://github.com/dharmendra-chik-baraik',
                'email' => 'dcbaraik143@gmail.com',
                'github' => 'https://github.com/dharmendra-chik-baraik',
            ]
        ];

        // Extract the plugin data we need
        $plugin_info = [
            'name' => $this->plugin_data['Name'] ?? 'TrackPress',
            'version' => $current_version,
            'author' => $this->plugin_data['Author'] ?? 'Dharmendra Chik Baraik',
            'requires' => $this->plugin_data['RequiresWP'] ?? $this->plugin_data['Requires at least'] ?? '5.6',
            'tested' => $this->plugin_data['Tested up to'] ?? $this->plugin_data['TestedUpTo'] ?? '6.4',
            'description' => $this->plugin_data['Description'] ?? ''
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

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('TrackPress: Using local version.json - Version: ' . ($remote_info->version ?? 'N/A'));
                    }

                    return $remote_info;
                }
            }

            // Fallback to GitHub API
            $github_api_url = 'https://api.github.com/repos/dharmendra-chik-baraik/trackpress/releases/latest';

            $response = wp_remote_get($github_api_url, [
                'timeout' => 15,
                'headers' => [
                    'Accept' => 'application/vnd.github.v3+json',
                    'User-Agent' => 'WordPress/TrackPress-Plugin'
                ]
            ]);

            // DEBUG: Log the entire response for troubleshooting
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TrackPress GitHub API Debug:');
                error_log('URL: ' . $github_api_url);

                if (is_wp_error($response)) {
                    error_log('WP_Error: ' . $response->get_error_message());
                    error_log('Error Code: ' . $response->get_error_code());
                } else {
                    error_log('Response Code: ' . wp_remote_retrieve_response_code($response));
                    $headers = wp_remote_retrieve_headers($response);
                    if (isset($headers['x-ratelimit-remaining'])) {
                        error_log('Rate Limit Remaining: ' . $headers['x-ratelimit-remaining']);
                    }
                }
            }

            if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200) {
                $github_data = json_decode(wp_remote_retrieve_body($response));

                if ($github_data && isset($github_data->tag_name)) {
                    $remote_info = new \stdClass();
                    $remote_info->version = ltrim($github_data->tag_name, 'v');
                    $remote_info->last_updated = $github_data->published_at;
                    $remote_info->download_url = $github_data->zipball_url;
                    $remote_info->changelog = ['Latest' => [$github_data->body]];

                    // Add WordPress compatibility info if available in release body
                    $remote_info->tested = $this->extract_tested_version($github_data->body);
                    $remote_info->requires = $this->extract_requires_version($github_data->body);

                    // Add optional fields if available
                    if (isset($github_data->assets[0])) {
                        $remote_info->download_url = $github_data->assets[0]->browser_download_url;
                    }

                    set_transient($transient_key, $remote_info, 12 * HOUR_IN_SECONDS);

                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('TrackPress: Got remote info from GitHub - Version: ' . $remote_info->version);
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('TrackPress GitHub API Error: Invalid response format');
                    }
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    $error_message = is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_response_message($response);
                    $error_code = is_wp_error($response) ? $response->get_error_code() : wp_remote_retrieve_response_code($response);
                    error_log('TrackPress GitHub API Error: ' . $error_message . ' (Code: ' . $error_code . ')');
                }
            }
        }

        return $remote_info;
    }

    // Helper to extract tested version from release body
    private function extract_tested_version($body)
    {
        if (preg_match('/Tested up to:\s*([0-9.]+)/i', $body, $matches)) {
            return $matches[1];
        }
        if (preg_match('/WordPress:\s*([0-9.]+)/i', $body, $matches)) {
            return $matches[1];
        }
        return $this->plugin_data['Tested up to'] ?? '6.4';
    }

    // Helper to extract requires version from release body
    private function extract_requires_version($body)
    {
        if (preg_match('/Requires at least:\s*([0-9.]+)/i', $body, $matches)) {
            return $matches[1];
        }
        if (preg_match('/Requires WordPress:\s*([0-9.]+)/i', $body, $matches)) {
            return $matches[1];
        }
        return $this->plugin_data['RequiresWP'] ?? $this->plugin_data['Requires at least'] ?? '5.6';
    }

    // Check for updates
    public function check_for_update($transient)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TrackPress: check_for_update() called');
        }

        if (empty($transient->checked)) {
            return $transient;
        }

        // Ensure plugin data is available
        if (empty($this->plugin_data)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TrackPress ERROR: $this->plugin_data is empty!');
            }
            return $transient;
        }

        $current_version = $this->plugin_data['Version'];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('TrackPress: Current version from plugin_data: ' . $current_version);
        }

        $remote_info = $this->get_remote_info();

        if ($remote_info && version_compare($remote_info->version, $current_version, '>')) {
            $plugin_slug = plugin_basename(TRACKPRESS_PLUGIN_FILE);

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TrackPress: Update available! Current: ' . $current_version . ', Remote: ' . $remote_info->version);
                error_log('TrackPress: Plugin slug: ' . $plugin_slug);
            }

            $obj = new \stdClass();
            $obj->slug = dirname($plugin_slug);
            $obj->new_version = $remote_info->version;
            $obj->url = 'https://github.com/dharmendra-chik-baraik/trackpress';
            $obj->package = $remote_info->download_url;
            $obj->tested = $remote_info->tested ?? $this->plugin_data['Tested up to'] ?? '';
            $obj->requires = $remote_info->requires ?? $this->plugin_data['Requires at least'] ?? '';
            $obj->requires_php = $remote_info->requires_php ?? $this->plugin_data['RequiresPHP'] ?? '';

            $transient->response[$plugin_slug] = $obj;

            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('TrackPress: Added update to response array');
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                if ($remote_info) {
                    error_log('TrackPress: No update needed. Current: ' . $current_version . ', Remote: ' . $remote_info->version);
                } else {
                    error_log('TrackPress: No remote info available');
                }
            }
        }

        return $transient;
    }

    // Plugin information for update modal
    public function plugin_info($false, $action, $response)
    {
        if ($action !== 'plugin_information') {
            return $false;
        }

        $plugin_slug = plugin_basename(TRACKPRESS_PLUGIN_FILE);

        if (empty($response->slug) || $response->slug !== dirname($plugin_slug)) {
            return $false;
        }

        $remote_info = $this->get_remote_info();

        if (!$remote_info) {
            return $false;
        }

        $info = new \stdClass();
        $info->slug = dirname($plugin_slug);
        $info->plugin_name = $this->plugin_data['Name'];
        $info->name = $this->plugin_data['Name'];
        $info->version = $remote_info->version;
        $info->author = $this->plugin_data['Author'];
        $info->author_profile = 'https://github.com/dharmendra-chik-baraik';
        $info->homepage = 'https://github.com/dharmendra-chik-baraik/trackpress';
        $info->requires = $remote_info->requires ?? $this->plugin_data['Requires at least'] ?? '';
        $info->tested = $remote_info->tested ?? $this->plugin_data['Tested up to'] ?? '';
        $info->requires_php = $remote_info->requires_php ?? $this->plugin_data['RequiresPHP'] ?? '';
        $info->downloaded = 0;
        $info->last_updated = $remote_info->last_updated;
        $info->sections = [
            'description' => $this->plugin_data['Description'],
            'changelog' => $this->format_changelog($remote_info->changelog)
        ];
        $info->download_link = $remote_info->download_url;
        $info->banners = [
            'low' => TRACKPRESS_PLUGIN_URL . 'assets/banner-772x250.png',
            'high' => TRACKPRESS_PLUGIN_URL . 'assets/banner-1544x500.png'
        ];

        return $info;
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
        if (empty($this->plugin_data)) {
            return;
        }

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

    // Debug page (for testing)
    public function render_debug_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'trackpress'));
        }

        echo '<div class="wrap">';
        echo '<h1>TrackPress Debug Information</h1>';

        echo '<h2>Plugin Data:</h2>';
        if (!empty($this->plugin_data)) {
            echo '<pre>' . esc_html(print_r($this->plugin_data, true)) . '</pre>';
        } else {
            echo '<p style="color: red;">Plugin data is empty!</p>';
        }

        echo '<h2>Remote Info:</h2>';
        $remote_info = $this->get_remote_info();
        if ($remote_info) {
            echo '<pre>' . esc_html(print_r($remote_info, true)) . '</pre>';
        } else {
            echo '<p>No remote info available.</p>';
        }

        echo '<h2>Transient Data:</h2>';
        $transient = get_transient('trackpress_remote_info');
        if ($transient) {
            echo '<pre>' . esc_html(print_r($transient, true)) . '</pre>';
        } else {
            echo '<p>No transient data found.</p>';
        }

        echo '<h2>Debug Actions:</h2>';
        echo '<form method="post">';
        wp_nonce_field('trackpress_debug_action', 'trackpress_debug_nonce');
        echo '<p><button type="submit" name="clear_update_cache" class="button button-primary">Clear Update Cache</button></p>';
        echo '</form>';

        if (isset($_POST['clear_update_cache']) && check_admin_referer('trackpress_debug_action', 'trackpress_debug_nonce')) {
            delete_transient('trackpress_remote_info');
            echo '<div class="notice notice-success"><p>Update cache cleared!</p></div>';
        }

        echo '</div>';
    }
}