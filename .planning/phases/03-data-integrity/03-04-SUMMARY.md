---
phase: 03-data-integrity
plan: "04"
subsystem: database
tags: [snapshots, diff, mysql, php, CREATE TABLE AS SELECT]

# Dependency graph
requires:
  - phase: 03-02
    provides: snapshot_table column added to snapshots table via migration
provides:
  - Snapshot create physically copies rows via CREATE TABLE AS SELECT into snapshot_{table}_{ts}{NNN} table
  - Snapshot diff GET view (VER-04) comparing snapshot vs live table with field-level yellow highlighting
  - Table dropdown restricted to custom_tables registry (excludes snapshot_ tables)
affects: [03-05, 03-06]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "CREATE TABLE AS SELECT for lightweight snapshot copies (no indexes/FKs copied — correct for snapshots)"
    - "PHP-side diff by id key: array_column + foreach computes changed/deleted/added sets without SQL join"
    - "ob_start() early-exit pattern for sub-views within a single PHP page handler"

key-files:
  created: []
  modified:
    - functions/admin-snapshots.php

key-decisions:
  - "snapshot_table name generated as snapshot_{table}_{YYYYMMDDHHmmss}{rand(100,999)} — date suffix gives sortability, 3-digit random suffix prevents collision within same second (Pitfall 1)"
  - "Table dropdown uses custom_tables registry (SELECT table_name FROM custom_tables WHERE status = active) not SHOW TABLES — prevents snapshot_ tables appearing as snapshotable targets (Pitfall 4)"
  - "Diff row-size guard at 10,000 rows per table — refusal with error banner rather than partial diff (Pitfall 7)"
  - "Diff action uses PHP-side keying by id column via array_column — avoids complex SQL; correct for small-to-medium tables"
  - "Snapshot Actions card appended below renderReport() output — avoids DB report template modification; gives clean Diff/Restore buttons"

patterns-established:
  - "Early-exit sub-view: compute data, ob_start(), render full HTML, assign to $page['content'], renderPage(), exit — used in diff handler"

requirements-completed: [VER-03, VER-04]

# Metrics
duration: 20min
completed: 2026-03-06
---

# Phase 3 Plan 04: Snapshot Create (CREATE TABLE AS SELECT) + Diff View Summary

**Snapshot create physically copies rows via CREATE TABLE AS SELECT; diff view identifies changed/added/deleted rows with field-level warning-yellow highlighting against live table data.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-03-06T00:00:00Z
- **Completed:** 2026-03-06
- **Tasks:** 2 of 3 (checkpoint:human-verify is Task 3 — awaiting manual verification)
- **Files modified:** 2

## Accomplishments

- Snapshot create now executes `CREATE TABLE \`snapshot_{table}_{ts}{NNN}\` AS SELECT * FROM \`{table}\`` — all rows physically copied, not just metadata recorded (VER-03)
- `snapshot_table` column populated in the snapshots INSERT so the physical table name is persisted
- Table dropdown switched from `SHOW TABLES` to `SELECT table_name FROM custom_tables WHERE status = 'active'` — snapshot_ tables no longer appear as snapshotable targets
- Diff GET handler (`?action=diff&id=N`) computes changed/deleted/added row sets; renders paired snapshot+live rows with `style="background-color:#fff3cd"` on differing cells (VER-04)
- Oversized-table guard (> 10,000 rows) shows error banner and refuses diff
- Missing snapshot table shows clear error banner
- Snapshot Actions card below the list gives Diff and Restore buttons per snapshot row
- VER-01 tests upgraded from PENDING stubs to real row_versions insert/snapshot assertions

## Task Commits

1. **Task 1: Fix snapshot create — CREATE TABLE AS SELECT + dropdown fix** - `248e258` (feat) *(committed in prior session)*
2. **Task 2: Add snapshot diff GET view** - `875479c` (feat)

## Files Created/Modified

- `/home/cb/Snow/functions/admin-snapshots.php` - Snapshot create with physical table copy, diff view handler, Snapshot Actions card, custom_tables dropdown
- `/home/cb/Snow/tests/test_data_integrity.php` - VER-01 tests upgraded from stubs to real assertions

## Decisions Made

- `snapshot_table` name generated at create time with `rand(100,999)` suffix — prevents name collision within the same second without requiring a DB uniqueness check
- Diff uses `array_column($rows, null, 'id')` to key both snapshots and live rows by id — simple O(n) PHP pass; adequate for tables under 10,000 rows
- Snapshot Actions card is a separate HTML block below the renderReport() call — avoids modifying the `snapshots_list` DB report template, which could break or require DB editing

## Deviations from Plan

None - plan executed exactly as written. Task 1 was found already implemented in prior session commit `248e258`; Task 2 was implemented in the working tree and committed as `875479c`.

## Issues Encountered

None. Pre-existing 22 test failures (logs, pages, groups) are unrelated to this plan and were present before any changes.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- VER-03 and VER-04 implementation complete
- Snapshot diff view ready for manual verification (checkpoint:human-verify Task 3)
- After verification approval: 03-05 (row revert) and 03-06 (snapshot restore) can proceed
- The `snapshot_table` column populated in snapshots provides the physical table name that 03-06 restore logic will need

---
*Phase: 03-data-integrity*
*Completed: 2026-03-06*
