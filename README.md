# k5n Recipes

A lightweight, standalone PHP recipe management application built with Bootstrap 5 and PDO.

[![CI](https://github.com/craigk5n/recipes/actions/workflows/ci.yml/badge.svg)](https://github.com/craigk5n/recipes/actions/workflows/ci.yml)
![PHP Version](https://img.shields.io/badge/php-%3E%3D8.1-8892bf.svg)
![License](https://img.shields.io/badge/license-MIT-green.svg)

k5n Recipes allows you to manage your personal recipe collection with ease. It features recipe importing from structured web data, photo management, and a clean, responsive UI.

## Features

- **Recipe Management:** Create, edit, and delete recipes with ingredients and instructions.
- **Import from URL:** Automatically extract recipes from websites using JSON-LD structured data.
- **Photo Support:** Upload and manage multiple photos for each recipe.
- **Flexible Authentication:** Supports Open, PIN-locked (Family), and Multi-user modes. See [AUTH.md](docs/AUTH.md).
- **Internationalization:** Modern i18n system with support for multiple languages. See [I18N.md](docs/I18N.md).
- **Search & Filter:** Quickly find recipes with real-time filtering and sorting.
- **Favorites:** Mark your favorite recipes for quick access.
- **Mobile Friendly:** Fully responsive design using Bootstrap 5.

## Installation

### Prerequisites
- PHP 8.1 or higher (the recipe category and unit enums require it)
- MySQL or MariaDB
- Apache or Nginx

### Setup
1. Clone the repository to your web server.
2. Install development dependencies (optional, for testing/linting):
   ```bash
   composer install
   ```
3. Run the interactive setup script. This will create the `.env` file for you if it doesn't exist, create the database, and run all migrations.
   ```bash
   php scripts/setup.php
   ```

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
