<?php
namespace TrackPress;

class User_Tracker
{

    private $user;
    private $current_action = 'page_view';

    public function __construct()
    {
        $this->user = wp_get_current_user();

        // Don't track if user shouldn't be tracked
        if ($this->should_skip_user()) {
            return;
        }

        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Track form submissions
        add_action('init', [$this, 'track_form_submissions']);

        // Track comments
        add_action('comment_post', [$this, 'track_comment_submission'], 10, 2);

        // Track WooCommerce actions if WooCommerce is active
        if (class_exists('WooCommerce')) {
            $this->init_woocommerce_hooks();
        }

        // Track search queries
        add_action('pre_get_search', [$this, 'track_search']);

        // Track custom actions via AJAX
        add_action('wp_ajax_trackpress_custom_action', [$this, 'handle_custom_action']);
        add_action('wp_ajax_nopriv_trackpress_custom_action', [$this, 'handle_custom_action']);

        // Track file downloads
        add_action('init', [$this, 'track_file_downloads']);

        // Track specific custom hooks
        $this->track_custom_hooks();
    }

    private function should_skip_user()
    {
        $settings = \TrackPress\Admin\Admin_Pages::get_settings();

        // Check if tracking is disabled for logged-in users
        if (!$settings['track_logged_in']) {
            return true;
        }

        // Check if user role is in skip list
        $skip_roles = is_array($settings['skip_roles']) ? $settings['skip_roles'] : [];
        $user_roles = $this->user->roles;

        if (array_intersect($skip_roles, $user_roles)) {
            return true;
        }

        // Check excluded IPs
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

    public function track_form_submissions()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $forms_to_track = apply_filters('trackpress_track_forms', [
            'contact_form' => [
                'action' => 'contact_form_submission',
                'fields' => ['name', 'email', 'subject', 'message']
            ],
            'newsletter_form' => [
                'action' => 'newsletter_subscription',
                'fields' => ['email']
            ]
        ]);

        foreach ($forms_to_track as $form_id => $form_config) {
            $track = false;
            $form_data = [];

            // Check for form submissions based on different criteria
            if (isset($_POST['form_id']) && $_POST['form_id'] === $form_id) {
                $track = true;
            } elseif (isset($_POST['action']) && $_POST['action'] === $form_id) {
                $track = true;
            } elseif (isset($_POST['_wpcf7']) || isset($_POST['_contact_form_id'])) {
                // Contact Form 7
                $track = true;
                $form_id = 'contact_form_7';
                $form_config['action'] = 'cf7_form_submission';
            }

            if ($track) {
                foreach ($form_config['fields'] as $field) {
                    if (isset($_POST[$field])) {
                        $form_data[$field] = substr(sanitize_text_field($_POST[$field]), 0, 100);
                    }
                }

                Database::insert_user_log([
                    'user_id' => $this->user->ID,
                    'user_login' => $this->user->user_login,
                    'user_email' => $this->user->user_email,
                    'action_type' => $form_config['action'],
                    'action_details' => maybe_serialize([
                        'form_id' => $form_id,
                        'form_data' => $form_data,
                        'page_url' => Database::get_current_url(),
                    ]),
                ]);

                break;
            }
        }
    }

    public function track_comment_submission($comment_id, $comment_approved)
    {
        $comment = get_comment($comment_id);

        if ($comment) {
            $post = get_post($comment->comment_post_ID);

            Database::insert_user_log([
                'user_id' => $this->user->ID,
                'user_login' => $this->user->user_login,
                'user_email' => $this->user->user_email,
                'action_type' => 'comment_submitted',
                'action_details' => maybe_serialize([
                    'comment_id' => $comment_id,
                    'comment_content' => wp_trim_words($comment->comment_content, 20),
                    'post_id' => $comment->comment_post_ID,
                    'post_title' => $post ? $post->post_title : 'Unknown',
                    'comment_approved' => $comment_approved,
                ]),
                'page_url' => get_permalink($comment->comment_post_ID),
            ]);
        }
    }

    private function init_woocommerce_hooks()
    {
        // Track cart actions
        add_action('woocommerce_add_to_cart', [$this, 'track_add_to_cart'], 10, 6);
        add_action('woocommerce_cart_item_removed', [$this, 'track_remove_from_cart'], 10, 2);

        // Track checkout
        add_action('woocommerce_checkout_order_processed', [$this, 'track_order_processed'], 10, 3);

        // Track product views
        add_action('template_redirect', [$this, 'track_product_view']);

        // Track wishlist if supported
        add_action('yith_wcwl_added_to_wishlist', [$this, 'track_add_to_wishlist'], 10, 4);
    }

    public function track_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data)
    {
        $product = wc_get_product($product_id);

        Database::insert_user_log([
            'user_id' => $this->user->ID,
            'user_login' => $this->user->user_login,
            'user_email' => $this->user->user_email,
            'action_type' => 'woocommerce_add_to_cart',
            'action_details' => maybe_serialize([
                'product_id' => $product_id,
                'product_name' => $product ? $product->get_name() : 'Unknown',
                'quantity' => $quantity,
                'variation_id' => $variation_id,
                'price' => $product ? $product->get_price() : 0,
                'cart_item_key' => $cart_item_key,
            ]),
            'page_url' => Database::get_current_url(),
        ]);
    }

    public function track_remove_from_cart($cart_item_key, $cart)
    {
        $product_id = isset($cart->removed_cart_contents[$cart_item_key]['product_id'])
            ? $cart->removed_cart_contents[$cart_item_key]['product_id']
            : 0;

        $product = wc_get_product($product_id);

        Database::insert_user_log([
            'user_id' => $this->user->ID,
            'user_login' => $this->user->user_login,
            'user_email' => $this->user->user_email,
            'action_type' => 'woocommerce_remove_from_cart',
            'action_details' => maybe_serialize([
                'product_id' => $product_id,
                'product_name' => $product ? $product->get_name() : 'Unknown',
                'cart_item_key' => $cart_item_key,
            ]),
            'page_url' => wc_get_cart_url(),
        ]);
    }

    public function track_order_processed($order_id, $posted_data, $order)
    {
        Database::insert_user_log([
            'user_id' => $this->user->ID,
            'user_login' => $this->user->user_login,
            'user_email' => $this->user->user_email,
            'action_type' => 'woocommerce_order_placed',
            'action_details' => maybe_serialize([
                'order_id' => $order_id,
                'order_total' => $order->get_total(),
                'payment_method' => $order->get_payment_method(),
                'billing_email' => $order->get_billing_email(),
                'item_count' => $order->get_item_count(),
            ]),
            'page_url' => wc_get_checkout_url(),
        ]);
    }

    public function track_product_view()
    {
        if (is_product()) {
            $product_id = get_the_ID();
            $product = wc_get_product($product_id);

            Database::insert_user_log([
                'user_id' => $this->user->ID,
                'user_login' => $this->user->user_login,
                'user_email' => $this->user->user_email,
                'action_type' => 'woocommerce_product_view',
                'action_details' => maybe_serialize([
                    'product_id' => $product_id,
                    'product_name' => $product ? $product->get_name() : 'Unknown',
                    'product_price' => $product ? $product->get_price() : 0,
                    'product_sku' => $product ? $product->get_sku() : '',
                ]),
                'page_url' => get_permalink($product_id),
            ]);
        }
    }

    public function track_add_to_wishlist($prod_id, $wishlist_id, $user_id, $dateadded)
    {
        if ($user_id == $this->user->ID) {
            $product = wc_get_product($prod_id);

            Database::insert_user_log([
                'user_id' => $this->user->ID,
                'user_login' => $this->user->user_login,
                'user_email' => $this->user->user_email,
                'action_type' => 'woocommerce_add_to_wishlist',
                'action_details' => maybe_serialize([
                    'product_id' => $prod_id,
                    'product_name' => $product ? $product->get_name() : 'Unknown',
                    'wishlist_id' => $wishlist_id,
                ]),
                'page_url' => Database::get_current_url(),
            ]);
        }
    }

    public function track_search($query)
    {
        if ($query->is_search() && !$query->is_admin()) {
            $search_query = get_search_query();

            if (!empty($search_query)) {
                Database::insert_user_log([
                    'user_id' => $this->user->ID,
                    'user_login' => $this->user->user_login,
                    'user_email' => $this->user->user_email,
                    'action_type' => 'search_query',
                    'action_details' => maybe_serialize([
                        'search_term' => $search_query,
                        'results_found' => $query->found_posts,
                        'search_url' => get_search_link($search_query),
                    ]),
                    'page_url' => get_search_link($search_query),
                ]);
            }
        }
    }

    public function track_file_downloads()
    {
        if (isset($_GET['trackpress_file_download']) && isset($_GET['file_id'])) {
            $file_id = intval($_GET['file_id']);
            $file_url = wp_get_attachment_url($file_id);

            if ($file_url) {
                $file = get_post($file_id);

                Database::insert_user_log([
                    'user_id' => $this->user->ID,
                    'user_login' => $this->user->user_login,
                    'user_email' => $this->user->user_email,
                    'action_type' => 'file_download',
                    'action_details' => maybe_serialize([
                        'file_id' => $file_id,
                        'file_name' => $file ? $file->post_title : 'Unknown',
                        'file_type' => $file ? $file->post_mime_type : '',
                        'file_size' => $file ? filesize(get_attached_file($file_id)) : 0,
                    ]),
                    'page_url' => Database::get_current_url(),
                ]);
            }
        }
    }

    public function handle_custom_action()
    {
        check_ajax_referer('trackpress_ajax_nonce', 'security');

        if (isset($_POST['action_type']) && isset($_POST['action_details'])) {
            $action_type = sanitize_text_field($_POST['action_type']);
            $action_details = sanitize_text_field($_POST['action_details']);

            Database::insert_user_log([
                'user_id' => $this->user->ID,
                'user_login' => $this->user->user_login,
                'user_email' => $this->user->user_email,
                'action_type' => $action_type,
                'action_details' => $action_details,
                'page_url' => isset($_POST['page_url']) ? esc_url_raw($_POST['page_url']) : Database::get_current_url(),
            ]);

            wp_send_json_success(['message' => 'Action tracked successfully']);
        }

        wp_send_json_error(['message' => 'Invalid request']);
    }

    private function track_custom_hooks()
    {
        // Allow developers to add custom tracking hooks
        $custom_hooks = apply_filters('trackpress_user_custom_hooks', []);

        foreach ($custom_hooks as $hook => $config) {
            add_action($hook, function () use ($config) {
                $action_type = isset($config['action_type']) ? $config['action_type'] : $hook;
                $action_details = isset($config['action_details']) ? $config['action_details'] : '';

                // Allow dynamic details via callback
                if (is_callable($action_details)) {
                    $action_details = call_user_func($action_details);
                }

                Database::insert_user_log([
                    'user_id' => $this->user->ID,
                    'user_login' => $this->user->user_login,
                    'user_email' => $this->user->user_email,
                    'action_type' => $action_type,
                    'action_details' => maybe_serialize($action_details),
                    'page_url' => Database::get_current_url(),
                ]);
            }, 10);
        }
    }

    public static function log_custom_user_action($user_id, $action_type, $action_details = '', $extra_data = [])
    {
        $user = get_userdata($user_id);

        if ($user) {
            $data = [
                'user_id' => $user_id,
                'user_login' => $user->user_login,
                'user_email' => $user->user_email,
                'action_type' => $action_type,
                'action_details' => $action_details,
            ];

            if (!empty($extra_data)) {
                $data = array_merge($data, $extra_data);
            }

            return Database::insert_user_log($data);
        }

        return false;
    }

    public static function get_user_activity_summary($user_id, $days = 7)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'trackpress_users_logs';

        $start_date = date('Y-m-d H:i:s', strtotime("-$days days"));

        // Get total actions
        $total_actions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE user_id = %d AND created_at >= %s",
            $user_id,
            $start_date
        ));

        // Get actions by type
        $actions_by_type = $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, COUNT(*) as count 
             FROM $table_name 
             WHERE user_id = %d AND created_at >= %s 
             GROUP BY action_type 
             ORDER BY count DESC",
            $user_id,
            $start_date
        ));

        // Get recent activity
        $recent_activity = $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, action_details, created_at 
             FROM $table_name 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT 10",
            $user_id
        ));

        return [
            'total_actions' => $total_actions,
            'actions_by_type' => $actions_by_type,
            'recent_activity' => $recent_activity,
        ];
    }
}