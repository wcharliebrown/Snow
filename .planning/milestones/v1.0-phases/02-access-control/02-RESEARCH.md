# Phase 2: Access Control - Research

**Researched:** 2026-03-01
**Domain:** PHP row-level ACL on a zero-dependency LAMP stack; MySQL schema provisioning; group-based access with comma-separated storage; admin group membership UI
**Confidence:** HIGH

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| ACL-01 | Users can belong to any number of groups | Schema already exists (`user_groups` junction table, `getUserGroups()` in `auth.php`); the admin-users.php edit form does NOT yet show or save group membership — that UI must be added |
| ACL-02 | Each managed table row has a view_groups field; only users in matching groups can view the row | `view_groups` column (VARCHAR, comma-separated group IDs) added to every provisioned table; `canViewRow()` function in new `acl.php`; list and detail views in `admin-custom-table.php` filtered through it; direct-URL access to detail view also blocked |
| ACL-03 | Each managed table row has an edit_groups field; only users in matching groups can edit the row | `edit_groups` column (VARCHAR, comma-separated group IDs) added to every provisioned table; `canEditRow()` function in `acl.php`; POST handler in `admin-custom-table.php` checks before saving; form rendered read-only or with a 403 message when edit access denied |
| DATA-01 | Every managed table automatically has standard fields: created_at, modified_at, status, view_groups, edit_groups | `provisionCustomTable()` in `admin-tables.php` updated to include all five columns; migration script adds these columns to all already-provisioned tables that are missing them |
</phase_requirements>

---

## Summary

Phase 2 builds row-level access control on top of the group infrastructure that already fully exists in the database and auth layer. The `user_groups_list`, `user_groups`, and `group_permissions` tables are in production. `getUserGroups($userId)` in `auth.php` returns a user's groups. The gap is that no row in any custom table currently carries group membership, and `admin-custom-table.php` enforces no per-row checks.

The implementation strategy is: (1) add `view_groups`, `edit_groups`, `created_at`, `modified_at`, `status` to every new provisioned table via `provisionCustomTable()`; (2) write a migration to add these columns to any already-existing custom tables; (3) create `functions/acl.php` with `canViewRow()`, `canEditRow()`, and a batch filter function; (4) wire these checks into `admin-custom-table.php`'s list view, detail/edit view, and POST handler; (5) add a group-assignment widget to the edit form for each row; (6) update the admin-users.php edit page to show and save group membership.

No new database tables are needed. No new admin pages are needed beyond the changes described above. The only schema changes are ALTER TABLE additions to existing custom tables plus the updated CREATE TABLE in `provisionCustomTable()`. This is a focused, surgical phase.

**Primary recommendation:** Store `view_groups` and `edit_groups` as comma-separated group ID strings (VARCHAR(500)); check access with PHP `array_intersect`; NULL means "open to all users with table_management permission". This avoids per-row junction table complexity and aligns with the project's existing patterns.

---

## Standard Stack

### Core

| Library | Version | Purpose | Why Standard |
|---------|---------|---------|--------------|
| PHP PDO + MySQL | Built-in (PHP 7.4+) | All DB access, schema ALTER | Already the only DB layer; `dbQuery()` wrapper handles parameterization |
| PHP `array_intersect()` / `explode()` | Built-in | ACL group ID intersection check | Zero deps; correct for comma-separated storage pattern |
| Bootstrap 5 (already loaded) | 5.x | Group checkbox widget in edit forms | All admin HTML already uses Bootstrap 5; `form-check` checkboxes match existing patterns in `admin-groups.php` |
| PHP `INFORMATION_SCHEMA` query | MySQL built-in | Conditional column addition during migration | Already used in Phase 1 migration; established pattern in this codebase |

### Supporting

| Library | Version | Purpose | When to Use |
|---------|---------|---------|-------------|
| PHP `implode()` / `explode()` | Built-in | Serialize/deserialize comma-separated group IDs | Used in `canViewRow()` and group widget POST handler |
| PHP `array_filter()` + `array_map('intval', ...)` | Built-in | Clean and cast group ID arrays from form POST | Prevents empty string elements from slipping into stored value |

### Alternatives Considered

| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Comma-separated group IDs in VARCHAR column | Per-row junction table (`row_view_groups`) | Junction table is normalized but requires JOIN on every list query or a subquery; comma-separated with PHP-side check is simpler and consistent with existing reporting/query patterns. The downside (no FK enforcement, no indexed lookup by group) is acceptable given the expected row counts and the fact that admins, not automation, set these values. |
| PHP-side intersection check | SQL WHERE with FIND_IN_SET() | `FIND_IN_SET(group_id, view_groups)` in SQL would filter at DB level but makes the report system's static `sql_where` clause unusable (it's per-user dynamic). PHP-side is simpler to integrate with the existing report rendering pipeline. |
| NULL = open to everyone | Empty string = open to everyone | NULL is more semantically correct (absence of constraint) and avoids ambiguity with empty CSV; consistent with how `required_permission` is stored in the `pages` table. |

**Installation:** None — zero external dependencies. All implementation is pure PHP + SQL.

---

## Architecture Patterns

### Recommended Project Structure

```
functions/
├── acl.php                  # canViewRow(), canEditRow(), filterRowsByViewAccess(),
│                            # getUserGroupIds(), groupIdsToNames()
├── admin-custom-table.php   # MODIFY: wire ACL checks into list, view, POST handler;
│                            # add group selector to add/edit forms
├── admin-tables.php         # MODIFY: update provisionCustomTable() with 5 standard cols
└── admin-users.php          # MODIFY: add group membership widget to edit form
```

No new admin page registrations needed. No new MySQL tables needed.

### Pattern 1: ACL Functions (acl.php)

**What:** A pure PHP file with three public functions. No state. Depends only on `getUserGroups()` from `auth.php` and `explode()`.

**When to use:** Called from `admin-custom-table.php` on every list query, detail fetch, and POST save. Also callable from future report scripts that need row-level filtering.

**Example:**

```php
// functions/acl.php

/**
 * Get current user's group IDs as an array of integers.
 * Returns empty array if not logged in.
 */
function getUserGroupIds(?int $userId = null): array {
    if ($userId === null) {
        $userId = getCurrentUserId();
    }
    if (!$userId) return [];
    $groups = getUserGroups($userId);
    return array_map('intval', array_column($groups, 'id'));
}

/**
 * Can the current user VIEW this row?
 * $row must contain a 'view_groups' key (may be null).
 * NULL view_groups = open to any authenticated user with table access.
 */
function canViewRow(array $row, ?int $userId = null): bool {
    if (empty($row['view_groups'])) {
        return true; // NULL or '' means no restriction
    }
    $allowed = array_filter(array_map('intval', explode(',', $row['view_groups'])));
    if (empty($allowed)) return true;
    $userGroupIds = getUserGroupIds($userId);
    return !empty(array_intersect($userGroupIds, $allowed));
}

/**
 * Can the current user EDIT this row?
 * NULL edit_groups = any user who can view can also edit.
 */
function canEditRow(array $row, ?int $userId = null): bool {
    if (empty($row['edit_groups'])) {
        return true;
    }
    $allowed = array_filter(array_map('intval', explode(',', $row['edit_groups'])));
    if (empty($allowed)) return true;
    $userGroupIds = getUserGroupIds($userId);
    return !empty(array_intersect($userGroupIds, $allowed));
}

/**
 * Filter a rows array to only those the current user can view.
 * Used for list views.
 */
function filterRowsByViewAccess(array $rows, ?int $userId = null): array {
    return array_values(array_filter($rows, function($row) use ($userId) {
        return canViewRow($row, $userId);
    }));
}

/**
 * Convert a stored comma-separated group ID string into a display string.
 * Returns 'All Users' for null/empty.
 */
function formatGroupIds(?string $groupIds): string {
    if (empty($groupIds)) return '<span class="text-muted">All Users</span>';
    $ids = array_filter(array_map('intval', explode(',', $groupIds)));
    if (empty($ids)) return '<span class="text-muted">All Users</span>';
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $rows = dbGetRows("SELECT name FROM user_groups_list WHERE id IN ($placeholders) ORDER BY name", $ids);
    $names = array_column($rows, 'name');
    return htmlspecialchars(implode(', ', $names));
}
```

### Pattern 2: Standard Column Provisioning (admin-tables.php)

**What:** Update `provisionCustomTable()` to add the five standard columns to every new table's CREATE TABLE statement.

**When to use:** Every time a new custom table is created via the admin UI.

**Example — updated CREATE TABLE block inside `provisionCustomTable()`:**

```php
dbQuery("CREATE TABLE IF NOT EXISTS `{$tableName}` (
    `id`          INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `status`      VARCHAR(20)  NOT NULL DEFAULT 'active',
    `view_groups` VARCHAR(500) DEFAULT NULL,
    `edit_groups` VARCHAR(500) DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `modified_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
```

**Migration for existing tables:** Any custom table already provisioned before Phase 2 will be missing these columns. A migration function must add them conditionally using INFORMATION_SCHEMA checks (same pattern used in Phase 1 for `password_changed_at`):

```php
function migrateExistingCustomTables(): void {
    $tables = dbGetRows("SELECT table_name FROM custom_tables WHERE status = 'active'", []);
    $standardCols = [
        'status'      => "VARCHAR(20) NOT NULL DEFAULT 'active'",
        'view_groups' => 'VARCHAR(500) DEFAULT NULL',
        'edit_groups' => 'VARCHAR(500) DEFAULT NULL',
        'created_at'  => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'modified_at' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
    ];
    foreach ($tables as $t) {
        $tableName = $t['table_name'];
        foreach ($standardCols as $colName => $colDef) {
            $exists = dbGetRow(
                "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$tableName, $colName]
            );
            if (empty($exists['n'])) {
                dbQuery("ALTER TABLE `{$tableName}` ADD COLUMN `{$colName}` {$colDef}");
            }
        }
    }
}
```

### Pattern 3: ACL Enforcement in admin-custom-table.php

**What:** Three enforcement points: (a) list view filters rows after query, (b) detail/edit view checks `canViewRow()` before rendering, blocks with 403 if denied, (c) POST handler checks `canEditRow()` before saving.

**When to use:** Every GET and POST in `admin-custom-table.php`.

**Example — list view (GET, list action):**

```php
// After fetching all rows:
$allRows = dbGetRows("SELECT * FROM `{$tableName}`");
$rows = filterRowsByViewAccess($allRows);
// Then render using $rows instead of $allRows
// Note: renderReport() fetches its own data — see Pitfall 2 below for how to handle this
```

**Example — detail/edit view (GET, edit action):**

```php
$record = dbGetRow("SELECT * FROM `{$tableName}` WHERE id = ?", [$recordId]);
if (!$record) {
    http_response_code(404);
    // render "not found" message
    return;
}
if (!canViewRow($record)) {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to view this record.</div>';
    return; // stops rendering
}
$canEdit = canEditRow($record);
// Pass $canEdit to the form to disable submit button or show read-only notice
```

**Example — POST handler (add action):**

```php
// For new records (add), no row-level check needed — the submitted groups define the row's ACL.
// The current user's group selection is stored as-is.

// For edit action, fetch the existing row first, then check:
$existing = dbGetRow("SELECT * FROM `{$tableName}` WHERE id = ?", [$recordId]);
if (!$existing || !canEditRow($existing)) {
    http_response_code(403);
    echo '<div class="alert alert-danger">You do not have permission to edit this record.</div>';
    return;
}
// Then proceed with update...
```

### Pattern 4: Group Selector Widget

**What:** A Bootstrap checkbox group rendered inside the add/edit form for each custom table row. Lets the admin choose which groups can view and which can edit the row. Uses the same checkbox pattern already in `admin-groups.php`.

**When to use:** Inside the add/edit form in `admin-custom-table.php`.

**Example:**

```php
// At top of form-rendering section, load all active groups:
$allGroups = dbGetRows("SELECT * FROM user_groups_list WHERE status = 'active' ORDER BY name", []);

// Current selections from DB (or POST on validation error):
$currentViewGroups = array_filter(array_map('intval', explode(',', $_POST['view_groups'] ?? $record['view_groups'] ?? '')));
$currentEditGroups = array_filter(array_map('intval', explode(',', $_POST['edit_groups'] ?? $record['edit_groups'] ?? '')));
?>
<div class="col-12">
    <label class="form-label">View Groups <small class="text-muted">(leave unchecked for all users)</small></label>
    <div class="row">
        <?php foreach ($allGroups as $g): ?>
        <div class="col-md-4">
            <div class="form-check">
                <input type="checkbox" name="view_groups[]" class="form-check-input"
                    id="vg_<?= (int)$g['id'] ?>" value="<?= (int)$g['id'] ?>"
                    <?= in_array((int)$g['id'], $currentViewGroups) ? 'checked' : '' ?>>
                <label class="form-check-label" for="vg_<?= (int)$g['id'] ?>">
                    <?= htmlspecialchars($g['name']) ?>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<!-- Identical block for edit_groups[] -->
```

**POST handler — serialize group selections:**

```php
// At the top of POST handling, before building $rowData:
$viewGroupIds = array_filter(array_map('intval', (array)($_POST['view_groups'] ?? [])));
$editGroupIds  = array_filter(array_map('intval', (array)($_POST['edit_groups'] ?? [])));

$rowData['view_groups'] = !empty($viewGroupIds) ? implode(',', $viewGroupIds) : null;
$rowData['edit_groups']  = !empty($editGroupIds)  ? implode(',', $editGroupIds)  : null;
```

### Pattern 5: Group Membership in admin-users.php Edit Form

**What:** Add a group membership widget (identical in style to the group permissions widget in `admin-groups.php`) to the user edit form. On POST, delete all existing `user_groups` rows for this user and insert new ones.

**When to use:** On the `edit` action in `admin-users.php`.

**Example:**

```php
// Load current group memberships for this user:
$userGroups = getUserGroups($userId); // from auth.php
$currentGroupIds = array_map('intval', array_column($userGroups, 'id'));
$selectedGroupIds = isset($_POST['groups'])
    ? array_map('intval', (array)$_POST['groups'])
    : $currentGroupIds;

// POST handler — save group memberships:
$newGroupIds = array_filter(array_map('intval', (array)($_POST['groups'] ?? [])));
dbQuery("DELETE FROM user_groups WHERE user_id = ?", [$userId]);
foreach ($newGroupIds as $gid) {
    dbInsert('user_groups', ['user_id' => $userId, 'group_id' => $gid]);
}
```

### Anti-Patterns to Avoid

- **Filtering at the report SQL level only:** The `renderReport()` function builds and runs its own SQL from the `report_templates` record. The `sql_where` clause in the stored report is static and cannot reference the current user's group IDs. Do NOT try to inject per-user WHERE clauses into the stored report SQL. Instead, filter the list results in PHP after fetching, OR bypass `renderReport()` for ACL-controlled lists and render the table directly with a filtered row set.

- **Checking ACL on POST but not on GET detail view:** A user who can guess a URL (`/admin/data/products?action=edit&id=42`) would reach the edit form without a view-level check. Both GET (rendering the edit form) and POST (processing the save) must check ACL independently.

- **Storing group names instead of group IDs:** Group names can be renamed. Storing IDs is stable. Always store integer IDs in `view_groups`/`edit_groups`.

- **Relying on `table_management` permission as the only gate:** `table_management` is a page-level permission check (already in place). Row-level ACL is an additional, finer-grained check. Never remove or weaken the page-level `requirePermission('table_management')` call.

- **Using `FIND_IN_SET()` in stored report SQL:** `FIND_IN_SET(?, view_groups)` requires knowing the user's group IDs at report-build time, which the static report template system cannot support. Do not add per-user SQL logic to `report_templates` rows.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Group ID intersection | Custom loop | `array_intersect()` | PHP built-in; correct; readable |
| Conditional ALTER TABLE | String manipulation | `INFORMATION_SCHEMA.COLUMNS` SELECT + conditional `ALTER TABLE` | Already established pattern in this codebase (Phase 1, password_changed_at migration) |
| Group selection UI | Custom JS widget | Bootstrap `form-check` checkboxes | Already used in `admin-groups.php` for permission assignment; consistent pattern |
| Group name lookup | Custom JOIN in ACL check | `getUserGroups()` already exists in `auth.php` | Function already returns id + name + status; no new query needed |

**Key insight:** The group infrastructure (tables, functions, admin UI) is fully built and working. Phase 2 is plumbing — connecting existing group data to row storage and adding enforcement checks at the right points in `admin-custom-table.php`.

---

## Common Pitfalls

### Pitfall 1: renderReport() Bypasses PHP-Side ACL Filter

**What goes wrong:** `admin-custom-table.php` list view currently calls `renderReport($listReport)` which runs its own SQL internally. If you call `filterRowsByViewAccess()` on rows you fetched separately, those filtered rows are thrown away — `renderReport()` fetches again from the DB without any ACL filter.

**Why it happens:** The report system is designed to be self-contained: it builds SQL from the stored template and paginates internally. It does not accept a pre-fetched row set.

**How to avoid:** For the list view in `admin-custom-table.php`, do NOT use `renderReport()` for the main data table. Instead, build the table HTML directly using the filtered rows. Keep `renderReport()` only for reports that don't need per-user row filtering. Alternatively, add a `sql_where` fragment injection mechanism to `renderReport()` — but this is more complex and risks breaking other reports.

**Recommended approach:** In `admin-custom-table.php`, replace the `renderReport($listReport)` call for the data table with direct PHP rendering. Fetch all rows for the table, apply `filterRowsByViewAccess()`, then render the table using the `custom_table_fields` column definitions. This is more code in `admin-custom-table.php` but keeps ACL enforcement correct and explicit.

**Warning signs:** A user in restricted groups can still see filtered rows by checking different page numbers, or a row count mismatch appears between the "N records" counter and the visible table rows.

### Pitfall 2: Modified_At Column Conflicts with Existing Tables

**What goes wrong:** The existing `provisionCustomTable()` creates tables with `created_at` and `updated_at` (not `modified_at`). Phase 2 standardizes on `modified_at`. If existing tables already have `updated_at`, a migration that adds `modified_at` will leave them with both columns, which is confusing. New tables will have `modified_at` but not `updated_at`.

**Why it happens:** The original provisioner used `updated_at` (standard MySQL convention). The requirements specify `modified_at`.

**How to avoid:** Check the actual column name the existing provisioner created. From code inspection, the current `provisionCustomTable()` in `admin-tables.php` creates `created_at` and `updated_at`. The requirements say the standard columns should be `created_at` and `modified_at`. Two options: (a) use `modified_at` for new tables and leave existing tables with `updated_at` (document the discrepancy); or (b) rename `updated_at` to `modified_at` in the migration. Option (a) is less disruptive. The planner must decide.

**Warning signs:** The migration script adds a `modified_at` column to a table that already has `updated_at`, resulting in two timestamp columns.

**Confirmed column names from code inspection of `admin-tables.php` line 113-118:**
```
`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
```
The phase 2 provisioner should use `modified_at` (per DATA-01 requirement). For existing tables: add `modified_at` as a new column (don't rename `updated_at` — too disruptive and risky for Phase 2 scope).

### Pitfall 3: Add Action Has No Row to Check ACL Against

**What goes wrong:** For the "add" action (creating a new row), there is no existing row to call `canViewRow()` or `canEditRow()` on. The ACL for the new row is what the user is defining right now.

**Why it happens:** `canEditRow()` takes a row as input. A new row doesn't exist yet.

**How to avoid:** For the `add` action, skip row-level ACL and rely solely on the page-level `requirePermission('table_management')` check. Any user with `table_management` permission can create new rows in any table. The view_groups/edit_groups they set on the new row will control future access.

**Warning signs:** Attempting to call `canEditRow([])` with an empty array and getting a false result, blocking all new row creation.

### Pitfall 4: Empty Array vs NULL in Group Storage

**What goes wrong:** When a user saves an edit form with no group checkboxes selected (intending "open to all"), the POST sends `$_POST['view_groups'] = []` (or the key is absent entirely). An uncareful implementation stores `""` (empty string) instead of `NULL`. A subsequent `canViewRow()` call may treat `""` differently from `NULL`.

**Why it happens:** `implode(',', [])` returns `""`, not `NULL`. And `empty("")` is true in PHP but `is_null("")` is false.

**How to avoid:** After building the group ID array from POST, store `NULL` (not `""`) when the array is empty:
```php
$rowData['view_groups'] = !empty($viewGroupIds) ? implode(',', $viewGroupIds) : null;
```
And in `canViewRow()`, use `empty($row['view_groups'])` as the open-access check — this correctly handles both `NULL` and `""`.

**Warning signs:** All rows appear accessible to all users regardless of group settings because `canViewRow()` treats stored `""` as NULL and bypasses the check.

### Pitfall 5: getUserGroups() Called Repeatedly Per Row in List View

**What goes wrong:** `filterRowsByViewAccess()` calls `getUserGroupIds()` for each row in the loop, which calls `getUserGroups()`, which runs a DB query. For a list of 50 rows, this is 50 identical DB queries.

**Why it happens:** The naive implementation of `filterRowsByViewAccess()` fetches user groups inside the per-row check.

**How to avoid:** Fetch the user's group IDs once before the filter loop and pass them in, or use a static/cached variable inside `getUserGroupIds()`:
```php
function getUserGroupIds(?int $userId = null): array {
    static $cache = [];
    if ($userId === null) $userId = getCurrentUserId();
    if (!$userId) return [];
    if (!isset($cache[$userId])) {
        $groups = getUserGroups($userId);
        $cache[$userId] = array_map('intval', array_column($groups, 'id'));
    }
    return $cache[$userId];
}
```

**Warning signs:** Slow list view page loads; DB query count proportional to row count in the table.

### Pitfall 6: Custom Table Existing Data Has No view_groups/edit_groups

**What goes wrong:** After the migration adds `view_groups` and `edit_groups` columns to existing tables, all existing rows have `NULL` in those columns. `canViewRow()` treats `NULL` as "open to all" — this is correct default behavior. But if an admin tries to view a row using the edit form before they know about the new columns, no errors occur.

**Why it happens:** NULL = open is the intended semantic. But there is no visible indicator in the list view that a row has no ACL set, which may confuse admins who expect rows to be locked down by default.

**How to avoid:** This is by design — NULL = "open to all with table access" is the correct default. Document this in the Phase plan so the admin understands the open-by-default behavior. Optionally add a visual indicator (e.g., a badge in the list view showing "Open" vs the group names) so admins can see which rows have ACL set.

---

## Code Examples

Verified patterns from direct codebase inspection:

### INFORMATION_SCHEMA Conditional Column Addition (Phase 1 Pattern)

This exact pattern is already in `database_schema.sql` (Phase 1 migration for `password_changed_at`):

```sql
-- Conditional guard: only adds column if it does not already exist (MySQL 8.0 compatible)
SET @col_exists = (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'users'
    AND COLUMN_NAME = 'password_changed_at'
);
SET @sql = IF(@col_exists = 0,
    'ALTER TABLE users ADD COLUMN password_changed_at DATETIME NULL AFTER last_login',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
```

For Phase 2 migration, the same pattern applies per-table, per-column. Since we're doing this programmatically across all custom tables (not one-off in SQL), the PHP-based INFORMATION_SCHEMA check approach is better:

```php
$exists = dbGetRow(
    "SELECT COUNT(*) AS n FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
    [$tableName, $colName]
);
if (empty($exists['n'])) {
    dbQuery("ALTER TABLE `{$tableName}` ADD COLUMN `{$colName}` {$colDef}");
}
```

### Existing getUserGroups() in auth.php

The function already exists and returns the correct shape:

```php
// Source: functions/auth.php line 282-287
function getUserGroups($userId) {
    $sql = "SELECT g.* FROM user_groups_list g
            JOIN user_groups ug ON g.id = ug.group_id
            WHERE ug.user_id = ? AND g.status = 'active'";
    return dbGetRows($sql, [$userId]);
}
// Returns: [['id' => 1, 'name' => 'Administrators', 'status' => 'active', ...], ...]
```

`acl.php` calls this with `array_column($groups, 'id')` to extract just the IDs.

### Existing Group Membership Tables (Schema)

Already in `database_schema.sql`:

```sql
-- User Groups junction table (already exists in production)
CREATE TABLE user_groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    created_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES user_groups_list(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_group (user_id, group_id),
    INDEX idx_user_id (user_id),
    INDEX idx_group_id (group_id)
);
```

**No new tables needed for Phase 2.**

### Existing Permission Check Pattern (pages.php line 34-37)

```php
// Source: functions/pages.php line 34-37
if ($page['required_permission'] && !hasPermission($page['required_permission'])) {
    http_response_code(403);
    renderErrorPage(403, 'Access denied');
    return;
}
```

Row-level ACL uses the same 403 pattern but inline in `admin-custom-table.php` rather than in `pages.php`.

### Existing Admin-Groups.php Group Checkbox Pattern

```php
// Source: functions/admin-groups.php line 163-177 (Permissions checkboxes — same pattern for Groups)
<?php foreach ($allPermissions as $perm): ?>
<div class="col-md-4">
    <div class="form-check">
        <input type="checkbox" name="permissions[]" class="form-check-input"
            id="perm_<?= (int)$perm['id'] ?>" value="<?= (int)$perm['id'] ?>"
            <?= in_array((int)$perm['id'], $selectedPerms) ? 'checked' : '' ?>>
        <label class="form-check-label" for="perm_<?= (int)$perm['id'] ?>">
            <strong><?= htmlspecialchars($perm['name']) ?></strong>
        </label>
    </div>
</div>
<?php endforeach; ?>
```

The group selector widget for `view_groups`/`edit_groups` in `admin-custom-table.php` and the group membership widget in `admin-users.php` use this exact same pattern with `user_groups_list` rows instead of `permissions` rows.

### SQL Migration: Add Standard Columns to Existing Tables

These are the five DDL fragments to add per existing custom table (only if the column does not already exist):

```sql
-- status (new standard column)
ALTER TABLE `{tableName}` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'active';

-- view_groups
ALTER TABLE `{tableName}` ADD COLUMN `view_groups` VARCHAR(500) DEFAULT NULL;

-- edit_groups
ALTER TABLE `{tableName}` ADD COLUMN `edit_groups` VARCHAR(500) DEFAULT NULL;

-- created_at (may already exist — check first)
ALTER TABLE `{tableName}` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP;

-- modified_at (new name; existing tables may have `updated_at` instead)
ALTER TABLE `{tableName}` ADD COLUMN `modified_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
```

---

## State of the Art

| Old Approach | Current Approach | Notes |
|--------------|------------------|-------|
| Page-level permission check only | Page-level + row-level ACL | Page permission gates which users reach the admin page; row ACL gates which rows they see within it |
| `updated_at` column name in provisioner | `modified_at` column name per DATA-01 | Existing tables keep `updated_at`; new tables get `modified_at` (see Pitfall 2) |
| All rows visible in list to any user with `table_management` | Only rows in user's view_groups visible | Group check done in PHP after DB fetch |
| Group membership visible only via admin-groups.php | Group membership also editable from admin-users.php edit page | ACL-01 requires admin can set group membership from user edit page |

**Existing patterns that this phase should NOT change:**

- The `requirePermission('table_management')` call at the top of `admin-custom-table.php` — keep it; row ACL is additive, not a replacement
- The `renderReport()` for non-custom-table reports (users_list, groups_list, etc.) — those are admin-only and do not need row-level ACL
- The `csrfField()` call in all forms — already in place from Phase 1; Phase 2 forms must also include it
- The `ob_start()`/`ob_get_clean()` output buffering pattern in all admin scripts

---

## Open Questions

1. **updated_at vs modified_at column naming**
   - What we know: Current provisioner creates `updated_at`; DATA-01 specifies `modified_at`
   - What's unclear: Whether to rename existing `updated_at` to `modified_at` or add `modified_at` alongside `updated_at`
   - Recommendation: Add `modified_at` as a new column to existing tables. Leave `updated_at` in place to avoid breaking existing report templates that may reference it. New tables get only `modified_at` (no `updated_at`). Planner should decide and document this.

2. **Should view_groups restrict Administrators group members?**
   - What we know: `Administrators` group is a core group with all permissions
   - What's unclear: Whether admins should be able to bypass row-level view_groups restrictions entirely
   - Recommendation: Admins bypass row-level ACL — if the user has `admin_access` permission, skip the `canViewRow()` check. This prevents admins from accidentally locking themselves out of all rows. Implement as: `if (hasPermission('admin_access')) return true;` at the top of `canViewRow()` and `canEditRow()`.

3. **How to handle ACL on the list view count display**
   - What we know: Current list view shows "N records" count from `SELECT COUNT(*) FROM {table}`
   - What's unclear: Whether the count should reflect total rows or visible rows after ACL filter
   - Recommendation: Show visible row count only (post-filter). Update the count to reflect `count(filterRowsByViewAccess($rows))`.

4. **generateCustomTableReport() report template and view_groups columns**
   - What we know: `generateCustomTableReport()` in `admin-tables.php` auto-generates the `*_list` report template. The generated SQL selects only fields from `custom_table_fields` plus `id`. view_groups and edit_groups are not in `custom_table_fields`.
   - What's unclear: Should the auto-generated list view show view_groups/edit_groups columns in the report table?
   - Recommendation: Do NOT add view_groups/edit_groups to the auto-generated report columns — they are system/meta columns, not user data. The ACL filter runs before the report renders. This is the cleanest approach.

---

## Files to Create / Modify (Summary)

### New Files

| File | Contents |
|------|---------|
| `functions/acl.php` | `getUserGroupIds()`, `canViewRow()`, `canEditRow()`, `filterRowsByViewAccess()`, `formatGroupIds()` |

### Files to Modify

| File | What Changes |
|------|-------------|
| `functions/admin-tables.php` | Update `provisionCustomTable()` CREATE TABLE to include all 5 standard columns; add `migrateExistingCustomTables()` function; call migration on a one-time trigger |
| `functions/admin-custom-table.php` | List view: apply `filterRowsByViewAccess()` and render HTML directly (bypass `renderReport()`); edit GET: add `canViewRow()` and `canEditRow()` checks; POST handler: add `canEditRow()` check; add/edit forms: add group selector widget for `view_groups`/`edit_groups` |
| `functions/admin-users.php` | Add group membership checkbox widget to the edit form; on POST save, delete+reinsert `user_groups` rows |
| `database_schema.sql` | Document the Phase 2 standard column definitions (for reference and fresh installs) |

---

## Sources

### Primary (HIGH confidence)

- Direct codebase inspection — `functions/admin-custom-table.php`, `functions/admin-tables.php`, `functions/admin-users.php`, `functions/admin-groups.php`, `functions/auth.php`, `functions/pages.php`, `functions/reports.php`, `database_schema.sql` — all patterns, existing functions, and schema details verified by reading source files
- Phase 1 migration in `database_schema.sql` — INFORMATION_SCHEMA conditional ALTER TABLE pattern confirmed as established project pattern
- `.planning/research/ARCHITECTURE.md` — ACL design decision (comma-separated group IDs in VARCHAR) and `acl.php` function signatures confirmed; `filterRowsByViewAccess()` data flow diagram verified

### Secondary (MEDIUM confidence)

- `.planning/phases/01-security-foundations/01-RESEARCH.md` — confirms function-per-file pattern, Bootstrap 5 checkbox pattern, ob_start/ob_get_clean pattern, and CSRF field requirement for all forms
- `.planning/STATE.md` — confirms Phase 1 complete, group infrastructure already in production, Phase 2 is next

### Tertiary (LOW confidence — none)

No unverified claims. All findings are from direct codebase inspection.

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — all implementation uses PHP built-ins and existing codebase patterns; confirmed by source code inspection
- Architecture: HIGH — group tables confirmed in production schema; `getUserGroups()` confirmed working; ACL design confirmed in ARCHITECTURE.md research doc
- Pitfalls: HIGH — identified from direct inspection of `renderReport()`, `admin-custom-table.php`, and the existing provisioner code, not speculation

**Research date:** 2026-03-01
**Valid until:** 2026-06-01 (90 days — stable LAMP/PHP patterns, no fast-moving ecosystem; internal architecture is locked by zero-dep constraint)
