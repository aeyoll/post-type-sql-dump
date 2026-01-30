# Post Type SQL Dump

A WordPress plugin that exports posts and their associated data (including Polylang translations) to SQL format via WP-CLI command.

## Description

- **Contributors:** aeyoll
- **Tags:** post-type-sql-dump, export, import, sql, polylang
- **Requires at least:** WordPress 5.0
- **Tested up to:** WordPress 6.9
- **Requires PHP:** 7.4+
- **Stable tag:** 1.0.0
- **License:** GPLv2 or later
- **License URI:** https://www.gnu.org/licenses/gpl-2.0.html

This plugin provides a WP-CLI command to export WordPress posts of any post type along with all their associated data:

- Posts with new IDs
- Featured images (attachments)
- Post metadata (including Polylang translation data)
- Terms and taxonomies (categories, tags, custom taxonomies)
- Term metadata
- Polylang translation groups and language assignments
- Term relationships

The exported SQL is designed to be imported into another WordPress installation while:
- Generating new IDs for all entities
- Preserving relationships between posts, terms, and translations
- Being compatible with Polylang multilingual plugin

## Features

- Export any post type
- Polylang compatible (preserves translations and language assignments)
- Exports featured images and their metadata
- Handles hierarchical posts (parent-child relationships)
- Handles hierarchical terms
- Exports all post metadata
- Exports all term metadata
- Clean deletion queries to remove existing data before import
- New IDs generated on import (no ID conflicts)

## Requirements

- WordPress 5.0 or higher
- PHP 7.4 or higher
- WP-CLI installed
- Polylang plugin (optional, for multilingual sites)

## Installation

1. Download the plugin files
2. Upload the `post-type-sql-dump` directory to `/wp-content/plugins/`
3. Activate the plugin through the 'Plugins' menu in WordPress

## Usage

### Basic Usage

Export posts (default post type):
```bash
wp ptsd dump --quiet
```

Export a specific post type:
```bash
wp ptsd dump --post_type=page --quiet
```

Export custom post type:
```bash
wp ptsd dump --post_type=product --quiet
```

### Save to File

To save the output to a SQL file:
```bash
wp ptsd dump --post_type=page --quiet > export.sql
```

### Import the SQL

To import the generated SQL into another WordPress installation:
```bash
mysql -u username -p database_name < export.sql
```

Or using WP-CLI:
```bash
wp db import export.sql
```

## What Gets Exported

1. **Posts**: All posts of the specified type with new IDs
2. **Featured Images**: Attachment posts linked to the exported posts
3. **Post Metadata**: All custom fields and post meta
4. **Terms**: Categories, tags, and custom taxonomy terms
5. **Term Metadata**: All term meta data
6. **Polylang Data**: Language assignments and translation groups
7. **Relationships**: All connections between posts and terms

## What Gets Deleted Before Import

The plugin generates deletion queries that clean up existing data before import:
- Posts of the specified post type
- Post metadata for those posts
- Terms in taxonomies used by the post type
- Term metadata for those terms
- All relationships (term_relationships)
- Translation groups (if using Polylang)

**⚠️ Warning**: The generated SQL will DELETE existing data of the same post type in the target database before importing. Always backup your database before importing.

## Support for Polylang

The plugin has full support for the Polylang multilingual plugin:
- Exports language assignments for posts and terms
- Exports translation groups (relationships between translated posts)
- Exports term translation groups
- Dynamically maps to existing languages in target database
- Preserves all translation relationships

## Troubleshooting

### No output generated
- Make sure WP-CLI is properly installed
- Check that the post type exists and has posts
- Verify database credentials in wp-config.php

## Changelog

### 1.0.0
- Initial release
- Full Polylang support
- Support for all post types
- Featured image export
- Term and taxonomy export
- Metadata export

## License

GPL v2 or later

## Author

Jean-Philippe Bidegain
