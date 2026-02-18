<?php
/**
 * Admin page for Post Type SQL Dump
 *
 * @package PostTypeSQLDump
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PTSD_Admin
{
    /**
     * Admin page slug.
     *
     * @var string
     */
    const PAGE_SLUG = 'ptsd-sql-dump';

    /**
     * Nonce action name.
     *
     * @var string
     */
    const NONCE_ACTION = 'ptsd_generate_dump';

    /**
     * Nonce field name.
     *
     * @var string
     */
    const NONCE_FIELD = 'ptsd_nonce';

    /**
     * Constructor — registers hooks.
     */
    public function __construct()
    {
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_post_ptsd_download', [$this, 'handle_download']);
    }

    /**
     * Register the admin menu page under Tools.
     */
    public function register_menu(): void
    {
        add_management_page(
            __('Post Type SQL Dump', 'post-type-sql-dump'),
            __('SQL Dump', 'post-type-sql-dump'),
            'manage_options',
            self::PAGE_SLUG,
            [$this, 'render_page']
        );
    }

    /**
     * Render the admin page.
     */
    public function render_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'post-type-sql-dump'));
        }

        $sql_output = '';
        $selected_post_type = '';
        $error_message = '';

        if (isset($_POST['ptsd_post_type'])) {
            if (
                !isset($_POST[self::NONCE_FIELD]) ||
                !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
            ) {
                $error_message = __('Security check failed. Please try again.', 'post-type-sql-dump');
            } else {
                $selected_post_type = sanitize_key($_POST['ptsd_post_type']);
                $available_post_types = array_keys(get_post_types(['public' => true]));

                if (!in_array($selected_post_type, $available_post_types, true)) {
                    $error_message = __('Invalid post type selected.', 'post-type-sql-dump');
                } else {
                    try {
                        $generator = new PTSD_Generator();
                        $sql_output = $generator->generate($selected_post_type);
                    } catch (Exception $e) {
                        $error_message = esc_html($e->getMessage());
                    }
                }
            }
        }

        $post_types = get_post_types(['public' => true], 'objects');

        require PTSD_PLUGIN_DIR . 'views/admin-page.php';
    }

    /**
     * Handle the SQL file download request.
     *
     * Expects a POST with nonce, post_type, and the generated SQL content.
     */
    public function handle_download(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions.', 'post-type-sql-dump'));
        }

        if (
            !isset($_POST[self::NONCE_FIELD]) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            wp_die(esc_html__('Security check failed.', 'post-type-sql-dump'));
        }

        $post_type = sanitize_key($_POST['ptsd_post_type'] ?? '');
        $available_post_types = array_keys(get_post_types(['public' => true]));

        if (!in_array($post_type, $available_post_types, true)) {
            wp_die(esc_html__('Invalid post type.', 'post-type-sql-dump'));
        }

        try {
            $generator = new PTSD_Generator();
            $sql = $generator->generate($post_type);
        } catch (Exception $e) {
            wp_die(esc_html($e->getMessage()));
        }

        $filename = 'ptsd-' . $post_type . '-' . gmdate('Y-m-d-His') . '.sql';

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        header('Pragma: no-cache');
        header('Expires: 0');

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Raw SQL file download
        echo $sql;
        exit;
    }
}
