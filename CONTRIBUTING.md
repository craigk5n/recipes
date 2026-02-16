# Contributing to k5n Recipes

Thank you for your interest in contributing to k5n Recipes! We welcome contributions from the community to help make this project better.

## How to Contribute

### Reporting Bugs
- Use the GitHub Issue Tracker to report bugs.
- Provide a clear description of the issue and steps to reproduce it.
- Include information about your environment (PHP version, browser, etc.).

### Suggesting Enhancements
- Open an issue to discuss your ideas before implementing them.
- Describe the feature you'd like to see and why it would be useful.

### Pull Requests
1. Fork the repository and create a new branch for your feature or fix.
2. Follow the existing coding style (PSR-12).
3. Ensure all tests pass by running `composer test`.
4. Run the linter and static analysis using `composer lint`.
5. Write clear, concise commit messages.
6. Submit your pull request against the `main` branch.

## Coding Standards
We follow [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standards. You can check your code by running:
```bash
composer phpcs
```

## Static Analysis
We use PHPStan for static analysis. Please ensure your changes do not introduce new issues:
```bash
composer phpstan
```

## Testing
Please add tests for any new features or bug fixes. Run the existing suite with:
```bash
composer test
```

## Security
If you discover a security vulnerability, please do NOT open a public issue. Instead, contact the maintainers directly (details in README or project profile).
