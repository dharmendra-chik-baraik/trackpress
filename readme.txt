=== TrackPress ===
Contributors: dharmendra chik baraik
Donate link: https://example.com
Tags: tracking, analytics, user tracking, visitor tracking, logs, monitoring
Requires at least: 5.0
Tested up to: 6.5
Stable tag: 1.0.0
Requires PHP: 7.2
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Minimal user and visitor tracking plugin with separate logs for users, visitors, and admin actions.

== Description ==

TrackPress is a lightweight and efficient tracking plugin for WordPress that helps you monitor user and visitor activities on your website. The plugin creates three separate tables for different types of tracking to keep your data organized and easily accessible.

**Features:**

* **User Tracking:** Track logged-in user activities including page views, form submissions, comments, and more
* **Visitor Tracking:** Monitor anonymous visitors with session tracking, device detection, and behavioral analytics
* **Admin Actions Tracking:** Keep a complete audit log of all admin area changes including posts, pages, users, plugins, and settings
* **Separate Database Tables:** Three optimized tables for users, visitors, and admin actions
* **Clean Admin Interface:** Simple, intuitive interface with pagination, search, and filtering
* **Export Functionality:** Export logs in CSV format for external analysis
* **Privacy Controls:** Exclude specific user roles, IP addresses, and pages from tracking
* **Auto Cleanup:** Automatically remove old logs based on your retention policy

**What Gets Tracked:**

* **Users:** Login/logout, page views, form submissions, comments, searches
* **Visitors:** Page views, outbound links, scroll depth, time on page, form submissions, 404 errors
* **Admin:** Post/page edits, user management, plugin/theme changes, settings updates, media uploads

== Installation ==

1. Upload the `trackpress` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to TrackPress -> Settings to configure tracking options
4. The plugin will automatically create the necessary database tables

== Frequently Asked Questions ==

= Can I exclude certain users from tracking? =

Yes, you can exclude specific user roles from tracking in the plugin settings.

= How long are logs stored? =

By default, logs are stored for 30 days. You can change this in settings or disable auto-cleanup entirely.

= Does this affect website performance? =

TrackPress is designed to be lightweight and efficient. Database queries are optimized and tracking happens asynchronously to minimize impact on page load times.

= Can I export the tracking data? =

Yes, you can export all logs in CSV format from the settings page.

= Is this plugin GDPR compliant? =

TrackPress provides tools to help with GDPR compliance (IP anonymization, data retention controls, export/delete functionality), but you should configure it according to your specific legal requirements and privacy policy.

== Changelog ==

= 1.0.0 =
* Initial release
* User tracking with separate table
* Visitor tracking with separate table  
* Admin actions tracking with separate table
* Clean admin interface
* Export functionality
* Privacy controls and exclusions

== Upgrade Notice ==

= 1.0.0 =
Initial release of TrackPress.