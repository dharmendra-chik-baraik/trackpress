<?php
namespace TrackPress;

class Admin_Tracker
{

    public function __construct()
    {
        $settings = \TrackPress\Admin\Admin_Pages::get_settings();

        // Don't track if admin tracking is disabled
        if (!$settings['track_admin']) {
            return;
        }

        if (wp_doing_ajax()) {
            return;
        }

        // Don't track for excluded roles
        $user = wp_get_current_user();
        $skip_roles = $settings['skip_roles'];
        $user_roles = $user->roles;

        if (array_intersect($skip_roles, $user_roles)) {
            return;
        }

        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Post and Page actions
        add_action('transition_post_status', [$this, 'track_post_status_change'], 10, 3);
        add_action('save_post', [$this, 'track_post_save'], 10, 3);
        add_action('delete_post', [$this, 'track_post_delete']);
        add_action('wp_trash_post', [$this, 'track_post_trash']);
        add_action('untrash_post', [$this, 'track_post_untrash']);

        // Media actions
        add_action('add_attachment', [$this, 'track_media_add']);
        add_action('edit_attachment', [$this, 'track_media_edit']);
        add_action('delete_attachment', [$this, 'track_media_delete']);

        // User actions
        add_action('edit_user_profile_update', [$this, 'track_user_profile_update']);
        add_action('delete_user', [$this, 'track_user_delete']);
        add_action('user_register', [$this, 'track_user_register_admin']);

        // Plugin actions
        add_action('activated_plugin', [$this, 'track_plugin_activation']);
        add_action('deactivated_plugin', [$this, 'track_plugin_deactivation']);
        add_action('upgrader_process_complete', [$this, 'track_plugin_update'], 10, 2);

        // Theme actions
        add_action('switch_theme', [$this, 'track_theme_change']);
        add_action('upgrader_process_complete', [$this, 'track_theme_update'], 10, 2);

        // Settings actions
        add_action('updated_option', [$this, 'track_option_update'], 10, 3);

        // Menu actions
        add_action('wp_update_nav_menu', [$this, 'track_menu_update']);
        add_action('wp_delete_nav_menu', [$this, 'track_menu_delete']);

        // Widget actions
        add_action('updated_option', [$this, 'track_widget_update'], 10, 3);

        // Comment actions
        add_action('transition_comment_status', [$this, 'track_comment_status_change'], 10, 3);
        add_action('edit_comment', [$this, 'track_comment_edit']);
        add_action('delete_comment', [$this, 'track_comment_delete']);
        add_action('trash_comment', [$this, 'track_comment_trash']);
        add_action('untrash_comment', [$this, 'track_comment_untrash']);

        // Taxonomy actions
        add_action('created_term', [$this, 'track_term_created'], 10, 3);
        add_action('edited_term', [$this, 'track_term_edited'], 10, 3);
        add_action('delete_term', [$this, 'track_term_deleted'], 10, 4);

        // Custom actions
        add_action('admin_notices', [$this, 'track_admin_notices']);

        // Custom hooks for other plugins
        $this->track_custom_hooks();
    }

    public function track_post_status_change($new_status, $old_status, $post)
    {
        if ($new_status === $old_status) {
            return;
        }

        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'post_status_change',
            'action_details' => sprintf(
                'Post status changed from "%s" to "%s"',
                $old_status,
                $new_status
            ),
            'object_type' => 'post',
            'object_id' => $post->ID,
            'object_name' => $post->post_title,
            'admin_page' => admin_url('post.php?post=' . $post->ID . '&action=edit'),
        ]);
    }

    public function track_post_save($post_id, $post, $update)
    {
        // Skip auto-drafts and revisions
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) {
            return;
        }

        $user = wp_get_current_user();
        $action_type = $update ? 'post_updated' : 'post_created';

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => $action_type,
            'action_details' => sprintf(
                'Post %s: "%s" (ID: %d)',
                $update ? 'updated' : 'created',
                $post->post_title,
                $post_id
            ),
            'object_type' => 'post',
            'object_id' => $post_id,
            'object_name' => $post->post_title,
            'admin_page' => admin_url('post.php?post=' . $post_id . '&action=edit'),
        ]);
    }

    public function track_post_delete($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'post_deleted',
            'action_details' => sprintf(
                'Post permanently deleted: "%s" (ID: %d)',
                $post->post_title,
                $post_id
            ),
            'object_type' => 'post',
            'object_id' => $post_id,
            'object_name' => $post->post_title,
            'admin_page' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
        ]);
    }

    public function track_post_trash($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'post_trashed',
            'action_details' => sprintf(
                'Post moved to trash: "%s" (ID: %d)',
                $post->post_title,
                $post_id
            ),
            'object_type' => 'post',
            'object_id' => $post_id,
            'object_name' => $post->post_title,
            'admin_page' => admin_url('edit.php'),
        ]);
    }

    public function track_post_untrash($post_id)
    {
        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'post_untrashed',
            'action_details' => sprintf(
                'Post restored from trash: "%s" (ID: %d)',
                $post->post_title,
                $post_id
            ),
            'object_type' => 'post',
            'object_id' => $post_id,
            'object_name' => $post->post_title,
            'admin_page' => admin_url('edit.php?post_status=trash'),
        ]);
    }

    public function track_media_add($attachment_id)
    {
        $attachment = get_post($attachment_id);
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'media_uploaded',
            'action_details' => sprintf(
                'Media uploaded: "%s" (ID: %d, Type: %s)',
                $attachment->post_title,
                $attachment_id,
                get_post_mime_type($attachment_id)
            ),
            'object_type' => 'attachment',
            'object_id' => $attachment_id,
            'object_name' => $attachment->post_title,
            'admin_page' => admin_url('upload.php'),
        ]);
    }

    public function track_media_edit($attachment_id)
    {
        $attachment = get_post($attachment_id);
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'media_edited',
            'action_details' => sprintf(
                'Media edited: "%s" (ID: %d)',
                $attachment->post_title,
                $attachment_id
            ),
            'object_type' => 'attachment',
            'object_id' => $attachment_id,
            'object_name' => $attachment->post_title,
            'admin_page' => admin_url('post.php?post=' . $attachment_id . '&action=edit'),
        ]);
    }

    public function track_media_delete($attachment_id)
    {
        $attachment = get_post($attachment_id);
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'media_deleted',
            'action_details' => sprintf(
                'Media deleted: "%s" (ID: %d)',
                $attachment ? $attachment->post_title : 'Unknown',
                $attachment_id
            ),
            'object_type' => 'attachment',
            'object_id' => $attachment_id,
            'object_name' => $attachment ? $attachment->post_title : 'Unknown',
            'admin_page' => admin_url('upload.php'),
        ]);
    }

    public function track_user_profile_update($user_id)
    {
        $user = get_userdata($user_id);
        $current_user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $current_user->ID,
            'user_login' => $current_user->user_login,
            'user_role' => current($current_user->roles),
            'action_type' => 'user_profile_updated',
            'action_details' => sprintf(
                'User profile updated: %s (ID: %d)',
                $user->user_login,
                $user_id
            ),
            'object_type' => 'user',
            'object_id' => $user_id,
            'object_name' => $user->user_login,
            'admin_page' => admin_url('user-edit.php?user_id=' . $user_id),
        ]);
    }

    public function track_user_delete($user_id)
    {
        $user = get_userdata($user_id);
        $current_user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $current_user->ID,
            'user_login' => $current_user->user_login,
            'user_role' => current($current_user->roles),
            'action_type' => 'user_deleted',
            'action_details' => sprintf(
                'User deleted: %s (ID: %d)',
                $user->user_login,
                $user_id
            ),
            'object_type' => 'user',
            'object_id' => $user_id,
            'object_name' => $user->user_login,
            'admin_page' => admin_url('users.php'),
        ]);
    }

    public function track_user_register_admin($user_id)
    {
        // Only track if in admin area (user created by admin)
        if (!is_admin()) {
            return;
        }

        $user = get_userdata($user_id);
        $current_user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $current_user->ID,
            'user_login' => $current_user->user_login,
            'user_role' => current($current_user->roles),
            'action_type' => 'user_created',
            'action_details' => sprintf(
                'New user created: %s (ID: %d)',
                $user->user_login,
                $user_id
            ),
            'object_type' => 'user',
            'object_id' => $user_id,
            'object_name' => $user->user_login,
            'admin_page' => admin_url('user-new.php'),
        ]);
    }

    public function track_plugin_activation($plugin)
    {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'plugin_activated',
            'action_details' => sprintf(
                'Plugin activated: %s v%s',
                $plugin_data['Name'],
                $plugin_data['Version']
            ),
            'object_type' => 'plugin',
            'object_name' => $plugin_data['Name'],
            'admin_page' => admin_url('plugins.php'),
        ]);
    }

    public function track_plugin_deactivation($plugin)
    {
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'plugin_deactivated',
            'action_details' => sprintf(
                'Plugin deactivated: %s',
                $plugin_data['Name']
            ),
            'object_type' => 'plugin',
            'object_name' => $plugin_data['Name'],
            'admin_page' => admin_url('plugins.php'),
        ]);
    }

    public function track_plugin_update($upgrader, $hook_extra)
    {
        if ($hook_extra['type'] !== 'plugin' || !isset($hook_extra['plugins'])) {
            return;
        }

        $user = wp_get_current_user();

        foreach ($hook_extra['plugins'] as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);

            Database::insert_admin_log([
                'user_id' => $user->ID,
                'user_login' => $user->user_login,
                'user_role' => current($user->roles),
                'action_type' => 'plugin_updated',
                'action_details' => sprintf(
                    'Plugin updated: %s',
                    $plugin_data['Name']
                ),
                'object_type' => 'plugin',
                'object_name' => $plugin_data['Name'],
                'admin_page' => admin_url('update-core.php'),
            ]);
        }
    }

    public function track_theme_change($new_theme)
    {
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'theme_changed',
            'action_details' => sprintf(
                'Theme changed to: %s',
                $new_theme->get('Name')
            ),
            'object_type' => 'theme',
            'object_name' => $new_theme->get('Name'),
            'admin_page' => admin_url('themes.php'),
        ]);
    }

    public function track_theme_update($upgrader, $hook_extra)
    {
        if ($hook_extra['type'] !== 'theme' || !isset($hook_extra['themes'])) {
            return;
        }

        $user = wp_get_current_user();

        foreach ($hook_extra['themes'] as $theme_slug) {
            $theme = wp_get_theme($theme_slug);

            Database::insert_admin_log([
                'user_id' => $user->ID,
                'user_login' => $user->user_login,
                'user_role' => current($user->roles),
                'action_type' => 'theme_updated',
                'action_details' => sprintf(
                    'Theme updated: %s',
                    $theme->get('Name')
                ),
                'object_type' => 'theme',
                'object_name' => $theme->get('Name'),
                'admin_page' => admin_url('update-core.php'),
            ]);
        }
    }

    public function track_option_update($option, $old_value, $value)
    {
        // Skip some options to reduce noise
        $skip_options = apply_filters('trackpress_skip_options', [
            'cron',
            'recently_edited',
            'auto_updater.lock',
            'core_updater.lock',
            'theme_switched',
            'widget_pages',
            'widget_calendar',
            'widget_archives',
            'widget_media_audio',
            'widget_media_image',
            'widget_media_gallery',
            'widget_media_video',
            'widget_meta',
            'widget_search',
            'widget_text',
            'widget_categories',
            'widget_recent-posts',
            'widget_recent-comments',
            'widget_rss',
            'widget_tag_cloud',
            'widget_nav_menu',
            'widget_custom_html',
            'sidebars_widgets',
            'trackpress_',
        ]);

        foreach ($skip_options as $skip) {
            if (strpos($option, $skip) === 0) {
                return;
            }
        }

        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'option_updated',
            'action_details' => sprintf(
                'Option updated: %s',
                $option
            ),
            'object_type' => 'option',
            'object_name' => $option,
            'admin_page' => $this->get_current_admin_page(),
        ]);
    }

    public function track_menu_update($menu_id)
    {
        $menu = wp_get_nav_menu_object($menu_id);
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'menu_updated',
            'action_details' => sprintf(
                'Menu updated: %s (ID: %d)',
                $menu->name,
                $menu_id
            ),
            'object_type' => 'menu',
            'object_id' => $menu_id,
            'object_name' => $menu->name,
            'admin_page' => admin_url('nav-menus.php'),
        ]);
    }

    public function track_menu_delete($menu_id)
    {
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'menu_deleted',
            'action_details' => sprintf(
                'Menu deleted: ID %d',
                $menu_id
            ),
            'object_type' => 'menu',
            'object_id' => $menu_id,
            'object_name' => 'Menu #' . $menu_id,
            'admin_page' => admin_url('nav-menus.php'),
        ]);
    }

    public function track_widget_update($option, $old_value, $value)
    {
        if (
            $option !== 'widget_pages' &&
            strpos($option, 'widget_') !== 0 &&
            $option !== 'sidebars_widgets'
        ) {
            return;
        }

        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'widgets_updated',
            'action_details' => sprintf(
                'Widgets updated: %s',
                $option
            ),
            'object_type' => 'widgets',
            'object_name' => $option,
            'admin_page' => admin_url('widgets.php'),
        ]);
    }

    public function track_comment_status_change($new_status, $old_status, $comment)
    {
        if ($new_status === $old_status) {
            return;
        }

        $user = wp_get_current_user();
        $post = get_post($comment->comment_post_ID);

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'comment_status_change',
            'action_details' => sprintf(
                'Comment status changed from "%s" to "%s" on post "%s"',
                $old_status,
                $new_status,
                $post->post_title
            ),
            'object_type' => 'comment',
            'object_id' => $comment->comment_ID,
            'object_name' => 'Comment #' . $comment->comment_ID,
            'admin_page' => admin_url('edit-comments.php'),
        ]);
    }

    public function track_comment_edit($comment_id)
    {
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'comment_edited',
            'action_details' => 'Comment edited',
            'object_type' => 'comment',
            'object_id' => $comment_id,
            'object_name' => 'Comment #' . $comment_id,
            'admin_page' => admin_url('comment.php?action=editcomment&c=' . $comment_id),
        ]);
    }

    public function track_comment_delete($comment_id)
    {
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'comment_deleted',
            'action_details' => 'Comment permanently deleted',
            'object_type' => 'comment',
            'object_id' => $comment_id,
            'object_name' => 'Comment #' . $comment_id,
            'admin_page' => admin_url('edit-comments.php'),
        ]);
    }

    public function track_comment_trash($comment_id)
    {
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'comment_trashed',
            'action_details' => 'Comment moved to trash',
            'object_type' => 'comment',
            'object_id' => $comment_id,
            'object_name' => 'Comment #' . $comment_id,
            'admin_page' => admin_url('edit-comments.php'),
        ]);
    }

    public function track_comment_untrash($comment_id)
    {
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'comment_untrashed',
            'action_details' => 'Comment restored from trash',
            'object_type' => 'comment',
            'object_id' => $comment_id,
            'object_name' => 'Comment #' . $comment_id,
            'admin_page' => admin_url('edit-comments.php?comment_status=trash'),
        ]);
    }

    public function track_term_created($term_id, $tt_id, $taxonomy)
    {
        $term = get_term($term_id, $taxonomy);
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'term_created',
            'action_details' => sprintf(
                '%s created: %s',
                $this->get_taxonomy_label($taxonomy),
                $term->name
            ),
            'object_type' => 'term',
            'object_id' => $term_id,
            'object_name' => $term->name,
            'admin_page' => admin_url('edit-tags.php?taxonomy=' . $taxonomy),
        ]);
    }

    public function track_term_edited($term_id, $tt_id, $taxonomy)
    {
        $term = get_term($term_id, $taxonomy);
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'term_edited',
            'action_details' => sprintf(
                '%s edited: %s',
                $this->get_taxonomy_label($taxonomy),
                $term->name
            ),
            'object_type' => 'term',
            'object_id' => $term_id,
            'object_name' => $term->name,
            'admin_page' => admin_url('term.php?taxonomy=' . $taxonomy . '&tag_ID=' . $term_id),
        ]);
    }

    public function track_term_deleted($term_id, $tt_id, $taxonomy, $deleted_term)
    {
        $user = wp_get_current_user();

        Database::insert_admin_log([
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => 'term_deleted',
            'action_details' => sprintf(
                '%s deleted: %s',
                $this->get_taxonomy_label($taxonomy),
                $deleted_term->name
            ),
            'object_type' => 'term',
            'object_id' => $term_id,
            'object_name' => $deleted_term->name,
            'admin_page' => admin_url('edit-tags.php?taxonomy=' . $taxonomy),
        ]);
    }

    private function get_taxonomy_label($taxonomy)
    {
        $taxonomy_obj = get_taxonomy($taxonomy);
        return $taxonomy_obj ? $taxonomy_obj->labels->singular_name : ucfirst($taxonomy);
    }

    public function track_admin_notices()
    {
        // Track important admin actions that show notices
        if (isset($_GET['message'])) {
            $messages = [
                '1' => 'Post updated',
                '2' => 'Custom field updated',
                '3' => 'Post deleted',
                '4' => 'Post published',
                '5' => 'Post saved as draft',
                '6' => 'Post submitted',
                '7' => 'Post saved',
                '8' => 'Post submitted',
                '9' => 'Post scheduled',
                '10' => 'Post draft updated',
            ];

            $message_id = intval($_GET['message']);
            if (isset($messages[$message_id])) {
                $user = wp_get_current_user();

                Database::insert_admin_log([
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'user_role' => current($user->roles),
                    'action_type' => 'admin_notice',
                    'action_details' => 'Admin notice: ' . $messages[$message_id],
                    'admin_page' => $this->get_current_admin_page(),
                ]);
            }
        }
    }

    private function track_custom_hooks()
    {
        // Allow developers to add custom admin tracking hooks
        $custom_hooks = apply_filters('trackpress_admin_custom_hooks', []);

        foreach ($custom_hooks as $hook => $config) {
            add_action($hook, function () use ($config) {
                $user = wp_get_current_user();
                $action_type = isset($config['action_type']) ? $config['action_type'] : $hook;
                $action_details = isset($config['action_details']) ? $config['action_details'] : '';

                // Allow dynamic details via callback
                if (is_callable($action_details)) {
                    $action_details = call_user_func($action_details);
                }

                Database::insert_admin_log([
                    'user_id' => $user->ID,
                    'user_login' => $user->user_login,
                    'user_role' => current($user->roles),
                    'action_type' => $action_type,
                    'action_details' => $action_details,
                    'object_type' => isset($config['object_type']) ? $config['object_type'] : '',
                    'object_id' => isset($config['object_id']) ? $config['object_id'] : 0,
                    'object_name' => isset($config['object_name']) ? $config['object_name'] : '',
                    'admin_page' => $this->get_current_admin_page(),
                ]);
            }, 10);
        }
    }

    private function get_current_admin_page()
    {
        return isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '';
    }

    public static function log_custom_admin_action($action_type, $action_details = '', $extra_data = [])
    {
        $user = wp_get_current_user();

        if ($user->ID === 0) {
            return false;
        }

        $data = [
            'user_id' => $user->ID,
            'user_login' => $user->user_login,
            'user_role' => current($user->roles),
            'action_type' => $action_type,
            'action_details' => $action_details,
            'admin_page' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
        ];

        if (!empty($extra_data)) {
            $data = array_merge($data, $extra_data);
        }

        return Database::insert_admin_log($data);
    }

    public static function get_admin_activity_summary($days = 7)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'trackpress_admin_logs';

        $start_date = date('Y-m-d H:i:s', strtotime("-$days days"));

        // Get total actions
        $total_actions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE created_at >= %s",
            $start_date
        ));

        // Get actions by user
        $actions_by_user = $wpdb->get_results($wpdb->prepare(
            "SELECT user_login, COUNT(*) as count 
         FROM {$table_name} 
         WHERE created_at >= %s 
         GROUP BY user_id 
         ORDER BY count DESC",
            $start_date
        ));

        // Get actions by type
        $actions_by_type = $wpdb->get_results($wpdb->prepare(
            "SELECT action_type, COUNT(*) as count 
         FROM {$table_name} 
         WHERE created_at >= %s 
         GROUP BY action_type 
         ORDER BY count DESC",
            $start_date
        ));

        // Get recent activity - No placeholder needed here
        $recent_activity = $wpdb->get_results(
            "SELECT * FROM {$table_name} 
         ORDER BY created_at DESC 
         LIMIT 10"
        );

        return [
            'total_actions' => $total_actions ?: 0,
            'actions_by_user' => $actions_by_user ?: [],
            'actions_by_type' => $actions_by_type ?: [],
            'recent_activity' => $recent_activity ?: [],
        ];
    }

    public static function get_user_admin_activity($user_id, $days = 30)
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'trackpress_admin_logs';

        $start_date = date('Y-m-d H:i:s', strtotime("-$days days"));

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE user_id = %d AND created_at >= %s 
             ORDER BY created_at DESC",
            $user_id,
            $start_date
        ));
    }
}