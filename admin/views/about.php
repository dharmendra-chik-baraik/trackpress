<?php
/**
 * About page template for TrackPress
 */
?>
<div class="wrap trackpress-about">
    <div class="trackpress-header">
        <h1 class="trackpress-title">
            <span class="dashicons dashicons-chart-area"></span>
            TrackPress
            <span class="version">v<?php echo esc_html($current_version); ?></span>
        </h1>
        <p class="description"><?php _e('User and Visitor Tracking Plugin', 'trackpress'); ?></p>
    </div>

    <div class="about-container">
        <div class="about-section">
            <h2><?php _e('About TrackPress', 'trackpress'); ?></h2>
            <p class="description">
                <?php _e('TrackPress is a comprehensive user and visitor tracking plugin for WordPress. It helps you monitor user activities, track visitor behavior, and log administrative actions for security and analysis.', 'trackpress'); ?>
            </p>
        </div>

        <div class="about-section features-section">
            <h2><?php _e('Features', 'trackpress'); ?></h2>
            <div class="features-grid">
                <?php foreach ($features as $feature): ?>
                    <div class="feature-card">
                        <div class="feature-icon">
                            <span class="dashicons <?php echo esc_attr($feature['icon']); ?>"></span>
                        </div>
                        <div class="feature-content">
                            <h3><?php echo esc_html($feature['title']); ?></h3>
                            <p><?php echo esc_html($feature['description']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="about-section version-section">
            <h2><?php _e('Version Information', 'trackpress'); ?></h2>
            <table class="widefat version-table">
                <tbody>
                    <tr>
                        <th><?php _e('Current Version', 'trackpress'); ?></th>
                        <td><?php echo esc_html($current_version); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Last Updated', 'trackpress'); ?></th>
                        <td><?php echo wp_kses_post($plugin_info['author']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Requires WordPress', 'trackpress'); ?></th>
                        <td><?php echo esc_html($plugin_info['requires']); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Tested up to', 'trackpress'); ?></th>
                        <td><?php echo esc_html($plugin_info['tested']); ?></td>
                    </tr>
                    <?php if ($remote_info): ?>
                        <tr>
                            <th><?php _e('Latest Version', 'trackpress'); ?></th>
                            <td>
                                <?php echo esc_html($remote_info->version); ?>
                                <?php if ($is_update_available): ?>
                                    <span class="update-badge"><?php _e('Update Available', 'trackpress'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="about-section developers-section">
            <h2><?php _e('Development Team', 'trackpress'); ?></h2>
            <div class="developers-grid">
                <?php foreach ($developers as $developer): ?>
                    <div class="developer-card">
                        <div class="developer-info">
                            <h3><?php echo esc_html($developer['name']); ?></h3>
                            <p class="role"><?php echo esc_html($developer['role']); ?></p>
                            <div class="flex">
                                <p class="github">
                                    <a href="<?php echo esc_url($developer['github']); ?>" target="_blank">
                                        <span class="dashicons dashicons-external"></span>
                                        <?php _e('GitHub Profile', 'trackpress'); ?>
                                    </a>
                                </p>
                                <p class="email">
                                    <a href="mailto:<?php echo esc_attr($developer['email']); ?>">
                                        <span class="dashicons dashicons-email-alt"></span>
                                        <?php echo esc_html($developer['email']); ?>
                                    </a>
                                </p>
                                <p class="website">
                                    <a href="<?php echo esc_url($developer['website']); ?>" target="_blank">
                                        <span class="dashicons dashicons-external"></span>
                                        <?php echo esc_html($developer['website']); ?>
                                    </a>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="about-section license-section">
            <h2><?php _e('License Information', 'trackpress'); ?></h2>
            <div class="license-card">
                <p>
                    <?php _e('TrackPress is released under the GNU General Public License v2.0 or later.', 'trackpress'); ?>
                </p>
                <p>
                    <a href="https://www.gnu.org/licenses/gpl-2.0.html" target="_blank" class="button">
                        <span class="dashicons dashicons-media-text"></span>
                        <?php _e('View GPL v2 License', 'trackpress'); ?>
                    </a>
                </p>
            </div>
        </div>

        <div class="about-section support-section">
            <h2><?php _e('Support & Resources', 'trackpress'); ?></h2>
            <div class="support-links">
                <a href="https://github.com/dharmendra-chik-baraik/trackpress" target="_blank"
                    class="button button-primary">
                    <span class="dashicons dashicons-book-alt"></span>
                    <?php _e('Documentation', 'trackpress'); ?>
                </a>
                <a href="https://github.com/dharmendra-chik-baraik/trackpress/issues" target="_blank" class="button">
                    <span class="dashicons dashicons-sos"></span>
                    <?php _e('Report Issues', 'trackpress'); ?>
                </a>
                <a href="https://github.com/dharmendra-chik-baraik/trackpress" target="_blank" class="button">
                    <span class="dashicons dashicons-star-filled"></span>
                    <?php _e('Star on GitHub', 'trackpress'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
    .trackpress-about {
        max-width: 1200px;
    }

    .trackpress-header {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    }

    .trackpress-title {
        margin: 0 0 15px 0;
        font-size: 23px;
        font-weight: 400;
        line-height: 1.3;
    }

    .trackpress-title .version {
        color: #50575e;
        font-size: 0.9em;
        margin-left: 10px;
        font-weight: normal;
    }

    .update-available {
        margin: 0;
    }

    .about-section {
        background: #fff;
        padding: 20px;
        margin: 20px 0;
        border: 1px solid #c3c4c7;
        box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
    }

    .about-section h2 {
        margin-top: 0;
        color: #1d2327;
        font-size: 18px;
        padding-bottom: 10px;
        margin-bottom: 20px;
        border-bottom: 1px solid #dcdcde;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }

    .feature-card {
        border: 1px solid #dcdcde;
        padding: 15px;
        background: #f6f7f7;
    }

    .feature-card:hover {
        border-color: #8c8f94;
    }

    .feature-icon {
        color: #2271b1;
        margin-bottom: 10px;
        float: left;
        margin-right: 10px;
    }

    .feature-content h3 {
        margin: 0 0 10px 40px;
        color: #1d2327;
        font-size: 16px;
    }

    .feature-content p {
        color: #50575e;
        margin: 0 0 0 40px;
        line-height: 1.5;
    }

    .version-table {
        width: 100%;
        border-collapse: collapse;
    }

    .version-table th,
    .version-table td {
        padding: 15px;
        border-bottom: 1px solid #dcdcde;
        text-align: left;
    }

    .version-table th {
        width: 200px;
        font-weight: 600;
        color: #1d2327;
    }

    .update-badge {
        background: #d63638;
        color: white;
        padding: 2px 6px;
        border-radius: 2px;
        font-size: 12px;
        margin-left: 8px;
    }

    .developers-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        margin: 20px 0;
    }

    .developer-card {
        flex: 1;
        min-width: 200px;
        border: 1px solid #dcdcde;
        padding: 15px;
        background: #f6f7f7;
    }

    .developer-info h3 {
        margin: 0 0 5px 0;
        color: #1d2327;
    }

    .developer-info .role {
        color: #50575e;
        font-style: italic;
        margin: 0 0 10px 0;
    }

    .license-card {
        background: #f6f7f7;
        padding: 15px;
        border-left: 4px solid #72aee6;
    }

    .support-links {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin: 20px 0 0 0;
    }

    .support-links .button {
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .flex {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    @media (max-width: 782px) {
        .features-grid {
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .developers-grid {
            flex-direction: column;
            gap: 15px;
        }

        .support-links {
            flex-direction: column;
            align-items: flex-start;
        }

        .support-links .button {
            width: 100%;
            justify-content: center;
            margin-bottom: 10px;
        }
    }
</style>