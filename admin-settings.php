<?php
// admin-settings.php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

function custom_bp_avatar_admin_menu() {
    add_options_page(
        __('Simple Buddypress Signup Avatar Settings', 'simple-bp-signup-avatar'),
        __('Simple BP Signup Avatar', 'simple-bp-signup-avatar'),
        'manage_options',
        'custom-bp-avatar-settings',
        'custom_bp_avatar_settings_page'
    );
}
add_action('admin_menu', 'custom_bp_avatar_admin_menu');

function custom_bp_avatar_settings_page() {
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save settings if the form is submitted
    if (isset($_POST['custom_bp_avatar_submit'])) {
        check_admin_referer('custom_bp_avatar_settings_nonce');
        
        $options = [
            'avatar_required' => isset($_POST['avatar_required']),
            'max_file_size' => isset($_POST['max_file_size']) ? max(absint($_POST['max_file_size']), 1) : 5,
            'max_dimensions' => isset($_POST['max_dimensions']) ? absint($_POST['max_dimensions']) : 1024,
            'compression' => isset($_POST['compression']) ? min(max(absint($_POST['compression']), 0), 100) : 85,
        ];
        
        update_option('custom_bp_avatar_settings', $options);
        echo '<div class="updated"><p>' . esc_html__('Settings saved.', 'simple-bp-signup-avatar') . '</p></div>';
    }

    // Get current settings
    $options = get_option('custom_bp_avatar_settings', [
        'avatar_required' => false,
        'max_file_size' => 5,
        'max_dimensions' => 1024,
        'compression' => 85,
    ]);

    // Settings form
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="" method="post">
            <?php wp_nonce_field('custom_bp_avatar_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Avatar Required', 'simple-bp-signup-avatar'); ?></th>
                    <td>
                        <label for="avatar_required">
                            <input type="checkbox" name="avatar_required" id="avatar_required" <?php checked($options['avatar_required']); ?>>
                            <?php esc_html_e('Make avatar upload mandatory', 'simple-bp-signup-avatar'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Max File Size (MB)', 'simple-bp-signup-avatar'); ?></th>
                    <td>
                        <input type="number" name="max_file_size" value="<?php echo esc_attr($options['max_file_size']); ?>" min="1" max="10">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Max Dimensions (pixels)', 'simple-bp-signup-avatar'); ?></th>
                    <td>
                        <input type="number" name="max_dimensions" value="<?php echo esc_attr($options['max_dimensions']); ?>" min="100">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Image Compression', 'simple-bp-signup-avatar'); ?></th>
                    <td>
                        <input type="range" name="compression" min="0" max="100" value="<?php echo esc_attr($options['compression']); ?>">
                        <span id="compression-value"><?php echo esc_html($options['compression']); ?></span>%
                        <p class="description"><?php esc_html_e('100 = no compression (highest quality)', 'simple-bp-signup-avatar'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(esc_html__('Save Settings', 'simple-bp-signup-avatar'), 'primary', 'custom_bp_avatar_submit'); ?>
        </form>
    </div>
    <script>
    document.querySelector('input[name="compression"]').addEventListener('input', function() {
        document.getElementById('compression-value').textContent = this.value;
    });
    </script>
    <?php
}