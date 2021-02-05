<?php
/**
 * Broken Link Checker
 *
 * @link              https://wordpress.org/plugins/broken-link-checker/
 * @since             1.0.0
 * @package           broken-link-checker
 *
 * @wordpress-plugin
 * Plugin Name: Broken Link Checker
 * Plugin URI:  https://wordpress.org/plugins/broken-link-checker/
 * Description: Checks your blog for broken links and missing images and notifies you on the dashboard if any are found.
 * Version:     1.11.15
 * Author:      WPMU DEV
 * Author URI:  https://premium.wpmudev.org/
 * Text Domain: broken-link-checker
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

/*
Broken Link Checker is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.

Broken Link Checker is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with Broken Link Checker. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
*/

// Path to this file.
if ( ! defined( 'BLC_PLUGIN_FILE' ) ) {
	define( 'BLC_PLUGIN_FILE', __FILE__ );
}

// Path to the plugin's directory.
if ( ! defined( 'BLC_DIRECTORY' ) ) {
	define( 'BLC_DIRECTORY', dirname( __FILE__ ) );
}

// Load the actual plugin.
require 'core/init.php';
