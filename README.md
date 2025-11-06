# Simple Metatag Module for Drupal 11

A lightweight metatag module for Drupal 11 that automatically generates SEO and Open Graph metatags for nodes and taxonomy terms, with powerful path-based override capabilities and Domain module integration.

## Features

### 1. Automatic Metatag Generation

The module automatically generates the following metatags for all nodes and taxonomy terms:

- `<title>` - Page title
- `<meta name="description">` - Page description
- `<meta property="og:title">` - Open Graph title
- `<meta property="og:description">` - Open Graph description
- `<meta property="og:image">` - Open Graph image
- `<meta property="og:url">` - Open Graph URL

### 2. Entity-Level Metatags

#### For Nodes:
- Adds **Meta Title**, **Meta Description**, and **Meta Image** fields to all node edit forms
- **Meta Image**: Upload custom image (JPG, PNG, GIF, WebP - max 5MB) or leave empty to use `field_image`
- If title/description fields are empty: auto-generates from node title and body (first 160 characters)
- All metatag data is stored in custom database table for reliable installation

#### For Taxonomy Terms:
- Adds **Meta Title**, **Meta Description**, and **Meta Image** fields to all taxonomy term edit forms
- **Meta Image**: Upload custom image (JPG, PNG, GIF, WebP - max 5MB)
- If fields are empty: auto-generates from term name and description
- **og:image fallback order**:
  1. Uploaded meta image
  2. `field_image` on the term itself
  3. Image from the latest node (by date) that references this term

### 3. Path-Based Metatag Overrides

Provides an admin interface for creating path-based metatag overrides that take precedence over entity-level metatags.

**Features:**
- Define metatags for specific paths or path patterns
- Supports wildcards (e.g., `/blog/*`)
- Supports homepage via `<front>` keyword
- **Domain module integration**: Select from configured domains (checkboxes)
- **Language dropdown**: Select from installed languages
- **Image upload**: Upload og:image directly (JPG, PNG, GIF, WebP - max 5MB)
- Path overrides always take priority over entity-level metatags

**Admin Interface Location:**
`Configuration → Search and metadata → Simple Metatag`

Or directly at: `/admin/config/search/simple-metatag`

**List View Columns:**
- Path
- Title
- Description (truncated to 80 chars)
- Domain (shows selected domains or "All")
- Language (shows language code or "All")
- Operations (Edit, Delete)

## Installation

1. Copy the module to your Drupal installation:
   ```bash
   composer require drupal/simple_metatag
   ```
   Or place the module in `/modules/custom/simple_metatag/`

2. Enable the module:
   ```bash
   drush en simple_metatag
   ```
   Or via the admin interface at `/admin/modules`

3. Clear cache:
   ```bash
   drush cr
   ```

## Configuration

### Node and Taxonomy Configuration

1. **Edit any node or taxonomy term**
2. Find the **"Metatags"** section (usually collapsed in the sidebar)
3. Fill in optional fields:
   - **Meta Title**: Custom title for this entity
   - **Meta Description**: Custom description for this entity
   - **Meta Image**: Upload a custom image for og:image (optional)
4. Leave any field empty to use auto-generated values
5. Save the entity

**Note:** Uploaded images are stored in `public://metatag/` directory and managed by Drupal's file system.

### Path-Based Override Configuration

1. Navigate to `Configuration → Search and metadata → Simple Metatag`
2. Click **"Add new override"**
3. Fill in the form:
   - **Path**: The path pattern (e.g., `/about`, `/blog/*`, `<front>`)
     - Use `<front>` for homepage
     - Use `*` as wildcard (e.g., `/blog/*` matches all blog posts)
   - **Domains**: Select one or more domains (requires Domain module)
     - Leave unchecked to apply to all domains
     - Only shows domains configured in Domain module
   - **Language**: Select from dropdown
     - Choose "- All languages -" to apply to all languages
   - **Meta Title**: Title for this path
   - **Meta Description**: Description for this path
   - **Image**: Upload og:image (optional)
4. Click **"Save"**

### Path Pattern Examples

- `<front>` - Homepage
- `/about` - Exact path match
- `/blog/*` - All paths starting with /blog/
- `/products/*/reviews` - Pattern matching with wildcard

## Domain Module Integration

If the **Domain** module is installed and enabled:
- The path-based form will show checkboxes for all configured domains
- You can select which domains each override applies to
- Leave all unchecked to apply to all domains

If Domain module is **not** installed:
- The form will show a single checkbox for the current domain
- Overrides will work for single-domain sites

## Permission

The module defines one permission:

- **Administer Simple Metatag**: Allows users to manage path-based metatag overrides

Assign this permission to roles that should manage metatags at: `/admin/people/permissions`

**Note:** All authenticated users with node/taxonomy edit permissions can fill metatag fields on individual entities.

## Database Schema

The module creates two custom tables:

### `simple_metatag_entity`
Stores entity-level metatags for nodes and taxonomy terms.

Fields:
- `id` - Primary key
- `entity_type` - Entity type (node or taxonomy_term)
- `entity_id` - Entity ID
- `title` - Meta title
- `description` - Meta description
- `image` - File ID (references managed file)

### `simple_metatag_path`
Stores path-based metatag overrides.

Fields:
- `id` - Primary key
- `path` - Path pattern
- `domains` - Serialized array of domains
- `language` - Language code
- `title` - Meta title
- `description` - Meta description
- `image` - File ID (references managed file)
- `weight` - Priority weight (not currently used in UI)
- `status` - Active status (always 1 in current implementation)

## Technical Details

### Service

The module provides a service `simple_metatag.generator` that handles metatag generation logic.

**Service ID:** `simple_metatag.generator`
**Class:** `Drupal\simple_metatag\MetatagGenerator`

### Hooks Implemented

- `hook_help()` - Provides help text
- `hook_form_BASE_FORM_ID_alter()` - Adds metatag fields to node and taxonomy forms
- `hook_page_attachments()` - Attaches metatags to page header
- `hook_node_insert()` / `hook_node_update()` - Saves node metatag data
- `hook_taxonomy_term_insert()` / `hook_taxonomy_term_update()` - Saves term metatag data

### Priority Order

Metatags are applied in the following priority (highest to lowest):

1. **Path-based overrides** (matching path + domain + language)
2. **Entity-level custom metatags** (if filled)
3. **Auto-generated metatags** (from entity title/body/images)

### File Management

- Uploaded images are stored in `public://metatag/` directory
- File extensions allowed: jpg, jpeg, png, gif, webp
- Maximum file size: 5MB
- Files are marked as permanent and tracked by Drupal's file usage system
- Validators use Drupal 11 syntax (FileExtension, FileSizeLimit with integer bytes)

## Requirements

- Drupal: ^11
- PHP: 8.1 or higher
- Core Modules:
  - Node (core)
  - Taxonomy (core)
  - File (core)
- Optional Modules:
  - Domain (for multi-site domain selection)

## Troubleshooting

### Metatags not appearing?

1. Clear Drupal cache: `drush cr`
2. Check that the module is enabled: `drush pm:list | grep simple_metatag`
3. Verify page is a node or taxonomy term page
4. Check browser source code (View → Developer → View Source)
5. Look for meta tags in `<head>` section

### Path overrides not working?

1. Check the path pattern matches exactly
   - Use `<front>` for homepage (not `/` or empty)
   - Verify wildcard placement
2. Check domain selection matches current domain
3. Check language selection matches current language
4. Clear cache after making changes: `drush cr`
5. Verify override appears in list at `/admin/config/search/simple-metatag`

### Images not uploading?

1. Check file permissions on `public://metatag/` directory
2. Verify file extension is allowed (jpg, jpeg, png, gif, webp)
3. Verify file size is under 5MB
4. Check PHP upload limits in php.ini
5. Look for error messages after attempting upload

### Images not showing in og:image?

1. For entity-level: Upload meta image or verify `field_image` exists and has an image
2. For path-based: Upload image in the override form
3. View page source to see the actual og:image URL
4. Verify URL is accessible (paste in browser)
5. Check that image file still exists in files directory

### Empty descriptions appearing?

The module automatically prevents empty descriptions from being added to meta tags. If a description cannot be generated or is empty, the description meta tags will be omitted entirely.

## Uninstallation

To completely remove the module:

1. Disable and uninstall via Drush:
   ```bash
   drush pmu simple_metatag
   ```

2. The custom database tables will be automatically removed

3. Uploaded files in `public://metatag/` should be manually deleted if desired

## Development

### Testing Path Patterns

Test your path-based overrides by visiting:
- Homepage: `<front>` pattern
- Specific paths: Match exactly
- Wildcard patterns: Test multiple matching URLs

### Debugging

Enable Drupal's error reporting to see detailed errors:
```php
// In settings.php
$config['system.logging']['error_level'] = 'verbose';
```

View metatag generation process by checking the MetatagGenerator service in `src/MetatagGenerator.php`.

## License

GPL-2.0-or-later
