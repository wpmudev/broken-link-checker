<?php
/*
Plugin Name: Broken Link Checker
Plugin URI: https://wordpress.org/plugins/broken-link-checker/
Description: Checks your blog for broken links and missing images and notifies you on the dashboard if any are found.
Version: 1.11.8
Author: ManageWP
Author URI: https://managewp.com
Text Domain: broken-link-checker
*/

//Path to this file
if ( !defined('BLC_PLUGIN_FILE') ){
	define('BLC_PLUGIN_FILE', __FILE__);
}

//Path to the plugin's directory
if ( !defined('BLC_DIRECTORY') ){
	define('BLC_DIRECTORY', dirname(__FILE__));
}

//Load the actual plugin
require 'core/init.php';
