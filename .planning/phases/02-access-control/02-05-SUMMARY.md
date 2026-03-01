---
phase: 02-access-control
plan: "05"
subsystem: ui
tags: [php, acl, forms, group-selector, admin]

# Dependency graph
requires:
  - phase: 02-access-control
    provides: group selector widget HTML in admin-custom-table.php add/edit forms (Phase 02-03)
provides:
  - Correct $_POST repopulation of View Groups and Edit Groups checkboxes on add and edit forms
  - REQUEST_METHOD-aware group selection state on edit form (GET uses DB value, POST uses submitted array)
affects: [02-access-control UAT tests 3-9, ACL-02, ACL-03, DATA-01]

# Tech tracking
tech-stack:
  added: []
  patterns: [Use (array) cast on $_POST array inputs instead of explode on non-existent _raw keys; split GET/POST paths with REQUEST_METHOD check when DB fallback needed]

key-files:
  created: []
  modified:
    - functions/admin-custom-table.php

key-decisions:
  - "Add form repopulation: (array)($_POST['view_groups'] ?? []) replaces explode on view_groups_raw (key never exists — PHP delivers name=view_groups[] as an array)"
  - "Edit form repopulation: split by REQUEST_METHOD — GET parses DB comma-string with explode, POST uses submitted array directly"

patterns-established:
  - "PHP checkbox array inputs: name='field[]' arrives as $_POST['field'] (array), never as a _raw string key"
  - "Edit form with DB fallback: use REQUEST_METHOD guard to distinguish fresh GET (read DB) from failed POST (read submitted data)"

requirements-completed: [ACL-02, ACL-03, DATA-01]

# Metrics
duration: 20min
completed: 2026-03-01
---

# Phase 2 Plan 05: Group Selector Repopulation Bug Fix Summary

**Fixed $_POST['view_groups_raw'] / $_POST['edit_groups_raw'] phantom key error so View Groups and Edit Groups checkbox sections now render and repopulate correctly on admin-custom-table.php add/edit forms**

## Performance

- **Duration:** ~20 min (including human-verify checkpoint)
- **Started:** 2026-03-01T19:12:19Z
- **Completed:** 2026-03-01
- **Tasks:** 2 of 2 complete
- **Files modified:** 1

## Accomplishments

- Fixed add form: `$_POST['view_groups_raw']` (nonexistent key) replaced with `(array)($_POST['view_groups'] ?? [])` — correctly reads PHP's delivered array from `name="view_groups[]"` inputs
- Fixed edit form: replaced single-expression fallback with REQUEST_METHOD branch — GET reads DB comma-string via explode(), POST reads submitted array directly
- PHP lint passes clean; `view_groups_raw` and `edit_groups_raw` have zero occurrences in file; HTML `name="view_groups[]"` and `name="edit_groups[]"` inputs unchanged (2 each)
- User confirmed: "That works and the groups also show when editing an item" — View Groups and Edit Groups sections visible on both add and edit forms
- UAT Tests 3, 4, 5 unblocked; UAT Tests 6-9 (view_groups/edit_groups saving and ACL enforcement) are now testable

## Task Commits

Each task was committed atomically:

1. **Task 1: Fix view_groups_raw / edit_groups_raw repopulation bug in add form and edit form** - `0e8eee1` (fix)
2. **Task 2: Verify View Groups and Edit Groups sections render on add and edit forms** - checkpoint approved by user

## Files Created/Modified

- `functions/admin-custom-table.php` - Fixed two repopulation blocks (add form lines ~168-171, edit form lines ~272-280)

## Decisions Made

- Add form uses `(array)($_POST['view_groups'] ?? [])` — no DB fallback needed since this is a new record; the (array) cast is safe for both the submitted-array case and the no-submission case
- Edit form uses `$_SERVER['REQUEST_METHOD'] === 'POST'` guard to choose between submitted array (POST) and DB comma-string (GET) — required because on GET the DB value is a VARCHAR string needing explode(), not an array

## Deviations from Plan

### Additional Fix (DB-only, discovered during human verification)

**tables_list report template — added "Data" button linking to /admin/data/{table_name}**

- **Found during:** Task 2 (human-verify checkpoint) — user could not navigate from the admin tables list to a specific table's data page to test the group widget
- **Issue:** The `tables_list` report template in the `report_templates` database table was missing a "Data" button. The row template did not include the `table_name` field or any link to `/admin/data/{table_name}`, making it impossible to reach the add/edit forms needed for verification.
- **Fix:** Direct database UPDATE to `report_templates` WHERE `name = 'tables_list'` — added `table_name` as a plain SQL field to the field list and a green "Data" button linking to `/admin/data/{table_name}` to the row template HTML.
- **Files modified:** None (DB-only change — no PHP file was modified)
- **Verification:** User confirmed navigation from the tables list to the data page worked after the fix, and that the group widget was visible and functional.
- **Committed in:** Not committed — database-only change; report_template content is not version-controlled as PHP source.

---

**Total deviations:** 1 additional DB fix (out-of-band, discovered during verification)
**Impact on plan:** The DB fix was necessary to complete the human-verify step. No PHP source changes beyond the two planned edits.

## Issues Encountered

During the human-verify checkpoint, the user discovered they could not navigate to a table's data page from the admin tables list because the `tables_list` report template lacked a "Data" button. This was resolved via a direct DB UPDATE before the user could complete group widget verification. See Deviations section above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Group widget renders and repopulates correctly on add and edit forms (user-verified)
- UAT Tests 3, 4, 5 are unblocked and expected passing
- UAT Tests 6-9 (view_groups and edit_groups saving, ACL enforcement) are unblocked
- Phase 2 ACL enforcement is complete end-to-end; phase can be declared done pending any remaining UAT sign-off

---
*Phase: 02-access-control*
*Completed: 2026-03-01*
