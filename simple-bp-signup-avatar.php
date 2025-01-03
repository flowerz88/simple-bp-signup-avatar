<?php
/*
Plugin Name: Simple BuddyPress Signup Avatar
Plugin URI: https://github.com/flowerz88/simple-bp-signup-avatar
Description: Enhances BuddyPress registration by allowing users to upload avatars during sign-up.
Version: 1.0
Author: Butterfly88
Author URI: https://github.com/flowerz88
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Text Domain: simple-bp-signup-avatar
Domain Path: /languages

Simple BuddyPress Signup Avatar is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Simple BuddyPress Signup Avatar is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
*/

// Load the main functionality file
require_once __DIR__ . '/bp_avatar_registration.php';
require_once __DIR__ . '/admin-settings.php';

// Load the text domain for translations
function custom_bp_avatar_load_textdomain() {
    load_plugin_textdomain('simple-bp-signup-avatar', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'custom_bp_avatar_load_textdomain');