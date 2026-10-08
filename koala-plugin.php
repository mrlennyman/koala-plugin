<?php
/*
Plugin Name: Koala CPT Plugin
Description: Manages Koala CPT shortcodes and maps.
Version: 1.2.1
Author: John Leonard
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin path
define('KOALA_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Load utility and shortcode files
require_once KOALA_PLUGIN_DIR . 'includes/koala-location-sync.php';
require_once KOALA_PLUGIN_DIR . 'includes/koala-admin-settings.php';
require_once KOALA_PLUGIN_DIR . 'includes/koala-utils.php';
require_once KOALA_PLUGIN_DIR . 'includes/koala-location-map.php';
require_once KOALA_PLUGIN_DIR . 'includes/koala-release-map.php';
require_once KOALA_PLUGIN_DIR . 'includes/koala-sighting-form.php';

// Not on WordPress.org: checks this (private) GitHub repo's tagged releases and
// shows a normal "Update available" notice. Needs a read-only GitHub token,
// defined in wp-config.php as KOALA_PLUGIN_GITHUB_TOKEN (never stored in the repo).
require_once KOALA_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$koala_plugin_update_checker = PucFactory::buildUpdateChecker(
    'https://github.com/mrlennyman/koala-plugin/',
    __FILE__,
    'koala-plugin'
);
$koala_plugin_update_checker->setBranch('main');
if (defined('KOALA_PLUGIN_GITHUB_TOKEN') && KOALA_PLUGIN_GITHUB_TOKEN) {
    $koala_plugin_update_checker->setAuthentication(KOALA_PLUGIN_GITHUB_TOKEN);
}