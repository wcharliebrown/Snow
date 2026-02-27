# Features Research: Zero-Dependency PHP LAMP Admin Framework

**Project:** Snow
**Research type:** Project Research — Features dimension
**Date:** 2026-02-27
**Milestone:** Subsequent (building on existing work)

---

## Context

Snow is a zero-dependency PHP admin framework. The following are already shipped:

- File-based report system (`reports/*.php` class files, synced to DB)
- Custom table provisioning (MySQL table + admin page + report auto-generated)
- Admin list views rendered through report templates
- User management, group management, permission management
- Session-based auth (PHP native sessions), password hashing via `password_hash()`
- Email template system with token substitution and unsubscribe tokens
- Snapshot recording (row-count metadata stored, not actual data copy)
- Plugin registry (metadata only, no runtime hook execution yet)
- AES-256-CBC field encryption with file-based key management
- File-based multi-level logging (ERROR, INFO, TRAFFIC, EMAIL channels)
- Custom web pages with per-page template, auth requirements, and permissions
- Basic password validation (min length, uppercase/lowercase/digit checks)

This research covers the **remaining features** to build and establishes the full feature taxonomy.

---

## What Admin/CMS Platforms Typically Include

### Industry-standard feature set (across WordPress Admin, Directus, Strapi, Filament, Nova)

**Auth & Identity**
- Login / logout / password reset
- Session management (timeout, concurrent session control)
- Role/permission system
- Two-factor authentication (TOTP app, email OTP, SMS)
- Password policies (complexity, expiry, reuse prevention)
- Account lockout after failed attempts

**Content & Data Management**
- CRUD for every managed entity
- Field types: text, textarea, number, date, boolean, select, file upload
- Required fields, field-level validation
- Bulk operations (bulk delete, bulk status change)
- Soft delete (status flag) vs hard delete
- Search, sort, filter on list views
- Pagination

**Access Control**
- Role-based access control (RBAC) at the feature/page level
- Row-level access control (who can see/edit which record)
- Ownership model (record belongs to creating user)

**Versioning & Audit**
- Who changed what, when (audit log)
- Version history per record with rollback
- Diff view between versions

**Scheduling & Workflow**
- Publish/unpublish on a future date
- Draft/published/archived status
- Approval workflow (draft -> review -> published)

**Notifications & Messaging**
- Email templates for system notifications
- In-app notification feed
- Outbound email log

**Extensibility**
- Hooks/callbacks on CRUD events
- Plugin/module system
- Custom field types
- Custom admin pages
- API (REST or GraphQL)

**Operations**
- Activity/audit log
- Error log
- Backup / export
- Data import (CSV)
- System health dashboard

---

## Feature Taxonomy for Snow

### TABLE STAKES
*Features users expect in any admin framework. Missing them means users abandon the tool.*

| Feature | Status | Complexity | Notes |
|---|---|---|---|
| Login / logout | Shipped | Low | `auth.php`, `login.php`, `logout.php` |
| Password reset via email token | Shipped | Low | `resetPassword()` in `auth.php` |
| User CRUD | Shipped | Low | `admin-users.php` |
| Group/role management | Shipped | Low | `admin-groups.php` with permissions |
| Permission-gated pages | Shipped | Low | `requirePermission()` in all admin files |
| CRUD for any custom table | Shipped | Medium | `admin-custom-table.php` |
| Pagination on list views | Shipped | Low | `renderReport()` + `buildPagination()` |
| Basic password validation | Shipped | Low | `validatePasswordStrength()` |
| Email templates | Shipped | Medium | `email.php`, `admin-emails.php` |
| Custom web pages | Shipped | Medium | `admin-pages.php`, `pages.php` |
| Multi-level logging | Shipped | Low | `logging.php` — file-based |
| CSRF protection | **Not built** | Low | Every POST form needs a token |
| DB-based session management | **Not built** | Medium | Native PHP sessions are file-based |
| Session timeout enforcement | Partial | Low | `checkSessionTimeout()` exists but not enforced globally |
| Search / sort / filter on list views | **Not built** | Medium | Report system has `sql_where`/`sql_order` but no UI controls |
| Soft delete (status flag) | Partial | Low | Some tables use `status` field; not universal |

**Why these are table stakes:** Any admin tool users evaluate first checks: "Can I log in securely? Can I manage data? Can I search? Is it CSRF-protected?" Failure on any of these is a deal-breaker.

---

### DIFFERENTIATORS
*Features that distinguish Snow from generic admin generators. These are the competitive moat.*

| Feature | Status | Complexity | Notes |
|---|---|---|---|
| File-based report system (reports/*.php) | Shipped | Medium | Unique: reports are version-controlled PHP class files |
| Any-table CRUD auto-provisioned | Shipped | Medium | One pattern for all tables |
| Group-based row-level view ACL | **Not built** | High | See implementation notes below |
| Group-based row-level edit ACL | **Not built** | High | Separate from view ACL |
| Row-level version control | **Not built** | High | Full history per record per table |
| Rollback to any prior version | **Not built** | Medium | Depends on version storage |
| Table snapshots (actual data copy) | **Partial** | Medium | Current `snapshots` table stores metadata only, not data |
| Diff tool (table vs snapshot) | **Not built** | High | Show field-level changes since snapshot |
| Snapshot restore | **Not built** | Medium | Depends on actual data snapshots |
| Two-factor authentication | **Not built** | High | TOTP + email magic link; see notes |
| Password policy (max age, reuse) | **Partial** | Low | Complexity rules exist; age/reuse history not built |
| Staged activation/deactivation | **Not built** | Medium | Scheduled publish/unpublish per row |
| Hooks/callbacks on row events | **Not built** | Medium | See implementation notes |
| DB-based sessions | **Not built** | Medium | Needed for session management at scale |

**Why these are differentiators:** Most LAMP admin tools either lock you into their data model (WordPress) or require Composer/npm (Filament, Strapi). Snow's combination of zero-dependency + any-table provisioning + full versioning is unusual. Row-level ACL in pure PHP without an ORM is rare.

---

### ANTI-FEATURES
*Things to deliberately NOT build. Including them would compromise the core constraint or waste effort.*

| Feature | Reason to exclude |
|---|---|
| REST/GraphQL API | Out of scope per PROJECT.md; adds complexity without serving admin use cases |
| File upload management | S3/filesystem concerns; not a LAMP-native feature; opens security surface |
| WebSockets / real-time notifications | Not a LAMP fit; requires Node or long-polling hack |
| In-app notification feed | High complexity, low value for admin tools; email covers it |
| Approval workflow (draft -> review -> published) | Scope creep; staged activation covers the 80% case |
| Import from CSV | Useful but high edge-case complexity (encoding, mapping); ship later |
| SMS-based 2FA | Requires Twilio or carrier API; violates zero-dependency constraint |
| CAPTCHA | External dependency (reCAPTCHA); rate limiting + lockout is sufficient |
| Installer/setup wizard | Adds maintenance burden; .env file is sufficient for zero-dep setup |
| Plugin marketplace / remote plugin install | Security risk; plugin registry is for custom code, not remote packages |
| OAuth / SSO (Google login, SAML) | External dependency; password + 2FA covers admin use cases |
| Markdown editor / WYSIWYG | External JS dependency (TinyMCE, etc.); plain textarea is dependency-free |

---

## Implementation Notes for Complex Features

### 1. CSRF Protection

**How it works in pure PHP:**
Every HTML form includes a hidden token generated from `session_id()` + a secret salt and stored in `$_SESSION['csrf_token']`. On POST, the submitted token is verified against the session token before processing.

**Implementation approach for Snow:**
```php
// In a new functions/csrf.php
function generateCsrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(): bool {
    $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';
    return $stored !== '' && hash_equals($stored, $submitted);
}

function requireCsrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !verifyCsrfToken()) {
        http_response_code(403);
        exit('CSRF validation failed');
    }
}
```

Every admin form adds `<input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">`. The `requireCsrf()` call goes at the top of each admin POST handler.

**Dependency:** Requires PHP sessions (already used). No external libraries.

---

### 2. DB-Based Session Management

**Why:** File-based sessions (PHP default) cannot be queried, cannot be invalidated across servers, and cannot support session listing/revocation in the admin panel.

**How it works in pure PHP:**
PHP supports a custom session save handler via `session_set_save_handler()`. Implement the handler backed by a `sessions` MySQL table.

**Schema:**
```sql
CREATE TABLE sessions (
    id          VARCHAR(128) NOT NULL PRIMARY KEY,
    user_id     INT DEFAULT NULL,
    data        MEDIUMTEXT NOT NULL,
    created_at  DATETIME NOT NULL,
    updated_at  DATETIME NOT NULL,
    expires_at  DATETIME NOT NULL,
    ip_address  VARCHAR(45) DEFAULT NULL,
    user_agent  VARCHAR(500) DEFAULT NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Implementation approach:** A `DbSessionHandler` class implements `SessionHandlerInterface`. Registered before `session_start()` in the bootstrap. The handler stores serialized session data in the `data` column and garbage-collects expired rows.

**Dependencies between features:** DB sessions must ship before 2FA (2FA state needs to persist across the challenge/verify page boundary) and before session revocation admin UI.

---

### 3. Row-Level ACL

**How it works in pure PHP:**

Row-level ACL stores, per row, which groups are allowed to **view** it and which groups are allowed to **edit** it. Two approaches:

**Option A: JSON columns on every table**
Add `view_groups` and `edit_groups` as JSON columns (or comma-separated varchar) to every managed table. Simple, but cannot be indexed or JOINed efficiently.

**Option B: Separate ACL junction table (recommended)**
```sql
CREATE TABLE row_acl (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    table_name  VARCHAR(64) NOT NULL,
    row_id      INT UNSIGNED NOT NULL,
    group_id    INT UNSIGNED NOT NULL,
    permission  ENUM('view','edit') NOT NULL,
    INDEX idx_table_row (table_name, row_id),
    INDEX idx_group (group_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Query pattern:** When fetching rows for display, the report system's `buildReportSQL()` appends a subquery or JOIN filtering to rows the current user's groups can view:

```php
// Injected into sql_where for ACL-protected tables
$userGroupIds = implode(',', array_map('intval', getUserGroupIds($userId)));
$aclClause = "(
    SELECT COUNT(*) FROM row_acl
    WHERE table_name = '{$table}'
    AND row_id = {$table}.id
    AND permission = 'view'
    AND group_id IN ({$userGroupIds})
) > 0 OR (
    SELECT COUNT(*) FROM row_acl
    WHERE table_name = '{$table}'
    AND row_id = {$table}.id
    AND permission = 'view'
) = 0";  -- rows with no ACL entries are visible to all
```

**Edit ACL check:** Before processing a POST edit/delete, call `canEditRow($tableName, $rowId, $userId)` which runs the equivalent check against `permission = 'edit'`.

**Admin UI:** On the edit form for any row, show a multi-select of groups for view access and edit access. This writes to `row_acl` on save.

**Dependencies:** Requires the group system (already shipped). DB sessions improve reliability but are not strictly required.

**Complexity:** High. The ACL WHERE clause injection must be added to `renderReport()` and `buildReportSQL()`. It must also be applied to the custom table CRUD handler before returning records. Performance degrades with subqueries on large tables; the indexed approach mitigates this.

---

### 4. Row-Level Version Control with Rollback

**How it works in pure PHP:**

Store every previous state of a row as a serialized snapshot in a `row_versions` table.

**Schema:**
```sql
CREATE TABLE row_versions (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    table_name  VARCHAR(64) NOT NULL,
    row_id      INT UNSIGNED NOT NULL,
    version     INT UNSIGNED NOT NULL,
    data        MEDIUMTEXT NOT NULL,   -- JSON-encoded row at time of save
    changed_by  INT UNSIGNED DEFAULT NULL,
    changed_at  DATETIME NOT NULL,
    change_note VARCHAR(500) DEFAULT NULL,
    INDEX idx_table_row (table_name, row_id),
    INDEX idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Capture pattern:** A `saveRowVersion($tableName, $rowId, $userId)` function fetches the current row state from the live table, JSON-encodes it, and inserts into `row_versions` before any UPDATE is applied. The version number increments per `(table_name, row_id)` pair.

**Rollback:** A `rollbackRow($tableName, $rowId, $versionId, $userId)` function fetches the target version's JSON data, decodes it, strips the `id` field, and issues a `dbUpdate()` on the live table. It also records the rollback itself as a new version with a note like "Rolled back to version 3".

**Diff view:** Compare two `row_versions` entries by JSON-decoding both and showing field-by-field differences in a simple HTML table. No external diff library needed.

**Dependencies:** Requires `admin-custom-table.php` to call `saveRowVersion()` before every UPDATE/DELETE. Also needed in built-in table handlers (users, groups, pages, email templates). The hooks system (below) can provide a cleaner injection point.

**Complexity:** High for full integration (must be added to every write path). The storage schema itself is low complexity.

---

### 5. Table Snapshots and Diff

**Current state:** The `snapshots` table stores only metadata (table name, row count, date). No actual data is copied.

**What needs to be built:**

**Full data snapshot:** Copy all rows from the target table into a `snapshot_rows` table (or generate a SQL dump to a file). The file approach is more practical for large tables.

```sql
CREATE TABLE snapshot_rows (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    snapshot_id   INT UNSIGNED NOT NULL,
    row_id        INT UNSIGNED NOT NULL,
    data          MEDIUMTEXT NOT NULL,  -- JSON of the row at snapshot time
    INDEX idx_snapshot (snapshot_id),
    INDEX idx_row (snapshot_id, row_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Diff tool:** Compare current live rows against `snapshot_rows` for the same `snapshot_id`. Three categories: added rows (in live, not in snapshot), deleted rows (in snapshot, not in live), modified rows (JSON differs). Output as an HTML table with color-coding.

**Restore:** For any row in a snapshot, run `dbUpdate()` with the snapshotted field values. For deleted rows, run `dbInsert()`. Restoration is destructive to current data, so require explicit confirmation.

**Dependencies:** Depends on `snapshots` table (already exists) and `admin-snapshots.php` (already exists — but needs data capture added). Row-level versioning should record the snapshot restore as a version event.

**Complexity:** Medium for snapshot creation and diff, High for restore (must handle schema drift between snapshot time and now).

---

### 6. Two-Factor Authentication

**Three modes to support per PROJECT.md:**

#### Mode A: Google Authenticator (TOTP)
TOTP (Time-based One-Time Password, RFC 6238) can be implemented in pure PHP with no library:
- Generate a random 20-byte Base32-encoded secret per user
- Store in `users.totp_secret` (encrypt it using the existing `encryptField()`)
- Generate the 6-digit code: `HMAC-SHA1(secret, floor(time() / 30))`, take last 4 bits of the hash as offset, extract 4 bytes from offset, mask to 31 bits, mod 1,000,000
- Verify by checking current window +/- 1 (30-second tolerance)
- Display QR code: generate the `otpauth://` URI and render it as a QR image — the only dependency-free option is generating the QR code in pure PHP (a ~400-line algorithm) or linking to `chart.googleapis.com` for the QR image (external, but optional)

```php
// functions/totp.php — pure PHP TOTP implementation
function generateTotpSecret(): string {
    $bytes = random_bytes(20);
    return base32Encode($bytes); // implement base32 encode inline
}

function verifyTotpCode(string $secret, string $code): bool {
    $decodedSecret = base32Decode($secret);
    $timeSlot = (int)floor(time() / 30);
    for ($i = -1; $i <= 1; $i++) {
        $expected = computeHotp($decodedSecret, $timeSlot + $i);
        if (hash_equals((string)$expected, $code)) return true;
    }
    return false;
}
```

#### Mode B: 6-Digit Email Code
- On login success (password verified), generate a 6-digit random code
- Store in `users.email_otp` and `users.email_otp_expires` (5-minute window)
- Send via `sendEmailTemplate('two_factor_code', ...)`
- Redirect to a `/verify-2fa` page; accept the code; clear the OTP fields on success

#### Mode C: Email Magic Link
- On login, generate a 64-byte random token
- Store in `users.magic_token` and `users.magic_token_expires` (15-minute window)
- Send a link to `/auth/magic?token=...` via email
- On visit, verify token, set session, clear token

**Session flow for all modes:** After password verification, set `$_SESSION['2fa_pending'] = $userId` (not `$_SESSION['user_id']`). The `/verify-2fa` page reads `2fa_pending`, validates the code, then promotes to `$_SESSION['user_id']`. This prevents a partially-authenticated state from granting any access.

**Schema additions to `users` table:**
```sql
ALTER TABLE users ADD COLUMN totp_secret VARCHAR(64) DEFAULT NULL;
ALTER TABLE users ADD COLUMN totp_enabled TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE users ADD COLUMN email_otp CHAR(6) DEFAULT NULL;
ALTER TABLE users ADD COLUMN email_otp_expires DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN magic_token VARCHAR(128) DEFAULT NULL;
ALTER TABLE users ADD COLUMN magic_token_expires DATETIME DEFAULT NULL;
ALTER TABLE users ADD COLUMN two_factor_method ENUM('none','totp','email_otp','magic_link') NOT NULL DEFAULT 'none';
```

**Dependencies:** DB-based sessions (strongly recommended — the 2FA pending state must survive a redirect, and file sessions on shared hosts can be unreliable). Email system (already shipped). Encryption (already shipped, use for TOTP secret storage).

**Complexity:** High for TOTP (requires pure-PHP Base32 + HMAC-SHA1 implementation). Medium for email OTP and magic link.

---

### 7. Password Policy (Max Age, Reuse Prevention)

**What is partially built:** `validatePasswordStrength()` in `encryption.php` checks length, uppercase, lowercase, digit. It reads `PASSWORD_MIN_LENGTH` from env.

**What needs to be built:**

**Max age enforcement:**
```sql
ALTER TABLE users ADD COLUMN password_changed_at DATETIME DEFAULT NULL;
```
In `requireLogin()` (or a new `checkPasswordPolicy()`), if `password_changed_at` is older than `PASSWORD_MAX_AGE_DAYS` env var, redirect to a forced password change page.

**Reuse prevention:**
```sql
CREATE TABLE password_history (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL,
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```
Before accepting a new password, loop through the last `PASSWORD_REUSE_LIMIT` (env var, default 5) entries for the user and reject if `password_verify($newPassword, $hash)` matches any.

**Complexity:** Low. The schema additions are trivial; the policy checks are simple comparisons.

---

### 8. Staged Activation / Deactivation

**Concept:** Any row in any managed table can have a `activate_at` and `deactivate_at` datetime. A scheduled check (or on-demand query) transitions `status` based on current time.

**Schema additions to custom tables (standard fields):**
```sql
activate_at   DATETIME DEFAULT NULL,
deactivate_at DATETIME DEFAULT NULL,
```
These fields need to be added as standard fields when provisioning new custom tables (in `admin-tables.php`).

**Execution:** A `processScheduledActivations()` function queries all custom tables for rows where:
- `activate_at IS NOT NULL AND activate_at <= NOW() AND status = 'inactive'` → set `status = 'active'`
- `deactivate_at IS NOT NULL AND deactivate_at <= NOW() AND status = 'active'` → set `status = 'inactive'`

This can run on every request (cheap query, cached by MySQL) or via a cron job. On every request is the simplest zero-dependency approach.

**UI:** Add `activate_at` and `deactivate_at` datetime inputs to the custom table edit form, rendered by `renderCustomFieldInput()` with type `datetime-local`.

**Dependencies:** Standard fields system (the PROJECT.md requirement for `created_at`, `modified_at`, `status`, `view_groups`, `edit_groups` on every table). Staged activation is an extension of this.

**Complexity:** Medium. The query logic is straightforward; the complexity is ensuring every custom table has these columns and that the scheduler runs reliably without a cron setup.

---

### 9. Hooks / Callbacks on Row Events

**How it works in pure PHP without libraries:**

A hook registry stores named hooks mapped to PHP callables. Hooks fire at defined points in the CRUD lifecycle.

**Implementation:**
```php
// functions/hooks.php
$GLOBALS['_snow_hooks'] = [];

function addHook(string $event, callable $callback, int $priority = 10): void {
    $GLOBALS['_snow_hooks'][$event][] = ['fn' => $callback, 'priority' => $priority];
}

function fireHook(string $event, array $context = []): array {
    $hooks = $GLOBALS['_snow_hooks'][$event] ?? [];
    usort($hooks, fn($a, $b) => $a['priority'] <=> $b['priority']);
    foreach ($hooks as $hook) {
        $result = ($hook['fn'])($context);
        if (is_array($result)) $context = array_merge($context, $result);
    }
    return $context;
}
```

**Hook events to define:**
- `before_row_insert` — context: `[table, data]`; return modified `data`
- `after_row_insert` — context: `[table, row_id, data]`; return value ignored
- `before_row_update` — context: `[table, row_id, data]`; return modified `data`
- `after_row_update` — context: `[table, row_id, data]`; return value ignored
- `before_row_delete` — context: `[table, row_id]`; return false to cancel
- `after_row_delete` — context: `[table, row_id]`; return value ignored
- `before_login` — context: `[email]`
- `after_login` — context: `[user_id, email]`

**How hooks get registered:** Hook files live in a `hooks/` directory. The bootstrap `require_once`s every `.php` file in `hooks/` after loading core functions. Each hook file calls `addHook()`.

**Plugin system integration:** The existing `admin-plugins.php` already has a plugin registry. Active plugins can have their hook file auto-loaded, giving the plugin system actual runtime behavior.

**Dependencies:** No external dependencies. The hook registration must happen in the bootstrap before any admin handler runs. Version control (`saveRowVersion()`) is itself implemented as a `before_row_update` hook, keeping the versioning concern out of the CRUD handlers.

**Complexity:** Medium for the registry. The complexity is in integrating `fireHook()` calls into every write path in `admin-custom-table.php` and built-in table handlers consistently.

---

### 10. Customizable Search / Sort / Filter

**Current state:** The report system has `sql_where` and `sql_order` as static strings defined in the report class file. No dynamic user-facing controls exist.

**What needs to be built:**

**Dynamic sort:** Each column header in the HTML row template links back to the same page with `?sort_col=field_name&sort_dir=asc|desc`. The `renderReport()` function reads these GET params, validates the column name against a whitelist of the report's `sql_fields`, and appends `ORDER BY {col} {dir}`.

**Dynamic search:** A search box at the top of the list view submits `?q=term`. The `renderReport()` function appends `AND (field1 LIKE ? OR field2 LIKE ?)` to the WHERE clause. Which fields are searchable is configurable per report (a new `search_fields` property on the report class).

**Filter dropdowns:** For fields with known value sets (e.g., `status`), render a `<select>` dropdown. Selected value appends `AND field = ?` to the WHERE clause.

**Security:** Column names for sort must be validated against a whitelist (the report's declared field list) to prevent SQL injection. Never interpolate user-supplied column names directly.

**Complexity:** Medium. The report class interface needs two new optional methods (`search_fields()`, `filter_fields()`). The `renderReport()` function grows moderately. The `buildReportSQL()` function needs to handle dynamic WHERE and ORDER clauses safely.

---

## Feature Dependencies Map

```
DB-based sessions
    └── 2FA (2fa_pending state requires reliable session storage)
    └── Session revocation admin UI

Password policy (age/reuse)
    └── Password history table

Row-level ACL (view_groups, edit_groups)
    └── Group system (shipped)
    └── Report system — renderReport() must filter by ACL

Row-level versioning
    └── Custom table CRUD (admin-custom-table.php write paths)
    └── Hooks system (cleanest integration point)

Table snapshots (actual data)
    └── Snapshots metadata table (shipped)

Snapshot diff
    └── Table snapshots (actual data)

Snapshot restore
    └── Snapshot diff

Staged activation
    └── Standard fields (created_at, modified_at, status on every table)

Hooks
    └── Plugin registry (shipped — gives hooks a registration mechanism)
    └── Hooks used by: versioning, staged activation, custom business logic

CSRF protection
    └── PHP sessions (already used)
    └── Must be added to every POST form

Search/sort/filter
    └── Report system (renderReport, buildReportSQL)
    └── Column whitelist from report sql_fields
```

---

## Complexity Summary

| Feature | Complexity | Effort estimate |
|---|---|---|
| CSRF protection | Low | 1-2 hours |
| Password policy (age/reuse) | Low | 2-4 hours |
| Staged activation | Medium | 4-6 hours |
| DB-based sessions | Medium | 4-6 hours |
| Hooks/callbacks | Medium | 4-6 hours |
| Search/sort/filter UI | Medium | 6-8 hours |
| Table snapshots (actual data) | Medium | 4-6 hours |
| Snapshot diff | High | 6-8 hours |
| Snapshot restore | High | 4-6 hours |
| Row-level ACL | High | 8-12 hours |
| Row-level versioning | High | 8-10 hours |
| 2FA — email OTP | Medium | 4-6 hours |
| 2FA — magic link | Medium | 3-4 hours |
| 2FA — TOTP (Google Auth) | High | 8-12 hours |

**Recommended build order:**
1. CSRF protection (unblocks everything; security prerequisite)
2. DB-based sessions (prerequisite for 2FA)
3. Password policy (age/reuse)
4. Hooks system (enables versioning to be a hook rather than embedded logic)
5. Row-level versioning (high value, uses hooks)
6. Search/sort/filter (high user-facing value)
7. Row-level ACL
8. Staged activation
9. Table snapshots (actual data)
10. Snapshot diff + restore
11. 2FA (email OTP first, TOTP last)

---

## Recommendations Specific to Snow's Architecture

**Function-per-file constraint:** Each feature above maps to one or two new files in `functions/`. CSRF → `csrf.php`. TOTP → `totp.php`. Hooks → `hooks.php`. Sessions → `session-handler.php`. This is consistent with the existing architecture.

**Dependency-free validation:** All TOTP math (HMAC-SHA1, Base32) is implementable in ~100 lines of pure PHP. PHP's `hash_hmac()` and `openssl_random_pseudo_bytes()` (both part of the PHP core, not extensions that need separate installation) handle the heavy lifting.

**Logging integration:** The existing logging system (`logMessage()`) should be called from every security-relevant hook: login attempts, 2FA failures, ACL denials, rollbacks, snapshot restores. This is already partially done for logins.

**Report system extension:** The search/sort/filter feature should extend the existing `renderReport()` / `buildReportSQL()` functions rather than replacing them. Add optional methods to the report class interface with default no-op implementations so existing report files do not break.

**CSRF integration path:** Add `requireCsrf()` at the top of every existing admin POST handler. Add `generateCsrfToken()` to the shared form template partial. Since all admin forms already follow a consistent pattern (hidden `action` field, POST to the same URL), the integration is mechanical.
