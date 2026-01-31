# Post Type SQL Dump

A WordPress plugin that exports posts and their associated data (including Polylang translations) to SQL format via WP-CLI command.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- WP-CLI
- Polylang (optional, for multilingual support)

## Installation

```bash
cd wp-content/plugins
git clone https://github.com/aeyoll/post-type-sql-dump.git
wp plugin activate post-type-sql-dump
```

Or download and extract to `wp-content/plugins/post-type-sql-dump/`

## Usage

Export posts to SQL format:

```bash
wp ptsd dump --post_type=page --quiet > export.sql
```

Import on another WordPress site:

```bash
wp db import export.sql
```

### Command Options

- `--post_type` - Post type to export (default: `post`)
- `--quiet` - Suppress WP-CLI progress output

### Examples

```bash
wp ptsd dump --quiet
wp ptsd dump --post_type=product --quiet > products.sql
```

## What Gets Exported

- Posts with newly generated IDs
- Featured images and attachments
- Post metadata and custom fields
- Terms and taxonomies
- Term metadata
- Polylang translations and language assignments
- All relationships preserved

## Important Notes

**⚠️ Warning**: The generated SQL includes deletion queries that will remove existing data of the same post type before importing. Always backup your database before importing.

The export is designed for migrating content between WordPress installations while:
- Avoiding ID conflicts by generating new IDs
- Preserving all relationships between posts, terms, and translations
- Maintaining Polylang multilingual structure

## Changelog

### 1.0.0

- Initial release

## License

GPLv2 or later - https://www.gnu.org/licenses/gpl-2.0.html

## Author

Jean-Philippe Bidegain
