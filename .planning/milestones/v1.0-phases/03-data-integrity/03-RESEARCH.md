# Phase 3: Data Integrity - Research

**Researched:** 2026-03-05
**Domain:** MySQL table versioning, row-level audit trails, table snapshot/diff/restore
**Confidence:** HIGH

---

<user_constraints>
## User Constraints (from CONTEXT.md)

### Locked Decisions

**Snapshot storage:**
- Snapshots are real MySQL table duplicates — `CREATE TABLE snapshot_{table}_{YYYYMMDDhhmmss} AS SELECT * FROM {table}`
- The snapshot table name format is: `snapshot_originalTableName_YYYYMMDDhhmmss`
- The existing `snapshots` metadata table (id, table_name, snapshot_name, description, snapshot_date, row_count, status, created_by) records each snapshot; `file_path` column remains NULL (not used for file-based storage)
- The existing `admin-snapshots.php` currently only inserts metadata — it needs to be updated to also execute the `CREATE TABLE AS SELECT` statement

**Snapshot restore:**
- Restore sequence: (1) auto-snapshot the live table first, (2) rename live table to a temporary name, (3) rename snapshot table to the live table name — so the admin always has a way back
- The auto-created pre-restore snapshot is recorded in the `snapshots` metadata table like any other snapshot
- No manual pre-restore snapshot required from the admin

**Snapshot diff display:**
- Only rows that have at least one field difference are shown
- For each changed row: snapshot values appear in one HTML table row; live values appear in the row immediately below it; fields that differ are highlighted
- Rows that exist only in the snapshot (deleted since snapshot) and rows that exist only in the live table (added since snapshot) are displayed in a separate section at the bottom of the diff
- Diff is compared by primary key (`id` column)

**Version history UI (row-level):**
- Two selectors appear on the edit form for every custom table row:
  1. **Diff selector** — lists past versions by timestamp; selecting one shows a diff of that version vs. the current state (highlighted field-level differences)
  2. **Revert selector** — lists past versions by timestamp; selecting one and confirming reverts the row to that version
- Both selectors only appear if version history exists for the row
- Reverting via the revert selector saves the current state as a new version entry before overwriting (current state is never lost)

**Row version storage:**
- A `row_versions` table stores a JSON snapshot of the full row before each edit: `(id, table_name, row_id, changed_by, changed_at, row_snapshot JSON)`
- Version capture happens in the `admin-custom-table.php` POST handler: before each `dbUpdate()` call, the existing row is fetched and inserted into `row_versions`
- Inserts (new rows) do not create a version entry — only edits (updates) do
- Deletes do not create a version entry — deletion is final

### Claude's Discretion
- Exact HTML/CSS for diff highlighting (color choice for changed fields)
- Whether the two selectors on the edit form are `<select>` dropdowns or another UI pattern
- Indexing strategy for `row_versions` table
- Pagination/truncation of version history lists for rows with many versions

### Deferred Ideas (OUT OF SCOPE)
None — discussion stayed within phase scope.
</user_constraints>

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|-----------------|
| VER-01 | System records which user modified each row and what changed (JSON snapshot before each edit) | `row_versions` table DDL, version capture pattern in POST handler, JSON column usage in MySQL 8.0 |
| VER-02 | Admin can roll back any row to any prior version | Revert selector UI, version-before-revert capture, `dbUpdate()` from JSON snapshot, flash message pattern |
| VER-03 | Admin can create a snapshot (full data copy) of any table | `CREATE TABLE ... AS SELECT`, snapshot metadata insert, `admin-snapshots.php` create action update |
| VER-04 | Admin can view a diff showing row-level changes to a table since its last snapshot | PHP-side diff algorithm keyed on `id`, two-row changed display, added/deleted sections |
| VER-05 | Admin can restore a table to its state at the time of any snapshot | Three-step atomic rename sequence using transactions, auto pre-restore snapshot, schema-drift handling |
</phase_requirements>

---

## Summary

Phase 3 adds two orthogonal versioning systems to Snow. The **row-level system** (VER-01, VER-02) captures a JSON before-image of every custom table row on each edit and exposes it via diff/revert selectors on the existing edit form. The **table-level system** (VER-03, VER-04, VER-05) makes the existing snapshot metadata page real by executing `CREATE TABLE ... AS SELECT` at create time and implementing diff and restore operations.

Both systems are pure MySQL + PHP — no external libraries, consistent with Snow's zero-dependency constraint. All helper functions needed (transactions, `dbGetRow`, `dbInsert`, `dbUpdate`, `dbTableExists`) are already present in `functions/database.php`. The primary implementation surface is two existing files: `admin-custom-table.php` (row versioning) and `admin-snapshots.php` (snapshot operations).

The most technically sensitive piece is the table restore. MySQL does not have an atomic `RENAME TABLE a TO b, b TO c` when foreign keys exist, but custom tables in Snow are self-contained (no FK references from other tables) so a two-RENAME sequence inside a transaction is reliable. Schema-drift (columns added/removed since snapshot) is handled by `CREATE TABLE ... AS SELECT` producing only the snapshot's column set — the live table columns take precedence at restore time because the snapshot table becomes the live table.

**Primary recommendation:** Implement in four work tasks — (1) `row_versions` migration + capture, (2) row diff/revert UI, (3) snapshot create fix + diff view, (4) snapshot restore. Each task is independently verifiable by manual browser test.

---

## Standard Stack

### Core
| Component | Version | Purpose | Why Standard |
|-----------|---------|---------|--------------|
| MySQL 8.0 (InnoDB) | 8.0.x | JSON column, RENAME TABLE, CREATE TABLE AS SELECT | Already in stack; JSON type native in 8.0 |
| PHP 8.x PDO | 8.x | All DB access | Already in stack via `functions/database.php` |
| Bootstrap 5 | 5.x | Diff highlighting, selector UI | Already in stack for all admin pages |

### No New Dependencies
This phase introduces zero new libraries. All operations use MySQL DDL statements (CREATE TABLE, RENAME TABLE) issued via existing `dbQuery()`, and JSON encode/decode via PHP's built-in `json_encode()` / `json_decode()`.

---

## Architecture Patterns

### Recommended File Layout Changes

```
functions/
├── admin-custom-table.php    # Add version capture + diff/revert UI sections
├── admin-snapshots.php       # Add CREATE TABLE AS SELECT, diff view, restore POST
└── database.php              # No changes needed — all helpers already present

database_schema.sql           # Add Phase 3 migration block at bottom
```

### Pattern 1: row_versions Table DDL

Create at the bottom of `database_schema.sql` as a Phase 3 migration block:

```sql
-- =============================================================================
-- Phase 3: Data Integrity — row_versions table (VER-01)
-- =============================================================================
CREATE TABLE IF NOT EXISTS row_versions (
    id         INT          NOT NULL AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(255) NOT NULL,
    row_id     INT          NOT NULL,
    changed_by INT          NULL,
    changed_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    row_snapshot JSON       NOT NULL,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_table_row (table_name, row_id),
    INDEX idx_changed_at (changed_at),
    INDEX idx_changed_by (changed_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Why this index:** `(table_name, row_id)` covers the most common query pattern — "fetch all versions for this row in this table." `changed_at` covers audit/chronological queries.

### Pattern 2: Version Capture in admin-custom-table.php POST Handler

Insert immediately before the existing `dbUpdate()` call in the `edit` branch:

```php
// Capture current row state before overwriting (VER-01)
$preEditRow = dbGetRow("SELECT * FROM `{$tableName}` WHERE id = ?", [$recordId]);
if ($preEditRow) {
    $currentUser = getCurrentUser();
    dbInsert('row_versions', [
        'table_name'   => $tableName,
        'row_id'       => $recordId,
        'changed_by'   => $currentUser['id'] ?? null,
        'changed_at'   => date('Y-m-d H:i:s'),
        'row_snapshot' => json_encode($preEditRow),
    ]);
}
// existing dbUpdate() call follows here
dbUpdate($tableName, $rowData, 'id = ?', [$recordId]);
```

**Why fetch before update:** The version entry records the state the row was IN before the change — the "before image" — so revert can restore it exactly.

### Pattern 3: Snapshot Create (fix admin-snapshots.php)

Add after the `dbInsert('snapshots', ...)` call in the `create` action, before the redirect:

```php
$snapshotTableName = 'snapshot_' . $tableName . '_' . date('YmdHis');
dbQuery("CREATE TABLE `{$snapshotTableName}` AS SELECT * FROM `{$tableName}`", []);
// Update the metadata row with the actual snapshot table name
// (snapshot_name field already captures human-readable name;
//  store the physical table name in a new column or use snapshot_name consistently)
```

**Important:** The snapshot table name uses `YmdHis` (no underscores between parts) matching the CONTEXT decision: `snapshot_products_20260305143022`.

### Pattern 4: Snapshot Restore (atomic rename sequence)

```php
// VER-05: Restore sequence inside a transaction
$liveTable     = $tableName;                          // e.g. "products"
$snapshotTable = $snapshotRow['snapshot_table'];      // e.g. "snapshot_products_20260305143022"
$tempTable     = $liveTable . '_pre_restore_' . date('YmdHis');

// Step 1: auto-snapshot live table first (preserves current state)
$autoSnapshotTable = 'snapshot_' . $liveTable . '_' . date('YmdHis');
dbQuery("CREATE TABLE `{$autoSnapshotTable}` AS SELECT * FROM `{$liveTable}`", []);
// record auto-snapshot in metadata ...
dbInsert('snapshots', [ /* ... */ ]);

// Step 2 & 3: rename live → temp, snapshot → live
dbBeginTransaction();
try {
    // MySQL supports multi-table RENAME in one statement (atomic)
    dbQuery(
        "RENAME TABLE `{$liveTable}` TO `{$tempTable}`, `{$snapshotTable}` TO `{$liveTable}`",
        []
    );
    dbCommit();
} catch (Exception $e) {
    dbRollback();
    // surface error to admin
}
```

**Why RENAME TABLE with two pairs in one statement:** MySQL executes multi-table `RENAME TABLE` atomically — both renames succeed or both fail. This is safer than two sequential `RENAME TABLE` calls.

**After restore:** The temp table (`_pre_restore_*`) can be dropped or kept. Keep it — it serves as an additional safety net. Record it in snapshots metadata as an auto-created entry so the admin can see it and delete it via the normal snapshot delete action.

### Pattern 5: Snapshot Diff (PHP-side comparison)

```php
// VER-04: load both sides
$snapshotRows = dbGetRows("SELECT * FROM `{$snapshotTable}`", []);
$liveRows     = dbGetRows("SELECT * FROM `{$liveTable}`", []);

// Index by id
$snapById = array_column($snapshotRows, null, 'id');
$liveById = array_column($liveRows,     null, 'id');

$changed = [];   // rows in both, with differences
$deleted = [];   // in snapshot only (deleted from live)
$added   = [];   // in live only (added since snapshot)

foreach ($snapById as $id => $snapRow) {
    if (!isset($liveById[$id])) {
        $deleted[] = $snapRow;
    } elseif ($snapRow !== $liveById[$id]) {
        $changed[] = ['snapshot' => $snapRow, 'live' => $liveById[$id]];
    }
}
foreach ($liveById as $id => $liveRow) {
    if (!isset($snapById[$id])) {
        $added[] = $liveRow;
    }
}
```

**Rendering changed rows:** Two `<tr>` elements per changed pair. Use inline style `background-color: #fff3cd` (Bootstrap warning-yellow) on `<td>` elements where values differ. This is visually clear without needing custom CSS.

### Pattern 6: Row Diff/Revert UI on Edit Form

Load versions once at the top of the `edit` GET branch:

```php
$versions = dbGetRows(
    "SELECT id, changed_by, changed_at FROM row_versions
     WHERE table_name = ? AND row_id = ?
     ORDER BY changed_at DESC",
    [$tableName, $recordId]
);
```

Render the two selectors only when `!empty($versions)`. Use `<select>` dropdowns — consistent with existing form controls in admin-custom-table.php.

**Diff selector:** On change, submit a GET request to `?action=edit&id={id}&version_diff={version_id}`. The edit handler loads the version's `row_snapshot` JSON and renders a field-by-field diff table above the form.

**Revert selector:** A separate form with a hidden `action=revert` and `version_id` field. On submit, load the version's JSON, capture current state as a new `row_versions` entry, then call `dbUpdate()` with the JSON fields.

### Anti-Patterns to Avoid

- **Storing diff instead of snapshot:** The CONTEXT decision is to store the full `row_snapshot` JSON. Computing diffs at write time loses flexibility — always store the full before-image and compute diffs at read time.
- **Using file-based snapshot storage:** The CONTEXT decision explicitly uses `CREATE TABLE AS SELECT`. The `SNOW_SNAPSHOTS` constant and directory remain unused.
- **Sequential RENAME TABLE calls:** Two separate `RENAME TABLE` statements are not atomic. Use a single `RENAME TABLE a TO b, c TO d` statement.
- **Mutating the snapshot table during restore:** The rename approach is non-destructive. Never do `TRUNCATE live; INSERT INTO live SELECT * FROM snapshot` — it is not atomic and loses data if interrupted.
- **Fetching all version JSON in the selector query:** Only load `id` and `changed_at` for the dropdown list. Load the full `row_snapshot` only when a specific version is selected.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| JSON serialization of row | Custom serializer | `json_encode()` / `json_decode()` | PHP built-in; MySQL JSON column validates at insert |
| Atomic table swap | Sequential renames | Single `RENAME TABLE a TO b, c TO d` | MySQL atomicity guarantee |
| Field-level diff | External diff library | PHP `array_diff_assoc()` on decoded JSON arrays | Built-in; no deps needed |
| Transaction wrapping | Manual PDO calls | `dbBeginTransaction()` / `dbCommit()` / `dbRollback()` | Already in `database.php` |

---

## Common Pitfalls

### Pitfall 1: Snapshot Table Name Collision
**What goes wrong:** Two admins create a snapshot of the same table within the same second — `date('YmdHis')` produces the same string, `CREATE TABLE` fails with "table already exists."
**Why it happens:** Second-resolution timestamp is not unique under rapid-fire usage.
**How to avoid:** Append a 3-digit random suffix: `date('YmdHis') . rand(100, 999)`. Alternatively, add a `SLEEP(1)` guard — but the random suffix is cleaner.
**Warning signs:** PDO exception "Table already exists" in create action.

### Pitfall 2: Schema Drift on Restore (Columns Added/Removed After Snapshot)
**What goes wrong:** Live table has columns that the snapshot table does not (or vice versa). After renaming snapshot → live, queries that expect the current column set break.
**Why it happens:** Columns may be added via Phase 5 (custom field management) after a snapshot was taken.
**How to avoid:** After the rename, detect column differences using `INFORMATION_SCHEMA` and log a warning to the admin. The STATE.md already flags this as a known concern. For v1, surface a warning banner on the restore confirmation page: "Column set may differ — verify after restore." Full column reconciliation is deferred.
**Warning signs:** PHP notices about undefined array keys on data pages after restore.

### Pitfall 3: Version Capture Runs on Every POST, Including ACL Failures
**What goes wrong:** Version is captured even when the edit POST fails the ACL check, recording a false "who changed it" entry.
**Why it happens:** The capture code is inserted before the ACL check.
**How to avoid:** Version capture must occur AFTER the ACL check passes and AFTER field validation passes — only when the edit will definitely proceed to `dbUpdate()`. In `admin-custom-table.php`, the ACL check sets `$hasError = true`. Insert capture code inside the `if (!$hasError)` block, directly before `dbUpdate()`.

### Pitfall 4: Snapshot Table Shown in Snapshot Create Dropdown
**What goes wrong:** The create snapshot form's table dropdown (populated by `SHOW TABLES`) lists `snapshot_*` tables, letting admins snapshot a snapshot.
**Why it happens:** `SHOW TABLES` returns all tables in the database.
**How to avoid:** Filter the table list to only include tables registered in `custom_tables` (the provisioner registry). Use `dbGetRows("SELECT table_name FROM custom_tables WHERE status = 'active'", [])` instead of `SHOW TABLES`.

### Pitfall 5: RENAME TABLE and Foreign Keys
**What goes wrong:** If a foreign key from another table references the live table, renaming it breaks the FK constraint.
**Why it happens:** MySQL FK references are by table name, not by object ID.
**How to avoid:** Custom tables in Snow have no FK references from other system tables — the schema confirms this. Document this assumption in code comments. This is safe for v1.

### Pitfall 6: json_decode Returns stdClass Not Array
**What goes wrong:** `json_decode($snapshot)` returns a `stdClass` object; using it with `dbUpdate()` (which expects an associative array) fails.
**How to avoid:** Always decode with the second argument true: `json_decode($snapshot, true)`. Then filter to only include columns that exist in the current live table before passing to `dbUpdate()`.

### Pitfall 7: Large Tables Make PHP-Side Diff Slow
**What goes wrong:** A table with 100,000 rows loaded into PHP arrays for diffing causes memory exhaustion.
**Why it happens:** `dbGetRows()` fetches all rows into memory.
**How to avoid:** For v1, document a soft limit (e.g., warn if row_count > 10,000 in snapshot metadata). The diff page can show a warning and refuse to diff oversized tables. Full streaming diff is out of scope.

---

## Code Examples

### Creating a Snapshot Table
```php
// Source: MySQL DDL pattern, verified against MySQL 8.0 docs
$snapshotTableName = 'snapshot_' . $tableName . '_' . date('YmdHis');
dbQuery("CREATE TABLE `{$snapshotTableName}` AS SELECT * FROM `{$tableName}`", []);
```
Note: `CREATE TABLE AS SELECT` copies data but NOT indexes or foreign key constraints — which is correct for snapshot purposes (the snapshot is a data archive, not a live table).

### Atomic Restore Rename
```php
// Source: MySQL RENAME TABLE docs — multi-table form is atomic
dbBeginTransaction();
try {
    dbQuery(
        "RENAME TABLE `{$liveTable}` TO `{$tempTable}`,
                      `{$snapshotTable}` TO `{$liveTable}`",
        []
    );
    dbCommit();
} catch (Exception $e) {
    dbRollback();
    $error = 'Restore failed: ' . $e->getMessage();
}
```

### Row Version Revert
```php
// Source: established pattern — load JSON, capture current, apply old values
$version = dbGetRow(
    "SELECT * FROM row_versions WHERE id = ? AND table_name = ? AND row_id = ?",
    [$versionId, $tableName, $recordId]
);
if ($version) {
    $restoredData = json_decode($version['row_snapshot'], true);
    // Capture current state first (current is never lost)
    $currentRow = dbGetRow("SELECT * FROM `{$tableName}` WHERE id = ?", [$recordId]);
    $currentUser = getCurrentUser();
    dbInsert('row_versions', [
        'table_name'   => $tableName,
        'row_id'       => $recordId,
        'changed_by'   => $currentUser['id'] ?? null,
        'changed_at'   => date('Y-m-d H:i:s'),
        'row_snapshot' => json_encode($currentRow),
    ]);
    // Remove meta columns that must not be written back
    unset($restoredData['id'], $restoredData['created_at']);
    dbUpdate($tableName, $restoredData, 'id = ?', [$recordId]);
}
```

### Field-Level Diff Between Two Row Arrays
```php
// Source: PHP built-in array functions
function diffRowArrays(array $old, array $current): array {
    $changedFields = [];
    foreach ($old as $col => $oldVal) {
        if (array_key_exists($col, $current) && (string)$oldVal !== (string)$current[$col]) {
            $changedFields[] = $col;
        }
    }
    return $changedFields;
}
```

---

## State of the Art

| Old Approach | Current Approach | Notes |
|--------------|------------------|-------|
| File-based snapshot (PHP serialize to disk) | `CREATE TABLE AS SELECT` (table duplicate) | User decision — fast restore via RENAME, no file I/O |
| Event sourcing (store diffs only) | Full row JSON snapshot (before-image) | Simpler to implement, no replay chain needed |
| Trigger-based audit | Application-level capture in POST handler | Consistent with Snow's no-trigger philosophy; application controls all writes |

---

## Open Questions

1. **Snapshot table name stored where in metadata?**
   - What we know: `snapshots` table has `snapshot_name` (human label) but no dedicated `snapshot_table` column for the physical MySQL table name.
   - What's unclear: The planner must decide whether to add a `snapshot_table VARCHAR(255)` column to `snapshots`, or derive the physical name by convention from `snapshot_name` + `snapshot_date`.
   - Recommendation: Add `snapshot_table VARCHAR(255) NULL` column to `snapshots` in the Phase 3 migration. Store the physical name there. This makes restore lookup unambiguous and handles edge cases where the human name differs from the table name.

2. **Revert selector: which columns to write back?**
   - What we know: `row_snapshot` JSON includes ALL columns at time of capture, including `id`, `created_at`, `modified_at`, `view_groups`, `edit_groups`.
   - What's unclear: Should revert restore ACL columns (`view_groups`, `edit_groups`) too, or only user-visible fields?
   - Recommendation: Strip `id` and `created_at` from revert data (these must never change). Restore `view_groups` and `edit_groups` as part of the revert (they represent historical state). Strip `modified_at` — MySQL will update it automatically on UPDATE. The planner should make this explicit in the task.

3. **Version history pagination**
   - What we know: CONTEXT gives Claude discretion on pagination.
   - Recommendation: Default to showing the 20 most recent versions in the selectors (ORDER BY changed_at DESC LIMIT 20). If more exist, show "(and N older versions)" below the selector. This keeps the form usable without complex pagination.

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | Custom SnowTestRunner (zero external deps) |
| Config file | None — tests are standalone PHP scripts |
| Quick run command | `php tests/test_all.php` |
| Full suite command | `php tests/test_all.php` |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| VER-01 | `row_versions` row is inserted before each `dbUpdate()` on a custom table | unit | `php tests/test_all.php` (suite must include VER tests) | Wave 0 |
| VER-01 | `row_snapshot` JSON contains correct before-image of all columns | unit | `php tests/test_all.php` | Wave 0 |
| VER-02 | Revert inserts current state as new version before overwriting | unit | `php tests/test_all.php` | Wave 0 |
| VER-02 | After revert, live row matches the selected version's snapshot | unit | `php tests/test_all.php` | Wave 0 |
| VER-03 | Snapshot create produces a MySQL table named `snapshot_{table}_{ts}` | integration | `php tests/test_all.php` | Wave 0 |
| VER-03 | `snapshots` metadata row is created with correct table_name and row_count | unit | `php tests/test_all.php` | Wave 0 |
| VER-04 | Diff correctly identifies changed, added, and deleted rows by `id` | unit | `php tests/test_all.php` | Wave 0 |
| VER-05 | Restore renames snapshot table to live table name atomically | integration | `php tests/test_all.php` | Wave 0 |
| VER-05 | Pre-restore auto-snapshot is created and recorded before rename | integration | `php tests/test_all.php` | Wave 0 |

### Sampling Rate
- **Per task commit:** `php tests/test_all.php`
- **Per wave merge:** `php tests/test_all.php`
- **Phase gate:** Full suite green before `/gsd:verify-work`

### Wave 0 Gaps
- [ ] `tests/test_data_integrity.php` — covers VER-01 through VER-05 (unit + integration tests for version capture, revert, snapshot create/diff/restore)

*(Existing `test_all.php` covers database/auth/template/logging — no versioning tests present.)*

---

## Sources

### Primary (HIGH confidence)
- MySQL 8.0 documentation — `CREATE TABLE ... AS SELECT`, `RENAME TABLE` atomicity, JSON column type
- Codebase direct inspection: `functions/database.php`, `functions/admin-custom-table.php`, `functions/admin-snapshots.php`, `database_schema.sql`
- CONTEXT.md locked decisions — all implementation choices verified against actual code

### Secondary (MEDIUM confidence)
- PHP documentation — `json_encode()` / `json_decode()`, `array_diff_assoc()` behavior with type coercion (string vs int — use string cast for comparison)

### Tertiary (LOW confidence — not needed for this phase)
- None

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — zero new dependencies; all tools already in codebase
- Architecture patterns: HIGH — derived directly from CONTEXT decisions + code inspection
- Pitfalls: HIGH — most identified from direct code reading (snapshot name collision, ACL timing, json_decode array flag) or from STATE.md flagged concern (schema drift)
- Validation architecture: HIGH — SnowTestRunner already used in phases 1 and 2; gaps clearly identified

**Research date:** 2026-03-05
**Valid until:** 2026-06-05 (stable domain — PHP/MySQL DDL patterns do not change rapidly)
