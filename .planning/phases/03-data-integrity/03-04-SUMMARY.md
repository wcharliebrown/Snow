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
  - "renderPage() called with path string not array — framework expects a file path string as first arg; passing $page array directly causes HTTP 500 (fixed during verification)"

patterns-established:
  - "Early-exit sub-view: compute data, ob_start(), render full HTML, assign to $page['content'], renderPage(path, $page), exit — used in diff handler"

requirements-completed: [VER-03, VER-04]

# Metrics
duration: 20min
completed: 2026-03-06
---

# Phase 3 Plan 04: Snapshot Create (CREATE TABLE AS SELECT) + Diff View Summary

**Snapshot create physically copies rows via CREATE TABLE AS SELECT; diff view identifies changed/added/deleted rows with field-level warning-yellow highlighting against live table data — verified working at /admin/snapshots?action=diff.**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-03-06T00:00:00Z
- **Completed:** 2026-03-06T14:27:05Z
- **Tasks:** 3 of 3 (including human-verify checkpoint — approved)
- **Files modified:** 1

## Accomplishments

- Snapshot create now executes `CREATE TABLE \`snapshot_{table}_{ts}{NNN}\` AS SELECT * FROM \`{table}\`` — all rows physically copied, not just metadata recorded (VER-03)
- `snapshot_table` column populated in the snapshots INSERT so the physical table name is persisted
- Table dropdown switched from `SHOW TABLES` to `SELECT table_name FROM custom_tables WHERE status = 'active'` — snapshot_ tables no longer appear as snapshotable targets
- Diff GET handler (`?action=diff&id=N`) computes changed/deleted/added row sets; renders paired snapshot+live rows with `style="background-color:#fff3cd"` on differing cells (VER-04)
- Oversized-table guard (> 10,000 rows) shows error banner and refuses diff
- Missing snapshot table shows clear error banner
- Snapshot Actions card below the list gives Diff and Restore buttons per snapshot row
- Human verification approved: diff view confirmed working at `/admin/snapshots?action=diff&id=31`

## Task Commits

1. **Task 1: Fix snapshot create — CREATE TABLE AS SELECT + dropdown fix** - `248e258` (feat)
2. **Task 2: Add snapshot diff GET view** - `875479c` (feat)
3. **Deviation fix: renderPage path string bug** - `1c32ba2` (fix)
4. **Task 3: Human-verify checkpoint — approved** (no code commit; verification via browser)

**Plan metadata:** `b36141f` (docs: complete plan — checkpoint reached)

## Files Created/Modified

- `functions/admin-snapshots.php` - Snapshot create with physical table copy, diff view handler, Snapshot Actions card, custom_tables dropdown

## Decisions Made

- `snapshot_table` name generated at create time with `rand(100,999)` suffix — prevents name collision within the same second without requiring a DB uniqueness check
- Diff uses `array_column($rows, null, 'id')` to key both snapshot and live rows by id — simple O(n) PHP pass; adequate for tables under 10,000 rows
- Snapshot Actions card is a separate HTML block below the renderReport() call — avoids modifying the `snapshots_list` DB report template, which could break or require DB editing
- `renderPage()` signature takes a path string as first argument — discovered during verification; passing the `$page` array directly caused HTTP 500

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed renderPage() call signature — path string not array**

- **Found during:** Task 3 (human-verify checkpoint) — diff view returned HTTP 500 on first browser load
- **Issue:** The diff GET handler called `renderPage($page)` passing the assembled `$page` array as the first argument. The framework's `renderPage()` function expects a file path string as its first argument, not the content array. This caused a fatal PHP error and blank/500 response.
- **Fix:** Corrected the renderPage() call to pass the proper path string, consistent with how other page handlers in the codebase invoke it.
- **Files modified:** `functions/admin-snapshots.php`
- **Verification:** Diff view at `/admin/snapshots?action=diff&id=31` loaded successfully, showed field-level yellow highlighting on changed rows — confirmed by user during checkpoint approval.
- **Committed in:** `1c32ba2`

---

**Total deviations:** 1 auto-fixed (Rule 1 — bug)
**Impact on plan:** Fix was necessary for the diff view to render. No scope creep.

## Issues Encountered

HTTP 500 on first diff view load due to `renderPage()` argument mismatch — diagnosed from error context, fixed in a single targeted edit, verified immediately during checkpoint review.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- VER-03 and VER-04 fully implemented and verified by human
- `snapshot_table` column is reliably populated on all new snapshots — 03-05 (row revert) and 03-06 (snapshot restore) can depend on the physical table being present
- Existing snapshots taken before this plan (if any) may have null `snapshot_table` — restore logic in 03-06 should guard against null before attempting table access

## Self-Check: PASSED

- `functions/admin-snapshots.php` exists and was verified working in browser
- Commits 248e258, 875479c, 1c32ba2 all present in git log
- SUMMARY.md created at `.planning/phases/03-data-integrity/03-04-SUMMARY.md`

---
*Phase: 03-data-integrity*
*Completed: 2026-03-06*
