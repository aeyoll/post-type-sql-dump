<?php
/**
 * SQL Generator for Post Type SQL Dump
 *
 * @package PostTypeSQLDump
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class PTSD_Generator
{
    /**
     * WordPress database object
     *
     * @var wpdb
     */
    private $wpdb;

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
     * Internal log messages collected during generation
     *
     * @var array
     */
    private $log_messages = [];

    /**
     * Generate the SQL dump for a given post type.
     *
     * @param string $post_type WordPress post type slug.
     * @return string The full SQL dump as a string.
     */
    public function generate(string $post_type): string
    {
        global $wpdb;

        $this->post_type = $post_type;
        $this->wpdb = $wpdb;
        $this->table_prefix = $wpdb->prefix;
        $this->sql_dump = [];
        $this->translation_groups = [];
        $this->term_translation_groups = [];
        $this->log_messages = [];

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

        return implode("\n", $this->sql_dump);
    }

    /**
     * Return log messages collected during generation.
     *
     * @return array
     */
    public function get_log_messages(): array
    {
        return $this->log_messages;
    }

    /**
     * Store a log message internally.
     *
     * @param string $message
     */
    private function log(string $message): void
    {
        $this->log_messages[] = $message;
    }

    /**
     * Store a warning message internally.
     *
     * @param string $message
     */
    private function warning(string $message): void
    {
        $this->log_messages[] = 'WARNING: ' . $message;
    }

    /**
     * Prepare IN clause placeholders for SQL query.
     *
     * @param array $values Array of values to include in IN clause.
     * @return string Prepared IN clause with placeholders.
     */
    private function prepare_in_clause(array $values): string
    {
        return implode(', ', array_fill(0, count($values), '%s'));
    }

    /**
     * Add SQL dump header.
     */
    private function add_header(): void
    {
        $database = DB_NAME;

        $this->sql_dump[] = '-- WordPress Posts Export with New IDs (Polylang Compatible)';
        $this->sql_dump[] = "-- Post Type: {$this->post_type}";
        $this->sql_dump[] = '-- Generated: ' . gmdate('Y-m-d H:i:s');
        $this->sql_dump[] = "-- Database: {$database}";
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';
        $this->sql_dump[] = 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";';
        $this->sql_dump[] = 'SET time_zone = "+00:00";';
        $this->sql_dump[] = 'SET NAMES utf8mb4;';
        $this->sql_dump[] = 'SET FOREIGN_KEY_CHECKS = 0;';
        $this->sql_dump[] = '';
    }

    /**
     * Add deletion queries for existing data.
     */
    private function add_deletion_queries(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = "-- Delete existing data for post_type: {$this->post_type}";
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';
        $this->sql_dump[] = '-- Store post IDs to delete in a temporary table';
        $this->sql_dump[] = 'CREATE TEMPORARY TABLE IF NOT EXISTS temp_posts_to_delete (post_id INT);';
        $this->sql_dump[] = "INSERT INTO temp_posts_to_delete (post_id) SELECT ID FROM `{$this->table_prefix}posts` WHERE post_type = '{$this->post_type}';";
        $this->sql_dump[] = '';

        $taxonomies = get_object_taxonomies($this->post_type);
        $taxonomies = array_filter($taxonomies, function ($tax) {
            return !in_array($tax, ['language', 'language_translations']);
        });

        if (!empty($taxonomies)) {
            $taxonomy_list = "'" . implode("','", array_map('esc_sql', $taxonomies)) . "'";
            $this->sql_dump[] = '-- Get all taxonomies linked to this post type';
            $this->sql_dump[] = '-- Store all terms from taxonomies linked to post type: ' . implode(', ', $taxonomies);
            $this->sql_dump[] = 'CREATE TEMPORARY TABLE IF NOT EXISTS temp_taxonomies_to_delete (';
            $this->sql_dump[] = '  term_taxonomy_id INT,';
            $this->sql_dump[] = '  term_id INT,';
            $this->sql_dump[] = '  taxonomy VARCHAR(32)';
            $this->sql_dump[] = ');';
            $this->sql_dump[] = '';
            $this->sql_dump[] = '-- Collect ALL term_taxonomy entries for the taxonomies linked to this post type';
            $this->sql_dump[] = 'INSERT INTO temp_taxonomies_to_delete (term_taxonomy_id, term_id, taxonomy)';
            $this->sql_dump[] = 'SELECT tt.term_taxonomy_id, tt.term_id, tt.taxonomy';
            $this->sql_dump[] = "FROM `{$this->table_prefix}term_taxonomy` tt";
            $this->sql_dump[] = "WHERE tt.taxonomy IN ({$taxonomy_list});";
            $this->sql_dump[] = '';
            $this->sql_dump[] = '-- Delete term relationships for these posts';
            $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}term_relationships` WHERE object_id IN (SELECT post_id FROM temp_posts_to_delete);";
            $this->sql_dump[] = '';
            $this->sql_dump[] = '-- Delete termmeta for terms that will be deleted';
            $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}termmeta` WHERE term_id IN (SELECT term_id FROM temp_taxonomies_to_delete);";
            $this->sql_dump[] = '';
            $this->sql_dump[] = '-- Delete term_taxonomy entries for these taxonomies';
            $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}term_taxonomy` WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM temp_taxonomies_to_delete);";
            $this->sql_dump[] = '';
            $this->sql_dump[] = '-- Delete the terms themselves';
            $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}terms` WHERE term_id IN (SELECT term_id FROM temp_taxonomies_to_delete);";
            $this->sql_dump[] = '';
            $this->sql_dump[] = '-- Drop temporary table for taxonomies';
            $this->sql_dump[] = 'DROP TEMPORARY TABLE IF EXISTS temp_taxonomies_to_delete;';
            $this->sql_dump[] = '';
        }

        $this->sql_dump[] = '-- Delete postmeta for these posts';
        $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}postmeta` WHERE post_id IN (SELECT post_id FROM temp_posts_to_delete);";
        $this->sql_dump[] = '';
        $this->sql_dump[] = '-- Delete the posts themselves';
        $this->sql_dump[] = "DELETE FROM `{$this->table_prefix}posts` WHERE post_type = '{$this->post_type}';";
        $this->sql_dump[] = '';
        $this->sql_dump[] = '-- Clean up any remaining orphaned terms with no taxonomy relationships';
        $this->sql_dump[] = "DELETE t FROM `{$this->table_prefix}terms` t";
        $this->sql_dump[] = "LEFT JOIN `{$this->table_prefix}term_taxonomy` tt ON t.term_id = tt.term_id";
        $this->sql_dump[] = 'WHERE tt.term_id IS NULL;';
        $this->sql_dump[] = '';
        $this->sql_dump[] = '-- Drop temporary table for posts';
        $this->sql_dump[] = 'DROP TEMPORARY TABLE IF EXISTS temp_posts_to_delete;';
        $this->sql_dump[] = '';
    }

    /**
     * Export language mapping for Polylang.
     */
    private function export_language_mapping(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Polylang Language Mapping (dynamically map to target database languages)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $language_slugs = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT t.slug
                FROM {$this->wpdb->prefix}term_taxonomy tt
                INNER JOIN {$this->wpdb->prefix}terms t ON tt.term_id = t.term_id
                WHERE tt.taxonomy = %s
                ORDER BY t.slug",
                'language'
            )
        );

        if (!empty($language_slugs)) {
            $this->sql_dump[] = '-- Dynamically map language slugs to target database term_taxonomy_ids';

            foreach ($language_slugs as $slug) {
                $slug_escaped = esc_sql($slug);

                $this->sql_dump[] = "SET @existing_lang_{$slug_escaped} = (";
                $this->sql_dump[] = '  SELECT tt.term_taxonomy_id';
                $this->sql_dump[] = "  FROM `{$this->table_prefix}term_taxonomy` tt";
                $this->sql_dump[] = "  INNER JOIN `{$this->table_prefix}terms` t ON tt.term_id = t.term_id";
                $this->sql_dump[] = "  WHERE tt.taxonomy = 'language' AND t.slug = '{$slug_escaped}'";
                $this->sql_dump[] = '  LIMIT 1';
                $this->sql_dump[] = ');';

                $this->sql_dump[] = "SET @existing_term_lang_{$slug_escaped} = (";
                $this->sql_dump[] = '  SELECT tt.term_taxonomy_id';
                $this->sql_dump[] = "  FROM `{$this->table_prefix}term_taxonomy` tt";
                $this->sql_dump[] = "  INNER JOIN `{$this->table_prefix}terms` t ON tt.term_id = t.term_id";
                $this->sql_dump[] = "  WHERE tt.taxonomy = 'term_language' AND t.slug = '{$slug_escaped}'";
                $this->sql_dump[] = '  LIMIT 1';
                $this->sql_dump[] = ');';
                $this->sql_dump[] = "SET @existing_term_lang_{$slug_escaped} = IFNULL(@existing_term_lang_{$slug_escaped}, (";
                $this->sql_dump[] = '  SELECT tt.term_taxonomy_id';
                $this->sql_dump[] = "  FROM `{$this->table_prefix}term_taxonomy` tt";
                $this->sql_dump[] = "  INNER JOIN `{$this->table_prefix}terms` t ON tt.term_id = t.term_id";
                $this->sql_dump[] = "  WHERE tt.taxonomy = 'term_language' AND t.slug = 'pll_{$slug_escaped}'";
                $this->sql_dump[] = '  LIMIT 1';
                $this->sql_dump[] = '));';
                $this->sql_dump[] = "SET @existing_term_lang_{$slug_escaped} = IFNULL(@existing_term_lang_{$slug_escaped}, @existing_lang_{$slug_escaped});";
            }

            $this->sql_dump[] = '';
        }
    }

    /**
     * Export posts with new IDs.
     */
    private function export_posts(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Posts (will get new IDs, keeping original author IDs)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $posts = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT p.*, t.slug as post_language
                FROM {$this->wpdb->prefix}posts p
                LEFT JOIN {$this->wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
                LEFT JOIN {$this->wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
                LEFT JOIN {$this->wpdb->prefix}terms t ON tt.term_id = t.term_id
                WHERE p.post_type = %s
                ORDER BY p.ID ASC",
                'language',
                $this->post_type
            ),
            ARRAY_A
        );

        if ($posts) {
            foreach ($posts as $post) {
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
                        $escaped_value = esc_sql($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = '`' . implode('`, `', $columns) . '`';
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}posts` ({$columns_list}) VALUES (" . implode(', ', $values) . ');';
                $this->sql_dump[] = "SET @post_id_{$old_post_id} = LAST_INSERT_ID();";

                if ($post_language) {
                    $post_language_escaped = esc_sql($post_language);
                    $this->sql_dump[] = "SET @post_{$old_post_id}_lang = '{$post_language_escaped}';";
                }

                $this->sql_dump[] = '';
                $this->log("Mapped post ID {$old_post_id} to new ID (language: " . ($post_language ?: 'none') . ')');
            }
        }
    }

    /**
     * Export featured images (attachment posts).
     */
    private function export_featured_images(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Featured Images (attachment posts)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $attachments = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT a.*
                FROM {$this->wpdb->prefix}posts a
                INNER JOIN {$this->wpdb->prefix}postmeta pm ON a.ID = pm.meta_value
                INNER JOIN {$this->wpdb->prefix}posts p ON pm.post_id = p.ID
                WHERE p.post_type = %s
                AND pm.meta_key = %s
                AND a.post_type = %s
                ORDER BY a.ID ASC",
                $this->post_type,
                '_thumbnail_id',
                'attachment'
            ),
            ARRAY_A
        );

        if ($attachments) {
            foreach ($attachments as $attachment) {
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
                        $escaped_value = esc_sql($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = '`' . implode('`, `', $columns) . '`';
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}posts` ({$columns_list}) VALUES (" . implode(', ', $values) . ');';
                $this->sql_dump[] = "SET @attachment_id_{$old_attachment_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = '';
                $this->log("Mapped attachment ID {$old_attachment_id} to new ID");
            }
        }
    }

    /**
     * Export attachment metadata.
     */
    private function export_attachment_metadata(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Attachment Meta (for featured images)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $attachment_meta = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT pm.*, a.ID as old_attachment_id
                FROM {$this->wpdb->prefix}postmeta pm
                INNER JOIN {$this->wpdb->prefix}posts a ON pm.post_id = a.ID
                INNER JOIN {$this->wpdb->prefix}postmeta pm2 ON a.ID = pm2.meta_value
                INNER JOIN {$this->wpdb->prefix}posts p ON pm2.post_id = p.ID
                WHERE p.post_type = %s
                AND pm2.meta_key = %s
                AND a.post_type = %s
                ORDER BY pm.meta_id ASC",
                $this->post_type,
                '_thumbnail_id',
                'attachment'
            ),
            ARRAY_A
        );

        if ($attachment_meta) {
            foreach ($attachment_meta as $meta) {
                $old_attachment_id = $meta['old_attachment_id'];
                $meta_key = esc_sql($meta['meta_key']);
                $meta_value = $meta['meta_value'] === null ? 'NULL' : "'" . esc_sql($meta['meta_value']) . "'";

                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES (@attachment_id_{$old_attachment_id}, '{$meta_key}', {$meta_value});";
            }

            $this->sql_dump[] = '';
        }
    }

    /**
     * Export post metadata with mapped post IDs.
     */
    private function export_post_metadata(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Post Meta (with mapped post IDs - includes Polylang data)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $postmeta = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT pm.*, p.ID as old_post_id
                FROM {$this->wpdb->prefix}postmeta pm
                INNER JOIN {$this->wpdb->prefix}posts p ON pm.post_id = p.ID
                WHERE p.post_type = %s
                ORDER BY pm.meta_id ASC",
                $this->post_type
            ),
            ARRAY_A
        );

        if ($postmeta) {
            foreach ($postmeta as $meta) {
                $old_post_id = $meta['old_post_id'];
                $meta_key = $meta['meta_key'];
                $meta_value = $meta['meta_value'];

                if ($meta_key === '_pll_translation_of' && is_numeric($meta_value) && $meta_value > 0) {
                    $meta_key_escaped = esc_sql($meta_key);
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES (@post_id_{$old_post_id}, '{$meta_key_escaped}', @post_id_{$meta_value});";
                } elseif ($meta_key === '_thumbnail_id' && is_numeric($meta_value) && $meta_value > 0) {
                    $meta_key_escaped = esc_sql($meta_key);
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES (@post_id_{$old_post_id}, '{$meta_key_escaped}', @attachment_id_{$meta_value});";
                } else {
                    $meta_key_escaped = esc_sql($meta_key);
                    $meta_value_escaped = $meta_value === null ? 'NULL' : "'" . esc_sql($meta_value) . "'";
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}postmeta` (`post_id`, `meta_key`, `meta_value`) VALUES (@post_id_{$old_post_id}, '{$meta_key_escaped}', {$meta_value_escaped});";
                }
            }

            $this->sql_dump[] = '';
        }
    }

    /**
     * Export Polylang translation group terms.
     */
    private function export_translation_group_terms(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Polylang Translation Group Terms (will get new IDs)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $translation_terms = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT t.*
                FROM {$this->wpdb->prefix}terms t
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
                INNER JOIN {$this->wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}posts p ON tr.object_id = p.ID
                WHERE tt.taxonomy = %s
                AND p.post_type = %s",
                'post_translations',
                $this->post_type
            ),
            ARRAY_A
        );

        if ($translation_terms) {
            foreach ($translation_terms as $term) {
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
                        $escaped_value = esc_sql($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = '`' . implode('`, `', $columns) . '`';
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}terms` ({$columns_list}) VALUES (" . implode(', ', $values) . ');';
                $this->sql_dump[] = "SET @trans_term_id_{$old_term_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = '';
            }
        }
    }

    /**
     * Export Polylang translation group term_taxonomy.
     */
    private function export_translation_group_taxonomy(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Polylang Translation Group Term Taxonomy';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $translation_taxonomy = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT tt.*
                FROM {$this->wpdb->prefix}term_taxonomy tt
                INNER JOIN {$this->wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}posts p ON tr.object_id = p.ID
                WHERE tt.taxonomy = %s
                AND p.post_type = %s",
                'post_translations',
                $this->post_type
            ),
            ARRAY_A
        );

        if ($translation_taxonomy) {
            foreach ($translation_taxonomy as $tt) {
                $old_term_taxonomy_id = $tt['term_taxonomy_id'];
                $old_term_id = $tt['term_id'];
                $tt_description = $tt['description'];

                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Legacy serialized data
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
                        $escaped_value = esc_sql($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = '`' . implode('`, `', $columns) . '`';
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_taxonomy` ({$columns_list}) VALUES (" . implode(', ', $values) . ');';
                $this->sql_dump[] = "SET @trans_term_taxonomy_id_{$old_term_taxonomy_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = '';
            }
        }
    }

    /**
     * Export regular terms with their languages.
     */
    private function export_regular_terms(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Regular Terms (will get new IDs)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $taxonomies = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT tt.taxonomy
                FROM {$this->wpdb->prefix}term_taxonomy tt
                INNER JOIN {$this->wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}posts p ON tr.object_id = p.ID
                WHERE p.post_type = %s
                AND tt.taxonomy NOT IN (%s, %s, %s, %s)",
                $this->post_type,
                'language',
                'post_translations',
                'term_language',
                'term_translations'
            )
        );

        if (!empty($taxonomies)) {
            $this->log('Found taxonomies: ' . implode(', ', $taxonomies));

            $placeholders = $this->prepare_in_clause($taxonomies);
            $query = "SELECT DISTINCT t.*, lang_t.slug as term_language
                FROM {$this->wpdb->prefix}terms t
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
                LEFT JOIN {$this->wpdb->prefix}term_relationships lang_tr ON t.term_id = lang_tr.object_id
                LEFT JOIN {$this->wpdb->prefix}term_taxonomy lang_tt ON lang_tr.term_taxonomy_id = lang_tt.term_taxonomy_id AND lang_tt.taxonomy = %s
                LEFT JOIN {$this->wpdb->prefix}terms lang_t ON lang_tt.term_id = lang_t.term_id
                WHERE tt.taxonomy IN ({$placeholders})
                ORDER BY t.term_id ASC";

            $prepared_args = array_merge(['term_language'], $taxonomies);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic IN clause is properly prepared
            $terms = $this->wpdb->get_results(
                $this->wpdb->prepare($query, $prepared_args),
                ARRAY_A
            );

            if ($terms) {
                foreach ($terms as $term) {
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
                            $escaped_value = esc_sql($value);
                            $values[] = "'{$escaped_value}'";
                        }
                    }

                    $columns_list = '`' . implode('`, `', $columns) . '`';
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}terms` ({$columns_list}) VALUES (" . implode(', ', $values) . ');';
                    $this->sql_dump[] = "SET @term_id_{$old_term_id} = LAST_INSERT_ID();";

                    if ($term_language) {
                        $term_language_escaped = esc_sql($term_language);
                        $this->sql_dump[] = "SET @term_{$old_term_id}_lang = '{$term_language_escaped}';";
                    }

                    $this->sql_dump[] = '';
                }
            }
        }
    }

    /**
     * Export term translation group terms.
     */
    private function export_term_translation_group_terms(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Term Translation Group Terms (will get new IDs)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $term_translation_terms = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT t.*
                FROM {$this->wpdb->prefix}terms t
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
                INNER JOIN {$this->wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}terms term_obj ON tr.object_id = term_obj.term_id
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt2 ON term_obj.term_id = tt2.term_id
                INNER JOIN {$this->wpdb->prefix}term_relationships tr2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}posts p ON tr2.object_id = p.ID
                WHERE tt.taxonomy = %s
                AND p.post_type = %s
                AND tt2.taxonomy NOT IN (%s, %s, %s, %s)",
                'term_translations',
                $this->post_type,
                'language',
                'post_translations',
                'term_language',
                'term_translations'
            ),
            ARRAY_A
        );

        if ($term_translation_terms) {
            foreach ($term_translation_terms as $term) {
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
                        $escaped_value = esc_sql($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = '`' . implode('`, `', $columns) . '`';
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}terms` ({$columns_list}) VALUES (" . implode(', ', $values) . ');';
                $this->sql_dump[] = "SET @term_trans_term_id_{$old_term_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = '';
            }
        }
    }

    /**
     * Export term translation group term_taxonomy.
     */
    private function export_term_translation_group_taxonomy(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Term Translation Group Term Taxonomy';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $term_translation_taxonomy = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT tt.*
                FROM {$this->wpdb->prefix}term_taxonomy tt
                INNER JOIN {$this->wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}terms term_obj ON tr.object_id = term_obj.term_id
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt2 ON term_obj.term_id = tt2.term_id
                INNER JOIN {$this->wpdb->prefix}term_relationships tr2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}posts p ON tr2.object_id = p.ID
                WHERE tt.taxonomy = %s
                AND p.post_type = %s
                AND tt2.taxonomy NOT IN (%s, %s, %s, %s)",
                'term_translations',
                $this->post_type,
                'language',
                'post_translations',
                'term_language',
                'term_translations'
            ),
            ARRAY_A
        );

        if ($term_translation_taxonomy) {
            foreach ($term_translation_taxonomy as $tt) {
                $old_term_taxonomy_id = $tt['term_taxonomy_id'];
                $old_term_id = $tt['term_id'];
                $tt_description = $tt['description'];

                // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Legacy serialized data
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
                        $escaped_value = esc_sql($value);
                        $values[] = "'{$escaped_value}'";
                    }
                }

                $columns_list = '`' . implode('`, `', $columns) . '`';
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_taxonomy` ({$columns_list}) VALUES (" . implode(', ', $values) . ');';
                $this->sql_dump[] = "SET @term_trans_term_taxonomy_id_{$old_term_taxonomy_id} = LAST_INSERT_ID();";
                $this->sql_dump[] = '';
            }
        }
    }

    /**
     * Export regular term_taxonomy with mapped term IDs.
     */
    private function export_regular_term_taxonomy(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Regular Term Taxonomy (with mapped term IDs)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $taxonomies = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT tt.taxonomy
                FROM {$this->wpdb->prefix}term_taxonomy tt
                INNER JOIN {$this->wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}posts p ON tr.object_id = p.ID
                WHERE p.post_type = %s
                AND tt.taxonomy NOT IN (%s, %s, %s, %s)",
                $this->post_type,
                'language',
                'post_translations',
                'term_language',
                'term_translations'
            )
        );

        if (!empty($taxonomies)) {
            $placeholders = $this->prepare_in_clause($taxonomies);
            $query = "SELECT DISTINCT tt.*
                FROM {$this->wpdb->prefix}term_taxonomy tt
                WHERE tt.taxonomy IN ({$placeholders})
                ORDER BY tt.term_taxonomy_id ASC";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic IN clause is properly prepared
            $term_taxonomy = $this->wpdb->get_results(
                $this->wpdb->prepare($query, $taxonomies),
                ARRAY_A
            );

            if ($term_taxonomy) {
                $this->log('Exporting ' . count($term_taxonomy) . ' term_taxonomy entries');

                foreach ($term_taxonomy as $tt) {
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
                            $escaped_value = esc_sql($value);
                            $values[] = "'{$escaped_value}'";
                        }
                    }

                    $columns_list = '`' . implode('`, `', $columns) . '`';
                    $this->sql_dump[] = "-- term_taxonomy_id: {$old_term_taxonomy_id}, term_id: {$old_term_id}, taxonomy: {$taxonomy}";
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_taxonomy` ({$columns_list}) SELECT " . implode(', ', $values) . " WHERE @term_id_{$old_term_id} IS NOT NULL;";
                    $this->sql_dump[] = "SET @term_taxonomy_id_{$old_term_taxonomy_id} = IF(@term_id_{$old_term_id} IS NOT NULL, LAST_INSERT_ID(), NULL);";
                    $this->sql_dump[] = '';
                }
            }
        }
    }

    /**
     * Export term metadata.
     */
    private function export_term_metadata(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Term Meta (with mapped term IDs)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $taxonomies = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT tt.taxonomy
                FROM {$this->wpdb->prefix}term_taxonomy tt
                INNER JOIN {$this->wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}posts p ON tr.object_id = p.ID
                WHERE p.post_type = %s
                AND tt.taxonomy NOT IN (%s, %s, %s, %s)",
                $this->post_type,
                'language',
                'post_translations',
                'term_language',
                'term_translations'
            )
        );

        if (!empty($taxonomies)) {
            $placeholders = $this->prepare_in_clause($taxonomies);
            $query = "SELECT tm.*, t.term_id as old_term_id
                FROM {$this->wpdb->prefix}termmeta tm
                INNER JOIN {$this->wpdb->prefix}terms t ON tm.term_id = t.term_id
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt ON t.term_id = tt.term_id
                WHERE tt.taxonomy IN ({$placeholders})
                ORDER BY tm.meta_id ASC";

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic IN clause is properly prepared
            $termmeta = $this->wpdb->get_results(
                $this->wpdb->prepare($query, $taxonomies),
                ARRAY_A
            );

            if ($termmeta) {
                foreach ($termmeta as $meta) {
                    $old_term_id = $meta['old_term_id'];
                    $meta_key = esc_sql($meta['meta_key']);
                    $meta_value = $meta['meta_value'] === null ? 'NULL' : "'" . esc_sql($meta['meta_value']) . "'";

                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}termmeta` (`term_id`, `meta_key`, `meta_value`) VALUES (@term_id_{$old_term_id}, '{$meta_key}', {$meta_value});";
                }

                $this->sql_dump[] = '';
            }
        }
    }

    /**
     * Assign terms to their languages.
     */
    private function assign_terms_to_languages(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Assign Terms to Languages (extracted from translation groups)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $taxonomies = $this->wpdb->get_col(
            $this->wpdb->prepare(
                "SELECT DISTINCT tt.taxonomy
                FROM {$this->wpdb->prefix}term_taxonomy tt
                INNER JOIN {$this->wpdb->prefix}term_relationships tr ON tt.term_taxonomy_id = tr.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}posts p ON tr.object_id = p.ID
                WHERE p.post_type = %s
                AND tt.taxonomy NOT IN (%s, %s, %s, %s)",
                $this->post_type,
                'language',
                'post_translations',
                'term_language',
                'term_translations'
            )
        );

        if (!empty($taxonomies)) {
            $placeholders = $this->prepare_in_clause($taxonomies);
            $query = "SELECT DISTINCT
                    trans_tr.object_id as old_term_id,
                    trans_tt.description
                FROM {$this->wpdb->prefix}term_taxonomy trans_tt
                INNER JOIN {$this->wpdb->prefix}term_relationships trans_tr ON trans_tt.term_taxonomy_id = trans_tr.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}terms t ON trans_tr.object_id = t.term_id
                WHERE trans_tt.taxonomy = %s
                AND trans_tt.description != ''
                AND EXISTS (
                    SELECT 1 FROM {$this->wpdb->prefix}term_taxonomy tt
                    WHERE tt.term_id = t.term_id
                    AND tt.taxonomy IN ({$placeholders})
                )";

            $prepared_args = array_merge(['term_translations'], $taxonomies);

            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Dynamic IN clause is properly prepared
            $term_languages = $this->wpdb->get_results(
                $this->wpdb->prepare($query, $prepared_args),
                ARRAY_A
            );

            $term_language_map = [];

            if ($term_languages) {
                $this->log('Processing ' . count($term_languages) . ' term translation groups');

                foreach ($term_languages as $tl) {
                    $old_term_id = $tl['old_term_id'];
                    $description = $tl['description'];

                    // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Legacy serialized data
                    $translation_data = @unserialize($description);

                    if (is_array($translation_data)) {
                        foreach ($translation_data as $lang => $term_id) {
                            if ($term_id == $old_term_id) {
                                $term_language_map[$old_term_id] = $lang;
                                break;
                            }
                        }
                    } else {
                        $this->warning("Failed to unserialize translation data for term {$old_term_id}: {$description}");
                    }
                }
            } else {
                $this->warning('No term translation groups found in query');
            }

            if (!empty($term_language_map)) {
                $this->log('Found ' . count($term_language_map) . ' term language assignments from translation groups');
                $this->sql_dump[] = '-- Found ' . count($term_language_map) . ' term language assignments';

                foreach ($term_language_map as $old_term_id => $language_slug) {
                    $language_slug_escaped = esc_sql($language_slug);
                    $this->sql_dump[] = "-- Assign term {$old_term_id} to language {$language_slug_escaped}";
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @term_id_{$old_term_id}, @existing_term_lang_{$language_slug_escaped}, 0 WHERE @existing_term_lang_{$language_slug_escaped} IS NOT NULL AND @term_id_{$old_term_id} IS NOT NULL;";
                }

                $this->sql_dump[] = '';
            } else {
                $this->warning('No term language assignments found in translation groups. Terms may not be linked to Polylang languages.');
                $this->sql_dump[] = '-- WARNING: No term language assignments found in translation groups';
            }
        }
    }

    /**
     * Assign posts to their languages.
     */
    private function assign_posts_to_languages(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Assign Posts to Languages (using existing language terms)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $post_languages = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT p.ID as old_post_id, t.slug as language_slug
                FROM {$this->wpdb->prefix}posts p
                INNER JOIN {$this->wpdb->prefix}term_relationships tr ON p.ID = tr.object_id
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}terms t ON tt.term_id = t.term_id
                WHERE p.post_type = %s
                AND tt.taxonomy = %s",
                $this->post_type,
                'language'
            ),
            ARRAY_A
        );

        if ($post_languages) {
            foreach ($post_languages as $pl) {
                $old_post_id = $pl['old_post_id'];
                $language_slug = esc_sql($pl['language_slug']);

                $this->sql_dump[] = "-- Assign post {$old_post_id} to language {$language_slug}";
                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @post_id_{$old_post_id}, @existing_lang_{$language_slug}, 0 WHERE @existing_lang_{$language_slug} IS NOT NULL;";
            }

            $this->sql_dump[] = '';
        }
    }

    /**
     * Export post-to-term relationships.
     */
    private function export_post_term_relationships(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Post to Term Relationships';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $term_relationships = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT tr.object_id, tr.term_taxonomy_id, tt.taxonomy, p.ID as old_post_id
                FROM {$this->wpdb->prefix}term_relationships tr
                INNER JOIN {$this->wpdb->prefix}posts p ON tr.object_id = p.ID
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE p.post_type = %s
                AND tt.taxonomy NOT IN (%s)
                ORDER BY tt.taxonomy, tr.object_id",
                $this->post_type,
                'language'
            ),
            ARRAY_A
        );

        if ($term_relationships) {
            foreach ($term_relationships as $tr) {
                $old_post_id = $tr['old_post_id'];
                $old_term_taxonomy_id = $tr['term_taxonomy_id'];
                $taxonomy = $tr['taxonomy'];

                if ($taxonomy === 'post_translations') {
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @post_id_{$old_post_id}, @trans_term_taxonomy_id_{$old_term_taxonomy_id}, 0 WHERE @trans_term_taxonomy_id_{$old_term_taxonomy_id} IS NOT NULL;";
                } else {
                    $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @post_id_{$old_post_id}, @term_taxonomy_id_{$old_term_taxonomy_id}, 0 WHERE @term_taxonomy_id_{$old_term_taxonomy_id} IS NOT NULL;";
                }
            }

            $this->sql_dump[] = '';
        }
    }

    /**
     * Export term translation relationships.
     */
    private function export_term_translation_relationships(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Term Translation Relationships';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        $term_trans_relationships = $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT DISTINCT tr.object_id as old_term_id, tr.term_taxonomy_id as old_term_taxonomy_id
                FROM {$this->wpdb->prefix}term_relationships tr
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}terms term_obj ON tr.object_id = term_obj.term_id
                INNER JOIN {$this->wpdb->prefix}term_taxonomy tt2 ON term_obj.term_id = tt2.term_id
                INNER JOIN {$this->wpdb->prefix}term_relationships tr2 ON tt2.term_taxonomy_id = tr2.term_taxonomy_id
                INNER JOIN {$this->wpdb->prefix}posts p ON tr2.object_id = p.ID
                WHERE tt.taxonomy = %s
                AND p.post_type = %s
                AND tt2.taxonomy NOT IN (%s, %s, %s, %s)",
                'term_translations',
                $this->post_type,
                'language',
                'post_translations',
                'term_language',
                'term_translations'
            ),
            ARRAY_A
        );

        if ($term_trans_relationships) {
            foreach ($term_trans_relationships as $ttr) {
                $old_term_id = $ttr['old_term_id'];
                $old_term_taxonomy_id = $ttr['old_term_taxonomy_id'];

                $this->sql_dump[] = "INSERT INTO `{$this->table_prefix}term_relationships` (`object_id`, `term_taxonomy_id`, `term_order`) SELECT @term_id_{$old_term_id}, @term_trans_term_taxonomy_id_{$old_term_taxonomy_id}, 0 WHERE @term_trans_term_taxonomy_id_{$old_term_taxonomy_id} IS NOT NULL AND @term_id_{$old_term_id} IS NOT NULL;";
            }

            $this->sql_dump[] = '';
        }
    }

    /**
     * Update post translation group descriptions.
     */
    private function update_post_translation_descriptions(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Update Translation Group Descriptions (map language codes to new post IDs)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        if (!empty($this->translation_groups)) {
            foreach ($this->translation_groups as $old_term_id => $translation_data) {
                $this->sql_dump[] = '-- Post translation group ' . $old_term_id . ': ' . json_encode($translation_data);

                $description_parts = [];

                foreach ($translation_data as $lang => $old_post_id) {
                    $lang_escaped = esc_sql($lang);
                    $lang_len = strlen($lang);
                    $description_parts[] = "CONCAT('s:{$lang_len}:\"{$lang_escaped}\";i:', @post_id_{$old_post_id}, ';')";
                }

                $count = count($translation_data);
                $description_sql = "CONCAT('a:{$count}:{', " . implode(', ', $description_parts) . ", '}')";

                $this->sql_dump[] = "UPDATE `{$this->table_prefix}term_taxonomy` SET `description` = {$description_sql} WHERE `term_id` = @trans_term_id_{$old_term_id} AND `taxonomy` = 'post_translations';";
                $this->sql_dump[] = '';
            }
        }
    }

    /**
     * Update term translation group descriptions.
     */
    private function update_term_translation_descriptions(): void
    {
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '-- Update Term Translation Group Descriptions (map language codes to new term IDs)';
        $this->sql_dump[] = '--';
        $this->sql_dump[] = '';

        if (!empty($this->term_translation_groups)) {
            foreach ($this->term_translation_groups as $old_term_id => $translation_data) {
                $this->sql_dump[] = '-- Term translation group ' . $old_term_id . ': ' . json_encode($translation_data);

                $description_parts = [];

                foreach ($translation_data as $lang => $old_term_id_in_group) {
                    $lang_escaped = esc_sql($lang);
                    $lang_len = strlen($lang);
                    $description_parts[] = "CONCAT('s:{$lang_len}:\"{$lang_escaped}\";i:', @term_id_{$old_term_id_in_group}, ';')";
                }

                $count = count($translation_data);
                $description_sql = "CONCAT('a:{$count}:{', " . implode(', ', $description_parts) . ", '}')";

                $this->sql_dump[] = "UPDATE `{$this->table_prefix}term_taxonomy` SET `description` = {$description_sql} WHERE `term_id` = @term_trans_term_id_{$old_term_id} AND `taxonomy` = 'term_translations';";
                $this->sql_dump[] = '';
            }
        }
    }

    /**
     * Add SQL dump footer.
     */
    private function add_footer(): void
    {
        $this->sql_dump[] = '';
        $this->sql_dump[] = 'SET FOREIGN_KEY_CHECKS = 1;';
    }
}
