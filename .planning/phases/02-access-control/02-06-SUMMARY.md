---
phase: 02-access-control
plan: 06
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
affects: [02-access-control, phase-03-versioning]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Permission separation: table_management for schema admin; table_data_access for data access with row-level ACL", "Sentinel null pattern: $viewGroupIds=null signals do-not-write (vs empty array meaning open-to-all)"]

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

patterns-established:
  - "Permission separation: table_management guards schema/ACL admin; table_data_access guards data page access"
  - "Sentinel null pattern for optional DB column writes: null means skip, array means write (even if empty)"

requirements-completed: [ACL-01, ACL-02, ACL-03]

# Metrics
duration: 15min
completed: 2026-03-01
---

# Phase 2 Plan 06: Gap Closure — Non-Admin Table Data Access Summary

**New table_data_access permission wired into admin/data/* pages, inner guard, and ACL widget gating — unblocking Phase 2 row-level ACL for non-admin users**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-03-01T20:21:25Z
- **Completed:** 2026-03-01T20:36:00Z
- **Tasks:** 2 of 3 complete (Task 3 is checkpoint:human-verify — awaiting user confirmation)
- **Files modified:** 3

## Accomplishments

- Added table_data_access permission to DB (live migration + schema file) — separates data-page access from schema management
- Migrated all existing admin/data/* pages from required_permission='table_management' to 'table_data_access' in the live DB
- Updated provisionCustomTable() to register future pages with table_data_access
- Changed admin-custom-table.php inner guard from requirePermission('table_management') to requirePermission('table_data_access')
- View Groups / Edit Groups UI hidden from users without table_management (both add and edit forms)
- POST handlers now use sentinel-null pattern to prevent data-only users from overwriting ACL group values via form tampering

## Task Commits

Each task was committed atomically:

1. **Task 1: Add table_data_access permission, update provisioner, run live DB migration** - `10165de` (feat)
2. **Task 2: Swap inner guard and gate ACL widget in admin-custom-table.php** - `729842b` (feat)
3. **Task 3: Checkpoint — human verification** - pending user approval

**Plan metadata:** TBD after checkpoint approval

## Files Created/Modified

- `/home/cb/Snow/database_schema.sql` - Appended Phase 2 gap-closure section: INSERT IGNORE for table_data_access permission + UPDATE to migrate admin/data/* pages
- `/home/cb/Snow/functions/admin-tables.php` - provisionCustomTable() savePage() call uses 'table_data_access' instead of 'table_management'
- `/home/cb/Snow/functions/admin-custom-table.php` - Inner guard changed; View/Edit Groups UI gated on hasPermission('table_management'); POST handlers use sentinel-null to skip ACL column writes for data-only users

## Decisions Made

- table_data_access is a separate permission from table_management — data users reach admin/data/* but cannot touch schema or ACL group assignments
- Sentinel null ($viewGroupIds = null) used to distinguish do-not-write from empty-array (open-to-all) in POST handlers
- hasPermission() gates both UI rendering and POST collection — defense in depth against form tampering
- INSERT IGNORE + conditional UPDATE make migration section idempotent — safe to re-run on DB restores

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Non-admin users with table_data_access permission can now reach /admin/data/{table} without hitting a 403
- Row-level ACL (canViewRow/canEditRow/filterRowsByViewAccess) runs for data-only users
- Admin users retain View Groups / Edit Groups selectors; data-only users see neither the UI nor can they POST those fields
- Phase 2 UAT tests 6-9 are now unblocked for execution
- Awaiting checkpoint human verification before marking plan complete

---
*Phase: 02-access-control*
*Completed: 2026-03-01*
