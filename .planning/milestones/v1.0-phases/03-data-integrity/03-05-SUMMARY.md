---
phase: 03-data-integrity
plan: 05
subsystem: database
tags: [versioning, row-versions, diff, revert, admin-ui, php]

# Dependency graph
requires:
  - phase: 03-02
    provides: row_versions table schema (id, table_name, row_id, changed_by, changed_at, row_snapshot JSON)
  - phase: 03-03
    provides: row version capture on edit POST (VER-01) — row_versions populated on each save
provides:
  - VER-02: field-level diff selector on edit form comparing any prior version to current state
  - Revert POST handler restoring row to any prior version with pre-revert snapshot preserved
  - Version history card UI (diff + revert selectors, hidden when no history exists)
affects:
  - 03-06
  - any future phase touching admin-custom-table.php edit form

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Strip id/created_at/modified_at before dbUpdate on restore — prevent PK and timestamp drift"
    - "SHOW COLUMNS + array_intersect_key to filter restored snapshot to live schema — handles schema drift after versions recorded"
    - "Save pre-revert state as new row_versions entry before overwriting — current state is never lost"
    - "Version selectors rendered only when $versions non-empty — no UI clutter on new/unedited rows"

key-files:
  created: []
  modified:
    - functions/admin-custom-table.php

key-decisions:
  - "Columns stripped before dbUpdate on revert: id, created_at, modified_at — id/created_at must never change; modified_at is auto-updated by MySQL on UPDATE"
  - "view_groups and edit_groups ARE restored to historical ACL state — preserves the row's intended access control at time of snapshot"
  - "SHOW COLUMNS used to filter $restoredData to live column set — guards against schema drift where columns were added/removed after snapshot was taken"
  - "Max 20 most recent versions loaded for selectors per 03-CONTEXT.md discretion guidance — balances usability vs query cost"
  - "String cast on both sides for diff comparison — avoids false positives from int/string type coercion in JSON decode"

patterns-established:
  - "Version revert pattern: load version → ACL check → snapshot current → decode historical → strip meta columns → filter to live schema → dbUpdate → redirect with ?msg=reverted"
  - "Diff panel pattern: version_diff GET param → load version row → json_decode → compute $versionDiffFields → render card above edit form"

requirements-completed:
  - VER-02

# Metrics
duration: ~30min
completed: 2026-03-06
---

# Phase 3 Plan 05: Version Diff and Revert Summary

**Field-level version diff selector and safe revert handler on the custom table edit form — current state always preserved as a new snapshot before any revert**

## Performance

- **Duration:** ~30 min
- **Started:** 2026-03-06
- **Completed:** 2026-03-06
- **Tasks:** 2 implementation tasks + 1 human-verify checkpoint (approved)
- **Files modified:** 1

## Accomplishments

- Added revert POST handler (`action=revert`) that: verifies ACL, saves current row state as a new row_versions entry, json_decodes the historical snapshot, strips meta columns (id, created_at, modified_at), filters to live schema columns via SHOW COLUMNS, and calls dbUpdate — then redirects with `?msg=reverted`
- Added version_diff GET handler that loads the selected version, decodes snapshot, and computes `$versionDiffFields` (list of columns that differ between historical and current)
- Added Version History card UI to the edit form (only rendered when $versions is non-empty): diff selector (GET form) and revert selector (POST form with confirm dialog) side-by-side; diff panel (yellow-highlighted table) appears above edit form when `version_diff` param is set
- Human verification confirmed: selectors appear after edits, diff table highlights changes, revert restores values and preserves pre-revert state as new version entry, selectors hidden on rows with no history

## Task Commits

Each task was committed atomically:

1. **Task 1: Add revert POST handler and version diff GET handler** - `f206f5b` (feat)
2. **Task 2: Add version diff and revert selector UI to edit form** - `7f150a8` (feat)

## Files Created/Modified

- `functions/admin-custom-table.php` — Added revert POST handler, version_diff GET handler, and version history card UI (diff + revert selectors) to the row edit form

## Decisions Made

- Columns stripped before dbUpdate: id, created_at, modified_at — id and created_at must never change; modified_at is auto-updated by MySQL on UPDATE so writing it back would set a stale timestamp
- view_groups and edit_groups ARE restored to historical values — the historical ACL state is semantically correct and intentional
- SHOW COLUMNS + array_intersect_key filters $restoredData to only live columns — prevents errors on columns that no longer exist and silently ignores new columns (safe degradation)
- Max 20 most recent versions loaded per selector per 03-CONTEXT.md Claude's Discretion guidance
- String cast on both sides for diff field detection to avoid PHP type coercion false positives from json_decode returning integers for numeric fields

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- VER-02 complete: admin can diff any prior version against current state with field-level highlighting and revert any row to any prior version
- row_versions is fully functional: capture (VER-01, plan 03-03), diff, and revert (VER-02, this plan)
- Phase 3 plans 03-03, 03-04, 03-05 complete — snapshot and versioning subsystems operational
- 03-06 (snapshot restore) is the next and final plan in Phase 3

---
*Phase: 03-data-integrity*
*Completed: 2026-03-06*
