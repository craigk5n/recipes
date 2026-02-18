## Epic 5: DevOps & Setup

**Priority:** MEDIUM
**Status:** Completed

**Description:**
Improve the setup and deployment process for the application.

**Tasks:**
- [x] Create an interactive `scripts/setup.php` script that handles database creation and migrations.
- [x] Consolidate all database changes into a numbered migration format (`scripts/migrate_XX-*.php`).
- [x] Update documentation to reflect the new, simplified setup process.

## Epic 6: Modernization & 1.0 Readiness

**Priority:** HIGH
**Status:** In Progress

**Description:**
Address architectural inconsistencies and blockers for a stable 1.0 release.

**Tasks:**
- [ ] **Fix PHP Compatibility:** Either update `composer.json` to require PHP 8.1+ or refactor Enums (in `src/Recipe/`) for PHP 7.4 compatibility.
- [ ] **Complete Migration:** Fully transition logic from procedural `includes/` shims to namespaced PSR-4 classes in `src/`.
- [ ] **Decouple from Parent Site:** Remove the hard dependency on `../header.php` and `../style.css` to allow for standalone operation.
- [ ] **Static Analysis Expansion:** Expand PHPStan coverage to the entire `src/` directory at level 6 or higher.
- [ ] **Standardize UI:** Replace legacy Bootstrap 5 shims with a consistent, component-based approach.

## Epic 7: Advanced Testing & QA

**Priority:** MEDIUM
**Status:** Planned

**Description:**
Improve code reliability through comprehensive testing strategies.

**Tasks:**
- [ ] **Increase Unit Test Coverage:** Target 80%+ coverage for `src/Recipe`, `src/Database`, and `src/Auth`.
- [ ] **Integration Tests:** Implement tests using a real MySQL/MariaDB test database to verify migrations and complex queries.
- [ ] **E2E Testing (Playwright):** Implement Playwright-based tests for core user journeys (Add -> Edit -> Delete -> Import).
- [ ] **GitHub Actions Matrix:** Expand CI to test against PHP 8.1, 8.2, and 8.3.

## Epic 8: New Feature Roadmap

**Priority:** MEDIUM
**Status:** Future

**Description:**
Expand application capabilities to compete with modern recipe managers.

**Tasks:**
- [ ] **Recipe Scaling:** Allow users to adjust serving sizes and automatically recalculate ingredient quantities.
- [ ] **Unit Conversion:** Support for toggling between Metric and Imperial units.
- [ ] **Shopping List Generation:** Allow users to select multiple recipes and generate a consolidated list.
- [ ] **Meal Planning:** Add a calendar-based meal planner.
- [ ] **PDF Export:** Implement a clean "Print to PDF" feature for offline use.
