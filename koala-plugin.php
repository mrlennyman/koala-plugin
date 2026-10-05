<?php
/*
Plugin Name: Koala CPT Plugin
Description: Manages Koala CPT shortcodes and maps.
Version: 1.0
Author: John Leonard
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin path
define('KOALA_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Load utility and shortcode files
require_once KOALA_PLUGIN_DIR . 'includes/koala-admin-settings.php';
require_once KOALA_PLUGIN_DIR . 'includes/koala-utils.php';
require_once KOALA_PLUGIN_DIR . 'includes/koala-location-map.php';
require_once KOALA_PLUGIN_DIR . 'includes/koala-release-map.php';
require_once KOALA_PLUGIN_DIR . 'includes/koala-sighting-form.php';