# Simple Metatag Module for Drupal 11

A lightweight metatag module for Drupal 11 that automatically generates SEO and Open Graph metatags for nodes and taxonomy terms, with powerful path-based override capabilities.

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
- Adds **Meta Title** and **Meta Description** fields to all node edit forms
- If fields are filled: uses custom values for metatags
- If fields are empty: auto-generates from node title and body (first 160 characters)
- **og:image**: Automatically pulls from `field_image` (first image only)

#### For Taxonomy Terms:
- Adds **Meta Title** and **Meta Description** fields to all taxonomy term edit forms
- If fields are filled: uses custom values for metatags
- If fields are empty: auto-generates from term name and description
- **og:image**:
  - First checks for `field_image` on the term itself
  - If not found, pulls from the latest node (by date) that references this term

### 3. Path-Based Metatag Overrides

Provides an admin interface for creating path-based metatag overrides that take precedence over entity-level metatags.

**Features:**
- Define metatags for specific paths or path patterns
- Supports wildcards (e.g., `/blog/*`)
- Supports homepage via `<front>` keyword
- Filter by domain (multi-site support)
- Filter by language code
- Set priority via weight system (higher weight = higher priority)
- Enable/disable individual overrides

**Admin Interface Location:**
`Configuration → Search and metadata → Simple Metatag`

Or directly at: `/admin/config/search/simple-metatag`

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
2. Find the **"Metatags"** section (usually in the sidebar or advanced options)
3. Fill in optional **Meta Title** and **Meta Description** fields
4. Leave empty to use auto-generated values
5. Save the entity

### Path-Based Override Configuration

1. Navigate to `Configuration → Search and metadata → Simple Metatag`
2. Click **"Add new override"**
3. Fill in the form:
   - **Path**: The path pattern (e.g., `/about`, `/blog/*`, `<front>`)
   - **Domains**: One domain per line (optional, leave empty for all domains)
   - **Language**: Language code like `en`, `es`, `fr` (optional, leave empty for all)
   - **Meta Title**: Required title for this path
   - **Meta Description**: Required description for this path
   - **Image URL**: Full URL to og:image (optional)
   - **Weight**: Priority (higher = more priority, default: 0)
   - **Active**: Enable/disable this override
4. Click **"Save"**

### Path Pattern Examples

- `<front>` - Homepage
- `/about` - Exact path match
- `/blog/*` - All paths starting with /blog/
- `/products/*/reviews` - Pattern matching

## Permission

The module defines one permission:

- **Administer Simple Metatag**: Allows users to manage path-based metatag overrides

Assign this permission to roles that should manage metatags at: `/admin/people/permissions`

## Database Schema

The module creates one custom table:

**`simple_metatag_path`** - Stores path-based metatag overrides

Fields:
- `id` - Primary key
- `path` - Path pattern
- `domains` - Serialized array of domains
- `language` - Language code
- `title` - Meta title
- `description` - Meta description
- `image` - Image URL
- `weight` - Priority weight
- `status` - Active status (0 or 1)

## Technical Details

### Service

The module provides a service `simple_metatag.generator` that handles metatag generation logic.

**Service ID:** `simple_metatag.generator`
**Class:** `Drupal\simple_metatag\MetatagGenerator`

### Hooks Implemented

- `hook_help()` - Provides help text
- `hook_form_BASE_FORM_ID_alter()` - Adds metatag fields to node and taxonomy forms
- `hook_page_attachments()` - Attaches metatags to page header
- `hook_entity_base_field_info()` - Defines base fields for metatag storage

### Priority Order

Metatags are applied in the following priority (highest to lowest):

1. **Path-based overrides** (highest weight first)
2. **Entity-level custom metatags** (if filled)
3. **Auto-generated metatags** (from entity title/body)

## Requirements

- Drupal: ^11
- PHP: 8.1 or higher
- Modules:
  - Node (core)
  - Taxonomy (core)

## Troubleshooting

### Metatags not appearing?

1. Clear Drupal cache: `drush cr`
2. Check that the module is enabled: `drush pm:list | grep simple_metatag`
3. Verify page is a node or taxonomy term
4. Check browser source code (View → Developer → View Source)

### Path overrides not working?

1. Check the path pattern matches exactly
2. Verify override is set to "Active"
3. Check weight - higher weight = higher priority
4. Clear cache after making changes

### Images not showing?

1. Verify `field_image` exists on the content type or vocabulary
2. Check image is uploaded and not empty
3. For taxonomy: ensure child nodes exist with images
4. View page source to see the actual og:image URL

## Maintainer

Current maintainer: [Your Name/Organization]

## License

GPL-2.0-or-later
