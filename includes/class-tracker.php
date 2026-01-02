<?php
namespace TrackPress;

class Tracker {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function __construct() {
        // Hook into WordPress actions for tracking
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Add cleanup schedule
        add_action('trackpress_cleanup_old_logs', [$this, 'cleanup_old_logs']);
        
        // Add stats to admin dashboard
        add_action('wp_dashboard_setup', [$this, 'add_dashboard_widget']);
        
        // Track specific WordPress events
        add_action('wp_login', [$this, 'track_user_login'], 10, 2);
        add_action('wp_logout', [$this, 'track_user_logout']);
        add_action('delete_user', [$this, 'track_user_deletion']);
        add_action('user_register', [$this, 'track_user_registration']);
        add_action('profile_update', [$this, 'track_profile_update']);
        
        // Track frontend page views via shutdown hook
        add_action('shutdown', [$this, 'track_page_view']);
    }
    
    public function cleanup_old_logs() {
        $days = apply_filters('trackpress_cleanup_days', 30);
        Database::cleanup_old_logs($days);
    }
    
    public function track_user_login($user_login, $user) {
        Database::insert_user_log([
            'user_id' => $user->ID,
            'user_login' => $user_login,
            'user_email' => $user->user_email,
            'action_type' => 'user_login',
            'action_details' => 'User logged in successfully',
            'page_url' => home_url('/wp-login.php'),
        ]);
    }
    
    public function track_user_logout() {
        $user = wp_get_current_user();
        if ($user->ID > 0) {
            Database::insert_user_log([
                'user_id' => $user->ID,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'action_type' => 'user_logout',
                'action_details' => 'User logged out',
                'page_url' => home_url(),
            ]);
        }
    }
    
    public function track_user_deletion($user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            $current_user = wp_get_current_user();
            Database::insert_admin_log([
                'user_id' => $current_user->ID,
                'user_login' => $current_user->user_login,
                'action_type' => 'user_deleted',
                'action_details' => sprintf('User %s (ID: %d) was deleted', $user->user_login, $user_id),
                'object_type' => 'user',
                'object_id' => $user_id,
                'object_name' => $user->user_login,
            ]);
        }
    }
    
    public function track_user_registration($user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            $current_user = wp_get_current_user();
            $action_type = is_admin() ? 'user_created_admin' : 'user_registered';
            
            if (is_admin()) {
                Database::insert_admin_log([
                    'user_id' => $current_user->ID,
                    'user_login' => $current_user->user_login,
                    'action_type' => $action_type,
                    'action_details' => sprintf('New user %s created (ID: %d)', $user->user_login, $user_id),
                    'object_type' => 'user',
                    'object_id' => $user_id,
                    'object_name' => $user->user_login,
                ]);
            } else {
                Database::insert_user_log([
                    'user_id' => $user_id,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'action_type' => $action_type,
                    'action_details' => 'New user registration completed',
                    'page_url' => home_url('/wp-login.php?action=register'),
                ]);
            }
        }
    }
    
    public function track_profile_update($user_id) {
        $user = get_userdata($user_id);
        $current_user = wp_get_current_user();
        
        if ($user && $current_user->ID > 0) {
            $is_own_profile = ($user_id == $current_user->ID);
            $action_type = $is_own_profile ? 'profile_updated_self' : 'profile_updated_other';
            
            Database::insert_admin_log([
                'user_id' => $current_user->ID,
                'user_login' => $current_user->user_login,
                'action_type' => $action_type,
                'action_details' => sprintf('User profile updated for %s (ID: %d)', $user->user_login, $user_id),
                'object_type' => 'user',
                'object_id' => $user_id,
                'object_name' => $user->user_login,
            ]);
        }
    }
    
    public function track_page_view() {
        // Don't track in admin area or during AJAX requests
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }
        
        // Don't track specific requests
        if ($this->should_skip_tracking()) {
            return;
        }
        
        // Track based on user status
        if (is_user_logged_in()) {
            $this->track_logged_in_user_view();
        } else {
            $this->track_visitor_view();
        }
    }
    
    private function track_logged_in_user_view() {
        $user = wp_get_current_user();
        
        $action_details = [
            'page_title' => wp_get_document_title(),
            'post_id' => get_the_ID() ?: 0,
            'post_type' => get_post_type() ?: '',
            'is_front_page' => is_front_page(),
            'is_home' => is_home(),
            'is_single' => is_single(),
            'is_page' => is_page(),
            'is_category' => is_category(),
            'is_tag' => is_tag(),
            'is_archive' => is_archive(),
            'is_search' => is_search(),
            'is_404' => is_404(),
        ];
        
        Database::insert_user_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_email' => $user->user_email,
            'action_type' => 'page_view',
            'action_details' => maybe_serialize($action_details),
        ]);
    }
    
    private function track_visitor_view() {
        $action_details = [
            'page_title' => wp_get_document_title(),
            'post_id' => get_the_ID() ?: 0,
            'post_type' => get_post_type() ?: '',
            'is_front_page' => is_front_page(),
            'is_home' => is_home(),
            'is_single' => is_single(),
            'is_page' => is_page(),
            'is_category' => is_category(),
            'is_tag' => is_tag(),
            'is_archive' => is_archive(),
            'is_search' => is_search(),
            'is_404' => is_404(),
        ];
        
        Database::insert_visitor_log([
            'action_type' => 'page_view',
            'action_details' => maybe_serialize($action_details),
        ]);
    }
    
    private function should_skip_tracking() {
        // Skip tracking for specific user roles
        $user = wp_get_current_user();
        if ($user->ID > 0) {
            $skip_roles = apply_filters('trackpress_skip_roles', ['administrator']);
            $user_roles = $user->roles;
            
            if (array_intersect($skip_roles, $user_roles)) {
                return true;
            }
        }
        
        // Skip tracking for specific pages
        $skip_pages = apply_filters('trackpress_skip_pages', [
            '/wp-admin/',
            '/wp-login.php',
            '/wp-cron.php',
            '/xmlrpc.php',
        ]);
        
        $current_url = Database::get_current_url();
        foreach ($skip_pages as $page) {
            if (strpos($current_url, $page) !== false) {
                return true;
            }
        }
        
        // Skip for bots and crawlers
        if ($this->is_bot()) {
            return true;
        }
        
        return false;
    }
    
    private function is_bot() {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';
        
        $bots = [
            'bot', 'spider', 'crawler', 'scanner', 'curl', 'wget',
            'googlebot', 'bingbot', 'slurp', 'duckduckbot',
            'baiduspider', 'yandexbot', 'sogou', 'exabot',
            'facebookexternalhit', 'twitterbot', 'rogerbot',
            'linkedinbot', 'embedly', 'quora link preview',
            'showyoubot', 'outbrain', 'pinterest', 'slackbot',
            'vkshare', 'w3c_validator', 'whatsapp'
        ];
        
        foreach ($bots as $bot) {
            if (strpos($user_agent, $bot) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    public function add_dashboard_widget() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        wp_add_dashboard_widget(
            'trackpress_dashboard_widget',
            'TrackPress - Activity Overview',
            [$this, 'render_dashboard_widget']
        );
    }
    
    public function render_dashboard_widget() {
        $stats = Database::get_stats();
        
        echo '<div style="padding: 10px 0;">';
        echo '<h3>Today\'s Activity</h3>';
        echo '<ul style="list-style: none; padding: 0; margin: 0;">';
        echo '<li><strong>User Logs:</strong> ' . esc_html($stats['today_users_logs']) . '</li>';
        echo '<li><strong>Visitor Logs:</strong> ' . esc_html($stats['today_visitors_logs']) . '</li>';
        echo '<li><strong>Admin Actions:</strong> ' . esc_html($stats['today_admin_logs']) . '</li>';
        echo '</ul>';
        
        echo '<h3>Total Records</h3>';
        echo '<ul style="list-style: none; padding: 0; margin: 0;">';
        echo '<li><strong>Total User Logs:</strong> ' . esc_html($stats['total_users_logs']) . '</li>';
        echo '<li><strong>Total Visitor Logs:</strong> ' . esc_html($stats['total_visitors_logs']) . '</li>';
        echo '<li><strong>Total Admin Logs:</strong> ' . esc_html($stats['total_admin_logs']) . '</li>';
        echo '</ul>';
        
        echo '<p style="margin-top: 15px;">';
        echo '<a href="' . admin_url('admin.php?page=trackpress-users') . '" class="button">View User Logs</a> ';
        echo '<a href="' . admin_url('admin.php?page=trackpress-visitors') . '" class="button">View Visitor Logs</a>';
        echo '</p>';
        echo '</div>';
    }
    
    public static function track_custom_action($action_type, $action_details = '', $user_id = null) {
        if (is_admin()) {
            $user = $user_id ? get_userdata($user_id) : wp_get_current_user();
            if ($user) {
                Database::insert_admin_log([
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'action_type' => $action_type,
                    'action_details' => $action_details,
                ]);
            }
        } else {
            if (is_user_logged_in()) {
                $user = $user_id ? get_userdata($user_id) : wp_get_current_user();
                Database::insert_user_log([
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'action_type' => $action_type,
                    'action_details' => $action_details,
                ]);
            } else {
                Database::insert_visitor_log([
                    'action_type' => $action_type,
                    'action_details' => $action_details,
                ]);
            }
        }
    }
}