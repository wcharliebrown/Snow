# Phase 3: Data Integrity - Context

**Gathered:** 2026-03-05
**Status:** Ready for planning

<domain>
## Phase Boundary

Every write to a custom table is recorded with before-state and author. Admins can roll back any row to any prior version directly from the edit form. Full-table snapshots can be created, diffed against live data, and restored. Only custom tables (managed via Snow's provisioner) are versioned and snapshotted — system tables (users, pages, etc.) are out of scope for this phase.

</domain>

<decisions>
## Implementation Decisions

### Snapshot storage
- Snapshots are real MySQL table duplicates — `CREATE TABLE snapshot_{table}_{YYYYMMDDhhmmss} AS SELECT * FROM {table}`
- The snapshot table name format is: `snapshot_originalTableName_YYYYMMDDhhmmss`
- The existing `snapshots` metadata table (id, table_name, snapshot_name, description, snapshot_date, row_count, status, created_by) records each snapshot; `file_path` column remains NULL (not used for file-based storage)
- The existing `admin-snapshots.php` currently only inserts metadata — it needs to be updated to also execute the `CREATE TABLE AS SELECT` statement

### Snapshot restore
- Restore sequence: (1) auto-snapshot the live table first, (2) rename live table to a temporary name, (3) rename snapshot table to the live table name — so the admin always has a way back
- The auto-created pre-restore snapshot is recorded in the `snapshots` metadata table like any other snapshot
- No manual pre-restore snapshot required from the admin

### Snapshot diff display
- Only rows that have at least one field difference are shown
- For each changed row: snapshot values appear in one HTML table row; live values appear in the row immediately below it; fields that differ are highlighted
- Rows that exist only in the snapshot (deleted since snapshot) and rows that exist only in the live table (added since snapshot) are displayed in a separate section at the bottom of the diff
- Diff is compared by primary key (`id` column)

### Version history UI (row-level)
- Two selectors appear on the edit form for every custom table row:
  1. **Diff selector** — lists past versions by timestamp; selecting one shows a diff of that version vs. the current state (highlighted field-level differences)
  2. **Revert selector** — lists past versions by timestamp; selecting one and confirming reverts the row to that version
- Both selectors only appear if version history exists for the row
- Reverting via the revert selector saves the current state as a new version entry before overwriting (current state is never lost)

### Row version storage
- A `row_versions` table stores a JSON snapshot of the full row before each edit: `(id, table_name, row_id, changed_by, changed_at, row_snapshot JSON)`
- Version capture happens in the `admin-custom-table.php` POST handler: before each `dbUpdate()` call, the existing row is fetched and inserted into `row_versions`
- Inserts (new rows) do not create a version entry — only edits (updates) do
- Deletes do not create a version entry — deletion is final

### Claude's Discretion
- Exact HTML/CSS for diff highlighting (color choice for changed fields)
- Whether the two selectors on the edit form are `<select>` dropdowns or another UI pattern
- Indexing strategy for `row_versions` table
- Pagination/truncation of version history lists for rows with many versions

</decisions>

<specifics>
## Specific Ideas

- Snapshot table naming is explicit and human-readable: `snapshot_products_20260305143022` — easy to identify in a MySQL client
- The restore operation is fundamentally a rename, making it fast regardless of table size
- Diff selectors use timestamps so admins can orient themselves by time ("which version was before last Tuesday's import?")

</specifics>

<code_context>
## Existing Code Insights

### Reusable Assets
- `dbBeginTransaction()`, `dbCommit()`, `dbRollback()` in `functions/database.php`: use for atomic snapshot restore (rename sequence must be atomic)
- `dbInsert()` in `functions/database.php`: use to record version entries and snapshot metadata
- `dbGetRow()` / `dbGetRows()`: use to fetch row before capture and to load version history
- `admin-snapshots.php`: exists but only writes metadata — needs `CREATE TABLE AS SELECT` added to the create action
- `snapshots` table: already in schema with correct columns; `file_path` column unused (NULL)
- `SNOW_SNAPSHOTS` constant: defined but directory unused — remains unused under the table-duplicate approach

### Established Patterns
- Permission guard: `requirePermission('snapshot_management')` already in `admin-snapshots.php` — version history access should respect `table_data_access` since it lives on the edit form
- Flash messages via GET `?msg=` param: use same pattern for restore/revert success messages
- POST handler pattern in `admin-custom-table.php`: insert version capture before the existing `dbUpdate()` call in the `edit` POST branch
- `dbTableExists()`: use to validate table names before snapshot/restore operations

### Integration Points
- `admin-custom-table.php` edit POST branch: add version capture (fetch row → insert into row_versions) immediately before `dbUpdate()`
- `admin-snapshots.php` create action: add `CREATE TABLE snapshot_{name}_{ts} AS SELECT * FROM {table}` after metadata insert
- `admin-snapshots.php`: add diff view (new GET action) and restore action (POST) — both new
- New `row_versions` table needed: migration in `database_schema.sql`
- New `snapshots` restore + diff UI: can be added as new GET actions in `admin-snapshots.php` or a separate `admin-snapshot-detail.php`

</code_context>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

</deferred>

---

*Phase: 03-data-integrity*
*Context gathered: 2026-03-05*
