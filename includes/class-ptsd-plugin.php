<?php
/**
 * Main plugin class
 *
 * @package PostTypeSQLDump
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PTSD_Plugin
{
    /**
     * Plugin version
     *
     * @var string
     */
    const VERSION = '1.0.0';

    /**
     * Constructor — boots the admin interface.
     */
    public function __construct()
    {
        new PTSD_Admin();
    }

    /**
     * Get plugin version.
     *
     * @return string
     */
    public function get_version(): string
    {
        return self::VERSION;
    }
}
