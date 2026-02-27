# Architecture Research: Snow Framework — Remaining Components

**Research type:** Project Research — Architecture dimension
**Date:** 2026-02-27
**Status:** Complete

---

## Context

Snow is a zero-dependency PHP LAMP admin framework. The foundation is already built:

- File-based report system (`reports/*.php` — each report is a PHP class)
- Custom table provisioning (MySQL table + admin page + `*_list` report auto-generated)
- Admin list views rendered via report templates
- Core function files: `database.php`, `auth.php`, `logging.php`, `email.php`, `encryption.php`, `template.php`, `pages.php`, `reports.php`
- Page routing via `pages` table + `custom_script` field pointing to a file in `functions/`
- Bootstrap 5 UI with no build step
- PHP native sessions (file-based, currently)

The architecture constraint is firm: **each function lives in its own PHP file**. No Composer, no npm.

The remaining components to build are: ACL (row-level), row-level versioning, DB sessions, 2FA, hooks, email templates (already started), snapshot/diff, logging (already file-based, needs DB tier), CSRF, and search/filter.

---

## Component Map

The system has a clear layered structure. Components are grouped by their position in the dependency graph.

### Layer 0 — Already Exists (Foundation)

| Component | Files | What it does |
|-----------|-------|--------------|
| Database | `functions/database.php` | PDO wrapper, all query helpers |
| Logging (file) | `functions/logging.php` | File-based log write/read, logError/logInfo/logTraffic/logEmail |
| Encryption | `functions/encryption.php` | AES-256-CBC, key files, field encryption |
| Auth (partial) | `functions/auth.php` | loginUser, requireLogin, hasPermission, group/permission lookup |
| Template | `functions/template.php` | Token replacement, processTokens |
| Pages | `functions/pages.php` | renderPage, routing, navigation |
| Reports | `functions/reports.php` | File-backed report classes, renderReport, pagination |
| Email (partial) | `functions/email.php` | sendEmailTemplate, processTokens, unsubscribe tokens |
| Groups | `functions/admin-groups.php` | Group CRUD, permission assignment |
| Custom Tables | `functions/admin-tables.php`, `functions/admin-custom-table.php` | Table + field provisioning, report generation |
| Snapshots (stub) | `functions/admin-snapshots.php` | Records snapshot metadata in `snapshots` table (row count only, no actual data copy yet) |

---

### Layer 1 — No Dependencies on New Components

These can be built immediately because they depend only on Layer 0.

#### 1A. DB Sessions

**Purpose:** Replace PHP file-based sessions with MySQL-backed sessions. Enables session listing, forced logout, and fine-grained audit.

**Boundary:** Sits between Apache/PHP session handler and the rest of the framework. Transparent once registered — existing `$_SESSION` reads/writes remain unchanged.

**How it works:**
- Register a custom session handler using `session_set_save_handler()` before `session_start()` in `index.php`'s `initializeFramework()`
- Handler functions: `open`, `close`, `read`, `write`, `destroy`, `gc`
- Each handler is a standalone function in a dedicated file: `functions/session.php`

**MySQL schema:**

```sql
CREATE TABLE sessions (
    session_id   VARCHAR(128)  NOT NULL,
    user_id      INT           DEFAULT NULL,  -- populated on login
    ip_address   VARCHAR(45)   NOT NULL DEFAULT '',
    user_agent   VARCHAR(512)  NOT NULL DEFAULT '',
    data         MEDIUMTEXT    NOT NULL,       -- serialized $_SESSION
    last_active  INT UNSIGNED  NOT NULL,       -- Unix timestamp
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (session_id),
    KEY idx_user_id   (user_id),
    KEY idx_last_active (last_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Files to create:**
- `functions/session.php` — `sessionOpen()`, `sessionClose()`, `sessionRead()`, `sessionWrite()`, `sessionDestroy()`, `sessionGc()`, `registerDbSessionHandler()`, `getActiveSessions($userId)`, `destroySessionById($sessionId)`

**Integration point:** `index.php` calls `registerDbSessionHandler()` before `session_start()`. The `loginUser()` function in `auth.php` should write `user_id` to the session after login so the sessions table gets the user ID populated on the next `sessionWrite()` call.

**Data flow:**

```
Request → index.php → registerDbSessionHandler() → session_start()
                                                          ↓
                                                    sessionRead() ← sessions table
       → ... page logic uses $_SESSION ...
                                                          ↓
                                                    sessionWrite() → sessions table
```

---

#### 1B. CSRF Protection

**Purpose:** Prevent cross-site request forgery on all state-changing POST forms.

**Boundary:** Two functions: one that generates and embeds a token into a form, one that validates it on POST. Both functions touch only `$_SESSION`.

**How it works:**
- Token is stored in `$_SESSION['csrf_token']` with a timestamp
- Every form includes a hidden `<input name="csrf_token">`
- Every POST handler calls `verifyCsrfToken()` before processing

**Files to create:**
- `functions/csrf.php` — `generateCsrfToken()` returns HTML hidden input, `verifyCsrfToken()` returns bool, `requireCsrfToken()` dies on failure

**No new MySQL tables required.** Token lives in session.

**Integration pattern:**

```php
// In every form:
<?= generateCsrfToken() ?>

// In every POST handler (at top of block):
requireCsrfToken();
```

**Existing forms that need to be updated** once CSRF is built: all `admin-*.php` POST handlers, `login.php`, `profile.php`.

---

#### 1C. Logging (DB tier)

**Purpose:** Complement file-based logs with a queryable MySQL table. File logs remain for performance; DB logs enable admin search/filter UI.

**Boundary:** Additive to `logging.php`. The existing `logMessage()` function writes to file. A new parallel path writes high-value events (logins, errors, email sends) to a DB table.

**MySQL schema:**

```sql
CREATE TABLE activity_log (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    logged_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    level       ENUM('ERROR','INFO','AUTH','EMAIL','HOOK') NOT NULL,
    user_id     INT          DEFAULT NULL,
    session_id  VARCHAR(128) DEFAULT NULL,
    ip_address  VARCHAR(45)  DEFAULT NULL,
    message     TEXT         NOT NULL,
    context     JSON         DEFAULT NULL,   -- structured extra data
    PRIMARY KEY (id),
    KEY idx_level     (level),
    KEY idx_user_id   (user_id),
    KEY idx_logged_at (logged_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Files to create/modify:**
- Modify `functions/logging.php` — add `logToDb($level, $message, $context = [])` called from `logMessage()` for ERROR and AUTH levels
- `functions/admin-logs.php` exists already — connect it to `activity_log` table for searchable admin UI

**Data flow:**

```
logError() / logInfo() / logEmail()
    → logMessage() [file write]
    → logToDb() [DB insert for ERROR/AUTH/EMAIL]
                    ↓
              activity_log table
                    ↓
          admin-logs.php report view
```

---

### Layer 2 — Depends on Layer 1

#### 2A. ACL System (Group-based + Row-level)

**Purpose:** Control who can view/edit each row in any managed table. Groups are already provisioned. Permissions exist. The missing piece is **row-level** view/edit group assignment and enforcement.

**Current state:** `auth.php` has `hasPermission()` which checks group → permission at the page/feature level. Row-level ACL does not exist yet.

**Design decision:** Row-level ACL is stored as comma-separated group IDs in standard columns on every managed table rather than in a separate junction table. This is simpler, avoids JOINs, and works with the existing pattern where every custom table gets provisioned with a fixed schema.

**Standard columns added to every managed table at provisioning time:**

```sql
view_groups  VARCHAR(500) DEFAULT NULL,  -- comma-separated group IDs allowed to view
edit_groups  VARCHAR(500) DEFAULT NULL,  -- comma-separated group IDs allowed to edit
```

`NULL` means "all groups with table access can view/edit."

**ACL check logic:**

```
getUserGroups(currentUserId)                → array of group IDs
rowGroupIds = explode(',', row['view_groups'])
intersection = user group IDs ∩ row group IDs
canView = (row['view_groups'] is NULL) OR (intersection is not empty)
```

**Files to create:**
- `functions/acl.php` — `canViewRow($row, $userId = null)`, `canEditRow($row, $userId = null)`, `filterRowsByViewAccess($rows, $userId = null)`, `getViewGroupsOptions($currentGroups)`, `setRowGroups($tableName, $rowId, $viewGroups, $editGroups)`

**Files to modify:**
- `functions/admin-custom-table.php` — wrap list queries with `filterRowsByViewAccess()`, add group selector to add/edit forms
- `functions/admin-tables.php` — add `view_groups`/`edit_groups` columns to provisioning SQL

**MySQL schema change (provisioning):**

```sql
-- Added to every custom table at CREATE time:
view_groups  VARCHAR(500) DEFAULT NULL,
edit_groups  VARCHAR(500) DEFAULT NULL,
```

**Data flow:**

```
HTTP request for /admin/data/products
    → admin-custom-table.php
    → dbGetRows("SELECT * FROM products")
    → filterRowsByViewAccess($rows) [acl.php]
        → getUserGroups(currentUserId) [auth.php]
        → compare row.view_groups with user's group IDs
    → renderReport($listReport, $filteredRows)
```

---

#### 2B. Password Policy

**Purpose:** Enforce configurable password rules: minimum length, maximum age, reuse prevention.

**Boundary:** Extends `encryption.php`'s `validatePasswordStrength()` and `auth.php`'s password change flow.

**MySQL schema:**

```sql
CREATE TABLE password_history (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id      INT           NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Config in `.env`:**

```
PASSWORD_MIN_LENGTH=10
PASSWORD_MAX_AGE_DAYS=90
PASSWORD_HISTORY_COUNT=5
```

**Files to create:**
- `functions/password-policy.php` — `validatePasswordPolicy($password, $userId)`, `checkPasswordReuse($userId, $newPassword)`, `isPasswordExpired($userId)`, `recordPasswordChange($userId, $hash)`, `requirePasswordChange()`

**Files to modify:**
- `functions/auth.php` — call `validatePasswordPolicy()` in `changePassword()` and `completePasswordReset()`, call `isPasswordExpired()` in `checkSessionTimeout()`

---

### Layer 3 — Depends on Layer 2

#### 3A. Row-Level Versioning

**Purpose:** Track every change to every row in every managed table. Enable rollback to any prior version.

**Boundary:** A versioning layer wraps all writes to managed tables. Rather than modifying every INSERT/UPDATE call, versioning hooks into a central `saveRow()` function (see Hooks below) or is called explicitly from `admin-custom-table.php`.

**Design:** A single `row_versions` table stores a JSON snapshot of the row before every change. The current live data stays in the original table — versions are append-only history.

**MySQL schema:**

```sql
CREATE TABLE row_versions (
    id           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    table_name   VARCHAR(128)  NOT NULL,
    row_id       INT           NOT NULL,
    version_num  SMALLINT      NOT NULL DEFAULT 1,
    changed_by   INT           DEFAULT NULL,  -- user_id
    changed_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    action       ENUM('create','update','delete') NOT NULL,
    row_snapshot JSON          NOT NULL,       -- full row at this point in time
    change_diff  JSON          DEFAULT NULL,   -- only changed fields (optional, computed)
    PRIMARY KEY  (id),
    KEY idx_table_row  (table_name, row_id),
    KEY idx_changed_by (changed_by),
    KEY idx_changed_at (changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Files to create:**
- `functions/versioning.php` — `recordVersion($tableName, $rowId, $action, $userId)`, `getRowVersions($tableName, $rowId)`, `rollbackToVersion($tableName, $rowId, $versionId)`, `diffVersions($versionA, $versionB)`, `getLatestVersion($tableName, $rowId)`

**Files to modify:**
- `functions/admin-custom-table.php` — call `recordVersion()` before every INSERT (`action='create'`), before every UPDATE (`action='update'`), before every DELETE (`action='delete'`)

**Data flow for a write:**

```
POST /admin/data/products?action=edit&id=42
    → admin-custom-table.php POST handler
    → recordVersion('products', 42, 'update', $currentUserId)
        → dbGetRow("SELECT * FROM products WHERE id=42")  [snapshot before]
        → dbInsert('row_versions', {snapshot, diff, ...})
    → dbUpdate('products', $rowData, 'id=?', [42])
    → logToDb('INFO', 'Row updated: products#42')
```

**Diff computation:** `diffVersions()` compares two JSON snapshots and returns an array of `[field => [old, new]]` pairs. Rendered in admin as a two-column before/after table.

---

#### 3B. Snapshot / Diff

**The existing `admin-snapshots.php` records only metadata** (table name, row count, timestamp). It does not copy data. The snapshot system needs to be upgraded to actually capture data.

**Revised approach:** A snapshot is a point-in-time JSON export of an entire table, stored in a file (in `snapshots/` directory) and referenced from the `snapshots` table. The diff tool compares two snapshot files or a snapshot file against the live table.

**MySQL schema (snapshots table — already exists, extend it):**

```sql
-- Existing columns (keep):
--   id, table_name, snapshot_name, description, snapshot_date, row_count, file_path, status, created_by

-- file_path should be non-null after this phase:
--   file_path VARCHAR(500) — path to JSON file in SNOW_SNAPSHOTS directory
```

**Files to modify/create:**
- Modify `functions/admin-snapshots.php` — on "create" action, dump `SELECT * FROM $tableName` to a JSON file in `SNOW_SNAPSHOTS/`, store `file_path`
- `functions/snapshot.php` — `createSnapshot($tableName, $name, $description, $userId)`, `loadSnapshot($snapshotId)`, `diffSnapshotWithLive($snapshotId)`, `diffSnapshotPair($snapshotIdA, $snapshotIdB)`, `restoreSnapshot($snapshotId)`

**Diff output format:** An array of `[row_id => [status => 'added'|'removed'|'changed', changes => [field => [old,new]]]]` suitable for rendering in a report-style HTML table.

**Data flow:**

```
Admin clicks "Record Snapshot"
    → admin-snapshots.php POST
    → createSnapshot('products', 'pre_launch', ...) [snapshot.php]
        → SELECT * FROM products
        → file_put_contents(SNOW_SNAPSHOTS/products_20260227_143022.json, $json)
        → dbInsert('snapshots', {file_path, row_count, ...})

Admin clicks "Diff vs Live"
    → diffSnapshotWithLive($snapshotId) [snapshot.php]
        → loadSnapshot() → parse JSON file
        → SELECT * FROM products → index by id
        → compare → return diff array
        → render diff HTML table
```

---

### Layer 4 — Depends on Layer 3

#### 4A. Hooks System

**Purpose:** Allow custom PHP code to run when a row is created or modified in any managed table, without modifying the core framework files.

**Design:** Hook definitions are stored in a `hooks` table. Each hook references a table name, an event (`before_create`, `after_create`, `before_update`, `after_update`, `before_delete`, `after_delete`), and a file path (relative to `SNOW_ROOT`) containing a PHP function named `hook_{name}($row, $context)`.

**MySQL schema:**

```sql
CREATE TABLE hooks (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name        VARCHAR(128)  NOT NULL,
    description TEXT          DEFAULT NULL,
    table_name  VARCHAR(128)  NOT NULL,       -- '*' means all tables
    event       ENUM('before_create','after_create','before_update',
                     'after_update','before_delete','after_delete') NOT NULL,
    hook_file   VARCHAR(500)  NOT NULL,       -- path to PHP file
    sort_order  SMALLINT      NOT NULL DEFAULT 0,
    status      VARCHAR(20)   NOT NULL DEFAULT 'active',
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_table_event (table_name, event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Hook file convention:** Each hook lives in `hooks/` directory (or any path stored in `hook_file`). The file must define a function named exactly `hook_{name}`. Example: hook named `notify_on_product_create` lives in `hooks/notify_on_product_create.php` and defines `function hook_notify_on_product_create($row, $context) { ... }`.

**Files to create:**
- `functions/hooks.php` — `fireHook($tableName, $event, $row, $context = [])`, `getHooksForEvent($tableName, $event)`, `registerHook($data)`, `disableHook($hookId)`
- `functions/admin-hooks.php` — Admin CRUD for hook records
- Directory: `hooks/` — user-created hook files live here

**Files to modify:**
- `functions/admin-custom-table.php` — call `fireHook($tableName, 'before_create', $rowData)` / `fireHook($tableName, 'after_create', $rowData)` etc. around each write

**Data flow:**

```
POST /admin/data/products?action=add
    → admin-custom-table.php
    → fireHook('products', 'before_create', $rowData) [hooks.php]
        → getHooksForEvent('products', 'before_create')
        → foreach hook: require_once $hook['hook_file']; call hook_fn($row, $ctx)
    → dbInsert('products', $rowData)
    → recordVersion('products', $newId, 'create', $userId)
    → fireHook('products', 'after_create', $rowData + ['id' => $newId])
```

**Hook context array** passes: `['table' => $tableName, 'user_id' => $userId, 'action' => 'create']`

---

#### 4B. Two-Factor Authentication (2FA)

**Purpose:** TOTP (Google Authenticator compatible, 6-digit code) and email magic link as alternative second factor.

**Boundary:** 2FA sits between successful password verification and session establishment in `loginUser()`. It requires DB sessions (Layer 1) to store the `pending_2fa` state between the password step and the code step.

**MySQL schema:**

```sql
-- Columns added to users table:
totp_secret      VARCHAR(64)  DEFAULT NULL,      -- base32 TOTP secret (AES-encrypted at rest)
totp_enabled     TINYINT(1)   NOT NULL DEFAULT 0,
email_otp        VARCHAR(10)  DEFAULT NULL,      -- current email OTP (short-lived)
email_otp_expiry DATETIME     DEFAULT NULL,

CREATE TABLE backup_codes (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id     INT           NOT NULL,
    code_hash   VARCHAR(255)  NOT NULL,           -- bcrypt hash of 8-char code
    used_at     DATETIME      DEFAULT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**TOTP implementation:** RFC 6238 TOTP is a 30-line PHP function using `hash_hmac`. No library needed.

```php
// Core TOTP algorithm — pure PHP, zero deps:
function totpGenerate($secret, $timeStep = 30, $digits = 6) {
    $time = floor(time() / $timeStep);
    $key  = base32Decode($secret);           // base32 decode also pure PHP
    $msg  = pack('J', $time);               // 8-byte big-endian
    $hash = hash_hmac('sha1', $msg, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $code = (unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF) % pow(10, $digits);
    return str_pad($code, $digits, '0', STR_PAD_LEFT);
}
```

**Files to create:**
- `functions/totp.php` — `totpGenerate($secret)`, `totpVerify($secret, $code, $window = 1)`, `generateTotpSecret()`, `base32Encode($data)`, `base32Decode($data)`, `generateQrCodeUrl($secret, $email, $issuer)`
- `functions/twofa.php` — `enableTotp($userId, $secret)`, `disableTotp($userId)`, `verifyTotp($userId, $code)`, `sendEmailOtp($userId)`, `verifyEmailOtp($userId, $code)`, `generateBackupCodes($userId)`, `verifyBackupCode($userId, $code)`, `isTwoFaRequired($userId)`, `markTwoFaPassed($userId)`

**Files to modify:**
- `functions/auth.php` — modify `loginUser()`: if password valid and 2FA enabled, store `$_SESSION['pending_2fa_user'] = $userId` and return `'2fa_required'` instead of setting `user_id`. After 2FA verified, complete session setup.
- `functions/login.php` — handle the 2FA step (second form: enter code)

**QR code display:** Use a `<img src="https://chart.googleapis.com/chart?...">` URL. This is a display convenience and involves no server-side dependency — it's a URL in the HTML.

Actually, for zero-dependency strict compliance, render the QR code as a URL only and let the user copy it into their authenticator app. Or use a pure-PHP QR code renderer (Data Matrix / QR Code in ~200 lines of PHP is feasible). Note this in implementation.

---

### Layer 5 — Depends on Layer 4

#### 5A. Search and Filter

**Purpose:** Enable column-level filtering, multi-column sort, and full-text search on any managed table's admin list view.

**Boundary:** Extends the report rendering system. Reports already define `sql_where` and `sql_order`. Search/filter adds dynamic GET parameters that modify these at render time.

**Design:** A report can define `filterable_fields` and `searchable_fields` as part of its class interface. The `renderReport()` function reads GET parameters (`q` for search, `filter_*` for column filters, `sort` and `dir` for ordering) and modifies the SQL accordingly.

**Report class interface extension:**

```php
class ProductsListReport {
    // Existing methods:
    public function sql_table() { return 'products'; }
    public function sql_fields() { ... }
    public function sql_where()  { return "status != 'deleted'"; }
    public function sql_order()  { return 'name ASC'; }

    // New optional methods:
    public function searchable_fields() { return ['name', 'description']; }
    public function filterable_fields() { return [
        'status'   => ['type' => 'select', 'options' => ['active','inactive']],
        'category' => ['type' => 'text'],
        'created_at' => ['type' => 'date_range'],
    ]; }
    public function sortable_fields() { return ['name', 'created_at', 'status']; }
}
```

**Files to modify:**
- `functions/reports.php` — extend `buildReportSQL()` and `renderReport()` to read and apply GET params; add `buildSearchFilterControls($report)` to render the filter UI above the table
- `functions/reports.php` — add `buildSearchFilterControls($report)` HTML builder

**SQL injection protection:** All search/filter values go through PDO prepared statement params. Column/field names are validated against the report's declared `filterable_fields` and `sortable_fields` whitelist — never interpolated raw from GET.

**Staged activation/deactivation** (from PROJECT.md): This is a special filter — rows with `activate_at` in the future or `deactivate_at` in the past are hidden from non-admin views. A scheduled check or on-read filter handles this. This belongs in report's `sql_where` as a conditional.

---

## Build Order

The dependency graph dictates this sequence:

```
Phase 1 (No deps — build first, unblock everything):
  1. DB Sessions            (functions/session.php)
  2. CSRF Protection        (functions/csrf.php)
  3. Logging DB tier        (modify functions/logging.php, activity_log table)
  4. Password Policy        (functions/password-policy.php, password_history table)

Phase 2 (Depends on Phase 1):
  5. ACL — row level        (functions/acl.php, modify admin-custom-table.php)
  6. Standard table columns  (view_groups, edit_groups added to provisioning)

Phase 3 (Depends on Phase 2):
  7. Row-level Versioning   (functions/versioning.php, row_versions table)
  8. Snapshot data capture  (upgrade admin-snapshots.php, functions/snapshot.php)

Phase 4 (Depends on Phase 3):
  9. Hooks system           (functions/hooks.php, functions/admin-hooks.php, hooks/ dir)
  10. 2FA                   (functions/totp.php, functions/twofa.php, modify auth.php)

Phase 5 (Depends on Phase 4, or can start after Phase 2):
  11. Search / Filter       (modify functions/reports.php)
  12. Staged activation     (add activate_at/deactivate_at to standard columns)
```

**Rationale for ordering:**

- DB Sessions must be first because 2FA depends on multi-step session state (`pending_2fa_user`). CSRF depends on sessions being reliable.
- CSRF should be in Phase 1 so all subsequent admin forms are built with it from the start.
- ACL depends on stable group infrastructure (already exists) but needs sessions to know the current user.
- Versioning depends on ACL because you need to know which user made each change — this requires reliable auth, which requires reliable sessions.
- Hooks depend on versioning because the recommended call sequence is: fire `before_*` hook → write row → record version → fire `after_*` hook. If you build hooks before versioning, you have to go back and adjust the call order.
- 2FA can technically be built in Phase 2 (it only needs sessions), but placing it in Phase 4 after ACL and versioning is complete means the admin setup pages for 2FA can themselves be ACL-protected and version-tracked.
- Search/Filter is last because it modifies `renderReport()` which all other admin pages use — it's safest to finalize after the data model (ACL groups in rows, standard columns) is locked.

---

## Data Flow Summary

### Request lifecycle with all components active:

```
1. HTTP request arrives
2. index.php: loadConfig() → registerDbSessionHandler() → session_start()
3. index.php: routeRequest() → logTraffic() [file] → renderPage($path)
4. pages.php: checkSessionTimeout() → isLoggedIn()
              → isPasswordExpired() [password-policy.php]
              → getPageByPath() → check require_auth, required_permission
5. pages.php: include $page['custom_script'] (e.g. admin-custom-table.php)
6. admin-custom-table.php:
   POST path:
     → requireCsrfToken() [csrf.php]
     → requirePermission('table_management') [auth.php]
     → canEditRow($row) [acl.php]
     → fireHook($table, 'before_update', $data) [hooks.php]
     → recordVersion($table, $id, 'update', $userId) [versioning.php]
     → dbUpdate($table, $data, ...)
     → fireHook($table, 'after_update', $data) [hooks.php]
     → logToDb('INFO', ...) [logging.php]
   GET path:
     → requirePermission() [auth.php]
     → dbGetRows() → filterRowsByViewAccess($rows) [acl.php]
     → renderReport($listReport) [reports.php]
         → buildSearchFilterControls() [with search/filter]
         → buildReportSQL() [with GET params applied]
         → renderReport output
7. pages.php: renderTemplate($templateFile, $data)
8. sessionWrite() → sessions table
```

### Information flows by component:

| From | To | Data carried |
|------|----|--------------|
| `$_SERVER['REQUEST_URI']` | router | path string |
| sessions table | `$_SESSION` | user_id, login_time, csrf_token, pending_2fa |
| users + user_groups + user_groups_list | `hasPermission()` | permission boolean |
| user_groups (IDs) | `canViewRow()` | group ID array |
| row.view_groups | `canViewRow()` | group ID list (comma-sep) |
| form POST | CSRF check | token string |
| dbInsert/dbUpdate result | `recordVersion()` | JSON snapshot |
| hook table + hook files | `fireHook()` | row data, context |
| all writes | `logToDb()` | level, message, context |

---

## MySQL Schema Summary

All tables in creation order:

```sql
-- Phase 1
sessions         (session_id, user_id, ip_address, user_agent, data, last_active, created_at)
activity_log     (id, logged_at, level, user_id, session_id, ip_address, message, context)
password_history (id, user_id, password_hash, created_at)

-- Phase 2
-- No new tables; view_groups/edit_groups columns added to each custom table at provision time

-- Phase 3
row_versions     (id, table_name, row_id, version_num, changed_by, changed_at, action, row_snapshot, change_diff)
-- snapshots table already exists; file_path column used but may need NOT NULL enforcement

-- Phase 4
hooks            (id, name, description, table_name, event, hook_file, sort_order, status, created_at)
backup_codes     (id, user_id, code_hash, used_at, created_at)
-- users table: +totp_secret, +totp_enabled, +email_otp, +email_otp_expiry

-- Phase 5
-- No new tables; activate_at/deactivate_at columns added to standard columns list
```

---

## Architectural Patterns to Preserve

These patterns are already established in the codebase and new components should follow them:

1. **Each function in its own file.** Even helper functions for a feature (e.g., `base32Decode`) belong in the same feature file (`totp.php`), not scattered or combined into a utility file.

2. **Admin page files are controllers, not routers.** Each `functions/admin-*.php` file handles exactly one admin section. It reads GET/POST, performs business logic, builds `$page['content']` with `ob_start()`/`ob_get_clean()`, and returns. The template system wraps it.

3. **requirePermission() is the first meaningful line in every admin file.** Never let any data access happen before the permission check.

4. **Report rendering is the only list view.** Even when a list needs extra data (like the groups list with permission counts), the approach is to enhance the report SQL or the report's `html_row_template`, not to bypass the report system.

5. **All writes go through PDO prepared statements via `dbInsert()`/`dbUpdate()`/`dbQuery()`.** Never interpolate user data into SQL strings. The search/filter system must enforce this via field whitelisting.

6. **Status fields, not hard deletes.** Rows get `status = 'deleted'` not `DELETE FROM`. Exceptions: `group_permissions`, `user_groups` junction rows (these are hard-deleted because they carry no history value).

7. **Flash messages via GET redirect.** After POST, redirect with `?msg=created`. The page reads `$_GET['msg']` and renders a Bootstrap alert. This prevents double-submit and keeps the pattern consistent.

---

## Open Questions for Implementation

1. **QR code for TOTP setup:** Pure PHP QR renderer (~200 LOC) vs. URL-only display vs. external URL (breaks strict zero-dep). Recommend URL-only for setup screen: show the `otpauth://` URI as text that users copy into their authenticator app. Add QR renderer later if needed.

2. **JSON column support:** `row_snapshot` and `context` fields use MySQL JSON type. Requires MySQL 5.7.8+. If the target host is older, use MEDIUMTEXT and encode/decode manually with `json_encode`/`json_decode`. Flag this in the install doc.

3. **Snapshot restore:** Restoring a snapshot (reverting a table to a prior state) is destructive. It should fire `before_delete`/`after_delete` hooks for removed rows and `before_create`/`after_create` for re-added rows, and record versions for all affected rows. This makes restore expensive on large tables — consider a row-count warning threshold.

4. **Hook execution errors:** If a hook file throws an exception, should it abort the write? Recommend: `before_*` hooks can abort (return false = cancel); `after_*` hooks should never abort (log error, continue). This needs to be documented clearly for hook authors.

5. **Search across joined tables:** The current report SQL is a single table (`sql_table`). For search/filter on joined data (e.g., products with category name from a categories table), the report class's `sql_table` field would need to support a subquery or JOIN. Defer this to a later phase; document the limitation now.
