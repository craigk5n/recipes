# Frontend Dependencies

This document describes how frontend assets (CSS, JS) are managed in the Recipes application.

## Overview

Frontend dependencies are managed via **Composer** and served **locally** (no CDN usage). This provides:

- **Version control** - Dependencies locked in composer.lock
- **Reproducible builds** - Same versions every time
- **Offline development** - No internet required after initial install
- **Security** - No external CDN dependencies

## Dependencies

The following frontend libraries are managed via Composer:

| Library | Composer Package | Version | Files |
|---------|-----------------|---------|-------|
| Bootstrap 5 | `twbs/bootstrap` | ^5.3 | bootstrap.min.css, bootstrap.bundle.min.js |
| jQuery | `components/jquery` | ^3.6 | jquery.min.js |

## Installation

### Initial Setup

```bash
composer install
```

This automatically:
1. Downloads packages to `vendor/`
2. Runs `scripts/install-assets.php`
3. Copies assets to `pub/` directory

### Manual Asset Installation

If you need to reinstall assets without running full composer install:

```bash
composer install-assets
```

Or run the script directly:

```bash
php scripts/install-assets.php
```

## How It Works

### Composer Packages

Dependencies are defined in `composer.json`:

```json
"require": {
    "php": ">=7.4",
    "twbs/bootstrap": "^5.3",
    "components/jquery": "^3.6"
}
```

### Post-Install Hook

After every `composer install` or `composer update`, the `install-assets` script runs automatically:

```json
"scripts": {
    "install-assets": "php scripts/install-assets.php",
    "post-install-cmd": ["@install-assets"],
    "post-update-cmd": ["@install-assets"]
}
```

### Asset Copy Script

The script `scripts/install-assets.php`:
1. Reads assets from `vendor/` directory
2. Copies minified files to `pub/` directory
3. Includes source maps for debugging

### File Structure

```
recipes/
├── composer.json           # Defines dependencies
├── scripts/
│   └── install-assets.php  # Asset copy script
├── pub/                    # Web-served assets (auto-generated)
│   ├── .gitkeep           # Keeps directory in git
│   ├── bootstrap.min.css  # Copied from vendor/twbs/bootstrap/
│   ├── bootstrap.bundle.min.js
│   ├── bootstrap.min.css.map
│   ├── bootstrap.bundle.min.js.map
│   └── jquery.min.js      # Copied from vendor/components/jquery/
└── vendor/                 # Composer packages (not served directly)
    ├── twbs/bootstrap/dist/...
    └── components/jquery/...
```

## Adding New Dependencies

### 1. Find the Composer Package

Look for frontend libraries on Packagist with the `npm-asset` or direct PHP packaging:

```bash
composer search bootstrap
```

### 2. Install the Package

```bash
composer require vendor/package-name
```

### 3. Update the Install Script

Edit `scripts/install-assets.php` and add the new asset mappings:

```php
$assets = [
    // Existing assets...
    
    // New library
    $vendorDir . '/vendor/package/dist/library.min.css' => $pubDir . '/library.min.css',
    $vendorDir . '/vendor/package/dist/library.min.js' => $pubDir . '/library.min.js',
];
```

### 4. Update HTML Includes

Add to `includes/styles.php` (CSS):
```html
<link href="pub/library.min.css" rel="stylesheet">
```

Add to `includes/js.php` (JS):
```html
<script src="pub/library.min.js"></script>
```

### 5. Run the Install Script

```bash
composer install-assets
```

## Updating Dependencies

To update to latest compatible versions:

```bash
composer update twbs/bootstrap components/jquery
```

This updates both the composer package AND copies the new assets to `pub/`.

## Git Workflow

### What's Tracked

- `composer.json` - Dependency definitions
- `composer.lock` - Locked versions
- `scripts/install-assets.php` - Asset management script
- `pub/.gitkeep` - Empty directory marker

### What's NOT Tracked

- `vendor/` - Composer packages (in .gitignore)
- `pub/*` - Copied assets (in .gitignore, auto-generated)

### For New Developers

After cloning the repository:

```bash
composer install
```

That's it! Assets are automatically installed to `pub/`.

## Troubleshooting

### Assets not found (404 errors)

Run the install script:
```bash
composer install-assets
```

### Wrong asset versions

Check composer.lock versions match pub/ files:
```bash
grep -A2 '"twbs/bootstrap"' composer.lock
ls -la pub/bootstrap*
```

### Missing source maps

Source maps (.map files) are optional but helpful for debugging. They're automatically copied when available.

### Custom asset locations

If you need assets in a different location, modify the destination paths in `scripts/install-assets.php`.

## Security Considerations

1. **No CDN usage** - All assets served locally
2. **Version locking** - composer.lock ensures reproducible builds
3. **Integrity** - Composer package signatures verified on install
4. **No npm** - Avoids Node.js/npm attack surface

## Alternative: Direct Vendor Serving (Not Recommended)

You could theoretically serve assets directly from `vendor/`, but this is NOT recommended because:

- Exposes internal directory structure
- Includes non-asset files (docs, tests, source files)
- Violates security principle of minimal exposure
- Harder to optimize (no concatenation/minification)

The copy approach (`vendor/` → `pub/`) is preferred for production use.
