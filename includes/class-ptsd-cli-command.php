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

class PTSD_CLI_Command {

    /**
     * Database connection
     *
     * @var mysqli
     */
    private $mysqli;

    /**
     * SQL dump array
     *
     * @var array
     */
    private $sql_dump = [];

    /**
     * Post type being exported
     *
     * @var string
     */
    private $post_type;

    /**
     * Database table prefix
     *
     * @var string
     */
    private $table_prefix = 'wp_';

    /**
     * Translation groups for posts
     *
     * @var array
     */
    private $translation_groups = [];

    /**
     * Term translation groups
     *
     * @var array
     */
    private $term_translation_groups = [];

    /**
     * Dump content for a specific post type
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
     * @param array $args Positional arguments
     * @param array $assoc_args Associative arguments
     */
    public function __invoke($args, $assoc_args) {
        $this->post_type = isset($assoc_args['post_type']) ? $assoc_args['post_type'] : 'post';

        try {
            $this->initialize_database();
            $this->add_header();
            $this->add_deletion_queries();
            $this->export_language_mapping();
            $this->export_posts();
            $this->export_featured_images();
            $this->export_attachment_metadata();
            $this->export_post_metadata();
            $this->export_translation_group_terms();
            $this->export_translation_group_taxonomy();
            $this->export_regular_terms();
            $this->export_term_translation_group_terms();
            $this->export_term_translation_group_taxonomy();
            $this->export_regular_term_taxonomy();
            $this->export_term_metadata();
            $this->assign_terms_to_languages();
            $this->assign_posts_to_languages();
            $this->export_post_term_relationships();
            $this->export_term_translation_relationships();
            $this->update_post_translation_descriptions();
            $this->update_term_translation_descriptions();
            $this->add_footer();

            $this->output_sql_dump();

            WP_CLI::success("SQL dump generated successfully for post_type: {$this->post_type} with new IDs (Polylang compatible)");
        } catch (Exception $e) {
            WP_CLI::error("Error: " . $e->getMessage());
        }
    }

    /**
     * Initialize database connection
     *
     * @throws Exception
     */
    private function initialize_database() {
        $database = defined('DB_NAME') ? DB_NAME : '';
        $host = defined('DB_HOST') ? DB_HOST : '';
        $user = defined('DB_USER') ? DB_USER : '';
        $password = defined('DB_PASSWORD') ? DB_PASSWORD : '';

        $this->mysqli = new mysqli($host, $user, $password, $database);

        if ($this->mysqli->connect_error) {
            throw new Exception("Connection failed: " . $this->mysqli->connect_error);
        }

        $this->mysqli->set_charset("utf8mb4");

        WP_CLI::log("Exporting content for post_type: {$this->post_type} with new IDs (Polylang support)");
    }

    /**
     * Add SQL dump header
     */
    private function add_header() {
        $database = defined('DB_NAME') ? DB_NAME : '';

        $this->sql_dump[] = "-- WordPress Posts Export with New IDs (Polylang Compatible)";
        $this->sql_dump[] = "-- Post Type: {$this->post_type}";
        $this->sql_dump[] = "-- Generated: " . date('Y-m-d H:i:s');
        $this->sql_dump[] = "-- Database: {$database}";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";
        $this->sql_dump[] = "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";";
        $this->sql_dump[] = "SET time_zone = \"+00:00\";";
        $this->sql_dump[] = "SET NAMES utf8mb4;";
        $this->sql_dump[] = "SET FOREIGN_KEY_CHECKS = 0;";
        $this->sql_dump[] = "";
    }

    /**
     * Add deletion queries for existing data
     */
    private function add_deletion_queries() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Delete existing data for post_type: {$this->post_type}";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";
        $this->sql_dump[] = "-- Store post IDs to delete in a temporary table";
        $this->sql_dump[] = "CREATE TEMPORARY TABLE IF NOT EXISTS temp_posts_to_delete (post_id INT);";
        $this->sql_dump[] = "INSERT INTO temp_posts_to_delete (post_id) SELECT ID FROM `{$this->table_prefix}posts` WHERE post_type = '{$this->post_type}';";
        $this->sql_dump[] = "";

        // Get taxonomies and add deletion queries for them
        $taxonomies = get_object_taxonomies($this->post_type);
        $taxonomies = array_filter($taxonomies, function ($tax) {
            return !in_array($tax, ['language', 'language_translations']);
        });

        if (!empty($taxonomies)) {
            $taxonomy_list = "'" . implode("','", array_map('esc_sql', $taxonomies)) . "'";
            $this->sql_dump[] = "-- Get all taxonomies linked to this post type";
            $this->sql_dump[] = "-- Store all terms from taxonomies linked to post type: " . implode(', ', $taxonomies);
            $this->sql_dump[] = "CREATE TEMPORARY TABLE IF NOT EXISTS temp_taxonomies_to_delete (";
            $this->sql_dump[] = "  term_taxonomy_id INT,";
            $this->sql_dump[] = "  term_id INT,";
            $this->sql_dump[] = "  taxonomy VARCHAR(32)";
            $this->sql_dump[] = ");";
            $this->sql_dump[] = "";
            $this->sql_dump[] = "-- Collect ALL term_taxonomy entries for the taxonomies linked to this post type";
            $this->sql_dump[] = "INSERT INTO temp_taxonomies_to_delete (term_taxonomy_id, term_id, taxonomy)";
            $this->sql_dump[] = "SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy";
            $this->sql_dump[] = "FROM `{$this->table_prefix}term_taxonomy` tt";
            $this->sql_dump[] = "WHERE tt.taxonomy IN ({$taxonomy_list});";
            $this->sql_dump[] = "";
            $this->sql_dump[] = "-- Delete term relationships for these posts";
            $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}term_relationships` WHERE object_id IN (SELECT post_id FROM temp_posts_to_delete);";
            $this->sql_dump[] = "";
            $this->sql_dump[] = "-- Delete termmeta for terms that will be deleted";
            $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}termmeta` WHERE term_id IN (SELECT term_id FROM temp_taxonomies_to_delete);";
            $this->sql_dump[] = "";
            $this->sql_dump[] = "-- Delete term_taxonomy entries for these taxonomies";
            $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}term_taxonomy` WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM temp_taxonomies_to_delete);";
            $this->sql_dump[] = "";
            $this->sql_dump[] = "-- Delete the terms themselves";
            $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}terms` WHERE term_id IN (SELECT term_id FROM temp_taxonomies_to_delete);";
            $this->sql_dump[] = "";
            $this->sql_dump[] = "-- Drop temporary table for taxonomies";
            $this->sql_dump[] = "DROP TEMPORARY TABLE IF EXISTS temp_taxonomies_to_delete;";
            $this->sql_dump[] = "";
        }

        $this->sql_dump[] = "-- Delete postmeta for these posts";
        $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}postmeta` WHERE post_id IN (SELECT post_id FROM temp_posts_to_delete);";
        $this->sql_dump[] = "";
        $this->sql_dump[] = "-- Delete the posts themselves";
        $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}posts` WHERE post_type = '{$this->post_type}';";
        $this->sql_dump[] = "";
        $this->sql_dump[] = "-- Clean up any remaining orphaned terms with no taxonomy relationships";
        $this->sql_dump[] = "DELETE t FROM `{$this->table_prefix}terms` t";
        $this->sql_dump[] = "LEFT JOIN `{$this->table_prefix}term_taxonomy` tt ON t.term_id = tt.term_id";
        $this->sql_dump[] = "WHERE tt.term_id IS NULL;";
        $this->sql_dump[] = "";
        $this->sql_dump[] = "-- Drop temporary table for posts";
        $this->sql_dump[] = "DROP TEMPORARY TABLE IF EXISTS temp_posts_to_delete;";
        $this->sql_dump[] = "";
    }

    /**
     * Export language mapping for Polylang
     */
    private function export_language_mapping() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Polylang Language Mapping (dynamically map to target database languages)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $language_mapping_query = "
            SELECT DISTINCT t.slug
            FROM {$this->table_prefix}term_taxonomy tt
            INNER JOIN {$this->table_prefix}terms t ON tt.term_id = t.term_id
            WHERE tt.taxonomy = 'language'
            ORDER BY t.slug
        ";

        $language_mapping_result = $this->mysqli->query($language_mapping_query);

        $language_slugs = [];
        if ($language_mapping_result && $language_mapping_result->num_rows > 0) {
            while ($lang_map = $language_mapping_result->fetch_assoc()) {
                $language_slugs[] = $lang_map['slug'];
            }
            $language_mapping_result->free();
        }

        if (!empty($language_slugs)) {
            $this->sql_dump[] = "-- Dynamically map language slugs to target database term_taxonomy_ids";
            foreach ($language_slugs as $slug) {
                // Map 'language' taxonomy (for posts)
                $this->sql_dump[] = "SET @existing_lang_{$slug} = (";
                $this->sql_dump[] = "  SELECT tt.term_taxonomy_id";
                $this->sql_dump[] = "  FROM `{$this->table_prefix}term_taxonomy` tt";
                $this->sql_dump[] = "  INNER JOIN `{$this->table_prefix}terms` t ON tt.term_id = t.term_id";
                $this->sql_dump[] = "  WHERE tt.taxonomy = 'language' AND t.slug = '{$slug}'";
                $this->sql_dump[] = "  LIMIT 1";
                $this->sql_dump[] = ");";

                // Map 'term_language' taxonomy (for terms)
                $this->sql_dump[] = "SET @existing_term_lang_{$slug} = (";
                $this->sql_dump[] = "  SELECT tt.term_taxonomy_id";
                $this->sql_dump[] = "  FROM `{$this->table_prefix}term_taxonomy` tt";
                $this->sql_dump[] = "  INNER JOIN `{$this->table_prefix}terms` t ON tt.term_id = t.term_id";
                $this->sql_dump[] = "  WHERE tt.taxonomy = 'term_language' AND t.slug = '{$slug}'";
                $this->sql_dump[] = "  LIMIT 1";
                $this->sql_dump[] = ");";
                // Try with 'pll_' prefix
                $this->sql_dump[] = "SET @existing_term_lang_{$slug} = IFNULL(@existing_term_lang_{$slug}, (";
                $this->sql_dump[] = "  SELECT tt.term_taxonomy_id";
                $this->sql_dump[] = "  FROM `{$this->table_prefix}term_taxonomy` tt";
                $this->sql_dump[] = "  INNER JOIN `{$this->table_prefix}terms` t ON tt.term_id = t.term_id";
                $this->sql_dump[] = "  WHERE tt.taxonomy = 'term_language' AND t.slug = 'pll_{$slug}'";
                $this->sql_dump[] = "  LIMIT 1";
                $this->sql_dump[] = "));";
                // Fallback to 'language' taxonomy
                $this->sql_dump[] = "SET @existing_term_lang_{$slug} = IFNULL(@existing_term_lang_{$slug}, @existing_lang_{$slug});";
            }
            $this->sql_dump[] = "";
        }
    }

    /**
     * Export posts with new IDs
     */
    private function export_posts() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Posts (will get new IDs, keeping original author IDs)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $posts_query = "
            SELECT p.*, t.slug as post_language
            FROM {$this->table_prefix}posts p
            LEFT JOIN {$this->table_prefix}term_relationships tr ON p.ID = tr.object_id
            LEFT JOIN {$this->table_prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
            LEFT JOIN {$this->table_prefix}terms t ON tt.term_id = t.term_id
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            ORDER BY p.ID ASC
        ";

        $posts_result = $this->mysqli->query($posts_query);

        if ($posts_result && $posts_result->num_rows > 0) {
            while ($post = $posts_result->fetch_assoc()) {
                $old_post_id = $post['ID'];
                $post_language = $post['post_language'];

                unset($post['ID'], $post['post_language']);

                $columns = [];
                $values = [];

                foreach ($post as $column => $value) {
                    $columns[] = $column;

                    if ($column === 'post_parent' && $value > 0) {
                        $values[] = "@post_id_{$value}";
                    } elseif ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $escaped_value = $this->mysqli->real_escape_string($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = "`" . implode("`, `", $columns) . "`";
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}posts` ({$columns_list}) VALUES (" . implode(", ", $values) . ");";
                $this->sql_dump[] = "SET @post_id_{$old_post_id} = LAST_INSERT_ID();";

                if ($post_language) {
                    $this->sql_dump[] = "SET @post_{$old_post_id}_lang = '{$post_language}';";
                }
                $this->sql_dump[] = "";

                WP_CLI::log("Mapped post ID {$old_post_id} to new ID (language: " . ($post_language ?: 'none') . ")");
            }
            $posts_result->free();
        }
    }

    /**
     * Export featured images (attachment posts)
     */
    private function export_featured_images() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Featured Images (attachment posts)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $attachments_query = "
            SELECT DISTINCT a.*
            FROM {$this->table_prefix}posts a
            INNER JOIN {$this->table_prefix}postmeta pm ON a.ID = pm.meta_value
            INNER JOIN {$this->table_prefix}posts p ON pm.post_id = p.ID
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND pm.meta_key = '_thumbnail_id'
            AND a.post_type = 'attachment'
            ORDER BY a.ID ASC
        ";

        $attachments_result = $this->mysqli->query($attachments_query);

        if ($attachments_result && $attachments_result->num_rows > 0) {
            while ($attachment = $attachments_result->fetch_assoc()) {
                $old_attachment_id = $attachment['ID'];

                unset($attachment['ID']);

                $columns = [];
                $values = [];

                foreach ($attachment as $column => $value) {
                    $columns[] = $column;

                    if ($column === 'post_parent' && $value > 0) {
                        $values[] = "IFNULL(@post_id_{$value}, 0)";
                    } elseif ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $escaped_value = $this->mysqli->real_escape_string($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = "`" . implode("`, `", $columns) . "`";
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}posts` ({$columns_list}) VALUES (" . implode(", ", $values) . ");";
                $this->sql_dump[] = "SET @attachment_id_{$old_attachment_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = "";

                WP_CLI::log("Mapped attachment ID {$old_attachment_id} to new ID");
            }
            $attachments_result->free();
        }
    }

    /**
     * Export attachment metadata
     */
    private function export_attachment_metadata() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Attachment Meta (for featured images)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $attachment_meta_query = "
            SELECT pm.*, a.ID as old_attachment_id
            FROM {$this->table_prefix}postmeta pm
            INNER JOIN {$this->table_prefix}posts a ON pm.post_id = a.ID
            INNER JOIN {$this->table_prefix}postmeta pm2 ON a.ID = pm2.meta_value
            INNER JOIN {$this->table_prefix}posts p ON pm2.post_id = p.ID
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND pm2.meta_key = '_thumbnail_id'
            AND a.post_type = 'attachment'
            ORDER BY pm.meta_id ASC
        ";

        $attachment_meta_result = $this->mysqli->query($attachment_meta_query);

        if ($attachment_meta_result && $attachment_meta_result->num_rows > 0) {
            while ($meta = $attachment_meta_result->fetch_assoc()) {
                $old_attachment_id = $meta['old_attachment_id'];
                $meta_key = $meta['meta_key'];
                $meta_value = $meta['meta_value'];

                $escaped_meta_key = $this->mysqli->real_escape_string($meta_key);
                $escaped_meta_value = $meta_value === null ? 'NULL' : "'" . $this->mysqli->real_escape_string($meta_value) . "'";
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES (@attachment_id_{$old_attachment_id}, '{$escaped_meta_key}', {$escaped_meta_value});";
            }
            $this->sql_dump[] = "";
            $attachment_meta_result->free();
        }
    }

    /**
     * Export post metadata with mapped post IDs
     */
    private function export_post_metadata() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Post Meta (with mapped post IDs - includes Polylang data)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $postmeta_query = "
            SELECT pm.*, p.ID as old_post_id
            FROM {$this->table_prefix}postmeta pm
            INNER JOIN {$this->table_prefix}posts p ON pm.post_id = p.ID
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            ORDER BY pm.meta_id ASC
        ";

        $postmeta_result = $this->mysqli->query($postmeta_query);

        if ($postmeta_result && $postmeta_result->num_rows > 0) {
            while ($meta = $postmeta_result->fetch_assoc()) {
                $old_post_id = $meta['old_post_id'];
                $meta_key = $meta['meta_key'];
                $meta_value = $meta['meta_value'];

                if ($meta_key === '_pll_translation_of' && is_numeric($meta_value) && $meta_value > 0) {
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES (@post_id_{$old_post_id}, '{$this->mysqli->real_escape_string($meta_key)}', @post_id_{$meta_value});";
                } elseif ($meta_key === '_thumbnail_id' && is_numeric($meta_value) && $meta_value > 0) {
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES (@post_id_{$old_post_id}, '{$this->mysqli->real_escape_string($meta_key)}', @attachment_id_{$meta_value});";
                } else {
                    $escaped_meta_key = $this->mysqli->real_escape_string($meta_key);
                    $escaped_meta_value = $meta_value === null ? 'NULL' : "'" . $this->mysqli->real_escape_string($meta_value) . "'";
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES (@post_id_{$old_post_id}, '{$escaped_meta_key}', {$escaped_meta_value});";
                }
            }
            $this->sql_dump[] = "";
            $postmeta_result->free();
        }
    }

    /**
     * Export Polylang translation group terms
     */
    private function export_translation_group_terms() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Polylang Translation Group Terms (will get new IDs)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $translation_terms_query = "
            SELECT DISTINCT t.*
            FROM {$this->table_prefix}terms t
            INNER JOIN {$this->table_prefix}term_taxonomy tt ON t.term_id = tt.term_id
            INNER JOIN {$this->table_prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$this->table_prefix}posts p ON tr.object_id = p.ID
            WHERE tt.taxonomy = 'post_translations'
            AND p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
        ";

        $translation_terms_result = $this->mysqli->query($translation_terms_query);

        if ($translation_terms_result && $translation_terms_result->num_rows > 0) {
            while ($term = $translation_terms_result->fetch_assoc()) {
                $old_term_id = $term['term_id'];

                unset($term['term_id']);
                if (isset($term['term_order'])) {
                    unset($term['term_order']);
                }

                $columns = array_keys($term);
                $values = [];

                foreach ($columns as $column) {
                    $value = $term[$column];

                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $escaped_value = $this->mysqli->real_escape_string($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = "`" . implode("`, `", $columns) . "`";
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}terms` ({$columns_list}) VALUES (" . implode(", ", $values) . ");";
                $this->sql_dump[] = "SET @trans_term_id_{$old_term_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = "";
            }
            $translation_terms_result->free();
        }
    }

    /**
     * Export Polylang translation group term_taxonomy
     */
    private function export_translation_group_taxonomy() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Polylang Translation Group Term Taxonomy";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $translation_taxonomy_query = "
            SELECT DISTINCT tt.*
            FROM {$this->table_prefix}term_taxonomy tt
            INNER JOIN {$this->table_prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$this->table_prefix}posts p ON tr.object_id = p.ID
            WHERE tt.taxonomy = 'post_translations'
            AND p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
        ";

        $translation_taxonomy_result = $this->mysqli->query($translation_taxonomy_query);

        if ($translation_taxonomy_result && $translation_taxonomy_result->num_rows > 0) {
            while ($tt = $translation_taxonomy_result->fetch_assoc()) {
                $old_term_taxonomy_id = $tt['term_taxonomy_id'];
                $old_term_id = $tt['term_id'];
                $tt_description = $tt['description'];

                $translation_data = @unserialize($tt_description);
                if (is_array($translation_data)) {
                    $this->translation_groups[$old_term_id] = $translation_data;
                }

                unset($tt['term_taxonomy_id'], $tt['term_id']);

                $columns = [];
                $values = [];

                $columns[] = 'term_id';
                $values[] = "@trans_term_id_{$old_term_id}";

                foreach ($tt as $column => $value) {
                    $columns[] = $column;

                    if ($column === 'description') {
                        $values[] = "''";
                    } elseif ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $escaped_value = $this->mysqli->real_escape_string($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = "`" . implode("`, `", $columns) . "`";
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_taxonomy` ({$columns_list}) VALUES (" . implode(", ", $values) . ");";
                $this->sql_dump[] = "SET @trans_term_taxonomy_id_{$old_term_taxonomy_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = "";
            }
            $translation_taxonomy_result->free();
        }
    }

    /**
     * Export regular terms with their languages
     */
    private function export_regular_terms() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Regular Terms (will get new IDs)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        // Get all taxonomies used by this post type
        $taxonomies_query = "
            SELECT DISTINCT tt.taxonomy
            FROM {$this->table_prefix}term_taxonomy tt
            INNER JOIN {$this->table_prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$this->table_prefix}posts p ON tr.object_id = p.ID
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND tt.taxonomy NOT IN ('language', 'post_translations', 'term_language', 'term_translations')
        ";

        $taxonomies_result = $this->mysqli->query($taxonomies_query);
        $taxonomies = [];

        if ($taxonomies_result && $taxonomies_result->num_rows > 0) {
            while ($tax = $taxonomies_result->fetch_assoc()) {
                $taxonomies[] = "'" . $this->mysqli->real_escape_string($tax['taxonomy']) . "'";
            }
            $taxonomies_result->free();
            WP_CLI::log("Found taxonomies: " . implode(', ', $taxonomies));
        }

        if (!empty($taxonomies)) {
            $taxonomies_list = implode(', ', $taxonomies);
            $terms_query = "
                SELECT DISTINCT t.*, lang_t.slug as term_language
                FROM {$this->table_prefix}terms t
                INNER JOIN {$this->table_prefix}term_taxonomy tt ON t.term_id = tt.term_id
                LEFT JOIN {$this->table_prefix}term_relationships lang_tr ON t.term_id = lang_tr.object_id
                LEFT JOIN {$this->table_prefix}term_taxonomy lang_tt ON lang_tr.term_taxonomy_id = lang_tt.term_taxonomy_id AND lang_tt.taxonomy = 'term_language'
                LEFT JOIN {$this->table_prefix}terms lang_t ON lang_tt.term_id = lang_t.term_id
                WHERE tt.taxonomy IN ({$taxonomies_list})
                ORDER BY t.term_id ASC
            ";

            $terms_result = $this->mysqli->query($terms_query);

            if ($terms_result && $terms_result->num_rows > 0) {
                while ($term = $terms_result->fetch_assoc()) {
                    $old_term_id = $term['term_id'];
                    $term_language = $term['term_language'];

                    unset($term['term_id'], $term['term_language']);
                    if (isset($term['term_order'])) {
                        unset($term['term_order']);
                    }

                    $columns = array_keys($term);
                    $values = [];

                    foreach ($columns as $column) {
                        $value = $term[$column];
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $escaped_value = $this->mysqli->real_escape_string($value);
                            $values[] = "'{$escaped_value}'";
                        }
                    }

                    $columns_list = "`" . implode("`, `", $columns) . "`";
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}terms` ({$columns_list}) VALUES (" . implode(", ", $values) . ");";
                    $this->sql_dump[] = "SET @term_id_{$old_term_id} = LAST_INSERT_ID();";

                    if ($term_language) {
                        $this->sql_dump[] = "SET @term_{$old_term_id}_lang = '{$term_language}';";
                    }
                    $this->sql_dump[] = "";
                }
                $terms_result->free();
            }
        }
    }

    /**
     * Export term translation group terms
     */
    private function export_term_translation_group_terms() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Term Translation Group Terms (will get new IDs)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $term_translation_terms_query = "
            SELECT DISTINCT t.*
            FROM {$this->table_prefix}terms t
            INNER JOIN {$this->table_prefix}term_taxonomy tt ON t.term_id = tt.term_id
            INNER JOIN {$this->table_prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$this->table_prefix}terms term_obj ON tr.object_id = term_obj.term_id
            INNER JOIN {$this->table_prefix}term_taxonomy tt2 ON term_obj.term_id = tt2.term_id
            INNER JOIN {$this->table_prefix}term_relationships tr2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
            INNER JOIN {$this->table_prefix}posts p ON tr2.object_id = p.ID
            WHERE tt.taxonomy = 'term_translations'
            AND p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND tt2.taxonomy NOT IN ('language', 'post_translations', 'term_language', 'term_translations')
        ";

        $term_translation_terms_result = $this->mysqli->query($term_translation_terms_query);

        if ($term_translation_terms_result && $term_translation_terms_result->num_rows > 0) {
            while ($term = $term_translation_terms_result->fetch_assoc()) {
                $old_term_id = $term['term_id'];

                unset($term['term_id']);
                if (isset($term['term_order'])) {
                    unset($term['term_order']);
                }

                $columns = array_keys($term);
                $values = [];

                foreach ($columns as $column) {
                    $value = $term[$column];

                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $escaped_value = $this->mysqli->real_escape_string($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = "`" . implode("`, `", $columns) . "`";
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}terms` ({$columns_list}) VALUES (" . implode(", ", $values) . ");";
                $this->sql_dump[] = "SET @term_trans_term_id_{$old_term_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = "";
            }
            $term_translation_terms_result->free();
        }
    }

    /**
     * Export term translation group term_taxonomy
     */
    private function export_term_translation_group_taxonomy() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Term Translation Group Term Taxonomy";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $term_translation_taxonomy_query = "
            SELECT DISTINCT tt.*
            FROM {$this->table_prefix}term_taxonomy tt
            INNER JOIN {$this->table_prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$this->table_prefix}terms term_obj ON tr.object_id = term_obj.term_id
            INNER JOIN {$this->table_prefix}term_taxonomy tt2 ON term_obj.term_id = tt2.term_id
            INNER JOIN {$this->table_prefix}term_relationships tr2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
            INNER JOIN {$this->table_prefix}posts p ON tr2.object_id = p.ID
            WHERE tt.taxonomy = 'term_translations'
            AND p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND tt2.taxonomy NOT IN ('language', 'post_translations', 'term_language', 'term_translations')
        ";

        $term_translation_taxonomy_result = $this->mysqli->query($term_translation_taxonomy_query);

        if ($term_translation_taxonomy_result && $term_translation_taxonomy_result->num_rows > 0) {
            while ($tt = $term_translation_taxonomy_result->fetch_assoc()) {
                $old_term_taxonomy_id = $tt['term_taxonomy_id'];
                $old_term_id = $tt['term_id'];
                $tt_description = $tt['description'];

                $translation_data = @unserialize($tt_description);
                if (is_array($translation_data)) {
                    $this->term_translation_groups[$old_term_id] = $translation_data;
                }

                unset($tt['term_taxonomy_id'], $tt['term_id']);

                $columns = [];
                $values = [];

                $columns[] = 'term_id';
                $values[] = "@term_trans_term_id_{$old_term_id}";

                foreach ($tt as $column => $value) {
                    $columns[] = $column;

                    if ($column === 'description') {
                        $values[] = "''";
                    } elseif ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_numeric($value)) {
                        $values[] = $value;
                    } else {
                        $escaped_value = $this->mysqli->real_escape_string($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = "`" . implode("`, `", $columns) . "`";
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_taxonomy` ({$columns_list}) VALUES (" . implode(", ", $values) . ");";
                $this->sql_dump[] = "SET @term_trans_term_taxonomy_id_{$old_term_taxonomy_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = "";
            }
            $term_translation_taxonomy_result->free();
        }
    }

    /**
     * Export regular term_taxonomy with mapped term IDs
     */
    private function export_regular_term_taxonomy() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Regular Term Taxonomy (with mapped term IDs)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        // Get taxonomies list
        $taxonomies_query = "
            SELECT DISTINCT tt.taxonomy
            FROM {$this->table_prefix}term_taxonomy tt
            INNER JOIN {$this->table_prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$this->table_prefix}posts p ON tr.object_id = p.ID
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND tt.taxonomy NOT IN ('language', 'post_translations', 'term_language', 'term_translations')
        ";

        $taxonomies_result = $this->mysqli->query($taxonomies_query);
        $taxonomies = [];

        if ($taxonomies_result && $taxonomies_result->num_rows > 0) {
            while ($tax = $taxonomies_result->fetch_assoc()) {
                $taxonomies[] = "'" . $this->mysqli->real_escape_string($tax['taxonomy']) . "'";
            }
            $taxonomies_result->free();
        }

        if (!empty($taxonomies)) {
            $taxonomies_list = implode(', ', $taxonomies);
            $term_taxonomy_query = "
                SELECT DISTINCT tt.*
                FROM {$this->table_prefix}term_taxonomy tt
                WHERE tt.taxonomy IN ({$taxonomies_list})
                ORDER BY tt.term_taxonomy_id ASC
            ";

            $term_taxonomy_result = $this->mysqli->query($term_taxonomy_query);

            if ($term_taxonomy_result && $term_taxonomy_result->num_rows > 0) {
                WP_CLI::log("Exporting " . $term_taxonomy_result->num_rows . " term_taxonomy entries");
                while ($tt = $term_taxonomy_result->fetch_assoc()) {
                    $old_term_taxonomy_id = $tt['term_taxonomy_id'];
                    $old_term_id = $tt['term_id'];
                    $taxonomy = $tt['taxonomy'];

                    unset($tt['term_taxonomy_id'], $tt['term_id']);

                    $columns = [];
                    $values = [];

                    $columns[] = 'term_id';
                    $values[] = "@term_id_{$old_term_id}";

                    foreach ($tt as $column => $value) {
                        $columns[] = $column;

                        if ($column === 'parent' && $value > 0) {
                            $values[] = "IFNULL(@term_id_{$value}, 0)";
                        } elseif ($value === null) {
                            $values[] = 'NULL';
                        } elseif (is_numeric($value)) {
                            $values[] = $value;
                        } else {
                            $escaped_value = $this->mysqli->real_escape_string($value);
                            $values[] = "'{$escaped_value}'";
                        }
                    }

                    $columns_list = "`" . implode("`, `", $columns) . "`";
                    $this->sql_dump[] = "-- term_taxonomy_id: {$old_term_taxonomy_id}, term_id: {$old_term_id}, taxonomy: {$taxonomy}";
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_taxonomy` ({$columns_list}) SELECT " . implode(", ", $values) . " WHERE @term_id_{$old_term_id} IS NOT NULL;";
                    $this->sql_dump[] = "SET @term_taxonomy_id_{$old_term_taxonomy_id} = IF(@term_id_{$old_term_id} IS NOT NULL, LAST_INSERT_ID(), NULL);";
                    $this->sql_dump[] = "";
                }
                $term_taxonomy_result->free();
            }
        }
    }

    /**
     * Export term metadata
     */
    private function export_term_metadata() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Term Meta (with mapped term IDs)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        // Get taxonomies list
        $taxonomies_query = "
            SELECT DISTINCT tt.taxonomy
            FROM {$this->table_prefix}term_taxonomy tt
            INNER JOIN {$this->table_prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$this->table_prefix}posts p ON tr.object_id = p.ID
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND tt.taxonomy NOT IN ('language', 'post_translations', 'term_language', 'term_translations')
        ";

        $taxonomies_result = $this->mysqli->query($taxonomies_query);
        $taxonomies = [];

        if ($taxonomies_result && $taxonomies_result->num_rows > 0) {
            while ($tax = $taxonomies_result->fetch_assoc()) {
                $taxonomies[] = "'" . $this->mysqli->real_escape_string($tax['taxonomy']) . "'";
            }
            $taxonomies_result->free();
        }

        if (!empty($taxonomies)) {
            $taxonomies_list = implode(', ', $taxonomies);
            $termmeta_query = "
                SELECT tm.*, t.term_id as old_term_id
                FROM {$this->table_prefix}termmeta tm
                INNER JOIN {$this->table_prefix}terms t ON tm.term_id = t.term_id
                INNER JOIN {$this->table_prefix}term_taxonomy tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ({$taxonomies_list})
                ORDER BY tm.meta_id ASC
            ";

            $termmeta_result = $this->mysqli->query($termmeta_query);

            if ($termmeta_result && $termmeta_result->num_rows > 0) {
                while ($meta = $termmeta_result->fetch_assoc()) {
                    $old_term_id = $meta['old_term_id'];
                    $meta_key = $meta['meta_key'];
                    $meta_value = $meta['meta_value'];

                    $escaped_meta_key = $this->mysqli->real_escape_string($meta_key);
                    $escaped_meta_value = $meta_value === null ? 'NULL' : "'" . $this->mysqli->real_escape_string($meta_value) . "'";
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}termmeta` (`term_id`, `meta_key`, `meta_value`) VALUES (@term_id_{$old_term_id}, '{$escaped_meta_key}', {$escaped_meta_value});";
                }
                $this->sql_dump[] = "";
                $termmeta_result->free();
            }
        }
    }

    /**
     * Assign terms to their languages
     */
    private function assign_terms_to_languages() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Assign Terms to Languages (extracted from translation groups)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        // Get taxonomies list
        $taxonomies_query = "
            SELECT DISTINCT tt.taxonomy
            FROM {$this->table_prefix}term_taxonomy tt
            INNER JOIN {$this->table_prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
            INNER JOIN {$this->table_prefix}posts p ON tr.object_id = p.ID
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND tt.taxonomy NOT IN ('language', 'post_translations', 'term_language', 'term_translations')
        ";

        $taxonomies_result = $this->mysqli->query($taxonomies_query);
        $taxonomies = [];

        if ($taxonomies_result && $taxonomies_result->num_rows > 0) {
            while ($tax = $taxonomies_result->fetch_assoc()) {
                $taxonomies[] = "'" . $this->mysqli->real_escape_string($tax['taxonomy']) . "'";
            }
            $taxonomies_result->free();
        }

        if (!empty($taxonomies)) {
            $taxonomies_list = implode(', ', $taxonomies);
            $term_languages_query = "
                SELECT DISTINCT
                    trans_tr.object_id as old_term_id,
                    trans_tt.description
                FROM {$this->table_prefix}term_taxonomy trans_tt
                INNER JOIN {$this->table_prefix}term_relationships trans_tr ON trans_tt.term_taxonomy_id = trans_tr.term_taxonomy_id
                INNER JOIN {$this->table_prefix}terms t ON trans_tr.object_id = t.term_id
                WHERE trans_tt.taxonomy = 'term_translations'
                AND trans_tt.description != ''
                AND EXISTS (
                    SELECT 1 FROM {$this->table_prefix}term_taxonomy tt
                    WHERE tt.term_id = t.term_id
                    AND tt.taxonomy IN ({$taxonomies_list})
                )
            ";

            WP_CLI::log("Term language query: " . $term_languages_query);

            $term_languages_result = $this->mysqli->query($term_languages_query);

            if (!$term_languages_result) {
                WP_CLI::error("Term language query failed: " . $this->mysqli->error);
            }

            $term_language_map = [];

            if ($term_languages_result && $term_languages_result->num_rows > 0) {
                WP_CLI::log("Processing " . $term_languages_result->num_rows . " term translation groups");
                while ($tl = $term_languages_result->fetch_assoc()) {
                    $old_term_id = $tl['old_term_id'];
                    $description = $tl['description'];

                    $translation_data = @unserialize($description);
                    if (is_array($translation_data)) {
                        WP_CLI::log("Term {$old_term_id} translation data: " . json_encode($translation_data));
                        foreach ($translation_data as $lang => $term_id) {
                            if ($term_id == $old_term_id) {
                                $term_language_map[$old_term_id] = $lang;
                                WP_CLI::log("  -> Mapped term {$old_term_id} to language '{$lang}'");
                                break;
                            }
                        }
                    } else {
                        WP_CLI::warning("Failed to unserialize translation data for term {$old_term_id}: {$description}");
                    }
                }
                $term_languages_result->free();
            } else {
                WP_CLI::warning("No term translation groups found in query");
            }

            if (!empty($term_language_map)) {
                WP_CLI::log("Found " . count($term_language_map) . " term language assignments from translation groups");
                $this->sql_dump[] = "-- Found " . count($term_language_map) . " term language assignments";
                foreach ($term_language_map as $old_term_id => $language_slug) {
                    $this->sql_dump[] = "-- Assign term {$old_term_id} to language {$language_slug}";
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @term_id_{$old_term_id}, @existing_term_lang_{$language_slug}, 0 WHERE @existing_term_lang_{$language_slug} IS NOT NULL AND @term_id_{$old_term_id} IS NOT NULL;";
                }
                $this->sql_dump[] = "";
            } else {
                WP_CLI::warning("No term language assignments found in translation groups. Terms may not be linked to Polylang languages.");
                $this->sql_dump[] = "-- WARNING: No term language assignments found in translation groups";
            }
        }
    }

    /**
     * Assign posts to their languages
     */
    private function assign_posts_to_languages() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Assign Posts to Languages (using existing language terms)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $post_languages_query = "
            SELECT DISTINCT p.ID as old_post_id, t.slug as language_slug
            FROM {$this->table_prefix}posts p
            INNER JOIN {$this->table_prefix}term_relationships tr ON p.ID = tr.object_id
            INNER JOIN {$this->table_prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$this->table_prefix}terms t ON tt.term_id = t.term_id
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND tt.taxonomy = 'language'
        ";

        $post_languages_result = $this->mysqli->query($post_languages_query);

        if ($post_languages_result && $post_languages_result->num_rows > 0) {
            while ($pl = $post_languages_result->fetch_assoc()) {
                $old_post_id = $pl['old_post_id'];
                $language_slug = $pl['language_slug'];

                $this->sql_dump[] = "-- Assign post {$old_post_id} to language {$language_slug}";
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @post_id_{$old_post_id}, @existing_lang_{$language_slug}, 0 WHERE @existing_lang_{$language_slug} IS NOT NULL;";
            }
            $this->sql_dump[] = "";
            $post_languages_result->free();
        }
    }

    /**
     * Export post-to-term relationships
     */
    private function export_post_term_relationships() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Post to Term Relationships";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $term_relationships_query = "
            SELECT tr.object_id, tr.term_taxonomy_id, tt.taxonomy, p.ID as old_post_id
            FROM {$this->table_prefix}term_relationships tr
            INNER JOIN {$this->table_prefix}posts p ON tr.object_id = p.ID
            INNER JOIN {$this->table_prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            WHERE p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND tt.taxonomy NOT IN ('language')
            ORDER BY tt.taxonomy, tr.object_id
        ";

        $term_relationships_result = $this->mysqli->query($term_relationships_query);

        if ($term_relationships_result && $term_relationships_result->num_rows > 0) {
            while ($tr = $term_relationships_result->fetch_assoc()) {
                $old_post_id = $tr['old_post_id'];
                $old_term_taxonomy_id = $tr['term_taxonomy_id'];
                $taxonomy = $tr['taxonomy'];

                if ($taxonomy === 'post_translations') {
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @post_id_{$old_post_id}, @trans_term_taxonomy_id_{$old_term_taxonomy_id}, 0 WHERE @trans_term_taxonomy_id_{$old_term_taxonomy_id} IS NOT NULL;";
                } else {
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @post_id_{$old_post_id}, @term_taxonomy_id_{$old_term_taxonomy_id}, 0 WHERE @term_taxonomy_id_{$old_term_taxonomy_id} IS NOT NULL;";
                }
            }
            $this->sql_dump[] = "";
            $term_relationships_result->free();
        }
    }

    /**
     * Export term translation relationships
     */
    private function export_term_translation_relationships() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Term Translation Relationships";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        $term_trans_relationships_query = "
            SELECT DISTINCT tr.object_id as old_term_id, tr.term_taxonomy_id as old_term_taxonomy_id
            FROM {$this->table_prefix}term_relationships tr
            INNER JOIN {$this->table_prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$this->table_prefix}terms term_obj ON tr.object_id = term_obj.term_id
            INNER JOIN {$this->table_prefix}term_taxonomy tt2 ON term_obj.term_id = tt2.term_id
            INNER JOIN {$this->table_prefix}term_relationships tr2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
            INNER JOIN {$this->table_prefix}posts p ON tr2.object_id = p.ID
            WHERE tt.taxonomy = 'term_translations'
            AND p.post_type = '{$this->mysqli->real_escape_string($this->post_type)}'
            AND tt2.taxonomy NOT IN ('language', 'post_translations', 'term_language', 'term_translations')
        ";

        $term_trans_relationships_result = $this->mysqli->query($term_trans_relationships_query);

        if ($term_trans_relationships_result && $term_trans_relationships_result->num_rows > 0) {
            while ($ttr = $term_trans_relationships_result->fetch_assoc()) {
                $old_term_id = $ttr['old_term_id'];
                $old_term_taxonomy_id = $ttr['old_term_taxonomy_id'];

                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @term_id_{$old_term_id}, @term_trans_term_taxonomy_id_{$old_term_taxonomy_id}, 0 WHERE @term_trans_term_taxonomy_id_{$old_term_taxonomy_id} IS NOT NULL AND @term_id_{$old_term_id} IS NOT NULL;";
            }
            $this->sql_dump[] = "";
            $term_trans_relationships_result->free();
        }
    }

    /**
     * Update post translation group descriptions
     */
    private function update_post_translation_descriptions() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Update Translation Group Descriptions (map language codes to new post IDs)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        if (!empty($this->translation_groups)) {
            foreach ($this->translation_groups as $old_term_id => $translation_data) {
                $this->sql_dump[] = "-- Post translation group {$old_term_id}: " . json_encode($translation_data);

                $description_parts = [];
                foreach ($translation_data as $lang => $old_post_id) {
                    $lang_len = strlen($lang);
                    $description_parts[] = "CONCAT('s:{$lang_len}:\"{$this->mysqli->real_escape_string($lang)}\";i:', @post_id_{$old_post_id}, ';')";
                }

                $count = count($translation_data);
                $description_sql = "CONCAT('a:{$count}:{', " . implode(", ", $description_parts) . ", '}')";

                $this->sql_dump[] = "UPDATE `{$this->table_prefix}term_taxonomy` SET `description` = {$description_sql} WHERE `term_id` = @trans_term_id_{$old_term_id} AND `taxonomy` = 'post_translations';";
                $this->sql_dump[] = "";
            }
        }
    }

    /**
     * Update term translation group descriptions
     */
    private function update_term_translation_descriptions() {
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "-- Update Term Translation Group Descriptions (map language codes to new term IDs)";
        $this->sql_dump[] = "--";
        $this->sql_dump[] = "";

        if (!empty($this->term_translation_groups)) {
            foreach ($this->term_translation_groups as $old_term_id => $translation_data) {
                $this->sql_dump[] = "-- Term translation group {$old_term_id}: " . json_encode($translation_data);

                $description_parts = [];
                foreach ($translation_data as $lang => $old_term_id_in_group) {
                    $lang_len = strlen($lang);
                    $description_parts[] = "CONCAT('s:{$lang_len}:\"{$this->mysqli->real_escape_string($lang)}\";i:', @term_id_{$old_term_id_in_group}, ';')";
                }

                $count = count($translation_data);
                $description_sql = "CONCAT('a:{$count}:{', " . implode(", ", $description_parts) . ", '}')";

                $this->sql_dump[] = "UPDATE `{$this->table_prefix}term_taxonomy` SET `description` = {$description_sql} WHERE `term_id` = @term_trans_term_id_{$old_term_id} AND `taxonomy` = 'term_translations';";
                $this->sql_dump[] = "";
            }
        }
    }

    /**
     * Add SQL dump footer
     */
    private function add_footer() {
        $this->sql_dump[] = "";
        $this->sql_dump[] = "SET FOREIGN_KEY_CHECKS = 1;";
    }

    /**
     * Output the SQL dump
     */
    private function output_sql_dump() {
        $final_sql_dump = implode("\n", $this->sql_dump);
        echo $final_sql_dump;

        // Close connection
        if ($this->mysqli) {
            $this->mysqli->close();
        }
    }
}
