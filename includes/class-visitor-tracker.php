<?php
namespace TrackPress;

class Visitor_Tracker
{

    private $visitor_hash;
    private $session_id;
    private $current_url;

    public function __construct()
    {
        // Don't track if visitor tracking is disabled
        $settings = \TrackPress\Admin\Admin_Pages::get_settings();
        if (!$settings['track_visitors']) {
            return;
        }

        $this->visitor_hash = $this->generate_visitor_hash();
        $this->session_id = $this->get_session_id();
        $this->current_url = Database::get_current_url();

        // Don't track if IP is excluded
        if ($this->should_skip_ip()) {
            return;
        }

        // Don't track bots
        if ($this->is_bot()) {
            return;
        }

        $this->init_hooks();
    }

    private function generate_visitor_hash()
    {
        $ip = Database::get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

        // Create a hash that's persistent for this visitor (using cookies)
        $cookie_name = 'trackpress_visitor_hash';

        if (isset($_COOKIE[$cookie_name])) {
            return $_COOKIE[$cookie_name];
        }

        $hash = hash('sha256', $ip . $user_agent . time() . uniqid());
        setcookie($cookie_name, $hash, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN);

        return $hash;
    }

    private function get_session_id()
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (!headers_sent()) {
                session_start();
            }
        }
        return session_id();
    }

    private function should_skip_ip()
    {
        $settings = \TrackPress\Admin\Admin_Pages::get_settings();

        // FIX: Ensure exclude_ips is a string
        $exclude_ips_string = is_array($settings['exclude_ips'])
            ? implode("\n", $settings['exclude_ips'])
            : (string) $settings['exclude_ips'];

        $exclude_ips = array_map('trim', explode("\n", $exclude_ips_string));
        $user_ip = Database::get_client_ip();

        foreach ($exclude_ips as $ip) {
            if ($ip && $user_ip === $ip) {
                return true;
            }
        }

        return false;
    }

    private function is_bot()
    {
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? strtolower($_SERVER['HTTP_USER_AGENT']) : '';

        if (empty($user_agent)) {
            return true;
        }

        $bots = [
            'bot',
            'spider',
            'crawler',
            'scanner',
            'curl',
            'wget',
            'googlebot',
            'bingbot',
            'slurp',
            'duckduckbot',
            'baiduspider',
            'yandexbot',
            'sogou',
            'exabot',
            'facebookexternalhit',
            'twitterbot',
            'rogerbot',
            'linkedinbot',
            'embedly',
            'quora link preview',
            'showyoubot',
            'outbrain',
            'pinterest',
            'slackbot',
            'vkshare',
            'w3c_validator',
            'whatsapp',
            'discordbot',
            'telegrambot',
            'applebot',
            'semrushbot',
            'ahrefsbot',
            'mj12bot',
            'dotbot',
            'moz.com',
            'seokicks'
        ];

        foreach ($bots as $bot) {
            if (strpos($user_agent, $bot) !== false) {
                return true;
            }
        }

        return false;
    }

    private function init_hooks()
    {
        // Track page views via shutdown hook (already handled by main tracker)
        // Add specific visitor-only tracking

        // Track form submissions
        add_action('init', [$this, 'track_form_submissions']);

        // Track outbound links
        add_action('wp_footer', [$this, 'track_outbound_links']);

        // Track time on page
        add_action('wp_footer', [$this, 'track_time_on_page']);

        // Track scroll depth
        add_action('wp_footer', [$this, 'track_scroll_depth']);

        // Track search queries
        add_action('pre_get_search', [$this, 'track_search']);

        // Track custom actions via AJAX
        add_action('wp_ajax_trackpress_visitor_custom_action', [$this, 'handle_custom_action']);
        add_action('wp_ajax_nopriv_trackpress_visitor_custom_action', [$this, 'handle_custom_action']);

        // Track 404 errors
        add_action('template_redirect', [$this, 'track_404']);

        // Track file downloads
        add_action('init', [$this, 'track_file_downloads']);

        // Track video plays (if any)
        add_action('wp_footer', [$this, 'track_video_plays']);
    }

    public function track_form_submissions()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $forms_to_track = apply_filters('trackpress_track_visitor_forms', [
            'contact_form' => [
                'action' => 'contact_form_submission',
                'fields' => ['name', 'email', 'subject', 'message']
            ],
            'newsletter_form' => [
                'action' => 'newsletter_subscription',
                'fields' => ['email']
            ],
            'lead_form' => [
                'action' => 'lead_generation',
                'fields' => ['name', 'email', 'phone']
            ]
        ]);

        foreach ($forms_to_track as $form_id => $form_config) {
            $track = false;
            $form_data = [];

            // Check for form submissions
            if (isset($_POST['form_id']) && $_POST['form_id'] === $form_id) {
                $track = true;
            } elseif (isset($_POST['action']) && $_POST['action'] === $form_id) {
                $track = true;
            } elseif (isset($_POST['_wpcf7']) || isset($_POST['_contact_form_id'])) {
                // Contact Form 7
                $track = true;
                $form_id = 'contact_form_7';
                $form_config['action'] = 'cf7_form_submission';
            } elseif (isset($_POST['gform_submit'])) {
                // Gravity Forms
                $track = true;
                $form_id = 'gravity_form_' . intval($_POST['gform_submit']);
                $form_config['action'] = 'gravity_form_submission';
            }

            if ($track) {
                foreach ($form_config['fields'] as $field) {
                    if (isset($_POST[$field])) {
                        $form_data[$field] = substr(sanitize_text_field($_POST[$field]), 0, 100);
                    }
                }

                Database::insert_visitor_log([
                    'visitor_hash' => $this->visitor_hash,
                    'action_type' => $form_config['action'],
                    'action_details' => maybe_serialize([
                        'form_id' => $form_id,
                        'form_data' => $form_data,
                        'page_url' => $this->current_url,
                    ]),
                    'session_id' => $this->session_id,
                ]);

                break;
            }
        }
    }

    public function track_outbound_links()
    {
        // Only add tracking on frontend
        if (is_admin()) {
            return;
        }

        // JavaScript for tracking outbound links
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                var links = document.querySelectorAll('a[href^="http"]:not([href*="<?php echo home_url(); ?>"])');

                links.forEach(function (link) {
                    link.addEventListener('click', function (e) {
                        var href = this.href;
                        var linkText = this.textContent.trim().substring(0, 100);

                        // Send tracking data via AJAX
                        var data = new FormData();
                        data.append('action', 'trackpress_visitor_custom_action');
                        data.append('security', '<?php echo wp_create_nonce('trackpress_ajax_nonce'); ?>');
                        data.append('action_type', 'outbound_link_click');
                        data.append('action_details', JSON.stringify({
                            'link_url': href,
                            'link_text': linkText,
                            'page_url': window.location.href
                        }));

                        // Use beacon API for reliability
                        if (navigator.sendBeacon) {
                            var blob = new Blob([data], { type: 'application/x-www-form-urlencoded' });
                            navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', blob);
                        } else {
                            // Fallback to fetch
                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                body: data,
                                keepalive: true
                            });
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function track_time_on_page()
    {
        if (is_admin()) {
            return;
        }

        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                var startTime = Date.now();
                var pageVisible = true;

                // Track visibility change
                document.addEventListener('visibilitychange', function () {
                    pageVisible = !document.hidden;
                });

                // Send time on page when leaving
                window.addEventListener('beforeunload', function () {
                    var timeSpent = Math.round((Date.now() - startTime) / 1000);

                    if (timeSpent > 5 && pageVisible) { // Only track if spent > 5 seconds
                        var data = new FormData();
                        data.append('action', 'trackpress_visitor_custom_action');
                        data.append('security', '<?php echo wp_create_nonce('trackpress_ajax_nonce'); ?>');
                        data.append('action_type', 'time_on_page');
                        data.append('action_details', JSON.stringify({
                            'time_spent_seconds': timeSpent,
                            'page_url': window.location.href
                        }));

                        navigator.sendBeacon('<?php echo admin_url('admin-ajax.php'); ?>', data);
                    }
                });
            });
        </script>
        <?php
    }

    public function track_scroll_depth()
    {
        if (is_admin()) {
            return;
        }

        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                var scrollDepths = [25, 50, 75, 100];
                var trackedDepths = [];
                var windowHeight = window.innerHeight;
                var documentHeight = document.documentElement.scrollHeight;
                var maxScroll = documentHeight - windowHeight;

                function trackScrollDepth() {
                    var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                    var scrollPercentage = Math.round((scrollTop / maxScroll) * 100);

                    scrollDepths.forEach(function (depth) {
                        if (scrollPercentage >= depth && !trackedDepths.includes(depth)) {
                            trackedDepths.push(depth);

                            var data = new FormData();
                            data.append('action', 'trackpress_visitor_custom_action');
                            data.append('security', '<?php echo wp_create_nonce('trackpress_ajax_nonce'); ?>');
                            data.append('action_type', 'scroll_depth');
                            data.append('action_details', JSON.stringify({
                                'scroll_percentage': depth,
                                'page_url': window.location.href
                            }));

                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                body: data
                            });
                        }
                    });
                }

                window.addEventListener('scroll', function () {
                    clearTimeout(window.scrollTimeout);
                    window.scrollTimeout = setTimeout(trackScrollDepth, 500);
                });
            });
        </script>
        <?php
    }

    public function track_search($query)
    {
        if ($query->is_search() && !$query->is_admin()) {
            $search_query = get_search_query();

            if (!empty($search_query)) {
                Database::insert_visitor_log([
                    'visitor_hash' => $this->visitor_hash,
                    'action_type' => 'search_query',
                    'action_details' => maybe_serialize([
                        'search_term' => $search_query,
                        'results_found' => $query->found_posts,
                        'search_url' => get_search_link($search_query),
                    ]),
                    'page_url' => get_search_link($search_query),
                    'session_id' => $this->session_id,
                ]);
            }
        }
    }

    public function track_404()
    {
        if (is_404()) {
            Database::insert_visitor_log([
                'visitor_hash' => $this->visitor_hash,
                'action_type' => '404_error',
                'action_details' => maybe_serialize([
                    'requested_url' => $this->current_url,
                    'referrer' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
                ]),
                'page_url' => $this->current_url,
                'session_id' => $this->session_id,
            ]);
        }
    }

    public function track_file_downloads()
    {
        if (isset($_GET['trackpress_file_download']) && isset($_GET['file_id'])) {
            $file_id = intval($_GET['file_id']);
            $file_url = wp_get_attachment_url($file_id);

            if ($file_url) {
                $file = get_post($file_id);

                Database::insert_visitor_log([
                    'visitor_hash' => $this->visitor_hash,
                    'action_type' => 'file_download',
                    'action_details' => maybe_serialize([
                        'file_id' => $file_id,
                        'file_name' => $file ? $file->post_title : 'Unknown',
                        'file_type' => $file ? $file->post_mime_type : '',
                    ]),
                    'page_url' => $this->current_url,
                    'session_id' => $this->session_id,
                ]);
            }
        }
    }

    public function track_video_plays()
    {
        if (is_admin()) {
            return;
        }

        // This is a simple implementation - can be extended for specific video players
        ?>
        <script type="text/javascript">
            document.addEventListener('DOMContentLoaded', function () {
                var videos = document.querySelectorAll('video');

                videos.forEach(function (video, index) {
                    var tracked = false;

                    video.addEventListener('play', function () {
                        if (!tracked) {
                            tracked = true;

                            var data = new FormData();
                            data.append('action', 'trackpress_visitor_custom_action');
                            data.append('security', '<?php echo wp_create_nonce('trackpress_ajax_nonce'); ?>');
                            data.append('action_type', 'video_play');
                            data.append('action_details', JSON.stringify({
                                'video_src': this.currentSrc || '',
                                'page_url': window.location.href
                            }));

                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                body: data
                            });
                        }
                    });

                    // Track when 50% of video is watched
                    video.addEventListener('timeupdate', function () {
                        if (!this.tracked50 && this.currentTime > (this.duration * 0.5)) {
                            this.tracked50 = true;

                            var data = new FormData();
                            data.append('action', 'trackpress_visitor_custom_action');
                            data.append('security', '<?php echo wp_create_nonce('trackpress_ajax_nonce'); ?>');
                            data.append('action_type', 'video_50_percent');
                            data.append('action_details', JSON.stringify({
                                'video_src': this.currentSrc || '',
                                'page_url': window.location.href
                            }));

                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                body: data
                            });
                        }
                    });
                });
            });
        </script>
        <?php
    }

    public function handle_custom_action()
    {
        check_ajax_referer('trackpress_ajax_nonce', 'security');

        if (isset($_POST['action_type']) && isset($_POST['action_details'])) {
            $action_type = sanitize_text_field($_POST['action_type']);
            $action_details = is_string($_POST['action_details']) ? $_POST['action_details'] : '';

            Database::insert_visitor_log([
                'visitor_hash' => $this->visitor_hash,
                'action_type' => $action_type,
                'action_details' => $action_details,
                'page_url' => isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : $this->current_url,
                'session_id' => $this->session_id,
            ]);

            wp_send_json_success(['message' => 'Visitor action tracked successfully']);
        }

        wp_send_json_error(['message' => 'Invalid request']);
    }

    public static function get_visitor_stats($visitor_hash = null)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'trackpress_visitors_logs';

        if ($visitor_hash) {
            // Get stats for specific visitor
            $total_visits = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE visitor_hash = %s",
                $visitor_hash
            ));

            $first_visit = $wpdb->get_var($wpdb->prepare(
                "SELECT MIN(created_at) FROM $table_name WHERE visitor_hash = %s",
                $visitor_hash
            ));

            $last_visit = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(created_at) FROM $table_name WHERE visitor_hash = %s",
                $visitor_hash
            ));

            $actions_by_type = $wpdb->get_results($wpdb->prepare(
                "SELECT action_type, COUNT(*) as count 
                 FROM $table_name 
                 WHERE visitor_hash = %s 
                 GROUP BY action_type 
                 ORDER BY count DESC",
                $visitor_hash
            ));

            return [
                'total_visits' => $total_visits,
                'first_visit' => $first_visit,
                'last_visit' => $last_visit,
                'actions_by_type' => $actions_by_type,
            ];
        } else {
            // Get overall visitor stats
            $total_unique_visitors = $wpdb->get_var("SELECT COUNT(DISTINCT visitor_hash) FROM $table_name");
            $total_visits = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
            $visits_today = $wpdb->get_var(
                "SELECT COUNT(*) FROM $table_name WHERE DATE(created_at) = CURDATE()"
            );

            $top_pages = $wpdb->get_results(
                "SELECT page_url, COUNT(*) as visits 
                 FROM $table_name 
                 WHERE action_type = 'page_view'
                 GROUP BY page_url 
                 ORDER BY visits DESC 
                 LIMIT 10"
            );

            $top_countries = $wpdb->get_results(
                "SELECT country_code, COUNT(*) as visits 
                 FROM $table_name 
                 WHERE country_code IS NOT NULL 
                 GROUP BY country_code 
                 ORDER BY visits DESC 
                 LIMIT 10"
            );

            return [
                'total_unique_visitors' => $total_unique_visitors,
                'total_visits' => $total_visits,
                'visits_today' => $visits_today,
                'top_pages' => $top_pages,
                'top_countries' => $top_countries,
            ];
        }
    }

    public static function get_recent_visitors($limit = 20)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'trackpress_visitors_logs';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT visitor_hash, MAX(created_at) as last_visit, 
                    COUNT(*) as total_visits, 
                    MIN(created_at) as first_visit,
                    ip_address, country_code, device_type
             FROM $table_name 
             GROUP BY visitor_hash 
             ORDER BY last_visit DESC 
             LIMIT %d",
            $limit
        ));
    }
}