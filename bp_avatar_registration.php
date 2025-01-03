<?php
require_once ABSPATH . 'wp-admin/includes/file.php';
// Define default settings
define('CUSTOM_BP_AVATAR_DEFAULTS', [
    'avatar_required' => false,
    'max_file_size' => 5,
    'max_dimensions' => 1024,
    'compression' => 85,
]);
define('CUSTOM_BP_AVATAR_TEMP_DIR', 'temp_signup_avatars');
register_activation_hook(__FILE__, 'custom_bp_avatar_create_temp_dir');

function get_custom_bp_avatar_settings() {
    $cache_key = 'custom_bp_avatar_settings';
    $settings = wp_cache_get($cache_key);

    if (false === $settings) {
        $settings = get_option('custom_bp_avatar_settings', CUSTOM_BP_AVATAR_DEFAULTS);
        wp_cache_set($cache_key, $settings, '', 3600); // Cache for 1 hour
    }

    return $settings;
}

// Store temporary avatars in designated folder
function custom_bp_avatar_create_temp_dir() {
    $upload_dir = wp_upload_dir();
    $base_dir = $upload_dir['basedir'];
    $temp_dir = $base_dir . '/' . CUSTOM_BP_AVATAR_TEMP_DIR;
    
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
    }
    
    // .htaccess security
    $htaccess_file = $temp_dir . '/.htaccess';
    if (!file_exists($htaccess_file)) {
        $htaccess_content = "Options -Indexes\nDeny from all";
        WP_Filesystem();
        global $wp_filesystem;
        $wp_filesystem->put_contents($htaccess_file, $htaccess_content, FS_CHMOD_FILE);
    }
}


// Hook to add the avatar upload field to the registration form
function custom_add_avatar_field_to_registration_form() {
    $options = get_custom_bp_avatar_settings();
    $required = $options['avatar_required'] ? 'required' : '';
    ?>
    <div class="register-avatar-upload">
        <?php wp_nonce_field('custom_bp_avatar_upload', 'custom_bp_avatar_nonce'); ?>
        <label for="signup_avatar"><?php echo $options['avatar_required'] ? esc_html__('Upload Your Avatar (required)', 'simple-bp-signup-avatar') : esc_html__('Upload Your Avatar (optional)', 'simple-bp-signup-avatar'); ?></label>
        <input type="file" name="signup_avatar" id="signup_avatar" accept="image/*" <?php echo esc_attr($required); ?> />
        <div id="avatar-error" style="color: red; margin-top: 10px;"></div>
        <div id="avatar-preview" style="margin-top: 10px; width: 150px; height: 150px; overflow: hidden; display: none;">
            <img id="avatar-preview-img" src="" alt="" style="width: 150px; height: 150px; object-fit: cover;" />
        </div>
    </div>
    <?php
    wp_enqueue_script('avatar-upload-helper', plugin_dir_url(__FILE__) . 'avatar_upload_helper.js', ['jquery'], null, true);

    wp_localize_script('avatar-upload-helper', 'avatarUploadStrings', array(
        'invalidType' => __('Invalid file type. Please upload a JPEG, PNG or GIF image.', 'simple-bp-signup-avatar'),
        // translators: %d is the maximum file size in megabytes
        'fileTooLarge' => sprintf(__('Avatar is too large. Maximum file size is %1$d MB.', 'simple-bp-signup-avatar'), $options['max_file_size']),
        // translators: %d is the maximum dimension for both width and height in pixels
        'dimensionsTooLarge' => sprintf(__('Avatar dimensions are too large. Maximum width and height is %d pixels.', 'simple-bp-signup-avatar'), $options['max_dimensions'], $options['max_dimensions']),
        'loadFailed' => __('Failed to load image. Please try another file.', 'simple-bp-signup-avatar'),
        'maxFileSize' => $options['max_file_size'] * 1024 * 1024,
        'maxDimensions' => $options['max_dimensions'],
        'required' => $options['avatar_required'],
        'compression' => $options['compression'], 
    ));
}
add_action('bp_after_signup_profile_fields', 'custom_add_avatar_field_to_registration_form');

// Process the avatar upload during registration
function custom_handle_avatar_upload_during_registration() {
    // Get current settings at the beginning of the function
    $options = get_custom_bp_avatar_settings();
    $max_file_size = $options['max_file_size'] * 1024 * 1024; // Convert MB to bytes
    $max_dimensions = $options['max_dimensions'];

    if (!isset($_POST['custom_bp_avatar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['custom_bp_avatar_nonce'])), 'custom_bp_avatar_upload')) {
    bp_core_add_message(__('Security check failed. Please try again.', 'simple-bp-signup-avatar'), 'error');
    return;
    }

        if (!empty($_FILES['signup_avatar']['name'])) {
        $file_name = sanitize_file_name($_FILES['signup_avatar']['name']);
        $file_size = isset($_FILES['signup_avatar']['size']) ? intval($_FILES['signup_avatar']['size']) : 0;
        $file_tmp = isset($_FILES['signup_avatar']['tmp_name']) ? sanitize_text_field($_FILES['signup_avatar']['tmp_name']) : '';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Check file size
        if ($_FILES['signup_avatar']['size'] > $max_file_size) {
            // translators: %d is the maximum file size in megabytes
            bp_core_add_message(sprintf(__('Avatar is too large. Maximum file size is %1$d MB.', 'simple-bp-signup-avatar'), $options['max_file_size']), 'error');
            return;
        }

        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/' . CUSTOM_BP_AVATAR_TEMP_DIR;
        
        // Ensure the temporary directory exists
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Use a unique filename to prevent overwriting
        $filename = wp_unique_filename($temp_dir, $file_name);
        $new_file = $temp_dir . '/' . $filename;

        // Move the uploaded file to our temporary directory
            $upload_overrides = array('test_form' => false);
            $movefile = wp_handle_upload($_FILES['signup_avatar'], $upload_overrides);
            if ($movefile && !isset($movefile['error'])) {
                $new_file = $movefile['file'];
            // Validate image dimensions
            list($width, $height) = getimagesize($new_file);
            if ($width > $max_dimensions || $height > $max_dimensions) {
                wp_delete_file($new_file);
            // translators: %d is the maximum dimension for both width and height in pixels
            bp_core_add_message(sprintf(__('Avatar dimensions are too large. Maximum width and height is %d pixels.', 'simple-bp-signup-avatar'), $max_dimensions), 'error');
                return;
            }

            // Process the image
            $image = wp_get_image_editor($new_file);
            if (!is_wp_error($image)) {
                // Crop to square
                $size = min($width, $height);
                $image->crop(($width - $size) / 2, ($height - $size) / 2, $size, $size);

                // Resize to 150x150
                $image->resize(150, 150, true);

                // Set quality based on compression setting
                $quality = $options['compression'];
                if ($quality > 0) {
                    $image->set_quality($quality);
                }

                // Save the processed image
                $image->save($new_file);

                // Manually remove EXIF data
                remove_exif($new_file);

                // Save the path to the database
                $user_email = !empty($_POST['signup_email']) ? sanitize_email(wp_unslash($_POST['signup_email'])) : '';
                if ($user_email) {
                    global $wpdb;
                    $table_name = bp_core_get_table_prefix() . 'signups';
                    $signup_id = $wpdb->get_var(
                    $wpdb->prepare("SELECT signup_id FROM `{$wpdb->prefix}signups` WHERE user_email = %s", $user_email)
                    );

                    if ($signup_id) {
                        $current_meta = $wpdb->get_var(
                        $wpdb->prepare("SELECT meta FROM `{$wpdb->prefix}signups` WHERE signup_id = %d", $signup_id)
                        );
                        $meta_data = $current_meta ? maybe_unserialize($current_meta) : [];
                        $meta_data['temporary_avatar'] = $new_file;

                        $wpdb->update(
                            $table_name,
                            ['meta' => maybe_serialize($meta_data)],
                            ['signup_id' => $signup_id],
                            ['%s'],
                            ['%d']
                        );
                    } else {
                        // error_log('Failed to retrieve signup ID for temporary avatar storage.');
                    }
                }
            } else {
                wp_delete_file($new_file);
                bp_core_add_message(__('Failed to process the uploaded image. Please try again.', 'simple-bp-signup-avatar'), 'error');
            }
        } else {
            bp_core_add_message(__('Avatar upload failed. Please try again.', 'simple-bp-signup-avatar'), 'error');
        }
    } elseif ($options['avatar_required']) {
        bp_core_add_message(__('Avatar upload is required.', 'simple-bp-signup-avatar'), 'error');
    }
}
add_action('bp_signup_validate', 'custom_handle_avatar_upload_during_registration');

// Function to manually remove EXIF data
function remove_exif($file_path) {
    static $options = null;
    if (is_null($options)) {
        $options = get_custom_bp_avatar_settings();
    }

    if (function_exists('exif_read_data')) {
        $image_type = exif_imagetype($file_path);
        if ($image_type == IMAGETYPE_JPEG) {
            $img = imagecreatefromjpeg($file_path);
            if ($img !== false) {
                imagejpeg($img, $file_path, $options['compression']);
                imagedestroy($img);
            }
        }
    }
}

// Move the avatar to its final location after activation
function custom_move_avatar_after_activation($user_id) {
    global $wpdb;
    $table_name = bp_core_get_table_prefix() . 'signups';

    // Get the options with the default values
    $options = get_custom_bp_avatar_settings();

    // Retrieve user email using the WordPress user data
    $user_email = get_userdata($user_id)->user_email;

    if ($user_email) {
    $signup = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}signups` WHERE user_email = %s", $user_email)
    );

    if ($signup && $signup->meta) {
    $meta_data = maybe_unserialize($signup->meta);
            $meta_data = maybe_unserialize($signup->meta);
            $temp_avatar_path = $meta_data['temporary_avatar'] ?? '';

            if ($temp_avatar_path && file_exists($temp_avatar_path)) {
                // Define the final avatar directory
                $avatar_dir = bp_core_avatar_upload_path() . '/avatars/' . $user_id;
                wp_mkdir_p($avatar_dir);

                // Generate unique filename with timestamp for BuddyPress avatars
                $timestamp = time();
                $thumbnail_name = "$timestamp-bpthumb.jpg";
                $full_name = "$timestamp-bpfull.jpg";

                // Process and copy the image
                $image = wp_get_image_editor($temp_avatar_path);
                if (!is_wp_error($image)) {
                    // Set quality based on compression setting
                    $quality = $options['compression'];
                    if ($quality > 0) {
                        $image->set_quality($quality);
                    }

                    // Save the full version
                    $image->save($avatar_dir . '/' . $full_name);

                    // Resize and save the thumbnail version
                    $image->resize(50, 50, true);
                    $image->save($avatar_dir . '/' . $thumbnail_name);
                }

                // Delete the temporary avatar file
                wp_delete_file($temp_avatar_path);

                // Update user meta for avatar
                update_user_meta($user_id, 'has_custom_avatar', true);

                // Ensure BuddyPress recognizes the avatar
                bp_members_get_user_url($user_id);

                // Remove the temporary avatar from meta
                unset($meta_data['temporary_avatar']);
                $wpdb->update(
                    $table_name,
                    ['meta' => maybe_serialize($meta_data)],
                    ['user_email' => $user_email],
                    ['%s'],
                    ['%s']
                );
            } else {
                if ($options['avatar_required']) {
                    // Optional: Log an error or take other action if an avatar is required but not found
                    // error_log("Required avatar not found for user $user_id during activation");
                }
            }
        }
    }
}
add_action('bp_core_activated_user', 'custom_move_avatar_after_activation', 10, 1);

// Cleanup temporary avatars if the user doesn't activate
function custom_cleanup_temporary_avatars() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'signups';
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/' . CUSTOM_BP_AVATAR_TEMP_DIR;

    // Get inactive signups older than 7 days
    $inactive_signups = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, meta FROM {$wpdb->prefix}signups WHERE active = 0 AND registered < %s",
            gmdate('Y-m-d H:i:s', strtotime('-7 days'))
        ),
        ARRAY_A
    );

    if (!empty($inactive_signups)) {
        foreach ($inactive_signups as $signup) {
            if (!empty($signup['meta'])) {
                $meta_data = maybe_unserialize($signup['meta']);
                $temp_avatar_path = $meta_data['temporary_avatar'] ?? '';

                if ($temp_avatar_path && file_exists($temp_avatar_path)) {
                    wp_delete_file($temp_avatar_path);

                    // Remove the temporary avatar from meta
                    unset($meta_data['temporary_avatar']);
                    $wpdb->update(
                        $table_name,
                        ['meta' => maybe_serialize($meta_data)],
                        ['id' => $signup['id']],
                        ['%s'],
                        ['%d']
                    );
                }
            }
        }
    }

    // Clean up orphaned files
    $files = glob($temp_dir . '/*');
    $current_time = time();
    foreach ($files as $file) {
        if (is_file($file) && ($current_time - filemtime($file) > 7 * 24 * 60 * 60)) {
            wp_delete_file($file);
        }
    }
}
add_action('bp_weekly_scheduled_events', 'custom_cleanup_temporary_avatars');