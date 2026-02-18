<?php
/**
 * Admin page template for Post Type SQL Dump
 *
 * Available variables:
 *   $post_types       - array of WP_Post_Type objects (public post types)
 *   $selected_post_type - string, the post type submitted (empty string if none yet)
 *   $sql_output       - string, the generated SQL (empty string if not yet generated)
 *   $error_message    - string, an error message to display (empty string if none)
 *
 * @package PostTypeSQLDump
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap">
    <h1><?php esc_html_e('Post Type SQL Dump', 'post-type-sql-dump'); ?></h1>

    <p><?php esc_html_e('Select a public post type to generate an SQL dump that can be imported into another WordPress database.', 'post-type-sql-dump'); ?></p>

    <?php if (!empty($error_message)) : ?>
        <div class="notice notice-error">
            <p><?php echo esc_html($error_message); ?></p>
        </div>
    <?php endif; ?>

    <form method="post" action="">
        <?php wp_nonce_field(PTSD_Admin::NONCE_ACTION, PTSD_Admin::NONCE_FIELD); ?>

        <table class="form-table" role="presentation">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="ptsd_post_type"><?php esc_html_e('Post Type', 'post-type-sql-dump'); ?></label>
                    </th>
                    <td>
                        <select name="ptsd_post_type" id="ptsd_post_type">
                            <?php foreach ($post_types as $pt) : ?>
                                <option
                                    value="<?php echo esc_attr($pt->name); ?>"
                                    <?php selected($selected_post_type, $pt->name); ?>
                                >
                                    <?php echo esc_html($pt->labels->singular_name . ' (' . $pt->name . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </tbody>
        </table>

        <?php submit_button(__('Generate SQL Dump', 'post-type-sql-dump')); ?>
    </form>

    <?php if (!empty($sql_output)) : ?>
        <hr />

        <h2><?php esc_html_e('Generated SQL', 'post-type-sql-dump'); ?></h2>

        <p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field(PTSD_Admin::NONCE_ACTION, PTSD_Admin::NONCE_FIELD); ?>
                <input type="hidden" name="action" value="ptsd_download">
                <input type="hidden" name="ptsd_post_type" value="<?php echo esc_attr($selected_post_type); ?>">
                <?php submit_button(__('Download as .sql file', 'post-type-sql-dump'), 'secondary', 'ptsd_download_btn', false); ?>
            </form>
        </p>

        <textarea
            readonly
            rows="30"
            style="width: 100%; font-family: monospace; font-size: 12px;"
            aria-label="<?php esc_attr_e('Generated SQL dump', 'post-type-sql-dump'); ?>"
        ><?php echo esc_textarea($sql_output); ?></textarea>
    <?php endif; ?>
</div>
