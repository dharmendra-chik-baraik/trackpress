<?php
namespace TrackPress;

class Database
{

    public static function init()
    {
        // Check if tables exist and create if not
        add_action('plugins_loaded', [__CLASS__, 'check_tables']);
    }

    public static function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Table 1: Logged-in Users Activity
        $table_users = $wpdb->prefix . 'trackpress_users_logs';
        $sql_users = "CREATE TABLE IF NOT EXISTS $table_users (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            user_login varchar(60) NOT NULL,
            user_email varchar(100) NOT NULL,
            action_type varchar(100) NOT NULL,
            action_details text,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            page_url text NOT NULL,
            http_method varchar(10) DEFAULT NULL,
            referrer_url text,
            session_id varchar(128) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY created_at (created_at),
            KEY user_login (user_login)
        ) $charset_collate;";

        // Table 2: Visitors Activity
        $table_visitors = $wpdb->prefix . 'trackpress_visitors_logs';
        $sql_visitors = "CREATE TABLE IF NOT EXISTS $table_visitors (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            visitor_hash varchar(64) NOT NULL,
            action_type varchar(100) NOT NULL,
            action_details text,
            ip_address varchar(45) DEFAULT NULL,
            user_agent text,
            page_url text NOT NULL,
            http_method varchar(10) DEFAULT NULL,
            referrer_url text,
            country_code varchar(2) DEFAULT NULL,
            city varchar(100) DEFAULT NULL,
            device_type varchar(50) DEFAULT NULL,
            browser varchar(100) DEFAULT NULL,
            session_id varchar(128) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY visitor_hash (visitor_hash),
            KEY action_type (action_type),
            KEY created_at (created_at),
            KEY ip_address (ip_address),
            KEY country_code (country_code)
        ) $charset_collate;";

        // Table 3: Admin Area Actions
        $table_admin = $wpdb->prefix . 'trackpress_admin_logs';
        $sql_admin = "CREATE TABLE IF NOT EXISTS $table_admin (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            user_login varchar(60) NOT NULL,
            user_role varchar(50) NOT NULL,
            action_type varchar(100) NOT NULL,
            action_details text NOT NULL,
            object_type varchar(100) DEFAULT NULL,
            object_id bigint(20) DEFAULT NULL,
            object_name varchar(255) DEFAULT NULL,
            ip_address varchar(45) DEFAULT NULL,
            admin_page varchar(255) DEFAULT NULL,
            http_method varchar(10) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action_type (action_type),
            KEY created_at (created_at),
            KEY object_type (object_type),
            KEY user_role (user_role)
        ) $charset_collate;";

        // Execute queries
        dbDelta($sql_users);
        dbDelta($sql_visitors);
        dbDelta($sql_admin);

        // Store version for future updates
        update_option('trackpress_db_version', '1.0.0');
    }

    public static function check_tables()
    {
        $db_version = get_option('trackpress_db_version', '0');

        if (version_compare($db_version, '1.0.0', '<')) {
            self::create_tables();
        }
    }

    public static function insert_user_log($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'trackpress_users_logs';

        $defaults = [
            'user_id' => 0,
            'user_login' => '',
            'user_email' => '',
            'action_type' => 'page_view',
            'action_details' => '',
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'page_url' => self::get_current_url(),
            'http_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field($_SERVER['REQUEST_METHOD']) : '',
            'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
            'session_id' => self::get_session_id(),
        ];

        $data = wp_parse_args($data, $defaults);

        return $wpdb->insert($table, $data);
    }

    public static function insert_visitor_log($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'trackpress_visitors_logs';

        // Generate visitor hash if not provided
        if (empty($data['visitor_hash'])) {
            $data['visitor_hash'] = self::generate_visitor_hash();
        }

        $defaults = [
            'visitor_hash' => '',
            'action_type' => 'page_view',
            'action_details' => '',
            'ip_address' => self::get_client_ip(),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            'page_url' => self::get_current_url(),
            'http_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field($_SERVER['REQUEST_METHOD']) : '',
            'referrer_url' => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw($_SERVER['HTTP_REFERER']) : '',
            'country_code' => self::get_country_code(self::get_client_ip()),
            'city' => '',
            'device_type' => self::get_device_type(),
            'browser' => self::get_browser(),
            'session_id' => self::get_session_id(),
        ];

        $data = wp_parse_args($data, $defaults);

        return $wpdb->insert($table, $data);
    }

    public static function insert_admin_log($data)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'trackpress_admin_logs';

        $user = wp_get_current_user();

        $defaults = [
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'admin_action',
            'action_details' => '',
            'object_type' => '',
            'object_id' => 0,
            'object_name' => '',
            'ip_address' => self::get_client_ip(),
            'admin_page' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
            'http_method' => isset($_SERVER['REQUEST_METHOD']) ? sanitize_text_field($_SERVER['REQUEST_METHOD']) : '',
        ];

        $data = wp_parse_args($data, $defaults);

        return $wpdb->insert($table, $data);
    }

    public static function get_client_ip()
    {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
    }

    private static function generate_visitor_hash()
    {
        $ip = self::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        return hash('sha256', $ip . $user_agent . time());
    }

    private static function get_session_id()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return session_id();
    }

    public static function get_current_url()
    {
        $protocol = is_ssl() ? 'https://' : 'http://';
        return $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }

    private static function get_country_code($ip)
    {
        // Simple country detection - can be enhanced with geoip database
        if ($ip === '127.0.0.1' || strpos($ip, '192.168.') === 0) {
            return 'LOCAL';
        }

        // For real implementation, you might want to use a GeoIP service
        return 'UN';
    }

    private static function get_device_type()
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';

        if (strpos($user_agent, 'mobile') !== false) {
            return 'mobile';
        } elseif (strpos($user_agent, 'tablet') !== false) {
            return 'tablet';
        } else {
            return 'desktop';
        }
    }

    private static function get_browser()
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        if (strpos($user_agent, 'Firefox') !== false) {
            return 'Firefox';
        } elseif (strpos($user_agent, 'Chrome') !== false) {
            return 'Chrome';
        } elseif (strpos($user_agent, 'Safari') !== false) {
            return 'Safari';
        } elseif (strpos($user_agent, 'Edge') !== false) {
            return 'Edge';
        } elseif (strpos($user_agent, 'MSIE') !== false || strpos($user_agent, 'Trident') !== false) {
            return 'Internet Explorer';
        } else {
            return 'Unknown';
        }
    }

    public static function cleanup_old_logs($days = 30)
    {
        global $wpdb;
        $date = date('Y-m-d H:i:s', strtotime("-$days days"));

        $tables = [
            $wpdb->prefix . 'trackpress_users_logs',
            $wpdb->prefix . 'trackpress_visitors_logs',
            $wpdb->prefix . 'trackpress_admin_logs'
        ];

        foreach ($tables as $table) {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE created_at < %s",
                $date
            ));
        }
    }

    public static function get_user_logs($limit = 100, $offset = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'trackpress_users_logs';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    public static function get_visitor_logs($limit = 100, $offset = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'trackpress_visitors_logs';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    public static function get_admin_logs($limit = 100, $offset = 0)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'trackpress_admin_logs';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $limit,
                $offset
            )
        );
    }

    public static function get_stats()
    {
        global $wpdb;

        $stats = [];

        // User logs count
        $table_users = $wpdb->prefix . 'trackpress_users_logs';
        $stats['total_users_logs'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_users");
        $stats['today_users_logs'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_users WHERE DATE(created_at) = CURDATE()"
        );

        // Visitor logs count
        $table_visitors = $wpdb->prefix . 'trackpress_visitors_logs';
        $stats['total_visitors_logs'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_visitors");
        $stats['today_visitors_logs'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_visitors WHERE DATE(created_at) = CURDATE()"
        );

        // Admin logs count
        $table_admin = $wpdb->prefix . 'trackpress_admin_logs';
        $stats['total_admin_logs'] = (int) $wpdb->get_var("SELECT COUNT(*) FROM $table_admin");
        $stats['today_admin_logs'] = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_admin WHERE DATE(created_at) = CURDATE()"
        );

        return $stats;
    }
}