<?php
/**
 * Plugin Name: TrackPress
 * Plugin URI: https://github.com/dharmendra-chik-baraik/trackpress
 * Description: Minimal user and visitor tracking plugin with separate logs for users, visitors, and admin actions.
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * Author: Dharmendra Chik Baraik
 * Author URI: https://github.com/dharmendra-chik-baraik
 * License: GPL v2 or later
 * Text Domain: trackpress
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TRACKPRESS_VERSION', '1.0.0');
define('TRACKPRESS_PLUGIN_FILE', __FILE__);
define('TRACKPRESS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TRACKPRESS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TRACKPRESS_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Simple file loader instead of autoloader to ensure proper loading order
function trackpress_load_files() {
    $files = [
        // Core files
        'includes/class-database.php',
        'includes/class-tracker.php',
        
        // Tracking classes
        'includes/class-user-tracker.php',
        'includes/class-visitor-tracker.php',
        'includes/class-admin-tracker.php',
        
        // Admin files
        'admin/class-admin-pages.php',
    ];
    
    foreach ($files as $file) {
        require_once TRACKPRESS_PLUGIN_DIR . $file;
    }
}

// Initialize plugin
function trackpress_init() {
    // Load all files
    trackpress_load_files();
    
    // Initialize database
    TrackPress\Database::init();
    
    // Initialize tracker
    $tracker = new TrackPress\Tracker();
    
    // Initialize admin
    if (is_admin()) {
        new TrackPress\Admin\Admin_Pages();
        new TrackPress\Admin_Tracker();
    }
    
    // Initialize user tracker for frontend
    if (!is_admin() || (defined('DOING_AJAX') && DOING_AJAX)) {
        if (is_user_logged_in()) {
            new TrackPress\User_Tracker();
        } else {
            new TrackPress\Visitor_Tracker();
        }
    }
}

// Activation hook
register_activation_hook(__FILE__, function() {
    require_once TRACKPRESS_PLUGIN_DIR . 'includes/class-database.php';
    TrackPress\Database::create_tables();
    
    // Schedule cleanup if needed
    if (!wp_next_scheduled('trackpress_cleanup_old_logs')) {
        wp_schedule_event(time(), 'daily', 'trackpress_cleanup_old_logs');
    }
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Clear scheduled cleanup
    wp_clear_scheduled_hook('trackpress_cleanup_old_logs');
});

// Load translations on init (required for WordPress 6.7+)
add_action('init', function() {
    load_plugin_textdomain('trackpress', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Now initialize the plugin AFTER translations are loaded
    trackpress_init();
});