<?php
/**
 * TrackPress Uninstall
 *
 * Fired when the plugin is uninstalled.
 *
 * @package TrackPress
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Check if user has permissions to uninstall
if (!current_user_can('activate_plugins')) {
    return;
}

global $wpdb;

// Define plugin tables
$tables = [
    $wpdb->prefix . 'trackpress_users_logs',
    $wpdb->prefix . 'trackpress_visitors_logs',
    $wpdb->prefix . 'trackpress_admin_logs'
];

// Option to keep data on uninstall
$keep_data = get_option('trackpress_keep_data', 0);

if (!$keep_data) {
    // Delete all plugin tables
    foreach ($tables as $table) {
        $wpdb->query("DROP TABLE IF EXISTS $table");
    }
    
    // Delete all plugin options
    $options = [
        'trackpress_db_version',
        'trackpress_settings',
        'trackpress_keep_data',
        'trackpress_version'
    ];
    
    foreach ($options as $option) {
        delete_option($option);
    }
    
    // Delete any scheduled events
    wp_clear_scheduled_hook('trackpress_cleanup_old_logs');
    
    // Delete any transients
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_trackpress_%'");
    $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_trackpress_%'");
}