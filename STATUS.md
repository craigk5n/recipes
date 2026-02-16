# k5n Recipes - Codebase Analysis & Improvement Roadmap

**Analysis Date:** 2026-02-15  
**Application:** k5n Recipes v1.0.4 - PHP Recipe Management Application  
**Framework:** Plain PHP with Bootstrap 5, PDO, CSRF Protection

---

## Executive Summary

The k5n Recipes application has a **solid security foundation** with modern PDO database access, proper CSRF protection, and consistent XSS prevention. However, it has **critical gaps** in authentication/authorization, contains dangerous legacy code, and lacks modern development practices. The app is suitable for trusted single-user environments but requires significant hardening for production use.

**Security Score:** 6/10 (Good foundation, critical auth gaps)  
**Code Quality Score:** 5/10 (Mixed modern/legacy, needs cleanup)  
**Maintainability Score:** 4/10 (Large unused codebase, no tests)

---

## Critical Security Issues

### 🔴 CRITICAL: No Authentication/Authorization
- **Risk:** Anyone with network access can add, edit, delete recipes
- **Impact:** Complete data integrity compromise
- **Location:** All endpoints

### 🔴 CRITICAL: Legacy Code Contains Dangerous Patterns
- **Risk:** `functions.php` has ~4,800 lines of WebCalendar code with unsafe global variable assignment
- **Impact:** XSS, potential RCE through crafted input
- **Location:** `includes/functions.php` lines 92-113

### ✅ FIXED: Database Credentials Now Support Environment Variables
- **Previous Risk:** `includes/settings.php` contained credentials in web-accessible directory
- **Solution:** Now supports environment variables and `.env` files
- **Status:** Credentials can be moved outside web root using env vars

### ✅ FIXED: Information Disclosure
- **Previous Risk:** Detailed error messages exposed paths and database errors
- **Solution:** `handleError()` function logs details internally, shows generic messages to users
- **Status:** Production-safe error handling implemented

### ✅ FIXED: Session Security
- **Previous Risk:** No session regeneration, secure flags, or timeout
- **Solution:** Secure session cookie settings (HttpOnly, Secure, SameSite)
- **Status:** Session security hardened

---

## Code Quality Issues

### 🔴 CRITICAL: 4,800 Lines of Unused Code
- **Impact:** `functions.php` contains WebCalendar calendar/event handling code
- **Recommendation:** Remove or isolate unused code

### 🟡 HIGH: Insecure Direct Object References
- **Risk:** No ownership verification when editing/deleting
- **Impact:** Users could modify others' data (if auth existed)
- **Location:** All handler files

### 🟡 MEDIUM: No Test Coverage
- **Impact:** No automated verification of functionality
- **Recommendation:** Implement unit and integration tests

### 🟢 LOW: Mixed Patterns
- Legacy `dbi_*()` wrappers alongside modern PDO
- Inconsistent error handling (die() vs exceptions)

---

## Positive Security Measures

✅ **CSRF Protection:** Cryptographically secure tokens with `random_bytes(32)` and `hash_equals()`  
✅ **SQL Injection Prevention:** PDO prepared statements with `ATTR_EMULATE_PREPARES => false`  
✅ **XSS Prevention:** Consistent `htmlspecialchars()` with `ENT_QUOTES`  
✅ **File Upload Security:** MIME type validation via `finfo`, size limits, image validation  
✅ **Input Sanitization:** `filter_var()` for integers, `htmlspecialchars()` for strings  
✅ **Security Headers:** X-Frame-Options, X-Content-Type-Options, CSP, and more  
✅ **Secure Error Handling:** Errors logged internally, generic messages shown to users  
✅ **Environment Configuration:** Database credentials via env vars (not in web root)  
✅ **Secure Session Cookies:** HttpOnly, Secure, SameSite flags enabled  
✅ **Rate Limiting:** Session-based rate limiting for imports and photo uploads  


---

## Improvement Roadmap

---

## Epic 1: Authentication & Authorization System

**Priority:** CRITICAL  
**Effort:** Large  
**Business Value:** Essential for production deployment

### Description
Implement a complete authentication and authorization system to protect recipe data from unauthorized access and modification.

### Tasks

#### Task 1.1: Design Authentication Strategy
**Status:** Completed  
**Assignee:** AI Assistant  
**Effort:** 4 hours

**Description:**
Research and design authentication approach for single-user recipe application.

**Acceptance Criteria:**
- [x] Document authentication options (Basic Auth, Session-based, OAuth)
- [x] Select approach based on security requirements and simplicity: **Session-based authentication with local user table.**
- [x] Define user data model (if needed beyond single user): `rec_users` table with `user_id`, `username`, `password_hash`.
- [x] Document session management strategy: Secure HttpOnly/Secure/SameSite cookies, 30min timeout, regeneration on login.

**Possible Unit Tests:**
- N/A (design task)

---

#### Task 1.2: Implement User Authentication
**Status:** Open  
**Assignee:** TBD  
**Effort:** 8 hours

**Description:**
Implement chosen authentication mechanism with secure session management.

**Acceptance Criteria:**
- [ ] Login page created with CSRF protection
- [ ] Password verification using `password_verify()`
- [ ] Session created on successful login
- [ ] Session regeneration on login (prevent fixation)
- [ ] Secure session cookie flags (HttpOnly, Secure, SameSite)
- [ ] Logout functionality clears session
- [ ] Password hashing uses Argon2id
- [ ] Failed login rate limiting (5 attempts per 15 minutes)

**Possible Unit Tests:**
```php
// Test successful login
public function testValidLogin() {
    $_POST['username'] = 'admin';
    $_POST['password'] = 'correct_password';
    $_POST['csrf_token'] = generateCsrfToken();
    
    $result = authenticateUser();
    
    assertTrue($result);
    assertTrue(isset($_SESSION['user_id']));
    assertNotEquals(session_id(), $oldSessionId); // Regenerated
}

// Test invalid password
public function testInvalidPassword() {
    $_POST['username'] = 'admin';
    $_POST['password'] = 'wrong_password';
    $_POST['csrf_token'] = generateCsrfToken();
    
    $result = authenticateUser();
    
    assertFalse($result);
    assertFalse(isset($_SESSION['user_id']));
}

// Test rate limiting
public function testRateLimiting() {
    for ($i = 0; $i < 6; $i++) {
        $result = attemptLogin('admin', 'wrong');
    }
    
    assertEquals('rate_limited', $result['error']);
}
```

---

#### Task 1.3: Protect All Endpoints
**Status:** Open  
**Assignee:** TBD  
**Effort:** 4 hours

**Description:**
Add authentication checks to all pages and handlers.

**Acceptance Criteria:**
- [ ] `requireAuth()` function created in `security.php`
- [ ] All pages (index.php, view.php, edit.php) check authentication
- [ ] All handlers (edit_handler.php, delete_handler.php, etc.) check authentication
- [ ] Unauthenticated users redirected to login
- [ ] AJAX handlers return 401 for unauthenticated requests

**Possible Unit Tests:**
```php
public function testUnauthenticatedAccessRedirected() {
    $_SESSION = []; // No session
    
    ob_start();
    include 'edit.php';
    $output = ob_get_clean();
    
    assertContains('Location: login.php', $output);
}
```

---

#### Task 1.4: Add Ownership Verification
**Status:** Open  
**Assignee:** TBD  
**Effort:** 6 hours

**Description:**
Verify user owns the resource before allowing modification.

**Acceptance Criteria:**
- [ ] Add `user_id` column to `rec_recipe` table
- [ ] Add `user_id` column to `rec_note` table
- [ ] `edit_handler.php` verifies current user owns recipe before update
- [ ] `delete_handler.php` verifies ownership before deletion
- [ ] `note_handler.php` verifies ownership
- [ ] `photo_handler.php` verifies ownership
- [ ] Returns 403 Forbidden if user doesn't own resource

**Possible Unit Tests:**
```php
public function testCannotEditOthersRecipe() {
    $_SESSION['user_id'] = 1;
    $_POST['id'] = 2; // Recipe owned by user 2
    
    $result = updateRecipe();
    
    assertEquals(403, $result['status']);
}
```

---

## Epic 2: Legacy Code Removal & Cleanup

**Priority:** CRITICAL  
**Effort:** Large  
**Business Value:** Reduces attack surface, improves maintainability

### Description
Remove or isolate the ~4,800 lines of unused WebCalendar code from `functions.php` and clean up legacy database abstraction.

### Tasks

#### Task 2.1: Audit functions.php Usage
**Status:** Open  
**Assignee:** TBD  
**Effort:** 4 hours

**Description:**
Identify which functions from `functions.php` are actually used by the recipes app.

**Acceptance Criteria:**
- [ ] List all functions called from `functions.php`
- [ ] Identify unused WebCalendar-specific code (calendar, event functions)
- [ ] Document dependencies between used and unused functions
- [ ] Create inventory of safe-to-remove code sections

**Possible Unit Tests:**
- N/A (audit task)

---

#### Task 2.2: Extract Used Functions
**Status:** Open  
**Assignee:** TBD  
**Effort:** 8 hours

**Description:**
Create a new `recipe_functions.php` with only the functions the recipes app needs.

**Acceptance Criteria:**
- [ ] Create `includes/recipe_functions.php`
- [ ] Copy only used functions from `functions.php`
- [ ] Remove unsafe global variable assignment code
- [ ] Update all includes to use new file
- [ ] Ensure no functionality is broken

**Possible Unit Tests:**
```php
// Test each extracted function still works
public function testExtractedFunctionsWork() {
    // Test date formatting
    assertEquals('2026-02-15', formatDate('2026-02-15'));
    
    // Test translation
    assertNotEmpty(translate('Home'));
}
```

---

#### Task 2.3: Remove Dangerous Global Assignment Code
**Status:** Open  
**Assignee:** TBD  
**Effort:** 2 hours

**Description:**
Remove the dangerous code that assigns user input directly to global variables.

**Acceptance Criteria:**
- [ ] Remove lines 92-113 from `functions.php` (or new file)
- [ ] Remove `$GLOBALS` assignment from user input
- [ ] Remove weak `<script` pattern matching
- [ ] Ensure no legitimate functionality depends on this behavior
- [ ] Test all pages still work

**Possible Unit Tests:**
```php
public function testNoGlobalAssignmentFromInput() {
    $_GET['malicious'] = '<script>alert(1)</script>';
    
    include 'includes/recipe_functions.php';
    
    assertFalse(isset($GLOBALS['malicious']));
}
```

---

#### Task 2.4: Remove Unused Files
**Status:** Completed  
**Assignee:** AI Assistant  
**Effort:** 1 hour

**Description:**
Delete files that are no longer used by the recipes application.

**Acceptance Criteria:**
- [x] Delete `includes/dbi4php.php`
- [x] Delete `load.php` (ISBN lookup - unused)
- [x] Verify no includes reference deleted files
- [x] Update documentation to reflect removed files

**Possible Unit Tests:**
- N/A (cleanup task)

---

## Epic 3: Security Hardening

**Priority:** HIGH  
**Effort:** Medium  
**Business Value:** Reduces vulnerability to attacks

### Description
Implement additional security measures to harden the application against common web vulnerabilities.

### Tasks

#### Task 3.1: Secure Database Credentials
**Status:** Completed  
**Assignee:** AI Assistant  
**Effort:** 2 hours

**Description:**
Move database credentials out of web-accessible directory or use environment variables.

**Acceptance Criteria:**
- [x] Create `.env.example` file documenting required variables
- [x] Add `.env` to `.gitignore`
- [x] Create `loadEnv()` function to read environment variables
- [x] Update `config.php` to use environment variables
- [x] Move credentials outside web root if not using env vars
- [x] Document setup process for new installations
- [x] Ensure no credentials in version control

**Possible Unit Tests:**
```php
public function testEnvFileNotInGit() {
    $gitignore = file_get_contents('.gitignore');
    assertContains('.env', $gitignore);
}

public function testCredentialsLoadedFromEnv() {
    putenv('DB_HOST=testhost');
    loadEnv();
    
    assertEquals('testhost', $db_host);
}
```

---

#### Task 3.2: Improve Error Handling
**Status:** Completed  
**Assignee:** AI Assistant  
**Effort:** 2 hours

**Description:**
Replace `die_miserable_death()` with proper error handling that doesn't leak information.

**Acceptance Criteria:**
- [x] Create error logging system (file or syslog)
- [x] Create user-friendly error page template
- [x] Log detailed errors internally (with stack traces)
- [x] Show generic error messages to users
- [x] Replace all `die()` calls with proper error handling
- [x] Ensure database errors aren't displayed
- [x] Add error level configuration (dev vs prod)

**Possible Unit Tests:**
```php
public function testDatabaseErrorNotExposed() {
    // Force database error
    $GLOBALS['db_host'] = 'invalid_host';
    
    ob_start();
    BookLogDB::connect();
    $output = ob_get_clean();
    
    assertNotContains('invalid_host', $output);
    assertContains('An error occurred', $output);
}
```

---

#### Task 3.3: Enhance Session Security
**Status:** Completed  
**Assignee:** AI Assistant  
**Effort:** 2 hours

**Description:**
Implement secure session management practices.

**Acceptance Criteria:**
- [x] Set session.cookie_httponly = true
- [x] Set session.cookie_secure = true (HTTPS only)
- [x] Set session.cookie_samesite = 'Strict'
- [ ] Implement session timeout (30 minutes idle) - *Deferred until auth implemented*
- [ ] Regenerate session ID on privilege change - *Deferred until auth implemented*
- [ ] Add session fingerprinting (IP + User-Agent hash) - *Deferred until auth implemented*
- [x] Create `destroySession()` function for logout

**Possible Unit Tests:**
```php
public function testSessionTimeout() {
    $_SESSION['last_activity'] = time() - 1801; // 30+ minutes ago
    
    checkSessionTimeout();
    
    assertEmpty($_SESSION);
}

public function testSessionRegenerationOnLogin() {
    $oldId = session_id();
    
    loginUser('admin', 'password');
    
    assertNotEquals($oldId, session_id());
}
```

---

#### Task 3.4: Add Security Headers
**Status:** Completed  
**Assignee:** AI Assistant  
**Effort:** 1 hour

**Description:**
Implement HTTP security headers to prevent common attacks.

**Acceptance Criteria:**
- [x] Add Content-Security-Policy header
- [x] Add X-Frame-Options: DENY
- [x] Add X-Content-Type-Options: nosniff
- [x] Add X-XSS-Protection: 1; mode=block
- [x] Add Referrer-Policy: strict-origin-when-cross-origin
- [x] Create function to set all security headers
- [x] Include function in `rec_includes.php`

**Possible Unit Tests:**
```php
public function testSecurityHeadersPresent() {
    ob_start();
    setSecurityHeaders();
    $headers = headers_list();
    ob_end_clean();
    
    assertContains('X-Frame-Options: DENY', $headers);
    assertContains('X-Content-Type-Options: nosniff', $headers);
}
```

---

#### Task 3.5: Implement Rate Limiting
**Status:** Completed  
**Assignee:** AI Assistant  
**Effort:** 2 hours

**Description:**
Add rate limiting for sensitive operations to prevent abuse.

**Acceptance Criteria:**
- [x] Create rate limiting function using session or database
- [x] Limit recipe imports (5 per hour)
- [ ] Limit login attempts (5 per 15 minutes) - *Deferred until auth implemented*
- [x] Limit photo uploads (20 per hour)
- [x] Return 429 Too Many Requests when limit exceeded
- [x] Log rate limit violations

**Possible Unit Tests:**
```php
public function testRateLimitEnforced() {
    for ($i = 0; $i < 6; $i++) {
        $result = checkRateLimit('import', 5, 3600);
    }
    
    assertFalse($result);
}
```

---

## Epic 4: Testing Infrastructure

**Priority:** MEDIUM  
**Effort:** Large  
**Business Value:** Ensures reliability, prevents regressions

### Description
Set up automated testing infrastructure and write comprehensive tests for critical functionality.

### Tasks

#### Task 4.1: Set Up Testing Framework
**Status:** Completed  
**Assignee:** AI Assistant  
**Effort:** 4 hours

**Description:**
Install and configure PHPUnit for unit testing.

**Acceptance Criteria:**
- [x] Install PHPUnit via Composer
- [x] Create `phpunit.xml` configuration
- [x] Create `tests/` directory structure
- [x] Set up test database configuration (MOCKED for now)
- [x] Create base test class with database setup/teardown (MOCKED)
- [x] Document how to run tests
- [x] Add test running to CI/CD (if applicable)

**Possible Unit Tests:**
- N/A (setup task)

---

#### Task 4.2: Write Security Tests
**Status:** Open  
**Assignee:** TBD  
**Effort:** 8 hours

**Description:**
Write tests for security-critical functionality.

**Acceptance Criteria:**
- [ ] CSRF token generation and validation tests
- [ ] Input sanitization tests
- [ ] SQL injection prevention tests
- [ ] XSS prevention tests
- [ ] File upload security tests
- [ ] Authentication tests (Task 1.2 examples)
- [ ] Authorization tests (Task 1.4 examples)
- [ ] Session security tests (Task 3.3 examples)

**Possible Unit Tests:**
See individual task examples above.

---

#### Task 4.3: Write Database Tests
**Status:** Open  
**Assignee:** TBD  
**Effort:** 8 hours

**Description:**
Write tests for database operations using test database.

**Acceptance Criteria:**
- [ ] Test recipe CRUD operations
- [ ] Test ingredient CRUD operations
- [ ] Test instruction CRUD operations
- [ ] Test photo upload/download
- [ ] Test note CRUD operations
- [ ] Test transaction rollback on error
- [ ] Test prepared statement parameter binding

**Possible Unit Tests:**
```php
public function testRecipeCreation() {
    $data = [
        'title' => 'Test Recipe',
        'source' => 'Test Source'
    ];
    
    $id = createRecipe($data);
    
    assertGreaterThan(0, $id);
    
    $recipe = getRecipe($id);
    assertEquals('Test Recipe', $recipe['title']);
}

public function testSqlInjectionPrevention() {
    $maliciousTitle = "Test'; DROP TABLE rec_recipe; --";
    
    $data = ['title' => $maliciousTitle];
    $id = createRecipe($data);
    
    // Table should still exist
    $result = BookLogDB::query("SELECT 1 FROM rec_recipe LIMIT 1");
    assertNotFalse($result);
}
```

---

#### Task 4.4: Write Handler Tests
**Status:** Open  
**Assignee:** TBD  
**Effort:** 8 hours

**Description:**
Write integration tests for form handlers.

**Acceptance Criteria:**
- [ ] Test edit_handler.php success path
- [ ] Test edit_handler.php validation errors
- [ ] Test delete_handler.php
- [ ] Test photo_handler.php
- [ ] Test note_handler.php
- [ ] Test favorite_handler.php
- [ ] Mock file uploads for testing
- [ ] Test CSRF validation in handlers

**Possible Unit Tests:**
```php
public function testEditHandlerCreatesRecipe() {
    $_POST = [
        'title' => 'New Recipe',
        'csrf_token' => generateCsrfToken()
    ];
    
    ob_start();
    include 'edit_handler.php';
    ob_end_clean();
    
    $recipe = getRecipeByTitle('New Recipe');
    assertNotNull($recipe);
}

public function testEditHandlerRejectsInvalidCsrf() {
    $_POST = [
        'title' => 'New Recipe',
        'csrf_token' => 'invalid_token'
    ];
    
    ob_start();
    include 'edit_handler.php';
    $output = ob_get_clean();
    
    assertContains('Invalid CSRF token', $output);
}
```

---

## Epic 5: Code Quality & Standards

**Priority:** MEDIUM  
**Effort:** Medium  
**Business Value:** Improves maintainability and readability

### Description
Apply PHP coding standards and improve code organization.

### Tasks

#### Task 5.1: Implement PSR Standards
**Status:** Open  
**Assignee:** TBD  
**Effort:** 8 hours

**Description:**
Refactor code to follow PSR-1, PSR-2, and PSR-12 coding standards.

**Acceptance Criteria:**
- [ ] Install PHP_CodeSniffer
- [ ] Create phpcs.xml configuration
- [ ] Fix indentation (4 spaces)
- [ ] Fix brace placement (same line for functions)
- [ ] Fix line length (max 120 chars)
- [ ] Add proper PHPDoc comments
- [ ] Fix naming conventions (camelCase for methods)
- [ ] Run phpcs and fix all errors

**Possible Unit Tests:**
```bash
# CI/CD test
./vendor/bin/phpcs --standard=PSR12 includes/
```

---

#### Task 5.2: Add Type Declarations
**Status:** Open  
**Assignee:** TBD  
**Effort:** 6 hours

**Description:**
Add PHP 7+ type declarations for better code safety.

**Acceptance Criteria:**
- [ ] Add parameter types to all functions
- [ ] Add return types to all functions
- [ ] Add property types to classes
- [ ] Enable strict_types=1 in files where appropriate
- [ ] Ensure no type errors introduced

**Possible Unit Tests:**
```php
public function testTypeDeclarationsWork() {
    // This should throw TypeError if wrong type passed
    $this->expectException(TypeError::class);
    sanitizeInt('not an int');
}
```

---

#### Task 5.3: Implement Autoloading
**Status:** Open  
**Assignee:** TBD  
**Effort:** 4 hours

**Description:**
Set up PSR-4 autoloading to replace manual includes.

**Acceptance Criteria:**
- [ ] Configure Composer autoload (psr-4)
- [ ] Move classes to appropriate namespace directories
- [ ] Replace all manual includes with autoload
- [ ] Ensure no circular dependencies
- [ ] Test all pages still work

**Possible Unit Tests:**
```php
public function testAutoloadingWorks() {
    $db = new \Recipes\Database\BookLogDB();
    assertInstanceOf(BookLogDB::class, $db);
}
```

---

#### Task 5.4: Implement PHPStan Static Analysis
**Status:** Open  
**Assignee:** TBD  
**Effort:** 6 hours

**Description:**
Set up PHPStan for static analysis to catch type errors and improve code quality.

**Acceptance Criteria:**
- [ ] Install PHPStan via Composer
- [ ] Create phpstan.neon configuration (start with level 5)
- [ ] Fix all level 5 errors
- [ ] Progressively increase level to 8 or 9
- [ ] Add PHPStan check to CI/CD pipeline
- [ ] Document how to run PHPStan locally

**Possible Unit Tests:**
```bash
# CI/CD test
./vendor/bin/phpstan analyse --level=5 includes/
```

---

#### Task 5.5: Set Up Composer Dependency Management
**Status:** Completed  
**Assignee:** AI Assistant  
**Effort:** 1 hour

**Description:**
Initialize Composer for PHP dependency management with policy for local assets only.

**Acceptance Criteria:**
- [x] Initialize Composer with `composer.json`
- [x] Install PHPUnit via Composer (Task 4.1 dependency)
- [x] Install PHP_CodeSniffer via Composer (Task 5.1 dependency)
- [x] Install PHPStan via Composer (Task 5.4 dependency)
- [x] **NO CDN ASSETS**: All CSS/JS assets must be local (not loaded from CDNs)
  - Keep Bootstrap, jQuery in `pub/` directory
  - Document how to update local assets
- [x] Add `composer.lock` to version control
- [x] Add `vendor/` to `.gitignore`

**Architecture Decision:**
All frontend assets (CSS, JavaScript) must be served locally from the `pub/` directory.
CDN assets are prohibited for privacy and offline functionality reasons.

**Possible Unit Tests:**
```bash
# CI/CD test
./vendor/bin/composer install --no-dev --optimize-autoloader
```

---

## Epic 6: Documentation

**Priority:** LOW  
**Effort:** Medium  
**Business Value:** Helps future developers and users

### Description
Create comprehensive documentation for the application.

### Tasks

#### Task 6.1: Create API Documentation
**Status:** Open  
**Assignee:** TBD  
**Effort:** 6 hours

**Description:**
Document all functions and classes using PHPDoc.

**Acceptance Criteria:**
- [ ] Document all functions in `includes/`
- [ ] Document all classes and methods
- [ ] Document database schema
- [ ] Generate HTML documentation with phpDocumentor
- [ ] Document handler endpoints and expected parameters

**Possible Unit Tests:**
- N/A (documentation task)

---

#### Task 6.2: Create Deployment Guide
**Status:** Open  
**Assignee:** TBD  
**Effort:** 4 hours

**Description:**
Write step-by-step deployment instructions.

**Acceptance Criteria:**
- [ ] Document server requirements (PHP version, extensions)
- [ ] Document database setup
- [ ] Document environment configuration
- [ ] Document security hardening steps
- [ ] Document backup procedures
- [ ] Include troubleshooting section

**Possible Unit Tests:**
- N/A (documentation task)

---

## Epic 7: Release Readiness & Deployment

**Priority:** HIGH  
**Effort:** Medium  
**Business Value:** Ensures the application can be safely and consistently deployed.

### Description
Prepare the application for its first official release, addressing environment-specific dependencies and finalizing documentation.

### Tasks

#### Task 7.1: Decouple from Parent Directory
**Status:** Open  
**Assignee:** TBD  
**Effort:** 4 hours

**Description:**
The application currently depends on files in `../` (e.g., `header.php`, `style.css`). These should be made optional or configurable to allow standalone deployment.

**Acceptance Criteria:**
- [ ] Identify all `../` includes and references.
- [ ] Implement checks to see if parent files exist before including.
- [ ] Provide default local versions or fallbacks for standalone mode.
- [ ] Add configuration setting to enable/disable parent site integration.

---

#### Task 7.2: Final Security Audit
**Status:** Open  
**Assignee:** TBD  
**Effort:** 8 hours

**Description:**
Perform a comprehensive manual and automated security audit before the 1.0 release.

**Acceptance Criteria:**
- [ ] Run automated vulnerability scanners (e.g., OWASP ZAP).
- [ ] Manually verify all input sanitization points.
- [ ] Confirm no sensitive information is logged in production.
- [ ] Verify all cookies are properly scoped and secured.

---

#### Task 7.3: Prepare Release Notes & Versioning
**Status:** Open  
**Assignee:** TBD  
**Effort:** 2 hours

**Description:**
Establish a formal versioning scheme and document changes.

**Acceptance Criteria:**
- [ ] Define semantic versioning policy.
- [ ] Create `CHANGELOG.md`.
- [ ] Tag the 1.0.0 release in Git.

---

## Quick Wins (Immediate Actions)

These tasks can be done immediately for quick security improvements:

1. **[x] Add .gitignore** - Prevent credentials from being committed
2. **[x] Remove dbi4php.php** - Delete unused file
3. **[x] Add security headers** - Simple addition to rec_includes.php (includes/security.php)
4. **[x] Review error messages** - Ensure no sensitive info is exposed
5. **[x] Document current settings** - Created .env.example with clear documentation

---

## Risk Assessment

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| No authentication | Certain | Critical | Epic 1 |
| Legacy code vulnerabilities | High | High | Epic 2 |
| Database credential exposure | Medium | High | Task 3.1 |
| XSS through import | Low | Medium | Review import.php |
| Session hijacking | Medium | Medium | Task 3.3 |
| File upload bypass | Low | High | Audit upload code |

---

## Success Metrics

**Security:**
- Zero critical or high vulnerabilities in security audit
- All endpoints require authentication
- No database credentials in version control

**Code Quality:**
- PSR-12 compliance (phpcs reports 0 errors)
- PHPStan level 5+ with zero errors
- 80%+ test coverage for critical paths
- Zero unused files in codebase

**Maintainability:**
- All functions documented
- Deployment time < 15 minutes for new developer
- Clear error messages for all failure modes

---

## Appendix: File Inventory

### Core Application Files
- `index.php` - Recipe listing
- `view.php` - Recipe detail view
- `edit.php` - Recipe form
- `edit_handler.php` - Form processing
- `delete_handler.php` - Deletion
- `import.php` - Recipe import
- `image.php` - Photo serving
- `favorite_handler.php` - Favorite toggle
- `photo_handler.php` - Photo management
- `note_handler.php` - Notes management

### Include Files
- `rec_includes.php` - Bootstrap
- `includes/config.php` - Configuration loader
- `includes/pdo_db.php` - Database (GOOD)
- `includes/security.php` - Security utilities (GOOD)
- `includes/functions.php` - Legacy code (NEEDS CLEANUP)
- `includes/dbtable.php` - Table utilities
- `includes/connect.php` - DB connection
- `includes/styles.php` - CSS
- `includes/js.php` - JavaScript
- `includes/translate.php` - I18n
- `includes/recipe_import.php` - Import logic
- `includes/settings.php` - Credentials

### Static Assets
- `pub/bootstrap.min.css`
- `pub/bootstrap.bundle.min.js`
- `pub/jquery.min.js`

### Build/Dependency Files
- `composer.json` - Composer dependencies configuration
- `composer.lock` - Locked dependency versions (version controlled)
- `vendor/` - Composer installed packages (gitignored)

---

## Epic 8: GitHub Integration & CI/CD

**Priority:** MEDIUM  
**Effort:** Medium  
**Business Value:** Automates quality checks and facilitates collaboration.

### Description
Set up the GitHub repository and implement basic CI/CD pipelines to ensure code quality.

### Tasks

#### Task 8.1: Initialize GitHub Repository
**Status:** Open  
**Assignee:** AI Assistant  
**Effort:** 1 hour

**Description:**
Push the local repository to GitHub and configure basic settings.

**Acceptance Criteria:**
- [ ] Create remote repository on GitHub.
- [ ] Push `main` branch.
- [ ] Set up branch protection rules for `main`.
- [ ] Add project description and relevant tags.

---

#### Task 8.2: Configure GitHub Actions for Linting
**Status:** Open  
**Assignee:** TBD  
**Effort:** 2 hours

**Description:**
Automate PHP code style checks on every push.

**Acceptance Criteria:**
- [ ] Create `.github/workflows/lint.yml`.
- [ ] Run PHP_CodeSniffer (PSR-12) on PRs.
- [ ] Fail build if linting errors are found.

---

#### Task 8.3: Configure GitHub Actions for Static Analysis
**Status:** Open  
**Assignee:** TBD  
**Effort:** 2 hours

**Description:**
Automate PHPStan analysis on every push.

**Acceptance Criteria:**
- [ ] Create `.github/workflows/static-analysis.yml`.
- [ ] Run PHPStan at level 5+.
- [ ] Fail build if analysis finds new issues.

---

**Document Version:** 1.3  
**Last Updated:** 2026-02-15 (Task 1.1 Designed, Task 4.1 Completed)  
**Next Review:** After Epic 1 completion
