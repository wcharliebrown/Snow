---
phase: 01-security-foundations
plan: 01
subsystem: database
tags: [mysql, migration, sessions, activity-log, password-policy, password-history]

# Dependency graph
requires: []
provides:
  - sessions table for DB-backed session storage (SEC-02)
  - activity_log table for structured event/audit logging (SEC-04)
  - password_policy table with seeded default config row (SEC-03)
  - password_history table for password reuse prevention (SEC-03)
  - password_changed_at column on users table for password age tracking (SEC-03)
affects:
  - 01-02 (session handler implementation depends on sessions table)
  - 01-03 (CSRF protection depends on sessions table)
  - 01-04 (password policy enforcement depends on password_policy and password_history tables)
  - 01-05 (activity logging depends on activity_log table)

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "IF NOT EXISTS on all CREATE TABLE statements — safe for re-runs and idempotent migrations"
    - "Conditional ALTER TABLE via PREPARE/EXECUTE for MySQL 8.0 compatibility (ADD COLUMN IF NOT EXISTS not supported in MySQL, only MariaDB)"
    - "INSERT ... SELECT ... WHERE NOT EXISTS pattern for idempotent seed data"

key-files:
  created: []
  modified:
    - database_schema.sql

key-decisions:
  - "LONGTEXT columns cannot have DEFAULT '' in MySQL 8.0 strict mode — removed default value from sessions.data column"
  - "ALTER TABLE ADD COLUMN IF NOT EXISTS is MariaDB syntax only, not supported in MySQL 8.0 — used PREPARE/EXECUTE conditional guard instead"
  - "Docker stack (snow_mysql) was not running; started it as part of migration apply step; .env created from .env.example with docker-compose credentials"

patterns-established:
  - "Migration pattern: append to database_schema.sql with phase header comment, apply via docker exec"
  - "Idempotent DDL: all CREATE TABLE use IF NOT EXISTS; ALTER TABLE uses PREPARE/EXECUTE conditional guard"

requirements-completed: [SEC-01, SEC-02, SEC-03, SEC-04]

# Metrics
duration: 20min
completed: 2026-02-28
---

# Phase 1 Plan 01: Security Foundations DB Schema Summary

**Four MySQL security tables (sessions, activity_log, password_policy, password_history) and users.password_changed_at column created and applied to running MySQL instance**

## Performance

- **Duration:** ~20 min
- **Started:** 2026-02-28T13:30:00Z
- **Completed:** 2026-02-28T13:58:00Z
- **Tasks:** 1 of 1
- **Files modified:** 1

## Accomplishments
- All four Phase 1 security tables created in MySQL with correct indexes and foreign keys
- password_policy seeded with default row (min_length=8, max_age_days=0, prevent_reuse_count=0)
- users table extended with password_changed_at DATETIME NULL column
- Migration is idempotent — safe to re-run on fresh or existing schema
- Docker stack started (snow_mysql container) and .env created from example

## Task Commits

Each task was committed atomically:

1. **Task 1: Add Phase 1 security tables to database_schema.sql and apply migration** - `9ecc538` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified
- `database_schema.sql` - Appended sessions, activity_log, password_policy, password_history CREATE TABLE DDL and ALTER TABLE users for password_changed_at

## Decisions Made
- Removed `DEFAULT ''` from `sessions.data LONGTEXT` column — MySQL 8.0 strict mode does not allow defaults on BLOB/TEXT/JSON columns
- Used `PREPARE/EXECUTE` conditional guard for `ALTER TABLE users ADD COLUMN password_changed_at` instead of `ADD COLUMN IF NOT EXISTS` — the latter is MariaDB syntax only, not supported in MySQL 8.0
- Created `.env` file from `.env.example` with docker-compose credentials (snow_user/snow_password/snow) so the application layer can connect to the running container

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] LONGTEXT column DEFAULT value causes syntax error in MySQL 8.0 strict mode**
- **Found during:** Task 1 (applying migration)
- **Issue:** Plan specified `data LONGTEXT NOT NULL DEFAULT ''` — MySQL 8.0 strict mode rejects DEFAULT values on BLOB/TEXT/GEOMETRY/JSON columns
- **Fix:** Removed `DEFAULT ''` from sessions.data column definition in both database_schema.sql and the applied migration
- **Files modified:** database_schema.sql
- **Verification:** Migration applied without error; sessions table created successfully
- **Committed in:** 9ecc538 (Task 1 commit)

**2. [Rule 1 - Bug] ALTER TABLE ADD COLUMN IF NOT EXISTS not supported in MySQL 8.0**
- **Found during:** Task 1 (applying migration)
- **Issue:** Plan specified `ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at` — this syntax is MariaDB-only; MySQL 8.0 rejects it with syntax error
- **Fix:** Replaced with PREPARE/EXECUTE conditional guard that checks INFORMATION_SCHEMA.COLUMNS before executing the ALTER. Updated database_schema.sql to use same pattern.
- **Files modified:** database_schema.sql
- **Verification:** ALTER applied without error; SHOW COLUMNS FROM users LIKE 'password_changed_at' returns the column
- **Committed in:** 9ecc538 (Task 1 commit)

---

**Total deviations:** 2 auto-fixed (both Rule 1 - bugs in plan DDL incompatible with MySQL 8.0)
**Impact on plan:** Both fixes were necessary for MySQL compatibility. No scope change. All required tables and columns created as specified.

## Issues Encountered
- Docker stack (snow_mysql) was not running at execution start — started it with `docker compose up -d mysql`
- .env file was missing — created from .env.example with docker-compose credentials matching docker-compose.yml hardcoded values

## User Setup Required
None - no external service configuration required. The .env file was created automatically with values matching the docker-compose.yml environment.

## Next Phase Readiness
- All four dependency tables exist and are verified in the running MySQL instance
- Sessions table is ready for Plan 02 (DB-backed session handler implementation)
- activity_log is ready for Plan 05 (structured logging implementation)
- password_policy and password_history are ready for Plan 04 (password policy enforcement)
- CSRF table (if needed) depends on sessions — sessions table is now available

---
*Phase: 01-security-foundations*
*Completed: 2026-02-28*

## Self-Check: PASSED

- database_schema.sql: FOUND
- 01-01-SUMMARY.md: FOUND
- commit 9ecc538: FOUND
- DB sessions table: EXISTS
- DB activity_log table: EXISTS
- DB password_policy table: EXISTS
- DB password_history table: EXISTS
- DB password_policy seed row: EXISTS (1 row, min_length=8, max_age_days=0, prevent_reuse_count=0)
- DB users.password_changed_at column: EXISTS (DATETIME NULL)
