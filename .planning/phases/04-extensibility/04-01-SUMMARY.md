---
phase: 04-extensibility
plan: 01
subsystem: testing
tags: [tdd, stubs, extensibility, otp, hooks, email-templates, custom-pages]
dependency_graph:
  requires: []
  provides: [test stubs for EXT-01, test stubs for SEC-05, test stubs for EXT-02, test stubs for EXT-03]
  affects: [tests/test_all.php, tests/test_extensibility.php]
tech_stack:
  added: []
  patterns: [SnowTestRunner describe/it, INFORMATION_SCHEMA guards, try/catch Throwable for hook testing, tempnam sentinel pattern]
key_files:
  created:
    - tests/test_extensibility.php
  modified:
    - tests/test_all.php
decisions:
  - login_otp FK violation: resolved by fetching a real user_id from users table instead of using 0
  - standalone run guard: isset($t) check added so file can be run directly or included from test_all.php
  - pre-existing file: test_extensibility.php was already committed in plan 04-02 run (as a deviation fix); Task 1 was effectively pre-completed; Task 2 (require_once append) still executed in this plan
metrics:
  duration_minutes: 4
  completed_date: "2026-03-06"
  tasks_completed: 2
  files_modified: 2
---

# Phase 4 Plan 01: Extensibility Test Stubs Summary

Phase 4, Wave 0 test stubs created covering all four extensibility requirements. All 14 tests pass against the current DB (schema was applied in 04-02, which ran before this plan).

## What Was Built

`tests/test_extensibility.php` containing four describe blocks:

- **EXT-01: Table Hooks** (4 tests) — INFORMATION_SCHEMA guards for `pre_edit_php_filename` and `post_edit_php_filename` columns on `custom_tables`; tempnam sentinel tests for hook include and exception absorption
- **SEC-05: Email OTP 2FA** (6 tests) — `login_otp` table existence and column check; `users.require_2fa` column; expired OTP and locked-out OTP DB query tests; session key invariant assertion
- **EXT-03: Email Templates** (2 tests) — `processTokens()` substitution via a transient `__test_ext03__` row; `login_otp` seed row presence check
- **EXT-02: Custom Pages** (2 tests) — `pages.content` and `pages.custom_script` column checks; tempnam sentinel for the `custom_script` include path pattern

`tests/test_all.php` — one `require_once` line appended after `test_data_integrity.php`.

## Tasks Completed

| Task | Name | Commit | Files |
|------|------|--------|-------|
| 1 | Create tests/test_extensibility.php | cfafebb (04-02 run) / present in repo | tests/test_extensibility.php |
| 2 | Append require_once to tests/test_all.php | d12dc60 | tests/test_all.php |

## Verification Results

```
php tests/test_extensibility.php
Total:   14
Passed:  14

php tests/test_all.php
Total:   186
Passed:  158   (extensibility suite: 14/14 pass; 22 pre-existing HTTP failures unaffected)
```

All 4 describe blocks appear in test_all.php output. No PHP fatals. No regressions.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] FK violation on login_otp insert with user_id = 0**
- **Found during:** Task 1 verification
- **Issue:** `login_otp` has `FOREIGN KEY (user_id) REFERENCES users(id)` — inserting user_id=0 throws `SQLSTATE[23000] Integrity constraint violation`
- **Fix:** Added `dbGetRow("SELECT id FROM users ORDER BY id LIMIT 1")` to resolve a real FK-safe user_id; added guard skip if no users exist
- **Files modified:** tests/test_extensibility.php
- **Commit:** cfafebb (bundled into 04-02 run that created the file)

### Pre-execution Overlap

`tests/test_extensibility.php` was committed in the 04-02 plan run (as Rule 1 deviation — it was needed to validate the migration). The file was already present and tested when this plan executed. Task 1 was effectively pre-completed; this plan's contribution was Task 2 (wiring the file into test_all.php) and formal documentation of the test stubs as the Wave-0 test baseline.

## Self-Check: PASSED

- tests/test_extensibility.php — FOUND
- tests/test_all.php — FOUND (require_once present)
- Commit d12dc60 — FOUND
