---
phase: 04-extensibility
plan: 03
subsystem: database
tags: [php, hooks, extensibility, custom-tables]

# Dependency graph
requires:
  - phase: 04-02
    provides: custom_tables schema with pre_edit_php_filename and post_edit_php_filename columns

provides:
  - Hook execution in GET branch (pre_edit) and POST branch (post_edit) for admin-custom-table.php
  - Hook filename management UI in admin-tables.php edit form
  - POST handler saves hook filenames to custom_tables registry

affects: [04-04, 04-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "PHP hook injection via include with catch(Throwable) wrapper — non-blocking extension point"
    - "Empty-string-to-NULL pattern: trim(...) ?: null for optional filename fields"

key-files:
  created: []
  modified:
    - functions/admin-custom-table.php
    - functions/admin-tables.php

key-decisions:
  - "pre_edit hook placed after $record is loaded and all page setup is done, immediately before HTML output begins — gives hook access to full page context"
  - "post_edit hook placed in both add (after dbInsert) and edit (after dbUpdate) POST branches — consistent behaviour for both create and update operations"
  - "Hook errors use logError() not display — user sees clean redirect regardless of hook failure"
  - "Empty hook filename stored as NULL (not empty string) via trim ?: null — clean NULL-check in GET branch"

patterns-established:
  - "EXT-01 hook pattern: read $tableDef['*_php_filename'], resolve to SNOW_FUNCTIONS path, include with catch(Throwable), logError on failure"

requirements-completed: [EXT-01]

# Metrics
duration: 1min
completed: 2026-03-06
---

# Phase 4 Plan 03: PHP Hook Execution Wired into Custom Table Admin Summary

**PHP file inclusion hooks (pre_edit/post_edit) wired into admin-custom-table.php GET and POST branches, with hook filename management UI added to admin-tables.php edit form**

## Performance

- **Duration:** 1 min
- **Started:** 2026-03-06T21:34:30Z
- **Completed:** 2026-03-06T21:35:30Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- Pre-edit hook included in GET branch of admin-custom-table.php after $record is loaded, before form HTML renders
- Post-edit hook included in both add (after dbInsert) and edit (after dbUpdate) POST branches before redirect
- All hook blocks use catch(Throwable $e) and call logError() on failure — non-blocking
- admin-tables.php edit form now shows pre_edit_php_filename and post_edit_php_filename text inputs
- Edit POST handler saves both hook filename columns to custom_tables (empty string stored as NULL)

## Task Commits

Each task was committed atomically:

1. **Task 1: Hook execution in admin-custom-table.php** - `95b1457` (feat)
2. **Task 2: Hook filename fields in admin-tables.php** - `385f0d7` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `functions/admin-custom-table.php` - Added 3 hook blocks: pre_edit in GET branch, post_edit in add POST branch, post_edit in edit POST branch
- `functions/admin-tables.php` - Added 2 hook filename text inputs to edit form; added both columns to dbUpdate call

## Decisions Made
- pre_edit hook placed after $record loaded and page setup complete, before HTML — hook has full context including $record, $tableDef, $tableName
- post_edit hook in both add and edit branches — consistent extension point for both create and update
- Hook errors silently logged (not displayed) — redirect proceeds regardless of hook success or failure
- Empty hook filename stored as NULL via `trim(...) ?: null` — clean NULL check possible in execution branch

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
None.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- EXT-01 complete: any custom table can now have PHP files registered as pre/post edit hooks
- Hook files are placed in the functions/ directory and registered via admin UI on the table metadata page
- Ready for 04-04 (SEC-05: Email OTP 2FA) and 04-05 (EXT-02/EXT-03) plans

---
*Phase: 04-extensibility*
*Completed: 2026-03-06*
