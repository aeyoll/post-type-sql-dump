<?php
/**
 * WP-CLI Command for Post Type SQL Dump
 *
 * @package PostTypeSQLDump
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

if (!defined('WP_CLI') || !WP_CLI) {
    return;
}

class PTSD_CLI_Command
{
    /**
     * Dump content for a specific post type.
     *
     * ## OPTIONS
     *
     * [--post_type=<post_type>]
     * : The post type to export. Default: post
     *
     * ## EXAMPLES
     *
     *     wp ptsd dump --post_type=page
     *     wp ptsd dump --post_type=product
     *
     * @param array $args Positional arguments.
     * @param array $assoc_args Associative arguments.
     */
    public function __invoke($args, $assoc_args)
    {
        $post_type = isset($assoc_args['post_type']) ? $assoc_args['post_type'] : 'post';

        WP_CLI::log("Exporting content for post_type: {$post_type} with new IDs (Polylang support)");

        try {
            $generator = new PTSD_Generator();
            $sql = $generator->generate($post_type);

            foreach ($generator->get_log_messages() as $message) {
                WP_CLI::log($message);
            }

            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI output of SQL dump
            fwrite(STDOUT, $sql);

            WP_CLI::success("SQL dump generated successfully for post_type: {$post_type} with new IDs (Polylang compatible)");
        } catch (Exception $e) {
            WP_CLI::error('Error: ' . $e->getMessage());
        }
    }
}
