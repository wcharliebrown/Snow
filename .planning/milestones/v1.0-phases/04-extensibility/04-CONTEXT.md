# Phase 4: Extensibility - Context

**Gathered:** 2026-03-06
**Status:** Ready for planning

<domain>
## Phase Boundary

Admins can register PHP hook files that fire on the edit flow for any custom table; users with 2FA enabled complete login with a 6-digit email OTP; admins can create custom web pages with HTML content and an optional PHP script; admins can create email templates with named `{{variable}}` placeholders that sending code renders by name.

</domain>

<decisions>
## Implementation Decisions

### Table hooks (EXT-01)
- Two new columns on the `custom_tables` table: `pre_edit_php_filename` and `post_edit_php_filename`
- `pre_edit_php_filename` executes before the edit form is rendered (GET)
- `post_edit_php_filename` executes after the edit form is submitted (POST)
- Admin sets these filenames per-table in the table metadata admin UI
- Hook failure is fire-and-forget: the row save still succeeds; errors are logged via `logError()`
- No auto-disable of failing hooks — errors surface in logs only

### 2FA / Email OTP (SEC-05)
- Per-user opt-in: new `require_2fa` boolean/checkbox field on the users table
- After correct credentials, users with `require_2fa = 1` receive a 6-digit code by email
- User cannot complete login until the correct code is entered
- 3 wrong OTP attempts triggers a temporary account lockout (same lockout mechanism as password attempts)
- OTP code validity window: Claude's Discretion (reasonable short window, e.g. 10–15 min)
- Lockout duration: Claude's Discretion (consistent with existing password lockout policy)

### Custom pages (EXT-02)
- Pages table already has `content` (HTML) and `custom_script` (PHP filename) — model is correct
- Admin UI for add/edit: plain `<textarea>` for HTML content, text input for PHP filename
- No changes to the serving mechanism — `pages.php` already executes `custom_script` and renders `content`

### Email templates (EXT-03)
- `{{variable}}` syntax via existing `processTokens()` — no change needed
- Admin UI requires no variable hints or test-send feature; developer is expected to know the variable names
- `sendEmailTemplate($name, $to, $data)` already implemented in `email.php`

### Claude's Discretion
- OTP code validity window and exact lockout duration (align with existing password-lockout settings where possible)
- DB table/column for storing pending OTP codes (e.g. `login_otp` table with user_id, code, expires_at, attempts)
- Exact wording on the OTP entry screen
- Whether `pre_edit_php_filename` receives `$row` data (existing row) vs an empty context on new-record forms

</decisions>

<specifics>
## Specific Ideas

- Hook model mirrors `custom_script` on pages — same file-reference pattern, consistent across the framework
- 2FA uses the same temporary lockout the password system uses — no new lockout mechanism needed

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `admin-pages.php`: full CRUD for custom pages already exists — EXT-02 is largely complete; verify content textarea and custom_script field are in the add/edit form
- `admin-emails.php` + `email.php`: email template CRUD and `sendEmailTemplate()` already work — EXT-03 is largely complete
- `pages.php renderPage()`: already executes `custom_script` via `include $scriptFile` — no changes needed for EXT-02 serving
- `processTokens()` in `template.php`: handles `{{variable}}` substitution — used by email and page rendering
- `logError()` in `logging.php`: use for hook failure logging
- `sendEmail()` / `sendEmailTemplate()` in `email.php`: use to deliver OTP code via an email template
- Existing password lockout logic in `auth.php`: reuse or mirror for OTP attempt lockout

### Established Patterns
- Filename-as-hook pattern: `custom_script` on pages table is the established model — replicate for `pre_edit_php_filename`/`post_edit_php_filename` on `custom_tables`
- Flash messages via `?msg=` GET param — same pattern for 2FA flow messages
- Permission guard via `requirePermission()` — hooks fields accessible via `table_management` permission
- ALTER TABLE with INFORMATION_SCHEMA guard (MySQL 8.0 compatible) — use for adding columns to `custom_tables`

### Integration Points
- `admin-custom-table.php` GET branch (edit form render): add `pre_edit_php_filename` include before form output
- `admin-custom-table.php` POST branch (after successful `dbUpdate`/`dbInsert`): add `post_edit_php_filename` include
- `admin-tables.php` (table metadata edit form): add two new filename fields for hook registration
- `login.php` POST handler: after credential check, if `require_2fa = 1`, generate OTP, send email, redirect to OTP entry screen instead of completing login
- `users` table: add `require_2fa` column via migration
- New `login_otp` table (or similar): stores pending codes with expiry and attempt count

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 04-extensibility*
*Context gathered: 2026-03-06*
