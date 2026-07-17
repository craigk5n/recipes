# Security Policy

## Supported Versions

k5n Recipes has no tagged releases yet. Security fixes land on `main`,
which is the only supported version. Deploy from `main` to stay current.

## Reporting a Vulnerability

**Do not file security issues as public GitHub issues.**

To report a vulnerability, email **craig@k5n.us** with:

- A description of the vulnerability
- Steps to reproduce or a proof of concept
- The affected commit or deployment
- Any suggested fix (optional)

### What to Expect

- **Acknowledgment** within 72 hours of your report.
- **Assessment** and severity determination within 1 week.
- **Fix or mitigation** for confirmed vulnerabilities, typically within
  30 days depending on complexity.
- A coordinated disclosure timeline agreed upon with the reporter.

## Disclosure Policy

We follow coordinated disclosure:

1. Reporter notifies us privately.
2. We confirm and develop a fix.
3. We release the fix and publish a
   [GitHub Security Advisory](https://github.com/craigk5n/recipes/security/advisories).
4. Reporter may publish details after the advisory is public.

We aim to resolve confirmed vulnerabilities within 90 days of the
initial report. If more time is needed, we will communicate the revised
timeline to the reporter.

## Credit

We credit reporters in the security advisory and release notes unless
they prefer to remain anonymous. Let us know your preference when
reporting.

## Scope

The following are in scope for security reports:

- Authentication or authorization bypass (any `AUTH_MODE`, including
  recipe ownership checks and the shared-PIN unlock)
- SQL injection
- Cross-site scripting (XSS)
- Cross-site request forgery (CSRF)
- Remote code execution
- Path traversal or local file inclusion
- Unsafe handling of uploaded photos
- Information disclosure (credentials, PII, internal paths)

The following are out of scope:

- Denial of service (unless trivially exploitable)
- Issues in third-party dependencies (report to the upstream project)
- Issues requiring physical access to the server
- Social engineering

## Deployment Notes

This app has no `public/` subdirectory: the project root *is* the web
root, so every file in it is served unless the web server says otherwise.
That shapes the advice below.

- **Put `SESSION_SAVE_PATH` outside the web root.** Session files contain
  the CSRF token, `user_id` and `is_admin`. A session directory inside
  the project can be fetched over HTTP by anyone who knows a session id.
  See `.env.example` for the directory and permissions to create.
- **Do not rely on `.htaccess`.** `AllowOverride` is `None` by default
  for `/var/www` on Debian/Ubuntu, which silently disables every
  `.htaccess` in the project — including the deny-all in `storage/`. If
  you depend on one, verify it works rather than assuming:
  `curl -i https://your-host/recipes/storage/` should be `403`.
- **Keep `.env` and `includes/settings.php` unreadable.** Both hold
  database credentials. `settings.php` is safe while PHP executes it, but
  leaks in full if PHP handling is ever disabled for the directory.
- **Set `AUTH_MODE` deliberately.** The default, `open`, lets anyone who
  can reach the app edit and delete recipes.
