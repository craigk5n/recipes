# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

k5n Recipes is a PHP recipe management web application using Bootstrap 5 for the UI, PDO for database access, and CSRF protection. It stores recipes with ingredients and instructions in a MySQL database. There is no build system, package manager, test suite, or CI/CD pipeline. Single-user (no authentication).

## Running the Application

The app runs directly on a web server (Apache) with PHP. Access it at `http://<server>/recipes/`. Configuration is in `includes/settings.php` (PDO connection to the `intranet` database on 127.0.0.1).

## Architecture

**Request model:** Each page is a standalone PHP file that includes `rec_includes.php`, which bootstraps the application by loading config, PDO database layer, security helpers, utility functions, and translation support.

**Database layer:** `includes/pdo_db.php` provides the `BookLogDB` class with PDO-based database access. All queries use parameterized prepared statements via `BookLogDB::query($sql, $params)`, `BookLogDB::fetchRow()`, `BookLogDB::freeResult()`. Legacy `dbi_*()` wrapper functions are also available for backward compatibility.

**Security:** `includes/security.php` provides `generateCsrfToken()`, `validateCsrfToken()`, `sanitizeString()`, `sanitizeInt()`. All forms include CSRF tokens. All user input is sanitized.

**Database tables:**
- `rec_recipe` — recipe metadata (id, title, last_updated, source)
- `rec_ingr` — ingredients (linked to recipe by rec_id, ordered by rec_ingr_num)
- `rec_instructions` — cooking instructions (linked to recipe by rec_id)

**Page structure:**
- `index.php` — lists all recipes in a searchable Bootstrap table
- `view.php` — displays a single recipe with ingredients, instructions, edit/delete buttons
- `edit.php` — add/edit recipe form with dynamic ingredient rows (jQuery)
- `edit_handler.php` — processes form submissions with CSRF validation and parameterized queries
- `delete_handler.php` — handles recipe deletion with CSRF validation
- `trailer.php` — closes card/container divs, includes parent site trailer

**Frontend:** Bootstrap 5.3.0 CSS/JS and jQuery 3.6.0 served from local `pub/` directory. Navigation via Bootstrap navbar (brand: "Recipes", items: Home, Add Recipe).

**Shared includes (`includes/`):**
- `config.php` — loads settings, defines language arrays, sets up `die_miserable_death()` error handler
- `pdo_db.php` — PDO database abstraction (`BookLogDB` class + legacy `dbi_*` wrappers)
- `security.php` — CSRF tokens, input sanitization
- `functions.php` (~4,800 lines) — core utilities inherited from WebCalendar (date formatting, input handling)
- `dbtable.php` — HTML table generation utilities for database-backed forms
- `connect.php` — establishes PDO database connection via `BookLogDB::connect()`
- `styles.php` — Bootstrap CSS link + custom styles
- `js.php` — jQuery + Bootstrap JS script references
- `translate.php` — i18n system using key-value files in `translations/`

**Static assets (`pub/`):**
- `bootstrap.min.css` — Bootstrap 5.3.0
- `bootstrap.bundle.min.js` — Bootstrap 5.3.0 JS bundle
- `jquery.min.js` — jQuery 3.6.0

**Parent site integration:** Pages include `../header.php`, `../trailer.php`, and `../style.css` from a parent directory (the broader intranet site).

## Known Issues

- `functions.php` contains substantial WebCalendar code (calendar views, event handling) that is unused by the recipes app
- `includes/dbi4php.php` is retained but no longer included; `pdo_db.php` replaces it
