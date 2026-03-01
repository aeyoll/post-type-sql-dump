<?php
/**
 * Plugin Name: Post Type SQL Dump
 * Plugin URI: https://wordpress.org/plugins/post-type-sql-dump/
 * Description: Export WordPress posts and their associated data (including Polylang translations) to SQL format via the admin interface (Tools > SQL Dump) or WP-CLI command
 * Version: 1.0.0
 * Author: Jean-Philippe Bidegain
 * Author URI: https://github.com/aeyoll/post-type-sql-dump
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

// Load shared classes (available in all contexts)
require_once PTSD_PLUGIN_DIR . 'includes/class-ptsd-generator.php';
require_once PTSD_PLUGIN_DIR . 'includes/class-ptsd-admin.php';
require_once PTSD_PLUGIN_DIR . 'includes/class-ptsd-plugin.php';

// Initialize the plugin
function ptsd_init()
{
    new PTSD_Plugin();

    if (defined('WP_CLI') && WP_CLI) {
        require_once PTSD_PLUGIN_DIR . 'includes/class-ptsd-cli-command.php';
        WP_CLI::add_command('ptsd dump', 'PTSD_CLI_Command');
    }
}
add_action('plugins_loaded', 'ptsd_init');
