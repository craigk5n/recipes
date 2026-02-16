# k5n Recipes

A lightweight, standalone PHP recipe management application built with Bootstrap 5 and PDO.

![PHP Version](https://img.shields.io/badge/php-%3E%3D7.4-8892bf.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

k5n Recipes allows you to manage your personal recipe collection with ease. It features recipe importing from structured web data, photo management, and a clean, responsive UI.

## Features

- **Recipe Management:** Create, edit, and delete recipes with ingredients and instructions.
- **Import from URL:** Automatically extract recipes from websites using JSON-LD structured data.
- **Photo Support:** Upload and manage multiple photos for each recipe.
- **Search & Filter:** Quickly find recipes with real-time filtering and sorting.
- **Favorites:** Mark your favorite recipes for quick access.
- **Mobile Friendly:** Fully responsive design using Bootstrap 5.

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL or MariaDB
- Apache or Nginx

### Setup
1. Clone the repository to your web server.
2. Install development dependencies (optional, for testing/linting):
   ```bash
   composer install
   ```
3. Configure your database:
   - Copy `.env.example` to `.env`.
   - Update the `DB_*` variables with your database credentials.
4. Import the database schema (if available) or ensure your database user has permission to create tables.

## Development

The project uses Composer to manage development tools and scripts.

- **Run Tests:** `composer test`
- **Linting:** `composer phpcs`
- **Static Analysis:** `composer phpstan`
- **Combined Lint:** `composer lint`

## Project Structure
- `index.php` - Main recipe listing and search.
- `view.php` - Detailed recipe view.
- `edit.php` - Recipe creation and editing form.
- `includes/` - Core logic and database abstraction.
- `pub/` - Static assets (CSS, JS).
- `tests/` - Unit and integration tests.

## Security
This application includes:
- **CSRF Protection** on all state-changing actions.
- **PDO Prepared Statements** to prevent SQL injection.
- **Secure Session Management** with HttpOnly and SameSite flags.
- **Input Sanitization** for all user-provided data.

## License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
