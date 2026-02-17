# GEMINI.md - Project Context

## Project Overview
**k5n Recipes** is a standalone PHP web application for recipe management. It uses a modern security foundation (PDO, CSRF protection, secure session handling) built on a legacy structure. The application allows users to create, view, edit, delete, and import recipes, including support for ingredients, instructions, photos, and notes.

- **Primary Technologies:** PHP 7.4+, MySQL/MariaDB (via PDO), Bootstrap 5.3.0, jQuery 3.6.0.
- **Architecture:** Page-Controller pattern. Each `.php` file in the root represents a page or an action handler. Centralized bootstrapping is handled via `rec_includes.php`.
- **Database Layer:** Modern PDO abstraction in `src/Database/Database.php` using the `Recipes\Database\Database` class. A legacy shim in `includes/pdo_db.php` provides backward compatibility for the `BookLogDB` class and `dbi_*` functions.
- **Frontend:** Server-side rendered HTML with Bootstrap 5 UI. JavaScript logic (e.g., dynamic rows in `edit.php`, filtering in `index.php`) uses jQuery.

## Getting Started

### Prerequisites
- PHP >= 7.4
- MySQL or MariaDB
- Composer (for development tools)
- Apache (or equivalent web server)

### Configuration & Setup
Run the interactive setup script. This will guide you through creating your database and `.env` file.
```bash
php scripts/setup.php
```
The application will automatically load these settings.

### Building and Running
The application is served directly by PHP. Point your web server's document root to the project directory or access it via `http://localhost/recipes/`.

## Key Development Commands
Development dependencies and scripts are managed via Composer.

- **Install Dependencies:** `composer install`
- **Run Tests:** `composer test` (Executes PHPUnit tests in `tests/`)
- **Code Style Check:** `composer phpcs` (Checks `includes/` against PSR-12)
- **Static Analysis:** `composer phpstan` (Runs PHPStan at Level 5)
- **Full Lint:** `composer lint` (Runs both `phpcs` and `phpstan`)

## Architecture & Conventions

### Directory Structure
- `/` - Page controllers (e.g., `index.php`, `view.php`) and handlers (e.g., `edit_handler.php`).
- `includes/` - Core logic, configuration, database abstraction, and utility functions.
- `pub/` - Static assets (CSS, JS) served locally (no CDNs).
- `translations/` - Key-value translation files for i18n support.
- `tests/` - PHPUnit test suite.
- `logs/` - Application logs (error, security).

### Bootstrapping
Every entry-point PHP file must include `rec_includes.php` at the very beginning. This file initializes sessions, security headers, database connections, and utility functions.

### Database Interaction
Always use the `Database` class with parameter binding to prevent SQL injection:
```php
use Recipes\Database\Database;
$res = Database::query("SELECT * FROM rec_recipe WHERE rec_id = ?", [$id]);
```

### Security Conventions
- **CSRF:** All forms must include a CSRF token using `generateCsrfToken()` and be validated in handlers using `validateCsrfToken($_POST['csrf_token'])`.
- **Sanitization:** Use `sanitizeString()`, `sanitizeInt()`, and `sanitizeEmail()` from `includes/security.php` for user input.
- **Output Encoding:** Use `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')` (or the `sanitizeString` helper) for all dynamic content rendered in HTML.
- **Sessions:** Session security (HttpOnly, Secure, SameSite) is configured in `includes/security.php`.

### Important Notes
- **Parent Site Integration:** The application currently looks for `../header.php` and `../style.css` for integration with a parent intranet site.
- **Authentication:** The application currently lacks a built-in authentication system and is intended for trusted single-user environments or protected behind web server auth.

## Roadmap & Status
See `STATUS.md` for a detailed security analysis and improvement roadmap, including plans for an internal authentication system and legacy code extraction.
