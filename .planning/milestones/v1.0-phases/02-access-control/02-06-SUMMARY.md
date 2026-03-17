---
phase: 02-access-control
plan: "06"
subsystem: database
tags: [php, mysql, permissions, acl, access-control]

# Dependency graph
requires:
  - phase: 02-access-control
    provides: "Row-level ACL (canViewRow/canEditRow/filterRowsByViewAccess) in acl.php; view_groups/edit_groups columns on custom tables"
provides:
  - "table_data_access permission in DB seed and live DB"
  - "admin/data/* pages migrated from table_management to table_data_access"
  - "provisionCustomTable() uses table_data_access for future pages"
  - "admin-custom-table.php inner guard uses table_data_access"
  - "View Groups / Edit Groups UI hidden from data-access-only users"
  - "POST guard prevents data-only users overwriting ACL group values"
  - "Administrators group granted table_data_access automatically (idempotent migration)"
affects: [02-access-control, phase-03-versioning]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Permission separation: table_management for schema admin; table_data_access for data access with row-level ACL"
    - "Sentinel null pattern: $viewGroupIds=null signals do-not-write (vs empty array meaning open-to-all)"
    - "INSERT IGNORE for idempotent permission grants in migration SQL"

key-files:
  created: []
  modified:
    - database_schema.sql
    - functions/admin-tables.php
    - functions/admin-custom-table.php

key-decisions:
  - "table_data_access is a separate permission from table_management — data users reach admin/data/* but cannot touch schema or ACL group assignments"
  - "Sentinel null ($viewGroupIds = null) used to distinguish do-not-write from empty-array (open-to-all) in POST handlers"
  - "hasPermission() gates both UI rendering and POST collection — defense in depth against form tampering"
  - "INSERT IGNORE + conditional UPDATE make migration section idempotent — safe to re-run on DB restores"
  - "Additional fix: any group holding table_management is also granted table_data_access via INSERT IGNORE — prevents admin lockout after permission split"

patterns-established:
  - "Permission separation: table_management guards schema/ACL admin; table_data_access guards data page access"
  - "Sentinel null pattern for optional DB column writes: null means skip, array means write (even if empty)"
  - "When splitting a permission, always grant the new child permission to groups already holding the parent"

requirements-completed: [ACL-01, ACL-02, ACL-03]

# Metrics
duration: 45min
completed: 2026-03-01
---

# Phase 2 Plan 06: Gap Closure — Non-Admin Table Data Access Summary

**New table_data_access permission wired into admin/data/* pages, inner guard, and ACL widget gating — enabling Phase 2 row-level ACL for non-admin users; human-verified passing**

## Performance

- **Duration:** ~45 min (including human verification round-trip and additional fix)
- **Started:** 2026-03-01T20:21:25Z
- **Completed:** 2026-03-01
- **Tasks:** 3 of 3 complete (2 auto + 1 checkpoint:human-verify — approved)
- **Files modified:** 3

## Accomplishments

- Added table_data_access permission to DB (live migration + schema file) — separates data-page access from schema management
- Migrated all existing admin/data/* pages from required_permission='table_management' to 'table_data_access' in the live DB
- Updated provisionCustomTable() to register future pages with table_data_access
- Changed admin-custom-table.php inner guard from requirePermission('table_management') to requirePermission('table_data_access')
- View Groups / Edit Groups UI hidden from users without table_management (both add and edit forms)
- POST handlers use sentinel-null pattern to prevent data-only users from overwriting ACL group values via form tampering
- Applied additional fix: any group holding table_management is automatically granted table_data_access (prevents admin lockout)
- Human verification passed: non-admin reaches list, sees only permitted rows, save works, admin retains ACL selectors

## Task Commits

Each task was committed atomically:

1. **Task 1: Add table_data_access permission, update provisioner, run live DB migration** - `10165de` (feat)
2. **Task 2: Swap inner guard and gate ACL widget in admin-custom-table.php** - `729842b` (feat)
3. **Additional fix: grant table_data_access to Administrators group in migration** - `97e0754` (fix)

## Files Created/Modified

- `database_schema.sql` - Appended Phase 2 gap-closure section: INSERT IGNORE for table_data_access permission, UPDATE to migrate admin/data/* pages, INSERT IGNORE to grant table_data_access to any group holding table_management
- `functions/admin-tables.php` - provisionCustomTable() savePage() call uses 'table_data_access' instead of 'table_management'
- `functions/admin-custom-table.php` - Inner guard changed to table_data_access; View/Edit Groups UI gated on hasPermission('table_management'); POST handlers use sentinel-null to skip ACL column writes for data-only users

## Decisions Made

- table_data_access is a separate permission from table_management — data users reach admin/data/* but cannot touch schema or ACL group assignments
- Sentinel null ($viewGroupIds = null) used to distinguish do-not-write from empty-array (open-to-all) in POST handlers
- hasPermission() gates both UI rendering and POST collection — defense in depth against form tampering
- INSERT IGNORE + conditional UPDATE make migration section idempotent — safe to re-run on DB restores
- Additional fix pattern: when splitting a permission, grant the new child to all groups holding the parent — prevents privilege regression for existing admin groups

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Administrators group lacked table_data_access after permission split**
- **Found during:** Human verification checkpoint (post-Task 2)
- **Issue:** After changing the inner guard from table_management to table_data_access, admin users whose groups only held table_management could no longer reach /admin/data/* — the new permission was never granted to existing admin groups
- **Fix:** Added INSERT IGNORE INTO group_permissions to grant table_data_access to any group already holding table_management; applied to live DB and appended to the database_schema.sql migration section
- **Files modified:** database_schema.sql
- **Verification:** Admin successfully reached /admin/data/testlist1 after fix; View Groups and Edit Groups appeared on admin edit form; human verification confirmed all checks passing
- **Committed in:** 97e0754 (applied outside agent after checkpoint)

---

**Total deviations:** 1 auto-fixed (1 bug — privilege regression from permission split)
**Impact on plan:** Fix was essential — without it admins were locked out of their own data pages. Fix is minimal and targeted; no scope creep.

## Issues Encountered

The permission split from table_management to table_data_access for the page guard was correct in isolation, but required a corresponding grant of table_data_access to groups that already held table_management. The plan did not anticipate this step. The additional commit (97e0754) resolved it idempotently via INSERT IGNORE in the migration SQL.

## User Setup Required

None - no external service configuration required. Permission grants are applied via migration SQL already run against the live DB.

## Next Phase Readiness

- Non-admin users with table_data_access can reach /admin/data/{table} without hitting a 403
- Row-level ACL (canViewRow/canEditRow/filterRowsByViewAccess) runs for data-only users
- Admin users retain View Groups / Edit Groups selectors; data-only users see neither the UI nor can POST those fields
- Phase 2 UAT tests 6-9 are now unblocked and expected to pass
- ACL-01, ACL-02, ACL-03 requirements fully satisfied
- Phase 3 (versioning/snapshots) can proceed

---
*Phase: 02-access-control*
*Completed: 2026-03-01*
