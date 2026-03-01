=== Post Type SQL Dump ===

Contributors: aeyoll
Tags: export, import, sql, polylang, wp-cli
Requires at least: 5.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Export WordPress posts and their associated data to SQL format via the admin interface (Tools > SQL Dump) or WP-CLI command.

== Description ==

Post Type SQL Dump allows you to export posts of any type along with all associated data for migration between WordPress installations. You can use it directly from the WordPress admin area or via WP-CLI.

**What Gets Exported:**

* Posts with newly generated IDs
* Featured images and attachments
* All post metadata and custom fields
* Categories, tags, and custom taxonomies
* Term metadata
* Polylang translations and language assignments
* All relationships between posts and terms

**Key Benefits:**

* No ID conflicts - generates new IDs on import
* Preserves all relationships and hierarchies
* Full Polylang multilingual support
* Clean imports with automatic cleanup of existing data

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/post-type-sql-dump/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Generate SQL dumps from the admin area (Tools > SQL Dump) or via WP-CLI

== Usage ==

=== Admin Interface ===

1. Go to **Tools > SQL Dump** in the WordPress admin
2. Select the post type you want to export
3. Click **Generate SQL Dump**
4. Review the generated SQL or click **Download as .sql file**

Import the downloaded file on another WordPress installation:

`wp db import export.sql`

=== WP-CLI ===

Basic export command (requires WP-CLI):

`wp ptsd dump --post_type=page --quiet > export.sql`

Import on another WordPress installation:

`wp db import export.sql`

**Available WP-CLI Options:**

* `--post_type` - Specify which post type to export (default: post)
* `--quiet` - Suppress progress output

== Frequently Asked Questions ==

= Does this work without WP-CLI? =

Yes. The plugin provides an admin interface under **Tools > SQL Dump** that works without WP-CLI. WP-CLI is only required if you want to use the command-line export option.

= Will it work with Polylang? =

Yes, the plugin has full support for Polylang multilingual sites and preserves all translation relationships.

= What happens to existing data on import? =

The generated SQL includes deletion queries that remove existing data of the same post type before importing. Always backup your database first.

= Can I export custom post types? =

Yes, you can export any registered post type using the `--post_type` parameter.

== Changelog ==

= 1.0.0 =
* Initial release
