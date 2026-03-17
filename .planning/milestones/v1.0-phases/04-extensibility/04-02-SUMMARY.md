---
phase: 04-extensibility
plan: 02
subsystem: database
tags: [mysql, migration, schema, hooks, 2fa, otp, email-templates]

# Dependency graph
requires:
  - phase: 04-01
    provides: Phase 4 test stubs and planning baseline
  - phase: 03-data-integrity
    provides: Phase 1-3 migration block pattern (INFORMATION_SCHEMA PREPARE/EXECUTE guard)
provides:
  - custom_tables.pre_edit_php_filename column (VARCHAR 255, NULL) — EXT-01 hook wiring prerequisite
  - custom_tables.post_edit_php_filename column (VARCHAR 255, NULL) — EXT-01 hook wiring prerequisite
  - users.require_2fa column (TINYINT(1) NOT NULL DEFAULT 0) — SEC-05 OTP flow prerequisite
  - login_otp table with FK to users, expires_at and user_id indexes — SEC-05 OTP store
  - email_templates login_otp seed row with {{otp_code}} and {{first_name}} tokens
affects: [04-03-hook-wiring, 04-04-2fa-flow, 04-05-pages]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Phase-delimited migration block: each phase appends labelled SQL enabling sed extraction for idempotent re-runs
    - INFORMATION_SCHEMA PREPARE/EXECUTE guard for MySQL 8.0 idempotent ALTER TABLE
    - CREATE TABLE IF NOT EXISTS for new tables (no INFORMATION_SCHEMA guard needed)
    - INSERT ... ON DUPLICATE KEY UPDATE for seed data rows

key-files:
  created:
    - tests/test_extensibility.php
  modified:
    - database_schema.sql

key-decisions:
  - "Phase 4 migration extracted via sed by line number (not pattern) to avoid ambiguity with duplicate delimiter lines at Phase 3/4 boundary"
  - "login_otp FK to users ON DELETE CASCADE — orphaned OTP rows auto-cleaned when user deleted"
  - "Test stubs used user_id=0 which violated FK constraint; fixed to resolve real user_id via SELECT FROM users ORDER BY id LIMIT 1"

patterns-established:
  - "Migration blocks appended with opening/closing -- ===...=== delimiters; extract by line range for targeted apply"

requirements-completed: [EXT-01, SEC-05]

# Metrics
duration: 2min
completed: 2026-03-06
---

# Phase 4 Plan 02: Database Schema Migration Summary

**Phase 4 schema prerequisites applied: two hook columns on custom_tables, require_2fa on users, login_otp table with FK and indexes, and login_otp email template seed row**

## Performance

- **Duration:** 2 min
- **Started:** 2026-03-06T21:28:00Z
- **Completed:** 2026-03-06T21:30:30Z
- **Tasks:** 1
- **Files modified:** 2

## Accomplishments

- Appended Phase 4 migration block to database_schema.sql using established INFORMATION_SCHEMA PREPARE/EXECUTE guard pattern
- Applied migration to live MySQL 8.0 database; all 4 schema objects confirmed present via SHOW COLUMNS / DESCRIBE
- Verified idempotency: running migration a second time produces no errors
- All 14 EXT-01/SEC-05 extensibility test stubs now pass (up from 12)

## Task Commits

1. **Task 1: Append Phase 4 migration block and apply to live DB** - `cfafebb` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `database_schema.sql` - Phase 4 migration block appended (EXT-01 hook columns, SEC-05 require_2fa + login_otp table + email template seed)
- `tests/test_extensibility.php` - Phase 4 test stubs (Wave 0); fixed FK violation in OTP insert tests (user_id=0 -> resolved from users table)

## Decisions Made

- Phase 4 migration block extracted by line number rather than sed pattern matching, because the Phase 3 closing delimiter and Phase 4 opening delimiter are adjacent lines making pattern-based extraction ambiguous.
- login_otp FK uses ON DELETE CASCADE so orphaned OTP rows are automatically removed when a user is deleted.
- Test data in OTP tests fixed to resolve a real user_id from the database rather than using hardcoded 0, which violates the FK constraint now that the table exists.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed FK violation in OTP test data using user_id=0**
- **Found during:** Task 1 (verification — running test_extensibility.php)
- **Issue:** Pre-migration stubs used `user_id => 0` for login_otp inserts; now that the table exists with a FK to users, 0 is not a valid user_id and caused "Database insert failed" exceptions in both OTP tests
- **Fix:** Added `dbGetRow("SELECT id FROM users ORDER BY id LIMIT 1")` call before each insert; skip test if no users; use resolved ID
- **Files modified:** tests/test_extensibility.php
- **Verification:** `php tests/test_extensibility.php` — 14/14 pass
- **Committed in:** cfafebb (Task 1 commit)

---

**Total deviations:** 1 auto-fixed (Rule 1 - Bug)
**Impact on plan:** Fix necessary for test suite correctness. No scope change.

## Issues Encountered

- The `sed -n '/Phase 4: Extensibility/,/^-- ===.*===$/p'` pattern from the plan's interface spec matched only 2 lines because the Phase 3 closing delimiter and Phase 4 opening delimiter are adjacent. Extracted by line number instead (`sed -n '843,$p'`) which correctly captured all 76 lines of the Phase 4 block.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Schema prerequisites for EXT-01 (hook columns) are in place — 04-03 can wire the pre/post_edit hook include logic
- Schema prerequisites for SEC-05 (login_otp table + require_2fa column) are in place — 04-04 can implement the OTP flow
- email_templates login_otp row seeded — 04-04 can call sendEmailTemplate('login_otp', ...) immediately

---
*Phase: 04-extensibility*
*Completed: 2026-03-06*
