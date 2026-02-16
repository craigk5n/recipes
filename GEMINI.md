# GEMINI.md - Project Context

## Project Overview
**k5n Recipes** is a standalone PHP web application for recipe management. It uses a modern security foundation (PDO, CSRF protection, secure session handling) built on a legacy structure. The application allows users to create, view, edit, delete, and import recipes, including support for ingredients, instructions, photos, and notes.

- **Primary Technologies:** PHP 7.4+, MySQL/MariaDB (via PDO), Bootstrap 5.3.0, jQuery 3.6.0.
- **Architecture:** Page-Controller pattern. Each `.php` file in the root represents a page or an action handler. Centralized bootstrapping is handled via `rec_includes.php`.
- **Database Layer:** Custom PDO wrapper in `includes/pdo_db.php` using the `BookLogDB` class, providing parameterized query support. Legacy `dbi_*` functions are maintained for backward compatibility.
- **Frontend:** Server-side rendered HTML with Bootstrap 5 UI. JavaScript logic (e.g., dynamic rows in `edit.php`, filtering in `index.php`) uses jQuery.

## Getting Started

### Prerequisites
- PHP >= 7.4
- MySQL or MariaDB
- Composer (for development tools)
- Apache (or equivalent web server)

### Configuration
1. Copy `.env.example` to `.env`.
2. Configure your database credentials in `.env`:
   ```env
   DB_HOST=127.0.0.1
   DB_DATABASE=recipes
   DB_LOGIN=your_username
   DB_PASSWORD=your_password
   ```
3. The application will automatically load these settings via `includes/config.php`.

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
Always use the `BookLogDB` class or the `dbi_*` wrappers with parameter binding to prevent SQL injection:
```php
$res = BookLogDB::query("SELECT * FROM rec_recipe WHERE rec_id = ?", [$id]);
```

### Security Conventions
- **CSRF:** All forms must include a CSRF token using `generateCsrfToken()` and be validated in handlers using `validateCsrfToken($_POST['csrf_token'])`.
- **Sanitization:** Use `sanitizeString()`, `sanitizeInt()`, and `sanitizeEmail()` from `includes/security.php` for user input.
- **Output Encoding:** Use `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')` (or the `sanitizeString` helper) for all dynamic content rendered in HTML.
- **Sessions:** Session security (HttpOnly, Secure, SameSite) is configured in `includes/security.php`.

### Important Notes
- **Legacy Code:** `includes/functions.php` contains approximately 4,800 lines of code inherited from WebCalendar. Much of this is currently unused and slated for cleanup (see `STATUS.md`).
- **Parent Site Integration:** The application currently looks for `../header.php` and `../style.css` for integration with a parent intranet site.
- **Authentication:** The application currently lacks a built-in authentication system and is intended for trusted single-user environments or protected behind web server auth.

## Roadmap & Status
See `STATUS.md` for a detailed security analysis and improvement roadmap, including plans for an internal authentication system and legacy code extraction.
