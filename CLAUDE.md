# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

k5n Recipes is a PHP recipe management web application using Bootstrap 5 for the UI, PDO for database access, and CSRF protection. It stores recipes with ingredients and instructions in a MySQL database. It uses Composer for dependencies and PHPUnit for testing.

## Running the Application

The app runs directly on a web server (Apache) with PHP. First, run the setup script:
```bash
php scripts/setup.php
```
Then, access it at `http://<server>/recipes/`. Configuration is in the `.env` file created during setup.

## Development Commands

```bash
composer install          # Install dependencies
composer test             # Run PHPUnit test suite
composer phpcs            # Check code style (PSR-12, config in phpcs.xml)
composer phpcbf           # Auto-fix the style violations phpcs can fix
composer phpstan          # Static analysis (level 5, includes/ and src/)
composer lint             # Run both phpcs and phpstan
composer install-assets   # Copy Bootstrap files to pub/
```

Run a single test file:
```bash
./vendor/bin/phpunit tests/SecurityTest.php
```

Run a single test method:
```bash
./vendor/bin/phpunit --filter testMethodName tests/SecurityTest.php
```

CI runs all three checks (phpcs, phpstan, phpunit) on PHP 8.2 — see `.github/workflows/ci.yml`.

## Architecture

**Request model:** Each page is a standalone PHP file that includes `rec_includes.php`, which bootstraps the application by loading config, PDO database layer, security helpers, utility functions, and translation support. Every new page must `include "rec_includes.php";` at the top. Form submissions are handled by separate `*_handler.php` files (e.g., `edit_handler.php`, `delete_handler.php`, `photo_handler.php`).

**Dual code structure (legacy + modern):** The codebase is migrating from procedural `includes/` files to PSR-4 namespaced classes in `src/` (namespace `Recipes\`). The `includes/` files now act as shims that delegate to the modern classes:
- `includes/config.php` → `src/Config.php` (`Recipes\Config`)
- `includes/pdo_db.php` → `src/Database/Database.php` (`Recipes\Database\Database`)
- `includes/security.php` → `src/Security/Security.php` (`Recipes\Security\Security`)

New code should use the `src/` classes directly. Legacy global functions (`dbi_query()`, `generateCsrfToken()`, `sanitizeString()`, etc.) still work via the shims. Note: `rec_includes.php` explicitly `require_once`s files containing standalone functions (e.g., `Translator.php`, `AuthManager.php`) because PSR-4 autoloading only handles classes, not functions.

**Database layer:** `Recipes\Database\Database` is a static class wrapping PDO. All queries use parameterized prepared statements:
```php
$res = Database::query("SELECT * FROM rec_recipe WHERE rec_id = ?", [$id]);
$row = Database::fetchRow($res);
Database::freeResult($res);
```

**Security:** `Recipes\Security\Security` provides CSRF tokens, input sanitization, security headers, rate limiting, and error handling. All forms must include CSRF tokens. All user input must be sanitized.

**Authentication & Access Control:**
- Managed via `src/Auth/AuthManager.php` (`Recipes\Auth\AuthManager`).
- Three modes configured via `AUTH_MODE` in `.env`: `open` (no auth), `pin` (shared PIN for edits), `user` (multi-user accounts).
- Permission checks: `$auth->can('edit')`, `$auth->can('delete')`, `$auth->can('admin')`.
- Global helper: `Recipes\Auth\getAuthManager()` returns singleton instance.

**i18n:** `src/I18n/Translator.php` provides JSON-based translations (`translations/*.json`). Use `Recipes\I18n\t('key')` to translate, `Recipes\I18n\et('key')` to echo. Keys use dot notation matching JSON structure.

**Configuration:** `Recipes\Config::get('KEY')` reads from `.env` file, environment variables, or legacy `includes/settings.php` (in that priority order).

**Enums:** `src/Recipe/Category.php` and `src/Recipe/Unit.php` define PHP 8.1 enums for recipe categories (breakfast, lunch, dinner, etc.) and measurement units (cups, tbsp, oz, etc.), each with `fromLegacy()` and `label()` methods.

**Database tables:**
- `rec_recipe` — recipe metadata (rec_id, rec_title, rec_last_updated, rec_source, rec_url, rec_date_added, rec_favorite, rec_category, user_id)
- `rec_ingr` — ingredients (rec_id, rec_ingr_num, rec_quantity, rec_quantity_type, rec_name, rec_prep)
- `rec_instructions` — cooking instructions (rec_id, rec_instructions)
- `rec_photo` — recipe photos stored as BLOBs (photo_id, rec_id, photo_data, original_name, mime_type, file_size, is_primary)
- `rec_users` — user accounts (user_id, username, password_hash, email, is_admin)
- `rec_note` — recipe notes (note_id, rec_id, note_text, created_at, user_id)

**Database migrations:** Located in `scripts/migrate_*.php`, run automatically by `scripts/setup.php`.

**Frontend:** Bootstrap 5.3.0 CSS/JS served from local `pub/` directory (managed via Composer `twbs/bootstrap` package, copied by post-install hook). All JavaScript uses vanilla JS (no jQuery). Navigation via Bootstrap navbar.

**Parent site integration:** Pages include `../header.php`, `../trailer.php`, and `../style.css` from a parent directory (the broader intranet site).

**Testing:** PHPUnit tests are in `tests/`. The test bootstrap (`tests/bootstrap.php`) loads autoloader and includes but skips the real database connection. Use `Database::setMockQueryResult()` / `Database::clearMockQueryResults()` for mocking DB queries in tests.

## Gotchas

- **Linting/analysis scope:** `composer phpstan` analyses `includes/` and `src/` (level 5, currently clean — keep it that way). `composer phpcs` reads `phpcs.xml` and covers `includes/` only, so `src/` is not style-checked yet. The whole pipeline (phpcs, phpstan, phpunit) is green — treat a failure as your change, not pre-existing noise.
- **phpcs.xml carve-outs:** `includes/settings.php` is excluded because it is generated by `install/index.php`, gitignored, and stores values inside a comment block that `Config::parseLegacySettingsFile()` reads by regex — never run phpcbf over it. `js.php`/`styles.php` are excluded as HTML partials with no PHP in them. `PSR1.Files.SideEffects` is silenced for the three bootstrap shims (`config.php`, `pdo_db.php`, `security.php`) that both declare functions and run code on include; it still applies to every other file.
- **PHP version mismatch:** `composer.json` declares `>=7.4`, but enums in `src/Recipe/` and `src/I18n/` require PHP 8.1+. CI runs on PHP 8.2.
- **Parent site dependency:** Pages include `../header.php`, `../trailer.php`, and `../style.css` — the app won't render properly without these files from the parent intranet site.
- **Timezone:** `rec_includes.php` hardcodes `America/New_York`.
