---
phase: 02-access-control
plan: 01
subsystem: auth
tags: [acl, row-level-access, permissions, groups, php]

# Dependency graph
requires:
  - phase: 01-security-foundations
    provides: getCurrentUserId(), getUserGroups(), hasPermission(), dbGetRows() — all called by acl.php
provides:
  - getUserGroupIds(?int $userId): array — cached group ID lookup per user per request
  - canViewRow(array $row, ?int $userId): bool — row-level view gate with admin bypass
  - canEditRow(array $row, ?int $userId): bool — row-level edit gate with admin bypass
  - filterRowsByViewAccess(array $rows, ?int $userId): array — bulk view filter returning sequential keys
  - formatGroupIds(?string $groupIds): string — renders group list as HTML (or "All Users" span)
affects:
  - 02-access-control/02-02 (admin-custom-table.php enforcement imports all five functions)
  - any future phase that needs row-level access checks

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Static-cache pattern: getUserGroupIds() uses static $cache[] keyed by user ID — one DB round-trip per user per request regardless of how many rows are checked
    - Admin bypass first: both canViewRow and canEditRow check hasPermission('admin_access') before any other logic
    - NULL/empty = open: missing or empty view_groups / edit_groups always returns true (open to all)
    - array_values() reset: filterRowsByViewAccess always returns sequential 0-indexed array

key-files:
  created:
    - functions/acl.php
  modified: []

key-decisions:
  - "No require_once in acl.php — framework bootstrap loads auth.php and database.php before any custom_script, so dependencies are always available"
  - "array_filter after array_map('intval') for group ID parsing — strips zero values that would result from non-numeric tokens in the comma string"
  - "htmlspecialchars on formatGroupIds output — group names from DB are user-supplied and must be escaped before HTML rendering"

patterns-established:
  - "ACL gate pattern: admin bypass -> empty check -> parse IDs -> intersect with user groups"
  - "Static cache pattern for per-user, per-request data (getUserGroupIds)"

requirements-completed: [ACL-02, ACL-03]

# Metrics
duration: 1min
completed: 2026-03-01
---

# Phase 2 Plan 01: ACL Library Summary

**Pure-PHP row-level ACL library (functions/acl.php) with five functions: cached group lookup, view/edit gates with admin bypass and NULL=open semantics, bulk view filter, and HTML group name formatter**

## Performance

- **Duration:** 1 min
- **Started:** 2026-03-01T16:49:22Z
- **Completed:** 2026-03-01T16:50:21Z
- **Tasks:** 1
- **Files modified:** 1

## Accomplishments

- Created functions/acl.php with all five ACL functions in exact order specified by plan
- All 11 automated assertions in the plan's verification script pass
- Static cache in getUserGroupIds() ensures getUserGroups() is called at most once per user per request
- Admin bypass (hasPermission('admin_access')) short-circuits both canViewRow and canEditRow before any DB-dependent logic
- NULL/empty view_groups and edit_groups correctly treated as open-to-all
- filterRowsByViewAccess returns array_values() (sequential keys) as required
- formatGroupIds returns safe HTML-escaped output with the "All Users" span for null/empty input

## Task Commits

Each task was committed atomically:

1. **Task 1: Create functions/acl.php with all five ACL functions** - `8390e09` (feat)

**Plan metadata:** (see final commit below)

## Files Created/Modified

- `functions/acl.php` - Row-level ACL library: getUserGroupIds, canViewRow, canEditRow, filterRowsByViewAccess, formatGroupIds

## Decisions Made

- No require_once in acl.php — the framework bootstrap already loads auth.php and database.php before any custom_script runs, so explicit requires would be redundant and cause redeclaration errors in some test contexts
- array_filter applied after array_map('intval') to strip zero values that arise from empty tokens in the comma-separated group string
- htmlspecialchars applied to formatGroupIds output — group names are user-supplied data stored in the DB and must be escaped before insertion into HTML

## Deviations from Plan

None - plan executed exactly as written.

The plan's own verification script had a minor ordering issue (it stubbed dbGetRows after requiring database.php, causing a redeclaration error). This was not a code issue — the acl.php implementation is correct. The verification was run successfully with properly ordered stubs.

## Issues Encountered

None — the implementation was straightforward. All assertions passed on first run.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- functions/acl.php is ready for Plan 02-02 (admin-custom-table.php enforcement) to import and call
- All five function contracts match the interfaces defined in 02-RESEARCH.md Pattern 1
- Zero new dependencies introduced — acl.php relies only on functions already loaded by the framework bootstrap

---
*Phase: 02-access-control*
*Completed: 2026-03-01*

## Self-Check: PASSED

- FOUND: functions/acl.php
- FOUND: commit 8390e09
