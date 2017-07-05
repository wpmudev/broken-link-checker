<?php
/*
Plugin Name: Broken Link Checker Fork
Plugin URI: http://w-shadow.com/blog/2007/08/05/broken-link-checker-for-wordpress/
Description: Checks your blog for broken links and missing images and notifies you on the dashboard if any are found. This is a fork version based in 1.11.2 verion
Version: 1.0.0
Author: Janis Elsts, Vladimir Prelovac
Author URI: http://w-shadow.com/
Text Domain: broken-link-checker
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
