# Stack Research: Zero-Dependency PHP LAMP Admin Framework

**Research type:** Project Research — Stack dimension
**Project:** Snow
**Date:** 2026-02-27
**Milestone:** Subsequent (building on existing work)

---

## Context

Snow already has a working foundation: PHP 8.4-FPM, MySQL 8.0, Apache, Bootstrap 5, PDO with prepared statements, file-based session storage, a report system, custom table provisioning, and a function-per-file architecture. The constraint is absolute: no Composer, no npm, no build step.

This document prescribes the specific PHP built-ins, MySQL features, and Bootstrap 5 patterns to implement the remaining features: group-based ACL, row-level versioning, 2FA, DB sessions, CSRF protection, email, and hooks.

---

## Runtime Environment (Already Established)

| Layer | Version | Notes |
|-------|---------|-------|
| PHP | 8.4-FPM | Confirmed in `Dockerfile.php` — `FROM php:8.4-fpm` |
| MySQL | 8.0 | Confirmed in `docker-compose.yml` — `image: mysql:8.0` |
| Apache | 2.4 | Reverse proxy to PHP-FPM via FastCGI |
| Bootstrap | 5.x | CDN-loaded, no build step |
| PHP extensions | pdo, pdo_mysql, openssl, mbstring, bcmath | Installed in Dockerfile |

---

## Feature-by-Feature Stack Decisions

---

### 1. DB-Based Session Management

**Recommendation:** Implement a custom `SessionHandlerInterface` backed by a MySQL table.

**Why:** The project document specifies "highly performant DB-based sessions." File-based sessions (the current default in `php.ini`) don't scale beyond a single server and can't be inspected or invalidated from the admin panel. A DB session handler allows: listing active sessions, force-logout of any user, session fingerprinting, and tying sessions to users for audit logging.

**PHP built-ins to use:**
- `SessionHandlerInterface` — implement `open()`, `close()`, `read()`, `write()`, `destroy()`, `gc()`, and `create_sid()`
- `session_set_save_handler($handler, true)` — register before `session_start()`
- `session_regenerate_id(true)` — call on login to prevent session fixation; the `true` parameter deletes the old session

**MySQL table design:**
```sql
CREATE TABLE sessions (
    session_id   VARCHAR(128) NOT NULL PRIMARY KEY,
    user_id      INT NULL,
    ip_address   VARCHAR(45) NOT NULL,
    user_agent   VARCHAR(500) NULL,
    payload      MEDIUMBLOB NOT NULL,
    last_activity INT UNSIGNED NOT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_last_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Why MEDIUMBLOB for payload:** PHP's `session_encode()` output is binary-safe. TEXT columns can corrupt binary session data. MEDIUMBLOB avoids character set conversion.

**GC integration:** Call `session_gc()` explicitly on a percentage of requests (e.g., 1-in-100) or via a cron job. The `gc()` handler method deletes rows where `last_activity < NOW() - session_gc_maxlifetime`.

**php.ini settings already in place:** `session.cookie_httponly = 1`, `session.use_only_cookies = 1`, `session.cookie_samesite = Lax` — these stay. Add `session.cookie_secure = 1` in production.

**Confidence:** HIGH — `SessionHandlerInterface` has been stable since PHP 5.4, unchanged in PHP 8.x. This is the canonical zero-dependency approach.

**What NOT to use:** `session.save_handler = memcached` or `redis` — these require additional server infrastructure and Pecl extensions. Not zero-dependency.

---

### 2. CSRF Protection

**Recommendation:** Synchronizer Token Pattern using PHP sessions and a per-form hidden field.

**Why:** CSRF attacks forge requests from authenticated browsers. The synchronizer token pattern is the OWASP-recommended defense for traditional form-based applications. It requires no external library — only PHP's session and random byte generation.

**PHP built-ins to use:**
- `random_bytes(32)` + `bin2hex()` — generate a 64-char hex token; `random_bytes()` is cryptographically secure (PHP 7+)
- `hash_equals($storedToken, $submittedToken)` — constant-time comparison prevents timing attacks; do NOT use `===` for token comparison
- `$_SESSION['csrf_token']` — store the token server-side

**Implementation pattern (two functions, two files):**

`functions/csrf-generate.php`:
```php
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfTokenField(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}
```

`functions/csrf-verify.php`:
```php
function verifyCsrfToken(): bool {
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';
    if (!$stored || !$submitted) return false;
    return hash_equals($stored, $submitted);
}

function requireCsrfToken(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}
```

**Rotation policy:** Rotate the token on each successful login (`session_regenerate_id(true)` already resets the session, which resets the token). Do NOT rotate per-request — this breaks multi-tab usage.

**Where to call:** At the top of every POST-handling page, before processing any `$_POST` data.

**Confidence:** HIGH — this is the OWASP standard pattern for server-rendered PHP apps, no library needed.

**What NOT to use:** Double-submit cookie pattern (fragile with SameSite cookies already set), or third-party CSRF libraries (violates zero-dependency constraint).

---

### 3. Two-Factor Authentication (2FA)

Snow specifies three 2FA methods: Google OTP (TOTP), short-term 6-digit code, and email magic links.

#### 3a. TOTP (Google Authenticator / RFC 6238)

**Recommendation:** Implement TOTP in pure PHP using only `hash_hmac()` and `base64_encode()`.

**Why:** TOTP is a well-defined algorithm (RFC 6238). The full implementation is about 50 lines of PHP. No library needed. Google Authenticator, Authy, and all standard OTP apps use the same RFC 6238 standard.

**PHP built-ins to use:**
- `hash_hmac('sha1', $message, $key, true)` — HMAC-SHA1 for TOTP (the RFC mandates SHA-1 for compatibility)
- `random_bytes(20)` — generate a 20-byte (160-bit) secret key on enrollment
- `base64_encode()` / custom Base32 encoder — TOTP secrets are Base32-encoded for QR codes
- `time()` — current UNIX timestamp for the 30-second window calculation
- `pack('N*', 0, $counter)` — pack the time counter into 8 bytes for HMAC input

**Base32 encoding:** PHP has no built-in Base32. Implement a 30-line pure-PHP Base32 encoder/decoder in `functions/totp-base32.php`. This is a well-known, trivial algorithm requiring no library.

**MySQL storage:** Add `totp_secret VARCHAR(64) NULL` and `totp_enabled TINYINT(1) DEFAULT 0` to the `users` table. Store the raw Base32 secret (not the key bytes) — this is what authenticator apps need for enrollment.

**QR code generation:** Use Google Charts API (no dependency): `https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=otpauth://totp/...`. Or implement a pure-PHP QR code in ~300 lines (there are well-known pure-PHP implementations). Given the no-dependency constraint, the Google Charts API approach is simpler and acceptable for an admin tool that isn't handling classified data.

**Window tolerance:** Accept tokens from the current 30-second window and the immediately prior window (1 window = ±30 seconds) to account for clock skew. Do NOT exceed ±1 window without additional justification.

**Confidence:** HIGH for TOTP logic. MEDIUM for QR code delivery (Google Charts dependency is external but not a code dependency).

#### 3b. Short-term 6-digit code (email/SMS OTP)

**Recommendation:** Generate a random 6-digit code stored in MySQL with a 10-minute expiry.

**PHP built-ins to use:**
- `random_int(100000, 999999)` — cryptographically secure random integer; NOT `rand()` or `mt_rand()`
- MySQL expiry: `otp_expiry DATETIME`, compare with `NOW()` in the query

**MySQL table:**
```sql
CREATE TABLE otp_codes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NOT NULL,
    code_hash  VARCHAR(255) NOT NULL,  -- Store hash, not plaintext
    purpose    ENUM('login','password_reset','email_verify') NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at    DATETIME NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
);
```

**Why hash the code:** Even a 6-digit code should be hashed with `password_hash()` before storage. This prevents plaintext exposure if the DB is compromised, and is consistent with how the project already handles passwords.

**Delivery:** PHP's built-in `mail()` function (already used in `functions/email.php`). No SMTP library needed for the admin tool's 2FA use case.

**Confidence:** HIGH — pure PHP, pure MySQL, no external libraries.

#### 3c. Email Magic Links

**Recommendation:** Generate a secure random token, store its hash in MySQL, email the URL.

**PHP built-ins to use:**
- `bin2hex(random_bytes(32))` — 64-char hex token (already used in `functions/auth.php` for password reset tokens — exact same pattern)
- `password_hash($token, PASSWORD_DEFAULT)` — store hashed, verify with `password_verify()`
- Or simpler: store raw token but use a one-time-use flag and expiry (already done for `reset_token` in the `users` table)

**Why this is already 90% implemented:** The existing `resetPassword()` in `functions/auth.php` uses the exact same pattern. Magic link 2FA reuses `bin2hex(random_bytes(32))`, `email_templates`, and `sendEmailTemplate()`. The delta is a separate `magic_links` table and a verification endpoint.

**Confidence:** HIGH — identical pattern to existing password reset.

---

### 4. Group-Based ACL with Row-Level Permissions

**Recommendation:** Store group membership in the existing `user_groups` junction table. Store row-level ACL as comma-separated group IDs in `view_groups` and `edit_groups` columns on each managed table.

**Why comma-separated IDs (not a junction table) for row-level ACL:** A junction table approach (`row_permissions` with `table_name`, `row_id`, `group_id`) creates O(rows × groups) storage and requires a JOIN on every data read. For an admin framework where groups are small in number (typically < 20), storing `view_groups = '1,3,5'` in the row itself is simpler, faster to query with `FIND_IN_SET()`, and consistent with the project's preference for simplicity.

**MySQL built-in to use:**
- `FIND_IN_SET(group_id, view_groups)` — MySQL built-in function; returns the position of `group_id` in the comma-separated string; non-zero means access granted
- Example: `WHERE FIND_IN_SET(3, view_groups) > 0 OR view_groups IS NULL OR view_groups = ''`
- `NULL` or empty `view_groups` means "all groups can view" (public within the admin)

**PHP ACL check pattern:**

`functions/acl-check.php`:
```php
function userCanView(int $userId, array $row): bool {
    $viewGroups = $row['view_groups'] ?? '';
    if (!$viewGroups) return true; // unrestricted
    $userGroups = getUserGroupIds($userId); // returns array of int IDs
    foreach ($userGroups as $gid) {
        if (in_array($gid, explode(',', $viewGroups))) return true;
    }
    return false;
}
```

**Performance:** Cache `getUserGroupIds()` result in `$_SESSION['group_ids']` after login. Invalidate on logout or group change. One session read per request, zero DB queries for ACL checks during the request.

**Standard fields on every managed table:**
```sql
view_groups  VARCHAR(500) NULL,   -- comma-sep group IDs, NULL=all
edit_groups  VARCHAR(500) NULL,   -- comma-sep group IDs, NULL=all
status       ENUM('active','inactive','deleted') DEFAULT 'active',
created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
created_by   INT NULL,
modified_by  INT NULL
```

Note: The existing `provisionCustomTable()` already creates `created_at` and `updated_at`. Extend it to add the ACL and audit columns.

**Confidence:** HIGH for the data model. MEDIUM for `FIND_IN_SET()` at very large row counts (>100k rows) — at that scale a junction table is more efficient, but for an admin framework this threshold is rarely reached.

**What NOT to use:** JSON columns for group lists — `JSON_CONTAINS()` is slower than `FIND_IN_SET()` and JSON is overkill for a list of integers. Also avoid a full RBAC library (violates zero-dependency constraint).

---

### 5. Row-Level Versioning

**Recommendation:** A single `row_versions` table stores JSON snapshots of every row before each change.

**Why JSON:** PHP 8.4 has `json_encode()` / `json_decode()` built in. Storing a full row snapshot as JSON in a single column is simpler than a column-per-change EAV approach and enables rollback without knowing the schema at query time.

**MySQL table:**
```sql
CREATE TABLE row_versions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    table_name  VARCHAR(255) NOT NULL,
    row_id      INT NOT NULL,
    version     INT NOT NULL DEFAULT 1,
    changed_by  INT NULL,
    changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    action      ENUM('create','update','delete') NOT NULL,
    snapshot    JSON NOT NULL,          -- full row data before this change
    diff        JSON NULL,              -- changed fields only {field: [old, new]}
    INDEX idx_table_row (table_name, row_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_changed_by (changed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**PHP built-ins to use:**
- `json_encode($rowArray, JSON_UNESCAPED_UNICODE)` — snapshot the row
- `array_diff_assoc($oldRow, $newRow)` — compute diff for the `diff` column
- `json_decode($snapshot, true)` — restore from snapshot on rollback

**MySQL JSON type:** Available since MySQL 5.7.8; confirmed available in MySQL 8.0. The `JSON` column type validates JSON on insert and enables `->` operator queries if needed.

**Version capture trigger point:** In the `dbUpdate()` function (already in `functions/database.php`), add a pre-update hook: fetch the current row, snapshot it, then perform the update and write the version record. This centralizes versioning without modifying every call site.

**Rollback implementation:** Select the desired version's `snapshot` JSON, `json_decode()` it, then call `dbUpdate()` with the decoded array. The rollback itself creates a new version record (action=`update`) so the rollback is auditable.

**Diff display:** Use `array_diff_assoc()` to show changed fields. Render as a two-column table (before/after) in the admin. Bootstrap 5 provides `table-success` / `table-danger` row classes for visual diff highlighting.

**Confidence:** HIGH — `json_encode/decode` are core PHP, MySQL JSON type is available in 8.0.

**What NOT to use:** MySQL triggers for versioning — triggers are invisible to the application, hard to debug, and can't access PHP session data to record `changed_by`. Application-level versioning is the correct approach here.

---

### 6. Email (Without External SMTP Library)

**Recommendation:** Continue using PHP's built-in `mail()` function for simple outbound email. The existing `functions/email.php` is correct.

**Why `mail()` is sufficient for this use case:** Snow is an admin framework, not a transactional email platform. The volumes are low (2FA codes, password resets, admin notifications). `mail()` delegates to the server's MTA (Postfix, Sendmail, or a configured relay).

**PHP built-ins to use:**
- `mail($to, $subject, $body, $additionalHeaders)` — already implemented
- `filter_var($email, FILTER_VALIDATE_EMAIL)` — validate email addresses before sending (already in `validateEmailTemplate()`)
- `mb_encode_mimeheader($subject, 'UTF-8', 'B')` — encode non-ASCII subjects for RFC 2047 compliance; use when subject might contain Unicode characters

**Additional headers for deliverability (no library needed):**
```
Content-Type: text/html; charset=UTF-8
MIME-Version: 1.0
X-Mailer: Snow Framework
```

**For SMTP relay (without a library):** Use `fsockopen()` to open a raw TCP connection to an SMTP server and implement the SMTP handshake in pure PHP (EHLO, AUTH LOGIN, MAIL FROM, RCPT TO, DATA). This is ~80 lines. Only needed if the hosting environment does not have a local MTA. Implement in `functions/smtp.php` as a fallback.

**Confidence:** HIGH for `mail()`. MEDIUM for raw SMTP via `fsockopen()` (more implementation work, but entirely feasible with no libraries).

**What NOT to use:** PHPMailer, SwiftMailer, Symfony Mailer — all require Composer. Raw `fsockopen()` SMTP is the zero-dependency alternative when `mail()` is unavailable.

---

### 7. Hooks (Custom PHP Code on Row Create/Modify)

**Recommendation:** File-based hook system. For each table, look for a file at `hooks/{table_name}_create.php` and `hooks/{table_name}_update.php`. Include the file if it exists, passing the row data via a local variable.

**Why file-based:** This matches the project's existing architecture (function-per-file, reports-as-files). It avoids a hook registry table, which adds complexity. Developers add hooks by dropping a PHP file — no admin UI needed to register them.

**PHP built-ins to use:**
- `file_exists()` — check for hook file before including
- `include_once` or `require_once` — execute the hook file in the current scope
- Alternatively: `call_user_func()` if hooks are registered as named functions

**Hook contract:**
```
hooks/
  products_create.php   -- receives $row (array), $userId (int)
  products_update.php   -- receives $row (array), $oldRow (array), $userId (int)
  users_update.php      -- etc.
```

**Dispatch pattern in `functions/hooks-dispatch.php`:**
```php
function dispatchHook(string $event, string $table, array $row, array $oldRow = []): void {
    $hookFile = SNOW_ROOT . '/hooks/' . $table . '_' . $event . '.php';
    if (file_exists($hookFile)) {
        $userId = getCurrentUserId();
        include $hookFile;  // $row, $oldRow, $userId available in hook scope
    }
}
```

**Call sites:** In `dbInsert()` after successful insert, call `dispatchHook('create', $table, $row)`. In `dbUpdate()` after successful update, call `dispatchHook('update', $table, $newRow, $oldRow)`.

**Security note:** Hook files must be outside `public_html/` (in `hooks/` at the project root, not web-accessible). This is already consistent with the project's directory structure where only `public_html/` is web-accessible.

**Confidence:** HIGH — this pattern is used by WordPress (wp-content/plugins), Drupal, and many other file-based PHP systems.

---

### 8. Password Policy

**Recommendation:** Configurable via `.env` variables, enforced in `functions/encryption.php` (already partially implemented in `validatePasswordStrength()`).

**PHP built-ins to use:**
- `strlen($password)` — minimum length check (already implemented)
- `preg_match()` — complexity rules (already implemented for uppercase, lowercase, digits)
- `password_hash($password, PASSWORD_BCRYPT, ['cost' => 12])` — bcrypt with cost 12 is the right balance in 2026 (cost 10 was the 2013 standard; hardware has improved)
- For password reuse: store last N hashes in a `password_history` table; check each with `password_verify()`

**New `.env` variables to add:**
```
PASSWORD_MIN_LENGTH=12
PASSWORD_MAX_AGE_DAYS=90
PASSWORD_HISTORY_COUNT=5
PASSWORD_REQUIRE_SPECIAL=1
```

**Confidence:** HIGH — entirely PHP built-ins.

---

### 9. Bootstrap 5 Integration Patterns

**Recommendation:** Use Bootstrap 5 exclusively via CDN. No build step, no SCSS compilation, no npm.

**CDN URL (pinned, not `latest`):**
```html
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH"
      crossorigin="anonymous">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmLO5A7eBB9cXoRlhBe4lFiqTMO0"
        crossorigin="anonymous"></script>
```

**Why pin to 5.3.3 with SRI:** CDN `latest` can deliver breaking changes. SRI (Subresource Integrity) hashes prevent CDN compromise from injecting malicious code.

**Bootstrap 5 components used or needed:**

| Component | Use Case | Notes |
|-----------|----------|-------|
| `table table-striped table-hover` | All report list views | Already in use |
| `badge bg-success/danger/warning` | Status indicators | Already in use |
| `alert alert-success/danger` | Flash messages | Already in use |
| `form-control`, `form-select` | All edit forms | Already in use |
| `modal` | Confirm delete, 2FA enrollment QR | No JS library needed — Bootstrap's own JS handles this |
| `nav nav-tabs` | Version history, diff view | Built-in Bootstrap component |
| `pagination` | Report pagination | Already in use |
| `collapse` | Expandable row details | Built-in Bootstrap JS |
| `toast` | Non-blocking notifications | Built-in Bootstrap JS |

**No JavaScript libraries beyond Bootstrap:** Bootstrap 5 dropped jQuery. Bootstrap's bundled JS (`bootstrap.bundle.min.js`) includes Popper.js. No additional JS framework is needed or appropriate.

**For the 2FA TOTP QR code:** Use Bootstrap's `modal` to display the QR image. The image itself comes from Google Charts API (no dependency) or a pure-PHP QR library included in the project.

**Confidence:** HIGH — Bootstrap 5 CDN with SRI is the standard zero-dependency UI approach for server-rendered PHP apps in 2026.

**What NOT to use:** React, Vue, Alpine.js, Tailwind (all require a build step or npm). jQuery (Bootstrap 5 doesn't need it).

---

## What NOT to Use (and Why)

| Item | Why Not |
|------|---------|
| Composer packages (e.g., PHPMailer, Google2FA, robthree/twofactorauth) | Violates zero-dependency constraint; requires Composer; adds deployment complexity |
| npm / Webpack / Vite | No build step is a hard constraint; Bootstrap CDN satisfies all UI needs |
| Redis / Memcached for sessions | Requires additional server infrastructure; MySQL is already present |
| MySQL triggers for versioning | Cannot access PHP session context (`changed_by`); invisible to the application layer; hard to debug |
| JWT for session tokens | Stateless JWT cannot be invalidated server-side without a blocklist (which requires DB anyway); DB sessions are simpler and fully revocable |
| SPAs (React, Vue) | No build step constraint; server-rendered PHP with Bootstrap 5 is sufficient for an admin tool |
| `eval()` for dynamic hooks | Security risk; file-based hooks are safer and auditable |
| `md5()` or `sha1()` for password or token hashing | Cryptographically broken; use `password_hash()` / `hash_hmac('sha256', ...)` |
| `rand()` or `mt_rand()` for security tokens | Not cryptographically secure; use `random_bytes()` / `random_int()` |

---

## PHP Function Reference by Feature Area

| Feature | PHP Function(s) |
|---------|----------------|
| Password hashing | `password_hash()`, `password_verify()`, `password_needs_rehash()` |
| Secure random tokens | `random_bytes()`, `bin2hex()`, `random_int()` |
| Token comparison | `hash_equals()` — constant-time, prevents timing attacks |
| TOTP HMAC | `hash_hmac('sha1', $msg, $key, true)` |
| AES encryption | `openssl_encrypt()`, `openssl_decrypt()`, `openssl_random_pseudo_bytes()` |
| Email | `mail()`, `filter_var($e, FILTER_VALIDATE_EMAIL)` |
| Session management | `SessionHandlerInterface`, `session_set_save_handler()`, `session_regenerate_id()` |
| JSON versioning | `json_encode()`, `json_decode()`, `array_diff_assoc()` |
| Input sanitization | `htmlspecialchars()`, `filter_input()`, `intval()`, `trim()` |
| Database | `PDO`, `PDO::prepare()`, `PDOStatement::execute()` |
| File hooks | `file_exists()`, `include` |
| CSRF tokens | `random_bytes()`, `hash_equals()`, `$_SESSION` |

---

## MySQL Feature Reference by Feature Area

| Feature | MySQL Construct |
|---------|----------------|
| Session storage | InnoDB table, `MEDIUMBLOB` payload, `INT UNSIGNED` timestamp |
| Row-level ACL | `FIND_IN_SET(group_id, view_groups)` |
| Versioning | `JSON` column type (MySQL 8.0+), `ON UPDATE CURRENT_TIMESTAMP` |
| TOTP secrets | `VARCHAR(64)` for Base32-encoded secret |
| OTP codes | `DATETIME` for expiry, `password_hash()` stored in `VARCHAR(255)` |
| Password history | Separate `password_history` table with `VARCHAR(255) hash` + `DATETIME created_at` |
| Staged activation | `activate_at DATETIME NULL`, `deactivate_at DATETIME NULL` — checked in report WHERE clause |
| Audit columns | `created_by INT NULL REFERENCES users(id)`, `modified_by INT NULL REFERENCES users(id)` |
| Soft delete | `status ENUM('active','inactive','deleted')` — already in use throughout |

---

## Confidence Summary

| Recommendation | Confidence | Rationale |
|---------------|-----------|-----------|
| `SessionHandlerInterface` for DB sessions | HIGH | Stable PHP API since 5.4, zero deps |
| CSRF via `random_bytes()` + `hash_equals()` | HIGH | OWASP standard pattern |
| TOTP via `hash_hmac('sha1', ...)` | HIGH | RFC 6238 implementation in ~50 lines |
| 6-digit OTP via `random_int()` | HIGH | Simple, no library needed |
| Email magic links (same as password reset) | HIGH | Already 90% implemented |
| `FIND_IN_SET()` for row-level ACL | HIGH | Native MySQL, fast for small group counts |
| JSON snapshots for row versioning | HIGH | MySQL 8.0 JSON type + PHP `json_encode` |
| File-based hooks | HIGH | Proven pattern, consistent with existing architecture |
| Bootstrap 5 CDN + SRI | HIGH | Standard zero-dep UI approach |
| Raw SMTP via `fsockopen()` | MEDIUM | More code to write, but no library needed |
| `FIND_IN_SET()` at >100k rows | MEDIUM | Performance degrades at scale; acceptable for admin use |
| Google Charts for QR codes | MEDIUM | External HTTP dependency, not a code dependency; acceptable for internal admin |

---

## Architecture Principles Reinforced

1. **Function-per-file:** Each feature above maps to 1-3 new PHP files (e.g., `functions/csrf-generate.php`, `functions/csrf-verify.php`, `functions/session-handler.php`). No file should exceed ~200 lines.

2. **DB first, file second:** Session data in DB. Version data in DB. Hook files on disk. Config in `.env`. This matches the existing pattern.

3. **No magic:** Every security function uses PHP built-ins only. No frameworks, no autoloaders beyond the existing `spl_autoload_register`.

4. **Consistent error handling:** All new functions follow the existing pattern: return `false` on failure, call `logError()`, never expose internal details to the browser in production.

---

*Generated: 2026-02-27*
