# Phase 1: Security Foundations - Research

**Researched:** 2026-02-27
**Domain:** PHP session management, CSRF protection, password policy, structured database logging
**Confidence:** HIGH

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| SEC-01 | System automatically applies CSRF protection to all POST forms | Token generation/validation pattern; central enforcement point identified in `index.php`; form injection point via template system |
| SEC-02 | Sessions stored in MySQL with `SessionHandlerInterface` and `SELECT FOR UPDATE` locking | PHP `SessionHandlerInterface` is the correct native abstraction; DB schema and locking strategy documented |
| SEC-03 | Admin can configure password policy (min length, max age, reuse control); users prompted on violation | Config table pattern; policy enforcement hook in `loginUser()` and password-change flows; reuse table schema |
| SEC-04 | System logs logins, errors, and outbound emails to DB (multi-level, structured); admin can query by event type | Existing file-based `logging.php` must be replaced/supplemented with DB writes; `activity_log` table schema; admin query UI follows existing report pattern |
</phase_requirements>

---

## Summary

Snow is a zero-dependency PHP/MySQL/Apache framework. No Composer, no npm — everything is hand-rolled PHP using PDO. The framework already has a working auth system (`functions/auth.php`), file-based logging (`functions/logging.php`), and session start in `public_html/index.php`. None of the four Phase 1 security features are implemented yet: there is no CSRF token generation or validation anywhere, sessions are PHP default file-backed, password policy is hardcoded to a minimum of 8 characters with no configurability or enforcement of age/reuse, and logging writes to flat files on disk with no database persistence.

The implementation approach for each requirement is clear and well-constrained by the existing codebase patterns: function-per-file architecture, PDO for all DB access, Bootstrap 5 HTML output, and a page/report/script pattern for admin UIs. No third-party libraries are needed or permitted — all four features are standard PHP and SQL.

The critical dependency ordering within this phase is: (1) create DB tables first (`activity_log`, `sessions`, `password_policy`, `password_history`), (2) implement DB session handler (needed before sessions can store secure data reliably), (3) implement CSRF (needs sessions to store tokens), (4) implement password policy enforcement, (5) implement DB logging and admin UI. The existing `admin-logs.php` reads from flat files — it will need to be updated to query the new `activity_log` table.

**Primary recommendation:** Implement all four features as standalone PHP function files following the one-function-one-file convention, enforced centrally via `public_html/index.php` (CSRF, session init) and `functions/auth.php` (password policy, login logging). Replace flat-file logging with DB inserts while keeping the same `logError()` / `logInfo()` / `logEmail()` API so call sites do not change.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP PDO + MySQL | Built-in (PHP 7.4+) | All DB access | Already used throughout codebase; `getDbConnection()` wrapper exists |
| PHP `SessionHandlerInterface` | Built-in | Custom session storage | The native PHP interface for DB-backed sessions; no external deps needed |
| PHP `random_bytes()` + `bin2hex()` | Built-in | CSRF token generation | Cryptographically secure; already used in `resetPassword()` |
| PHP `password_hash()` / `password_verify()` | Built-in | Password hashing | Already used throughout `auth.php` and `admin-users.php` |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| Bootstrap 5 (already loaded) | 5.x | Admin UI for sessions viewer, policy config, log viewer | All admin HTML output uses Bootstrap 5 already |
| PHP `hash_equals()` | Built-in | Timing-safe CSRF token comparison | Prevents timing attacks on token validation |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| `SessionHandlerInterface` custom class | Redis session store | Redis requires external service; MySQL is already the datastore |
| CSRF hidden field + session token | SameSite=Strict cookie only | SameSite alone is not sufficient for LAMP stacks that may run on HTTP; explicit token is defense-in-depth |
| DB activity log | File log only | File logs cannot be queried by event type as required by SEC-04 |

**Installation:** None — zero external dependencies. All implementation is pure PHP + SQL.

---

## Architecture Patterns

### Recommended Project Structure

```
functions/
├── csrf.php              # generateCsrfToken(), validateCsrfToken(), csrfField()
├── session-handler.php   # SnowSessionHandler implements SessionHandlerInterface
├── password-policy.php   # getPasswordPolicy(), validatePassword(), checkPasswordAge(),
│                         # checkPasswordReuse(), recordPasswordChange()
├── logging.php           # REPLACE: logError/logInfo/logEmail now write to activity_log
├── admin-sessions.php    # Admin UI: list active sessions, force-logout
├── admin-password-policy.php  # Admin UI: configure password policy
└── admin-logs.php        # UPDATE: query activity_log table instead of flat files

database_schema.sql       # ADD: sessions, activity_log, password_policy, password_history tables
```

### Pattern 1: DB-Backed Session Handler (SEC-02)

**What:** A class implementing PHP's `SessionHandlerInterface` that stores session data in a MySQL `sessions` table. Uses `SELECT ... FOR UPDATE` to prevent race conditions when multiple requests from the same session overlap.

**When to use:** At framework init in `index.php`, before `session_start()`.

**Example:**
```php
// functions/session-handler.php
class SnowSessionHandler implements SessionHandlerInterface {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function open(string $path, string $name): bool { return true; }
    public function close(): bool { return true; }

    public function read(string $id): string|false {
        // SELECT FOR UPDATE acquires row lock — prevents concurrent session corruption
        $stmt = $this->db->prepare(
            "SELECT data FROM sessions WHERE session_id = ? AND expires_at > NOW() FOR UPDATE"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $row['data'] : '';
    }

    public function write(string $id, string $data): bool {
        $ttl = (int)(ini_get('session.gc_maxlifetime') ?: 1440);
        $stmt = $this->db->prepare(
            "INSERT INTO sessions (session_id, user_id, data, ip_address, user_agent, expires_at)
             VALUES (?, NULL, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))
             ON DUPLICATE KEY UPDATE
               data = VALUES(data),
               expires_at = VALUES(expires_at)"
        );
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
        return $stmt->execute([$id, $data, $ip, $ua, $ttl]);
    }

    public function destroy(string $id): bool {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE session_id = ?");
        return $stmt->execute([$id]);
    }

    public function gc(int $max_lifetime): int|false {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE expires_at < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }
}
```

**Session fixation prevention:** Call `session_regenerate_id(true)` immediately after successful login in `loginUser()`. This creates a new session ID, deleting the old one — prevents session fixation attacks where an attacker pre-sets a known session ID.

**Force-logout:** Delete the session row from `sessions` by `session_id` or by `user_id`. The next request from that browser will find no session data and be redirected to login.

### Pattern 2: CSRF Token (SEC-01)

**What:** A per-session CSRF token stored in `$_SESSION['csrf_token']`. Generated on first use. Injected as a hidden field into every POST form. Validated at the top of every POST handler.

**When to use:** Enforced centrally before page script execution in `index.php`, and via helper functions in every form.

**Example:**
```php
// functions/csrf.php

function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken(string $submittedToken): bool {
    $stored = $_SESSION['csrf_token'] ?? '';
    if (empty($stored) || empty($submittedToken)) {
        return false;
    }
    return hash_equals($stored, $submittedToken);
}

// Renders the hidden input field — call inside every <form>
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

// Call before processing any POST — aborts with 403 if invalid
function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        http_response_code(403);
        // Render error page using framework template
        die('Invalid or missing CSRF token. Please go back and try again.');
    }
}
```

**Central enforcement point:** In `index.php`'s `routeRequest()` or in `pages.php`'s `renderPage()`, call `requireCsrf()` before loading any page script. This means every POST to any page is protected without touching individual handler files.

**Form injection:** Add `<?= csrfField() ?>` inside every `<form method="post">`. The six existing form-producing files that need updating: `functions/login.php`, `functions/admin-users.php`, `functions/admin-tables.php`, `functions/admin-custom-table.php`, `functions/admin-logs.php`, and any template HTML files with forms.

### Pattern 3: Password Policy (SEC-03)

**What:** A `password_policy` config table with one row (min_length, max_age_days, prevent_reuse_count). A `password_history` table stores hashed previous passwords per user. On login and on password change, enforce policy and redirect to change-password page if violated.

**Example:**
```php
// functions/password-policy.php

function getPasswordPolicy(): array {
    $row = dbGetRow("SELECT * FROM password_policy LIMIT 1");
    return $row ?: ['min_length' => 8, 'max_age_days' => 0, 'prevent_reuse_count' => 0];
}

function validatePasswordStrength(string $password): array {
    $policy = getPasswordPolicy();
    $errors = [];
    if (strlen($password) < (int)$policy['min_length']) {
        $errors[] = 'Password must be at least ' . $policy['min_length'] . ' characters.';
    }
    return $errors;
}

function isPasswordExpired(int $userId): bool {
    $policy = getPasswordPolicy();
    if ((int)$policy['max_age_days'] === 0) return false;
    $row = dbGetRow(
        "SELECT password_changed_at FROM users WHERE id = ?",
        [$userId]
    );
    if (!$row || !$row['password_changed_at']) return false;
    $age = (time() - strtotime($row['password_changed_at'])) / 86400;
    return $age > (int)$policy['max_age_days'];
}

function isPasswordReused(int $userId, string $newPassword): bool {
    $policy = getPasswordPolicy();
    $count = (int)$policy['prevent_reuse_count'];
    if ($count === 0) return false;
    $history = dbGetRows(
        "SELECT password_hash FROM password_history
         WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
        [$userId, $count]
    );
    foreach ($history as $h) {
        if (password_verify($newPassword, $h['password_hash'])) return true;
    }
    return false;
}

function recordPasswordChange(int $userId, string $newHash): void {
    dbInsert('password_history', ['user_id' => $userId, 'password_hash' => $newHash]);
    dbUpdate('users', ['password_changed_at' => date('Y-m-d H:i:s')], 'id = ?', [$userId]);
}
```

**Enforcement hook:** After `loginUser()` succeeds, check `isPasswordExpired()`. If true, set `$_SESSION['require_password_change'] = true` and redirect to a password-change page. Similarly check reuse on the change-password POST.

### Pattern 4: Structured DB Logging (SEC-04)

**What:** Replace flat-file log writes in `logging.php` with inserts into an `activity_log` table. Keep the existing `logError()`, `logInfo()`, `logEmail()` function signatures — only the internal `logMessage()` implementation changes. The admin log viewer (`admin-logs.php`) queries the table instead of reading files.

**Example:**
```php
// Updated logMessage() in functions/logging.php

function logMessage(string $level, string $message, array $context = []): void {
    try {
        $db = getDbConnection();
        $stmt = $db->prepare(
            "INSERT INTO activity_log (level, event_type, message, user_id, session_id, ip_address, context)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $userId    = function_exists('getCurrentUserId') ? getCurrentUserId() : null;
        $sessionId = session_id() ?: null;
        $ip        = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt->execute([
            $level,
            strtolower($level),  // event_type: 'error', 'info', 'email', etc.
            $message,
            $userId,
            $sessionId,
            $ip,
            $context ? json_encode($context) : null,
        ]);
    } catch (Exception $e) {
        // Fallback to file log if DB unavailable (e.g. during DB connection failure itself)
        $logFile = (defined('SNOW_LOGS') ? SNOW_LOGS : dirname(__DIR__) . '/logs') . '/snow.log';
        $entry = sprintf("[%s] [%s] [User:%s] %s\n", date('Y-m-d H:i:s'), $level, 'system', $message);
        file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
```

### Anti-Patterns to Avoid

- **Rotating CSRF tokens per request:** Do not generate a new CSRF token on every form load. Per-session tokens are simpler and correct; per-request tokens break the back button and multi-tab workflows.
- **Storing session data in `$_SESSION` without DB handler set:** If `session_set_save_handler()` is called after `session_start()`, the handler is ignored. Must register handler before `session_start()`.
- **Using `SELECT ... FOR UPDATE` outside a transaction:** `FOR UPDATE` only locks within a transaction. Begin transaction in `read()`, commit in `write()`. Otherwise the lock is immediately released.
- **Logging in `logMessage()` before DB connection is available:** `logMessage()` is called from `logError()` which is called from `getDbConnection()` on failure — a recursive loop. Always have a flat-file fallback path.
- **Hardcoding password policy:** The existing `admin-users.php` has `strlen($password) < 8` hardcoded in two places. Both must be replaced with `validatePasswordStrength()`.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Secure random token | Custom PRNG | `random_bytes()` + `bin2hex()` | Already used in `resetPassword()`; cryptographically secure |
| Token comparison | `===` comparison | `hash_equals()` | Timing-safe — prevents timing-oracle attacks on token comparison |
| Password hashing | MD5, SHA1, custom | `password_hash(PASSWORD_DEFAULT)` | Already in use; bcrypt with correct cost factor |
| Session locking | Semaphore/file lock | `SELECT ... FOR UPDATE` in transaction | MySQL row-level locking is correct and portable within the existing stack |

**Key insight:** All primitive security operations (random, hash comparison, password hashing, session serialization) are provided by PHP's standard library. The work is integration plumbing, not algorithm implementation.

---

## Common Pitfalls

### Pitfall 1: SESSION Handler Registration Timing
**What goes wrong:** `session_set_save_handler($handler, true)` is called after `session_start()`, or after session functions have already been called. PHP silently ignores the handler.
**Why it happens:** `index.php` calls `session_start()` inside `initializeFramework()`. If the handler registration is done later (e.g. inside a function file loaded after), sessions fall back to file storage silently.
**How to avoid:** Register the session handler in `initializeFramework()` before `session_start()`. The handler needs the DB connection, so call `getDbConnection()` to bootstrap PDO, then register, then start session.
**Warning signs:** Sessions still work but data disappears on restart — indicates file sessions, not DB sessions.

### Pitfall 2: CSRF Token on Login Form
**What goes wrong:** Applying CSRF to the login form creates a chicken-and-egg problem: a CSRF token requires a session, but the session doesn't yet have a user. The login form is pre-auth.
**Why it happens:** Login is a GET then POST flow with no authenticated session at the start.
**How to avoid:** CSRF tokens are per-session, not per-user. The session (with a CSRF token) is started before login, so the login form CAN and SHOULD be CSRF-protected. Generate the token when rendering the login form; validate it on POST. This is correct and desirable.
**Warning signs:** If the login form is excluded from CSRF, an attacker can force-login a victim to an attacker-controlled account.

### Pitfall 3: SELECT FOR UPDATE Without a Transaction
**What goes wrong:** `SELECT ... FOR UPDATE` outside an explicit transaction still acquires a lock but releases it immediately after the statement — providing no protection.
**Why it happens:** MySQL auto-commits each statement unless a transaction is active. The lock is useless in autocommit mode.
**How to avoid:** In `SnowSessionHandler::read()`, call `$this->db->beginTransaction()` before the SELECT. In `write()`, call `$this->db->commit()`. In `destroy()` and `gc()`, use standalone queries (no transaction needed).
**Warning signs:** Concurrent requests modify each other's session data unpredictably.

### Pitfall 4: Logging Recursion on DB Failure
**What goes wrong:** `logError()` → `logMessage()` → `getDbConnection()` throws Exception → `logError()` is called again → infinite recursion → stack overflow.
**Why it happens:** `getDbConnection()` already calls `logError()` on failure (see `functions/database.php` line 31).
**How to avoid:** In the new `logMessage()`, wrap the DB insert in a try/catch. On any exception, write to flat file (not call `logError()`). Use a simple `file_put_contents()` fallback directly. The `getDbConnection()` call in `database.php` should also be guarded: check if `logError` exists before calling it, which it already does via `function_exists('logError')`.
**Warning signs:** PHP fatal error "Maximum function nesting level" during DB connection failures.

### Pitfall 5: Password Policy Table Missing on First Boot
**What goes wrong:** `password_policy` table exists but has zero rows. `getPasswordPolicy()` returns false/null. Downstream code fails on `$policy['min_length']`.
**Why it happens:** The table is created but never seeded with a default row.
**How to avoid:** `getPasswordPolicy()` must return a safe default when no row exists:  `['min_length' => 8, 'max_age_days' => 0, 'prevent_reuse_count' => 0]`. Also seed one default row in the schema migration.
**Warning signs:** PHP notices about undefined array keys; password validation silently passes all checks.

### Pitfall 6: Custom Table Provisioner Column Name Injection (STATE.md PITFALL-A06)
**What goes wrong:** The table provisioner uses `{$tableName}` and `{$fieldName}` directly in SQL strings without parameterization.
**Current assessment (HIGH confidence):** After reading `admin-tables.php`, both `$tableName` and `$fieldName` are sanitized with `preg_replace('/[^a-z0-9_]/i', '_', ...)` and then validated against `preg_match('/^[a-z][a-z0-9_]*$/', ...)` before use. The table name additionally requires a letter as the first character. The ALTER TABLE statement backtick-quotes both the table name and column name: `` ALTER TABLE `{$tableName}` ADD COLUMN `{$fieldName}` ``. This is the correct pattern for identifiers that cannot be parameterized.
**Verdict:** The sanitization chain is adequate for SQL identifier injection. The field_type is resolved through a whitelist map (`fieldTypeToSQL()`). No schema injection path is evident. Audit confirms PITFALL-A06 does not represent an exploitable vulnerability in the current code. Phase 1 audit task can be a quick verification pass, not a remediation.
**Warning signs to confirm during audit:** Ensure no path exists where `$tableName` or `$fieldName` reaches an SQL string without going through both the `preg_replace` sanitizer and the `preg_match` validator.

---

## Code Examples

Verified patterns from PHP documentation and existing codebase:

### Session Handler Registration (before session_start)
```php
// In initializeFramework() — BEFORE session_start()
require_once SNOW_FUNCTIONS . '/session-handler.php';
$sessionDb = getDbConnection();
$handler = new SnowSessionHandler($sessionDb);
session_set_save_handler($handler, true);

// Then start session as before
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', getenv('HTTPS') ? 1 : 0);
    ini_set('session.use_only_cookies', 1);
    session_start();
}
```

### Session Fixation Prevention on Login
```php
// In loginUser() in functions/auth.php, after verifying credentials:
session_regenerate_id(true);  // true = delete old session
$_SESSION['user_id'] = $user['id'];
$_SESSION['login_time'] = time();
```

### CSRF Central Enforcement in renderPage()
```php
// In functions/pages.php, inside renderPage(), before loading custom_script:
require_once SNOW_FUNCTIONS . '/csrf.php';
requireCsrf();  // Only blocks POST with invalid token; GET passes through
```

### Force-Logout via Session Delete
```php
// In a new function forceLogoutSession(string $sessionId):
function forceLogoutSession(string $sessionId): bool {
    return (bool) dbQuery("DELETE FROM sessions WHERE session_id = ?", [$sessionId])->rowCount();
}
```

### DB Schema: New Tables Required
```sql
-- Sessions table (SEC-02)
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    user_id INT NULL,
    data LONGTEXT NOT NULL DEFAULT '',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_session_id (session_id),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log table (SEC-04)
CREATE TABLE activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    level VARCHAR(20) NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    user_id INT NULL,
    session_id VARCHAR(128) NULL,
    ip_address VARCHAR(45) NULL,
    context JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_level (level),
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password policy config table (SEC-03)
CREATE TABLE password_policy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_length INT NOT NULL DEFAULT 8,
    max_age_days INT NOT NULL DEFAULT 0,
    prevent_reuse_count INT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO password_policy (min_length, max_age_days, prevent_reuse_count) VALUES (8, 0, 0);

-- Password history table (SEC-03)
CREATE TABLE password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add password_changed_at column to users table (SEC-03)
ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER last_login;
```

### Admin Pages Needed

The following new admin pages need to be registered in the `pages` table and scripted:

| URL Path | Script | Permission | Purpose |
|----------|--------|-----------|---------|
| `admin/sessions` | `admin-sessions.php` | `admin_access` | List active sessions; force-logout button |
| `admin/password-policy` | `admin-password-policy.php` | `user_management` | Configure password policy settings |

The existing `admin/logs` page and `admin-logs.php` script need to be **updated** (not replaced) to query `activity_log` table instead of reading flat files.

---

## State of the Art

| Old Approach | Current Approach | Notes |
|--------------|------------------|-------|
| File-based sessions (default PHP) | `SessionHandlerInterface` + MySQL | PHP has provided `SessionHandlerInterface` since PHP 5.4; it is the canonical way to override session storage |
| MD5/SHA1 password hashing | `password_hash(PASSWORD_DEFAULT)` (bcrypt) | Already in use in Snow |
| Per-request CSRF token | Per-session CSRF token | Per-session is simpler and avoids back-button issues; both are valid |
| Text-file log parsing | Structured DB logging with indexed columns | DB enables O(log n) queries by event type; file parsing is O(n) |

**Deprecated/outdated patterns in this codebase to fix:**
- `logging.php` currently writes to flat files — must be supplemented with DB inserts.
- `admin-logs.php` currently reads flat files via `getLogEntries()` — must be updated to query `activity_log` table.
- `admin-users.php` has hardcoded `strlen($password) < 8` checks in two places — must delegate to `validatePasswordStrength()`.
- `auth.php` `loginUser()` has no session regeneration call — must add `session_regenerate_id(true)` post-login.

---

## Open Questions

1. **Should flat-file logging be kept alongside DB logging?**
   - What we know: DB logging satisfies SEC-04. Flat file logging is redundant but adds resilience if DB is down.
   - What's unclear: User has not expressed a preference. The fallback path already handles DB-down gracefully.
   - Recommendation: Keep flat-file as a silent fallback (already in the proposed `logMessage()` implementation) but do not expose it in the admin UI. Only the DB log is admin-queryable. This satisfies SEC-04 without breaking existing resilience.

2. **Should `password_changed_at` be backfilled for existing users?**
   - What we know: Adding the column with `NULL` default means existing users have `NULL` for `password_changed_at`. `isPasswordExpired()` treats NULL as not expired (safe fallback).
   - What's unclear: Whether existing users should be required to change password on first login after this feature ships.
   - Recommendation: Leave NULL as "not expired" unless the admin explicitly sets a `max_age_days` policy. Document this behavior.

3. **Session admin page: what columns to show for active sessions?**
   - What we know: The `sessions` table has `session_id`, `user_id`, `ip_address`, `user_agent`, `created_at`, `expires_at`.
   - What's unclear: Whether session ID should be shown (security-sensitive; could be used by an insider attacker to hijack).
   - Recommendation: Show user name (joined from `users`), IP, user agent, created time, expires time, and a Force Logout button. Do not display the raw `session_id` in the UI.

---

## Existing Code Audit Summary

Files that require modification (not new files) in Phase 1:

| File | Change Required |
|------|----------------|
| `public_html/index.php` | Register `SnowSessionHandler` before `session_start()`; call `requireCsrf()` centrally |
| `functions/auth.php` | Add `session_regenerate_id(true)` in `loginUser()`; add password-expiry check post-login; update `changePassword()` to use policy validation and `recordPasswordChange()` |
| `functions/logging.php` | Update `logMessage()` to write to `activity_log` DB table with flat-file fallback |
| `functions/admin-logs.php` | Rewrite log-fetch and search to query `activity_log` table; update filter UI to match DB columns |
| `functions/admin-users.php` | Replace hardcoded `strlen($password) < 8` (two locations) with `validatePasswordStrength()` calls |
| `database_schema.sql` | Add `sessions`, `activity_log`, `password_policy`, `password_history` tables; ALTER users to add `password_changed_at` |

New files to create:

| File | Contents |
|------|---------|
| `functions/csrf.php` | `generateCsrfToken()`, `validateCsrfToken()`, `csrfField()`, `requireCsrf()` |
| `functions/session-handler.php` | `SnowSessionHandler implements SessionHandlerInterface` |
| `functions/password-policy.php` | `getPasswordPolicy()`, `validatePasswordStrength()`, `isPasswordExpired()`, `isPasswordReused()`, `recordPasswordChange()` |
| `functions/admin-sessions.php` | Admin UI: active session list, force-logout |
| `functions/admin-password-policy.php` | Admin UI: read/save password policy config |

CSRF hidden field `<?= csrfField() ?>` must be added inside all `<form method="post">` tags in:
- `functions/login.php` (login form in template)
- `functions/admin-users.php` (add, edit, delete forms)
- `functions/admin-tables.php` (add table, edit table, add field, delete field forms)
- `functions/admin-custom-table.php` (add, edit, delete record forms)
- `functions/admin-logs.php` (clear log forms)
- Any HTML template files containing `<form method="post">`

---

## Sources

### Primary (HIGH confidence)

- PHP Manual — `SessionHandlerInterface` interface: https://www.php.net/manual/en/class.sessionhandlerinterface.php
- PHP Manual — `session_set_save_handler()`: https://www.php.net/manual/en/function.session-set-save-handler.php
- PHP Manual — `session_regenerate_id()`: https://www.php.net/manual/en/function.session-regenerate-id.php
- PHP Manual — `random_bytes()`: https://www.php.net/manual/en/function.random-bytes.php
- PHP Manual — `hash_equals()`: https://www.php.net/manual/en/function.hash-equals.php
- PHP Manual — `password_hash()`: https://www.php.net/manual/en/function.password-hash.php
- MySQL 8 — `SELECT ... FOR UPDATE`: https://dev.mysql.com/doc/refman/8.0/en/innodb-locking-reads.html
- Codebase direct inspection — `public_html/index.php`, `functions/auth.php`, `functions/logging.php`, `functions/admin-tables.php`, `functions/admin-logs.php`, `database_schema.sql`

### Secondary (MEDIUM confidence)

- OWASP CSRF Prevention Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html — confirms synchronized token pattern (hidden field + session token) as primary defense for traditional form-based applications.
- OWASP Session Management Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html — confirms session regeneration on privilege change (login).

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all implementation uses PHP built-ins already present in the codebase
- Architecture: HIGH — function-per-file pattern is established; DB schema is clear; integration points identified precisely
- Pitfalls: HIGH — identified from direct code inspection of existing files, not speculation

**Research date:** 2026-02-27
**Valid until:** 2026-05-27 (90 days — stable LAMP/PHP patterns, no fast-moving ecosystem)
