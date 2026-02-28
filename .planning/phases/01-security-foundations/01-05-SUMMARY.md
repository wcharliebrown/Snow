---
phase: 01-security-foundations
plan: 05
subsystem: logging
tags: [logging, activity-log, db-logging, audit-trail, admin-ui, sec-04]

# Dependency graph
requires:
  - 01-01 (activity_log table)
provides:
  - DB-backed structured logging via activity_log table (SEC-04)
  - Admin log viewer with event_type filter and user/IP context display
affects:
  - All logging call sites (logError, logInfo, logEmail, logTraffic — signatures unchanged)
  - Admin /admin/logs page

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "DB-first logging with flat-file fallback: try/catch wraps DB insert; @file_put_contents used directly in catch to avoid logError() recursion"
    - "Recursion guard: logMessage() catch block never calls logError() or getDbConnection() — writes flat file only"
    - "Dual write: DB insert is primary path; flat-file write always runs as secondary audit trail"
    - "DB log queries use LEFT JOIN users to enrich entries with user_name and user_email"

key-files:
  created: []
  modified:
    - functions/logging.php
    - functions/admin-logs.php

key-decisions:
  - "Flat-file write runs unconditionally (not only as fallback) — provides audit redundancy even when DB is healthy"
  - "logMessage() catch block uses @file_put_contents directly rather than calling logError() — eliminates recursion risk when DB is down (PITFALL-4)"
  - "getLogEntries/searchLogEntries return empty array on DB failure — callers get graceful degradation without recurse into logError"
  - "Email badge colour changed from warning (yellow) to success (green) to distinguish from TRAFFIC; TRAFFIC stays secondary (grey)"

# Metrics
duration: 15min
completed: 2026-02-28
---

# Phase 1 Plan 05: DB-Backed Structured Logging Summary

**logMessage() now writes to activity_log table (DB-first with flat-file fallback); admin log viewer queries the table with user/IP context and event_type filter**

## Performance

- **Duration:** ~15 min
- **Started:** 2026-02-28T18:04:16Z
- **Completed:** 2026-02-28T18:19:00Z
- **Tasks:** 2 of 2
- **Files modified:** 2

## Accomplishments

- logMessage() updated to INSERT into activity_log as primary path; flat-file write always runs as secondary audit trail
- Recursion guard implemented: catch block uses @file_put_contents directly — never calls logError() or getDbConnection()
- getLogEntries() rewritten from flat-file parsing to SELECT with LEFT JOIN users (returns user_name, user_email, ip_address, event_type, context)
- searchLogEntries() rewritten to query activity_log with LIKE filter on message/ip_address/email/first_name
- clearLogs() now clears activity_log rows AND flat-log files
- getLogStats() now queries activity_log COUNT/SUM aggregates instead of parsing flat files
- admin-logs.php updated: table shows created_at, level (badge), event_type, user (name+email or "System"), ip_address, message
- Context JSON rendered via collapsible <details>/<summary> toggle with json_encode pretty-print
- Filter dropdown uses ERROR/INFO/EMAIL/TRAFFIC matching activity_log.level values
- All public function signatures unchanged (logError, logInfo, logTraffic, logEmail)

## Task Commits

Each task was committed atomically:

1. **Task 1: Update logMessage() in functions/logging.php to write to activity_log with flat-file fallback** - `01e7392` (feat)
2. **Task 2: Update admin-logs.php to display activity_log data with event_type filter** - `579b213` (feat)

**Plan metadata:** (docs commit — see below)

## Files Created/Modified

- `functions/logging.php` — logMessage() DB-first write; getLogEntries/searchLogEntries/clearLogs/getLogStats all query activity_log
- `functions/admin-logs.php` — Admin log viewer updated to display DB-backed entries with user context, IP, event_type column, and context JSON toggle

## Decisions Made

- Flat-file write is unconditional (not just a fallback): even when DB write succeeds, the flat file is written — provides audit redundancy
- Recursion guard: catch block in logMessage() uses `@file_put_contents` directly and never calls `logError()` or `getDbConnection()` — eliminates infinite recursion when DB is unavailable (PITFALL-4 from RESEARCH.md)
- `getLogEntries()`, `searchLogEntries()`, `getLogStats()` all return empty/default values on DB failure — graceful degradation without recursing into logError
- EMAIL badge colour set to `success` (green) rather than `warning` (yellow) to distinguish from TRAFFIC (secondary/grey)

## Deviations from Plan

None - plan executed exactly as written.

## Success Criteria Verification

- [x] functions/logging.php logMessage() writes to activity_log table (INSERT INTO activity_log)
- [x] Flat-file logging still works as fallback when DB unavailable (flat-file block is unconditional)
- [x] logError/logInfo/logEmail function signatures unchanged (call sites don't break)
- [x] functions/admin-logs.php admin UI queries activity_log, shows level/event_type filter
- [x] Admin can filter log by event type (ERROR, INFO, EMAIL, TRAFFIC)
- [x] Log entries show user context (name/email), IP address, and timestamp

---
*Phase: 01-security-foundations*
*Completed: 2026-02-28*

## Self-Check: PASSED

- functions/logging.php: FOUND
- functions/admin-logs.php: FOUND
- commit 01e7392 (Task 1): FOUND
- commit 579b213 (Task 2): FOUND
- PHP lint logging.php: PASSED
- PHP lint admin-logs.php: PASSED
- INSERT INTO activity_log in logging.php: FOUND (line 66)
- FROM activity_log in logging.php: FOUND (lines 135, 165, 229)
- getLogEntries in admin-logs.php: FOUND
- event_type column display in admin-logs.php: FOUND
- csrfField() in admin-logs.php POST forms: FOUND
- No flat-file read code in admin-logs.php: CONFIRMED
