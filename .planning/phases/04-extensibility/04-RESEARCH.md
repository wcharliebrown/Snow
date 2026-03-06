# Phase 4: Extensibility - Research

**Researched:** 2026-03-06
**Domain:** PHP hook execution, email OTP 2FA, admin UI extensions, MySQL schema migration
**Confidence:** HIGH — all findings based on direct codebase inspection

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Table hooks (EXT-01)**
- Two new columns on the `custom_tables` table: `pre_edit_php_filename` and `post_edit_php_filename`
- `pre_edit_php_filename` executes before the edit form is rendered (GET)
- `post_edit_php_filename` executes after the edit form is submitted (POST)
- Admin sets these filenames per-table in the table metadata admin UI
- Hook failure is fire-and-forget: the row save still succeeds; errors are logged via `logError()`
- No auto-disable of failing hooks — errors surface in logs only

**2FA / Email OTP (SEC-05)**
- Per-user opt-in: new `require_2fa` boolean/checkbox field on the users table
- After correct credentials, users with `require_2fa = 1` receive a 6-digit code by email
- User cannot complete login until the correct code is entered
- 3 wrong OTP attempts triggers a temporary account lockout (same lockout mechanism as password attempts)
- OTP code validity window: Claude's Discretion (reasonable short window, e.g. 10–15 min)
- Lockout duration: Claude's Discretion (consistent with existing password lockout policy)

**Custom pages (EXT-02)**
- Pages table already has `content` (HTML) and `custom_script` (PHP filename) — model is correct
- Admin UI for add/edit: plain `<textarea>` for HTML content, text input for PHP filename
- No changes to the serving mechanism — `pages.php` already executes `custom_script` and renders `content`

**Email templates (EXT-03)**
- `{{variable}}` syntax via existing `processTokens()` — no change needed
- Admin UI requires no variable hints or test-send feature; developer is expected to know the variable names
- `sendEmailTemplate($name, $to, $data)` already implemented in `email.php`

### Claude's Discretion
- OTP code validity window and exact lockout duration (align with existing password-lockout settings where possible)
- DB table/column for storing pending OTP codes (e.g. `login_otp` table with user_id, code, expires_at, attempts)
- Exact wording on the OTP entry screen
- Whether `pre_edit_php_filename` receives `$row` data (existing row) vs an empty context on new-record forms

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| EXT-01 | Admin can register PHP hook files that execute automatically on row create/modify for any table | Hook column schema conflict identified; execution pattern from `pages.php` `custom_script` model; `admin-custom-table.php` edit branches mapped |
| EXT-02 | Admin can create custom web pages served by the framework | Admin UI already fully exists in `admin-pages.php`; serving via `pages.php renderPage()` already works; research confirms it needs only verification |
| EXT-03 | Admin can create email templates for customized outbound messages | `admin-emails.php` and `email.php` are complete; `sendEmailTemplate()` works; research confirms verification only |
| SEC-05 | User can complete login using a short-term 6-digit code sent to their email address (2FA) | Full login flow in `login.php`/`auth.php` mapped; OTP table design specified; lockout pattern from password-policy.php understood |
</phase_requirements>

---

## Summary

Phase 4 is the least net-new-code phase in this project. Two of its four requirements (EXT-02 custom pages, EXT-03 email templates) are functionally complete — the admin UIs, data models, serving logic, and template substitution are all already shipped. The planner should treat these as verification-and-seed tasks, not build tasks.

EXT-01 (table hooks) requires a schema migration, integration into `admin-custom-table.php` (two insertion points — GET and POST branches), and an update to `admin-tables.php` (two new filename fields on the table edit form). There is a **critical schema conflict**: `database_schema.sql` already defines `custom_script_before_edit` and `custom_script_after_edit` on `custom_tables`, but the CONTEXT.md decision uses different names (`pre_edit_php_filename`/`post_edit_php_filename`). The migration must check both names and use INFORMATION_SCHEMA guards.

SEC-05 (email OTP) is the only greenfield feature. It requires: a new `login_otp` table, a `require_2fa` column on `users`, changes to the `login.php` POST handler to branch on 2FA, a new OTP entry page, and an email template for the code. The existing `sendEmailTemplate()`, `logError()`, and session-flash patterns are direct reuse targets.

**Primary recommendation:** Plan 4 plans total — (1) schema migration for all 4 requirements, (2) hook execution wiring in admin-custom-table + admin-tables, (3) 2FA login flow and OTP page, (4) verification of EXT-02/EXT-03 with seed data if missing.

---

## Standard Stack

### Core (all built-in, zero external dependencies)

| Component | Location | Purpose | Status |
|-----------|----------|---------|--------|
| `functions/auth.php` | `/home/cb/Snow/functions/auth.php` | loginUser(), session management | Exists — needs 2FA fork |
| `functions/login.php` | `/home/cb/Snow/functions/login.php` | Login page POST handler | Exists — needs OTP branch |
| `functions/email.php` | `/home/cb/Snow/functions/email.php` | sendEmailTemplate(), sendEmail() | Exists — ready to use |
| `functions/admin-custom-table.php` | `/home/cb/Snow/functions/admin-custom-table.php` | Custom table row CRUD | Exists — needs hook calls |
| `functions/admin-tables.php` | `/home/cb/Snow/functions/admin-tables.php` | Table metadata admin | Exists — needs 2 hook fields |
| `functions/admin-pages.php` | `/home/cb/Snow/functions/admin-pages.php` | Page CRUD admin | Exists — already complete for EXT-02 |
| `functions/admin-emails.php` | `/home/cb/Snow/functions/admin-emails.php` | Email template admin | Exists — already complete for EXT-03 |
| `functions/logging.php` | `/home/cb/Snow/functions/logging.php` | logError(), logInfo() | Exists — use for hook failure logging |
| `functions/pages.php` | `/home/cb/Snow/functions/pages.php` | renderPage(), custom_script include pattern | Established pattern to replicate |
| `functions/password-policy.php` | `/home/cb/Snow/functions/password-policy.php` | Lockout pattern reference | Exists — mirror for OTP lockout |

### No External Dependencies
The project has a hard zero-deps constraint. No Composer packages, no npm, no TOTP libraries. OTP generation uses PHP's built-in `random_int(100000, 999999)`.

---

## Architecture Patterns

### Established Pattern: Filename-as-Hook

`pages` table has a `custom_script VARCHAR(255)` column. `renderPage()` in `pages.php` does:

```php
// Source: /home/cb/Snow/functions/pages.php lines 45-49
if ($page['custom_script']) {
    $scriptFile = SNOW_FUNCTIONS . '/' . $page['custom_script'];
    if (file_exists($scriptFile)) {
        include $scriptFile;
    }
}
```

The `pre_edit_php_filename` / `post_edit_php_filename` columns replicate this exactly, but on `custom_tables` instead of `pages`. Hook scripts live in `SNOW_FUNCTIONS` (the `functions/` directory).

### Hook Execution: Fire-and-Forget with Error Logging

```php
// Pattern for hook execution in admin-custom-table.php
// For pre_edit_php_filename (GET branch, before form render):
$hookFile = $tableDef['pre_edit_php_filename'] ?? null;
if ($hookFile) {
    $hookPath = SNOW_FUNCTIONS . '/' . $hookFile;
    if (file_exists($hookPath)) {
        try {
            include $hookPath;
        } catch (Throwable $e) {
            logError("Hook {$hookFile} failed for table {$tableName}: " . $e->getMessage());
        }
    }
}

// For post_edit_php_filename (POST branch, after successful dbUpdate/dbInsert):
$hookFile = $tableDef['post_edit_php_filename'] ?? null;
if ($hookFile) {
    $hookPath = SNOW_FUNCTIONS . '/' . $hookFile;
    if (file_exists($hookPath)) {
        try {
            include $hookPath;
        } catch (Throwable $e) {
            logError("Hook {$hookFile} failed for table {$tableName}: " . $e->getMessage());
        }
    }
}
```

Use `Throwable` (not `Exception`) so PHP fatal errors in hook files are also caught.

### 2FA Login Flow

Current `login.php` POST flow:
1. Validate credentials with `loginUser($email, $password)`
2. On success: set session, redirect to `/admin`

Required 2FA fork after `loginUser()` returns a user:
1. Check `$user['require_2fa']`
2. If `1`: generate 6-digit code, insert into `login_otp`, send email, redirect to `/login-otp?uid={$user['id']}` (or use session to carry user_id pending 2FA)
3. OTP entry page: verify code against `login_otp`, check expiry and attempts
4. On success: complete session setup (the same lines currently run immediately in `loginUser()`)

**Session design for pending 2FA:** Store pending user_id in `$_SESSION['otp_pending_user_id']` before completing login. Do NOT set `$_SESSION['user_id']` until OTP is verified. This prevents access to protected pages during the OTP window.

### OTP Table Design (Claude's Discretion)

```sql
CREATE TABLE IF NOT EXISTS login_otp (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id    INT          NOT NULL,
    code       VARCHAR(6)   NOT NULL,
    expires_at DATETIME     NOT NULL,
    attempts   TINYINT      NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id  (user_id),
    INDEX idx_expires  (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

One row per pending OTP per user. Delete/replace on new code generation (one active code per user at a time). Expiry: 15 minutes (`DATE_ADD(NOW(), INTERVAL 15 MINUTE)`).

### OTP Lockout (Claude's Discretion)

Mirror the session-level approach: after 3 failed OTP attempts on a single `login_otp` row, set an `otp_locked_until` column on the `login_otp` row (or on `users` directly). Simpler approach: use `attempts >= 3` as the lockout trigger — delete the OTP row and set `$_SESSION['otp_pending_user_id'] = null`, forcing a full re-login. This reuses the principle of the password attempt lockout without adding a new lockout column. Lockout duration: 15 minutes (same as OTP window, so the code simply expires).

### INFORMATION_SCHEMA Migration Guard Pattern

Established across Phase 1-3. Template for Phase 4 columns:

```sql
-- Add pre_edit_php_filename to custom_tables if not present
SET @preparedStatement = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE custom_tables ADD COLUMN pre_edit_php_filename VARCHAR(255) NULL AFTER description',
        'SELECT 1'
    )
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'custom_tables'
      AND COLUMN_NAME  = 'pre_edit_php_filename'
);
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

Repeat for: `post_edit_php_filename`, `require_2fa` on `users`, and `login_otp` table (use `CREATE TABLE IF NOT EXISTS`).

### Flash Message / Redirect Pattern

All POST handlers follow `?msg=key` redirect-after-post:

```php
// Source: functions/admin-custom-table.php lines 93, 152
header('Location: /' . $page['path'] . '?msg=created');
exit;
```

OTP entry page errors use `$_SESSION['otp_error']` or inline `$error` variable (same pattern as `$page['error']` in `login.php`).

### Anti-Patterns to Avoid

- **Setting `$_SESSION['user_id']` before OTP verification:** This would grant full authenticated access during the OTP window. Always use a separate `otp_pending_user_id` key.
- **Using `Exception` catch instead of `Throwable`:** PHP fatal errors (syntax, include failures) are not `Exception` subclasses. Use `Throwable` in hook catch blocks.
- **Using ALTER TABLE without INFORMATION_SCHEMA guard:** The schema file already has `custom_script_before_edit`/`custom_script_after_edit` columns on `custom_tables`. Check which columns actually exist before altering.
- **Storing OTP code as plain integer:** Store as VARCHAR(6) with zero-padding to handle codes like `000123`.
- **Not deleting used/expired OTP rows:** Stale rows in `login_otp` could be a confusion source. Delete on: successful verification, 3-attempt lockout, new code generation.

---

## Critical Schema Conflict: Hook Column Names

**HIGH confidence finding from direct code inspection.**

`database_schema.sql` lines 200-201 define `custom_script_before_edit` and `custom_script_after_edit` on the `custom_tables` CREATE TABLE. These columns may already exist in the live database.

CONTEXT.md decides to use `pre_edit_php_filename` and `post_edit_php_filename` (different names).

**Resolution required during planning:**
- The migration must check whether `custom_script_before_edit` / `custom_script_after_edit` exist (they may, depending on when the DB was last initialized).
- The migration adds `pre_edit_php_filename` / `post_edit_php_filename` as the canonical columns.
- The application code uses only the new names. The old columns (if present) become dead columns — no rename or data migration needed since they have no data (Phase 4 is the first time hooks are used).
- The INFORMATION_SCHEMA guard handles the case where neither old nor new columns exist (fresh install) and the case where only new columns exist (idempotent re-run).

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Email delivery | Custom SMTP client | `sendEmail()` / `sendEmailTemplate()` in `email.php` | Already implemented, logs to activity_log |
| OTP code generation | Manual rand() | `random_int(100000, 999999)` | Cryptographically secure, built-in |
| Token storage for 2FA | Session-only | `login_otp` MySQL table | Survives session restart, enables expiry queries |
| Hook error isolation | `set_error_handler` | `try/catch (Throwable $e)` + `logError()` | Simple, no global state side effects |
| Template variable substitution | Custom parser | `processTokens()` in `template.php` | Already handles `{{variable}}` with htmlspecialchars |

---

## Common Pitfalls

### Pitfall 1: Completing Login Before OTP Verification
**What goes wrong:** If `loginUser()` is called successfully and `$_SESSION['user_id']` is set, the user is authenticated even before OTP entry. Any page check via `isLoggedIn()` will pass.
**Why it happens:** The natural refactor path is to call `loginUser()` then add an OTP check, but `loginUser()` already sets the session.
**How to avoid:** Fork at the credential check level. After `dbGetRow` + `password_verify` succeed (inside or parallel to `loginUser()`), do NOT set `$_SESSION['user_id']`. Instead set `$_SESSION['otp_pending_user_id']`. Only set `$_SESSION['user_id']` after OTP success.
**Warning signs:** A user can reach `/admin` by navigating directly after entering credentials but before entering OTP.

### Pitfall 2: Hook Include Breaks Form Rendering
**What goes wrong:** A hook file has a syntax error or calls `exit`. The edit form never renders; the admin sees a blank page or PHP error.
**Why it happens:** `include` runs in the same PHP process — a fatal error halts execution.
**How to avoid:** Wrap hook include in `try/catch (Throwable $e)`. Log the error. Continue with form rendering.
**Warning signs:** Admin reports blank page when editing a record for a table with a hook registered.

### Pitfall 3: OTP Code Collision / Replay
**What goes wrong:** An expired code is accepted, or a new code is generated but the old one still works.
**Why it happens:** Not deleting the old row before inserting a new one, or not checking `expires_at` on verification.
**How to avoid:** On new code generation: `DELETE FROM login_otp WHERE user_id = ?` before `INSERT`. On verification: `WHERE user_id = ? AND code = ? AND expires_at > NOW() AND attempts < 3`.
**Warning signs:** User receives "OTP sent" but old code still works.

### Pitfall 4: Schema Migration Against Pre-Existing Columns
**What goes wrong:** `ALTER TABLE custom_tables ADD COLUMN custom_script_before_edit` fails if the column already exists (MySQL error 1060 "Duplicate column name"). The migration script aborts.
**Why it happens:** The base `database_schema.sql` already creates these columns; some environments already have them.
**How to avoid:** All ALTER TABLE statements use the INFORMATION_SCHEMA guard pattern established in Phase 1. Check for the target column name (`pre_edit_php_filename`), not the old name.
**Warning signs:** Migration script throws MySQL error 1060 on any ALTER TABLE.

### Pitfall 5: EXT-02 / EXT-03 Verification Missing Edge Case
**What goes wrong:** The admin UI forms exist but the `admin-pages.php` content textarea is `htmlspecialchars()`-escaped on display, meaning HTML stored in `content` is escaped again on re-edit.
**Why it happens:** `htmlspecialchars($_POST['content'] ?? $editPage['content'] ?? '')` is correct for a textarea — it prevents double-HTML-rendering in the form, not in the served page.
**How to avoid:** Verify that `pages.php` / `renderTemplate()` outputs `content` raw (not escaped) when serving, and that the edit textarea correctly shows the raw HTML for re-editing.
**Warning signs:** Stored `<h1>` appears as `&lt;h1&gt;` in the edit form or as literal text on the served page.

---

## Code Examples

### OTP Code Generation and Storage

```php
// Generate and store OTP (in login.php POST handler, after credential success)
$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiresAt = date('Y-m-d H:i:s', strtotime('+15 minutes'));

// Delete any existing pending code for this user
dbQuery("DELETE FROM login_otp WHERE user_id = ?", [$user['id']]);

// Insert new code
dbInsert('login_otp', [
    'user_id'    => $user['id'],
    'code'       => $code,
    'expires_at' => $expiresAt,
    'attempts'   => 0,
]);

// Store pending user in session (NOT user_id)
$_SESSION['otp_pending_user_id'] = $user['id'];

// Send OTP email
sendEmailTemplate('login_otp', $user['email'], [
    'first_name' => $user['first_name'],
    'otp_code'   => $code,
]);

header('Location: /login-otp');
exit;
```

### OTP Verification

```php
// In login-otp.php POST handler
$pendingUserId = $_SESSION['otp_pending_user_id'] ?? null;
if (!$pendingUserId) {
    header('Location: /login');
    exit;
}

$submittedCode = trim($_POST['otp_code'] ?? '');
$otpRow = dbGetRow(
    "SELECT * FROM login_otp WHERE user_id = ? AND expires_at > NOW() AND attempts < 3",
    [$pendingUserId]
);

if (!$otpRow || $otpRow['code'] !== $submittedCode) {
    // Increment attempts
    if ($otpRow) {
        dbQuery("UPDATE login_otp SET attempts = attempts + 1 WHERE id = ?", [$otpRow['id']]);
        if ($otpRow['attempts'] + 1 >= 3) {
            dbQuery("DELETE FROM login_otp WHERE id = ?", [$otpRow['id']]);
            unset($_SESSION['otp_pending_user_id']);
            $error = 'Too many failed attempts. Please log in again.';
        } else {
            $error = 'Invalid code. ' . (2 - $otpRow['attempts']) . ' attempt(s) remaining.';
        }
    } else {
        $error = 'Code expired or invalid. Please log in again.';
        unset($_SESSION['otp_pending_user_id']);
    }
} else {
    // Success — complete the login
    dbQuery("DELETE FROM login_otp WHERE id = ?", [$otpRow['id']]);
    $user = dbGetRow("SELECT * FROM users WHERE id = ?", [$pendingUserId]);

    session_regenerate_id(true);
    $_SESSION['user_id']  = $user['id'];
    $_SESSION['login_time'] = time();
    unset($_SESSION['otp_pending_user_id']);

    // Update sessions table with authenticated user_id
    $sessionId = session_id();
    if ($sessionId) {
        dbQuery("UPDATE sessions SET user_id = ? WHERE session_id = ?", [$user['id'], $sessionId]);
    }

    logInfo("User completed 2FA login: {$user['id']} ({$user['email']})");

    $redirect = $_SESSION['redirect_after_login'] ?? '/admin';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
}
```

### Hook Execution in admin-custom-table.php (GET branch)

```php
// After $tableDef is loaded, before ob_start() / form render:
$preHook = $tableDef['pre_edit_php_filename'] ?? null;
if ($preHook) {
    $hookPath = SNOW_FUNCTIONS . '/' . $preHook;
    if (file_exists($hookPath)) {
        try {
            include $hookPath;
        } catch (Throwable $e) {
            logError("pre_edit hook '{$preHook}' failed for table '{$tableName}': " . $e->getMessage());
        }
    }
}
```

### Hook Execution in admin-custom-table.php (POST branch)

```php
// In the 'edit' POST branch, after successful dbUpdate(); in the 'add' POST branch, after dbInsert():
$postHook = $tableDef['post_edit_php_filename'] ?? null;
if ($postHook) {
    $hookPath = SNOW_FUNCTIONS . '/' . $postHook;
    if (file_exists($hookPath)) {
        try {
            include $hookPath;
        } catch (Throwable $e) {
            logError("post_edit hook '{$postHook}' failed for table '{$tableName}': " . $e->getMessage());
        }
    }
}
// Then redirect as usual
header('Location: /' . $page['path'] . '?msg=updated');
exit;
```

---

## State of the Art (Existing Completion Status)

| Requirement | Build Status | What's Missing |
|-------------|-------------|----------------|
| EXT-01 (table hooks) | NOT BUILT | Schema migration, admin-tables.php hook fields, admin-custom-table.php hook execution |
| EXT-02 (custom pages) | SUBSTANTIALLY COMPLETE | Verify content textarea + custom_script field exist in add/edit form; confirm renderPage() outputs content raw; seed login_otp email template |
| EXT-03 (email templates) | SUBSTANTIALLY COMPLETE | Verify admin-emails.php add/edit form is complete; confirm sendEmailTemplate() works end-to-end |
| SEC-05 (email OTP 2FA) | NOT BUILT | login_otp table, require_2fa column, login.php fork, OTP entry page, OTP email template, users admin UI checkbox |

---

## Validation Architecture

### Test Framework

| Property | Value |
|----------|-------|
| Framework | Custom `SnowTestRunner` (no external deps) |
| Config file | `tests/SnowTestRunner.php` |
| Quick run command | `php tests/test_all.php` |
| Full suite command | `php tests/test_all.php` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| EXT-01 | Hook columns exist on custom_tables | unit | `php tests/test_all.php` | ❌ Wave 0 |
| EXT-01 | pre_edit hook executes on GET edit | unit | `php tests/test_all.php` | ❌ Wave 0 |
| EXT-01 | post_edit hook executes after POST save | unit | `php tests/test_all.php` | ❌ Wave 0 |
| EXT-01 | Hook failure does not prevent row save | unit | `php tests/test_all.php` | ❌ Wave 0 |
| EXT-02 | Custom page is served by renderPage() | manual-only | — | N/A — requires live HTTP |
| EXT-02 | custom_script include pattern works | unit | `php tests/test_all.php` | ❌ Wave 0 |
| EXT-03 | sendEmailTemplate() renders {{variable}} | unit | `php tests/test_all.php` | ❌ Wave 0 |
| SEC-05 | require_2fa column exists on users | unit | `php tests/test_all.php` | ❌ Wave 0 |
| SEC-05 | login_otp table exists with correct columns | unit | `php tests/test_all.php` | ❌ Wave 0 |
| SEC-05 | OTP not accepted after expiry | unit | `php tests/test_all.php` | ❌ Wave 0 |
| SEC-05 | 3 failed OTP attempts locks out the code | unit | `php tests/test_all.php` | ❌ Wave 0 |
| SEC-05 | Session user_id not set before OTP verified | unit | `php tests/test_all.php` | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php tests/test_all.php`
- **Per wave merge:** `php tests/test_all.php`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/test_extensibility.php` — covers EXT-01 hook execution, EXT-03 template rendering, SEC-05 OTP logic
- [ ] `tests/test_all.php` — must include `test_extensibility.php` via require

---

## Open Questions

1. **Hook column name conflict — use new or old names?**
   - What we know: `database_schema.sql` defines `custom_script_before_edit`/`custom_script_after_edit`; CONTEXT.md decided on `pre_edit_php_filename`/`post_edit_php_filename`
   - What's unclear: Whether any existing live DB has data in the old columns
   - Recommendation: Since no hook logic was ever implemented, there is no data in the old columns. Treat them as dead columns. Add the new columns via INFORMATION_SCHEMA-guarded ALTER TABLE. The planner should choose the CONTEXT.md names — they are the locked decision.

2. **$row context available to pre_edit hook?**
   - What we know: CONTEXT.md marks this as Claude's Discretion
   - What's unclear: On a "new record" form, there is no existing `$row`; on an "edit" form, `$record` is already loaded
   - Recommendation: For edit forms, make `$record` available to the hook script (it is already in scope in `admin-custom-table.php`). For add forms, `$record` is an empty array `[]`. The hook script can check `empty($record['id'])` to detect new vs edit. Document this in a code comment adjacent to the include.

3. **OTP email template — pre-seeded or admin-created?**
   - What we know: `sendEmailTemplate('login_otp', ...)` requires a row in `email_templates` named `login_otp`
   - What's unclear: Should the migration seed this template, or must the admin create it manually?
   - Recommendation: Seed a default `login_otp` email template in the Phase 4 migration SQL. Without it, 2FA silently fails for all users. The admin can customize it later via the admin UI.

---

## Sources

### Primary (HIGH confidence)
- Direct inspection of `/home/cb/Snow/functions/auth.php` — loginUser() flow, session setup
- Direct inspection of `/home/cb/Snow/functions/login.php` — current POST handler structure
- Direct inspection of `/home/cb/Snow/functions/admin-custom-table.php` — GET/POST branch structure, hook insertion points
- Direct inspection of `/home/cb/Snow/functions/admin-tables.php` — table metadata edit form
- Direct inspection of `/home/cb/Snow/functions/admin-pages.php` — page CRUD completeness for EXT-02
- Direct inspection of `/home/cb/Snow/functions/admin-emails.php` — email template CRUD completeness for EXT-03
- Direct inspection of `/home/cb/Snow/functions/email.php` — sendEmailTemplate() implementation
- Direct inspection of `/home/cb/Snow/functions/pages.php` — custom_script include pattern
- Direct inspection of `/home/cb/Snow/functions/password-policy.php` — lockout pattern reference
- Direct inspection of `/home/cb/Snow/functions/logging.php` — logError() signature
- Direct inspection of `/home/cb/Snow/database_schema.sql` — existing custom_tables columns, all table schemas
- Direct inspection of `/home/cb/Snow/.planning/phases/04-extensibility/04-CONTEXT.md` — locked decisions

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all components identified by direct file inspection
- Architecture: HIGH — hook pattern directly observed in pages.php; OTP design follows established session patterns
- Pitfalls: HIGH — schema conflict confirmed by direct file comparison; auth flow risk derived from code reading
- Schema conflict finding: HIGH — confirmed `custom_script_before_edit` exists in schema.sql, `pre_edit_php_filename` in CONTEXT.md

**Research date:** 2026-03-06
**Valid until:** Stable — no external dependencies; valid until codebase changes
