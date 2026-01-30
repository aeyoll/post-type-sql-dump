<?php
/**
 * Plugin Name: Post Type SQL Dump
 * Plugin URI: https://wordpress.org/plugins/post-type-sql-dump/
 * Description: Export WordPress posts and their associated data (including Polylang translations) to SQL format via WP-CLI command
 * Version: 1.0.0
 * Author: Jean-Philippe Bidegain
 * Author URI: https://wordpress.org/plugins/post-type-sql-dump/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: post-type-sql-dump
 * Requires at least: 5.0
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PTSD_VERSION', '1.0.0');
define('PTSD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PTSD_PLUGIN_URL', plugin_dir_url(__FILE__));

// Load the main plugin class
require_once PTSD_PLUGIN_DIR . 'includes/class-post-type-sql-dump.php';

// Initialize the plugin
function ptsd_init() {
    if (defined('WP_CLI') && WP_CLI) {
        require_once PTSD_PLUGIN_DIR . 'includes/class-ptsd-cli-command.php';
        WP_CLI::add_command('ptsd dump', 'PTSD_CLI_Command');
    }
}
add_action('plugins_loaded', 'ptsd_init');
