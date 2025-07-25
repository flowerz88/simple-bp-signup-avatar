<?php
if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
define('SBSA_CACHE_TIME', 3600); // Cache time in seconds
require_once ABSPATH . 'wp-admin/includes/file.php';
// Define default settings
define('SBSA_DEFAULTS', [
    'avatar_required' => false,
    'max_file_size' => 5,
    'max_dimensions' => 1024,
    'compression' => 85,
]);
define('SBSA_TEMP_DIR', 'temp_signup_avatars'); // Folder to temporarily store avatars

// Create safe folder to store temporary avatars during signup
add_action('init', function() {
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/' . SBSA_TEMP_DIR;
    
    if (!file_exists($temp_dir)) {
        $created = wp_mkdir_p($temp_dir);
        
        if ($created) {
            // Index base
            file_put_contents($temp_dir . '/index.php', "<?php\n// Silence is golden");
            
            // .htaccess security
            $htaccess_content = "Options -Indexes\n";
            $htaccess_content .= "<Files ~ \"^.*\.(php|php3|php4|php5|php6|php7|phtml|pl|py|jsp|asp|aspx|cgi)$\">\n";
            $htaccess_content .= "    deny from all\n";
            $htaccess_content .= "</Files>\n";
            $htaccess_content .= "<IfModule mod_php.c>\n";
            $htaccess_content .= "    php_flag engine off\n";
            $htaccess_content .= "</IfModule>\n";
            
            file_put_contents($temp_dir . '/.htaccess', $htaccess_content);
            
            // Set permissions
            chmod($temp_dir, 0755);
            chmod($temp_dir . '/index.php', 0644);
            chmod($temp_dir . '/.htaccess', 0644);
        }
    }
});

function sbsa_get_settings() {
    $cache_key = 'sbsa_settings';
    $settings = wp_cache_get($cache_key);

    if (false === $settings) {
        $settings = get_option('sbsa_settings', SBSA_DEFAULTS);
        wp_cache_set($cache_key, $settings, '', SBSA_CACHE_TIME); 
    }

    return $settings;
}

// Hook to add the avatar upload field to the registration form
function sbsa_add_avatar_field_to_registration_form() {
    $options = sbsa_get_settings();
    $required = $options['avatar_required'] ? 'required' : '';
    ?>
    <div class="register-avatar-upload">
        <?php wp_nonce_field('sbsa_avatar_upload', 'sbsa_avatar_nonce'); ?>
        <label for="signup_avatar"><?php echo $options['avatar_required'] ? esc_html__('Upload Your Avatar (required)', 'simple-buddypress-signup-avatar') : esc_html__('Upload Your Avatar (optional)', 'simple-buddypress-signup-avatar'); ?></label>
        <input type="file" name="signup_avatar" id="signup_avatar" accept="image/*" <?php echo esc_attr($required); ?> />
        <div id="avatar-error" style="color: red; margin-top: 10px;"></div>
        <div id="avatar-preview" style="margin-top: 10px; width: 150px; height: 150px; overflow: hidden; display: none;">
            <img id="avatar-preview-img" src="" alt="" style="width: 150px; height: 150px; object-fit: cover;" />
        </div>
    </div>
    <?php
    wp_enqueue_script('avatar-upload-helper', plugin_dir_url(__FILE__) . 'avatar_upload_helper.js', ['jquery'], null, true);

    wp_localize_script('avatar-upload-helper', 'avatarUploadStrings', array(
        'invalidType' => __('Invalid file type. Please upload a JPEG, PNG or GIF image.', 'simple-buddypress-signup-avatar'),
        'fileTooLarge' => sprintf(__('Avatar is too large. Maximum file size is %1$d MB.', 'simple-buddypress-signup-avatar'), $options['max_file_size']),
        'dimensionsTooLarge' => sprintf(__('Avatar dimensions are too large. Maximum width and height is %d pixels.', 'simple-buddypress-signup-avatar'), $options['max_dimensions'], $options['max_dimensions']),
        'loadFailed' => __('Failed to load image. Please try another file.', 'simple-buddypress-signup-avatar'),
        'maxFileSize' => $options['max_file_size'] * 1024 * 1024,
        'maxDimensions' => $options['max_dimensions'],
        'required' => $options['avatar_required'],
        'compression' => $options['compression'], 
    ));
}
add_action('bp_after_signup_profile_fields', 'sbsa_add_avatar_field_to_registration_form');

// Process the avatar upload during registration
function sbsa_handle_avatar_upload_during_registration() {
    try {
        // Get current settings at the beginning of the function
        $options = sbsa_get_settings();
        $max_file_size = $options['max_file_size'] * 1024 * 1024; // Convert MB to bytes
        $max_dimensions = $options['max_dimensions'];

        if (!isset($_POST['sbsa_avatar_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sbsa_avatar_nonce'])), 'sbsa_avatar_upload')) {
            bp_core_add_message(__('Security check failed. Please try again.', 'simple-buddypress-signup-avatar'), 'error');
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
                bp_core_add_message(sprintf(__('Avatar is too large. Maximum file size is %1$d MB.', 'simple-buddypress-signup-avatar'), $options['max_file_size']), 'error');
                return;
            }

            $upload_dir = wp_upload_dir();
            $temp_dir = $upload_dir['basedir'] . '/' . SBSA_TEMP_DIR;
            
            // Ensure the temporary directory exists
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }
            
            // Set unique filename
            $filename = uniqid('avatar_') . '_' . $file_name;
            $new_file = $temp_dir . '/' . $filename;
            
            // Copy file
            if (move_uploaded_file($_FILES['signup_avatar']['tmp_name'], $new_file)) {
                // Set file permissions
                chmod($new_file, 0644);
                
                // Check filetype
                $file_type = wp_check_filetype($new_file);
                
                if (!in_array($file_type['type'], ['image/jpeg', 'image/png', 'image/gif'])) {
                    wp_delete_file($new_file);
                    bp_core_add_message(__('Invalid file type. Please upload a JPEG, PNG or GIF image.', 'simple-buddypress-signup-avatar'), 'error');
                    return;
                }
                
                // Validate image dimensions
                $image_info = @getimagesize($new_file);
                if ($image_info === false) {
                    wp_delete_file($new_file);
                    bp_core_add_message(__('Failed to process the uploaded image. Please try again.', 'simple-buddypress-signup-avatar'), 'error');
                    return;
                }
                
                list($width, $height) = $image_info;

            // MIME type verification
                if (function_exists('finfo_open')) {
                    $allowed_mime = ['image/jpeg', 'image/png', 'image/gif'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $actual_mime = finfo_file($finfo, $new_file);
                    finfo_close($finfo);
    
                if (!in_array($actual_mime, $allowed_mime)) {
                    wp_delete_file($new_file);
                    bp_core_add_message(__('Invalid file type detected.', 'simple-buddypress-signup-avatar'), 'error');
                    return;
                }
            }
                
                if ($width > $max_dimensions || $height > $max_dimensions) {
                    wp_delete_file($new_file);
                    bp_core_add_message(sprintf(__('Avatar dimensions are too large. Maximum width and height is %d pixels.', 'simple-buddypress-signup-avatar'), $max_dimensions), 'error');
                    return;
                }

                // Process the image
                $image = wp_get_image_editor($new_file);
                if (is_wp_error($image)) {
                    wp_delete_file($new_file);
                    bp_core_add_message(__('Failed to process the uploaded image. Please try again.', 'simple-buddypress-signup-avatar'), 'error');
                    return;
                }

                // Crop to square
                $size = min($width, $height);
                $crop_result = $image->crop(($width - $size) / 2, ($height - $size) / 2, $size, $size);
                
                // Resize to 150x150
                $resize_result = $image->resize(150, 150, true);

                // Set quality based on compression setting
                $quality = $options['compression'];
                if ($quality > 0) {
                    $image->set_quality($quality);
                }

                // Save the processed image
                $save_result = $image->save($new_file);
                if (is_wp_error($save_result)) {
                    wp_delete_file($new_file);
                    bp_core_add_message(__('Failed to save the processed image. Please try again.', 'simple-buddypress-signup-avatar'), 'error');
                    return;
                }

                // Manually remove EXIF data
                sbsa_remove_exif($new_file);

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

                        $update_result = $wpdb->update(
                            $table_name,
                            ['meta' => maybe_serialize($meta_data)],
                            ['signup_id' => $signup_id],
                            ['%s'],
                            ['%d']
                        );
                    }
                }
            } else {
                bp_core_add_message(__('Avatar upload failed. Please try again.', 'simple-buddypress-signup-avatar'), 'error');
            }
        } elseif ($options['avatar_required']) {
            bp_core_add_message(__('Avatar upload is required.', 'simple-buddypress-signup-avatar'), 'error');
        }
        
    } catch (Exception $e) {
        bp_core_add_message(__('An error occurred while processing your avatar. Please try again.', 'simple-buddypress-signup-avatar'), 'error');
    }
}
add_action('bp_signup_validate', 'sbsa_handle_avatar_upload_during_registration');

// Function to manually remove EXIF data
function sbsa_remove_exif($file_path) {
    static $options = null;
    if (is_null($options)) {
        $options = sbsa_get_settings();
    }

    if (function_exists('exif_read_data')) {
        $image_type = exif_imagetype($file_path);
        
        if ($image_type == IMAGETYPE_JPEG) {
            $img = @imagecreatefromjpeg($file_path);
            if ($img !== false) {
                $result = imagejpeg($img, $file_path, $options['compression']);
                imagedestroy($img);
            }
        }
    }
}

// Move the avatar to its final location after activation
function sbsa_move_avatar_after_activation($user_id) {
    global $wpdb;
    $table_name = bp_core_get_table_prefix() . 'signups';

    // Get the options with the default values
    $options = sbsa_get_settings();

    // Retrieve user email using the WordPress user data
    $user_data = get_userdata($user_id);
    if (!$user_data) {
        return;
    }
    
    $user_email = $user_data->user_email;

    if ($user_email) {
        $signup = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM `{$wpdb->prefix}signups` WHERE user_email = %s", $user_email)
        );
        
        if ($signup && $signup->meta) {
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
                    $full_result = $image->save($avatar_dir . '/' . $full_name);
                    if (!is_wp_error($full_result)) {
                        // Set file permissions
                        chmod($avatar_dir . '/' . $full_name, 0644);
                    }

                    // Resize and save the thumbnail version
                    $resize_result = $image->resize(50, 50, true);
                    if (!is_wp_error($resize_result)) {
                        $thumb_result = $image->save($avatar_dir . '/' . $thumbnail_name);
                        if (!is_wp_error($thumb_result)) {
                            // Set file permissions
                            chmod($avatar_dir . '/' . $thumbnail_name, 0644);
                        }
                    }
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
            }
        }
    }
}
add_action('bp_core_activated_user', 'sbsa_move_avatar_after_activation', 10, 1);

// Cleanup temporary avatars if the user doesn't activate
function sbsa_cleanup_temporary_avatars() {
    global $wpdb;
    $table_name = "{$wpdb->prefix}signups";
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/' . SBSA_TEMP_DIR;

    // Get inactive signups older than 7 days
    $query = $wpdb->prepare(
        "SELECT signup_id, meta FROM {$wpdb->prefix}signups WHERE active = 0 AND registered < %s",
        gmdate('Y-m-d H:i:s', strtotime('-7 days'))
    );
    
    $inactive_signups = $wpdb->get_results($query, ARRAY_A);

    if (!empty($inactive_signups)) {
        foreach ($inactive_signups as $signup) {
            if (!empty($signup['meta'])) {
                $meta_data = maybe_unserialize($signup['meta']);
                $temp_avatar_path = $meta_data['temporary_avatar'] ?? '';

                if ($temp_avatar_path && file_exists($temp_avatar_path)) {
                    $delete_result = wp_delete_file($temp_avatar_path);

                    // Remove the temporary avatar from meta
                    unset($meta_data['temporary_avatar']);
                    $update_result = $wpdb->update(
                        $table_name,
                        ['meta' => maybe_serialize($meta_data)],
                        ['signup_id' => $signup['signup_id']],
                        ['%s'],
                        ['%d']
                    );
                }
            }
        }
    }

    // Clean up orphaned files
    if (file_exists($temp_dir)) {
        $files = glob($temp_dir . '/*');
        $current_time = time();
        foreach ($files as $file) {
            if (is_file($file) && basename($file) !== 'index.php' && basename($file) !== '.htaccess') {
                $file_age = $current_time - filemtime($file);
                if ($file_age > 7 * 24 * 60 * 60) {
                    wp_delete_file($file);
                }
            }
        }
    }
}
add_action('bp_weekly_scheduled_events', 'sbsa_cleanup_temporary_avatars');
