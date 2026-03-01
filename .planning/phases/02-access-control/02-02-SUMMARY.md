---
phase: 02-access-control
plan: "02"
subsystem: database
tags: [mysql, schema, custom-tables, access-control, migration, information-schema]

# Dependency graph
requires:
  - phase: 01-security-foundations
    provides: INFORMATION_SCHEMA conditional column-check pattern established in database_schema.sql

provides:
  - provisionCustomTable() creates new custom tables with six standard columns: id, status, view_groups, edit_groups, created_at, modified_at
  - migrateExistingCustomTables() backfills standard Phase 2 columns to all pre-Phase-2 tables using idempotent INFORMATION_SCHEMA checks
  - database_schema.sql Phase 2 section documents standard column definitions and NULL semantics for view_groups/edit_groups

affects:
  - 02-03-acl (canViewRow/canEditRow will read view_groups/edit_groups from these standard columns)
  - 02-04-admin-ui (row-level ACL UI depends on status, view_groups, edit_groups columns existing)
  - 03-versioning (snapshots will include the standard columns)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "INFORMATION_SCHEMA idempotent column-existence check before ALTER TABLE — safe to call on every page load"
    - "Migration-on-load pattern: migrateExistingCustomTables() called at top of admin-tables.php on each request"
    - "Legacy column preservation: updated_at left in place on pre-Phase-2 tables to avoid breaking existing report templates"

key-files:
  created: []
  modified:
    - functions/admin-tables.php
    - database_schema.sql

key-decisions:
  - "updated_at column left in place on pre-Phase-2 tables — not renamed to modified_at — to avoid breaking existing report templates that reference the old column name"
  - "Migration called on every admin-tables.php page load (not a one-time script) — INFORMATION_SCHEMA guards make it idempotent with negligible overhead"
  - "view_groups and edit_groups stored as VARCHAR(500) comma-separated group IDs — NULL means open to all table_management permission holders"
  - "status column uses VARCHAR(20) not ENUM to allow future lifecycle states without schema migration"

patterns-established:
  - "Phase 2 standard column set for all custom tables: id, status, view_groups, edit_groups, created_at, modified_at"
  - "Backward-compatible migration: add new columns via INFORMATION_SCHEMA check, never drop or rename existing columns"

requirements-completed: [DATA-01]

# Metrics
duration: 2min
completed: 2026-03-01
---

# Phase 02 Plan 02: Custom Table Provisioner Phase 2 Columns Summary

**provisionCustomTable() updated with 6-column Phase 2 schema (status, view_groups, edit_groups, created_at, modified_at) and idempotent migrateExistingCustomTables() added to backfill all pre-Phase-2 tables on page load**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-01T16:49:27Z
- **Completed:** 2026-03-01T16:50:53Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Updated `provisionCustomTable()` CREATE TABLE from 3 columns (id, created_at, updated_at) to 6 columns including status, view_groups, edit_groups, and modified_at
- Updated identical fallback CREATE TABLE in the add_field handler with the same 6-column Phase 2 definition
- Added `migrateExistingCustomTables()` function that uses INFORMATION_SCHEMA checks to idempotently add missing standard columns to all pre-Phase-2 tables
- Added migration call on every admin-tables.php page load after page variable initialization
- Documented Phase 2 standard columns in database_schema.sql with column semantics, NULL behavior, and legacy coexistence note

## Task Commits

Each task was committed atomically:

1. **Task 1: Update provisionCustomTable() and add migrateExistingCustomTables()** - `00adbb0` (feat)
2. **Task 2: Document Phase 2 standard columns in database_schema.sql** - `fef29e1` (docs)

**Plan metadata:** (docs: complete plan — this commit)

## Files Created/Modified

- `functions/admin-tables.php` - Updated CREATE TABLE statements (provisionCustomTable + fallback) and added migrateExistingCustomTables() function with call site
- `database_schema.sql` - Added Phase 2 section documenting standard column definitions, NULL semantics, and legacy updated_at coexistence

## Decisions Made

- `updated_at` column left in place on pre-Phase-2 tables — not renamed — to avoid breaking existing report templates that reference the old column name. Both `updated_at` and `modified_at` may coexist on legacy tables.
- Migration runs on every admin-tables.php page load rather than as a one-time script. INFORMATION_SCHEMA guards ensure no duplicate ALTERs; overhead is negligible.
- `view_groups` and `edit_groups` stored as VARCHAR(500) comma-separated group IDs. NULL means open to all users with `table_management` permission. ACL enforcement deferred to Phase 2 plan 03 (canViewRow/canEditRow in functions/acl.php).
- `status` uses VARCHAR(20) rather than ENUM to allow future lifecycle states without schema migration.

## Deviations from Plan

None — plan executed exactly as written.

## Issues Encountered

None. The `updated_at` reference in `grep -c "updated_at"` returning 1 (not 0) is only a doc comment in the `migrateExistingCustomTables()` docblock — the doc comment is part of the plan's own code spec and explains the intentional preservation decision. No `updated_at` appears in any CREATE TABLE statement.

## User Setup Required

None — no external service configuration required. The migration runs automatically on the next admin-tables.php page load.

## Next Phase Readiness

- All custom tables (existing and new) will have `status`, `view_groups`, `edit_groups`, `created_at`, `modified_at` columns ready for ACL enforcement
- Plan 02-03 can implement `canViewRow()` / `canEditRow()` in `functions/acl.php` using these standard columns
- `database_schema.sql` serves as authoritative reference for fresh installs

## Self-Check: PASSED

- FOUND: functions/admin-tables.php
- FOUND: database_schema.sql
- FOUND: .planning/phases/02-access-control/02-02-SUMMARY.md
- FOUND: commit 00adbb0 (Task 1)
- FOUND: commit fef29e1 (Task 2)

---
*Phase: 02-access-control*
*Completed: 2026-03-01*
