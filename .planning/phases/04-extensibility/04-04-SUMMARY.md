---
phase: 04-extensibility
plan: 04
subsystem: auth
tags: [php, 2fa, otp, email, session, security]

# Dependency graph
requires:
  - phase: 04-02
    provides: login_otp table, require_2fa column, login_otp email template seed
provides:
  - functions/login.php 2FA fork: require_2fa branch sends OTP, unsetts user_id, sets otp_pending_user_id
  - functions/login-otp.php: OTP entry page with GET form render and POST verification
  - pages table row for login-otp path serving login-otp.php custom_script
  - admin-users.php require_2fa checkbox with persist on edit
affects: [04-05-pages, any future auth work]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - 2FA session isolation: loginUser() sets user_id in session; 2FA fork immediately unsets it and sets otp_pending_user_id instead
    - OTP verification: SELECT WHERE expires_at > NOW() AND attempts < 3 — single query enforces both expiry and attempt limit
    - Attempt increment before delete check: increment first, compare attempts+1 >= 3 to decide whether to delete
    - Page registration pattern: pages table row with path + custom_script mirrors existing /login page structure

key-files:
  created:
    - functions/login-otp.php
  modified:
    - functions/login.php
    - functions/admin-users.php
    - database_schema.sql

key-decisions:
  - "2FA fork placed inside if ($user) block in login.php — NOT in auth.php loginUser() — keeps loginUser() reusable and avoids auth.php coupling to OTP logic"
  - "unset($_SESSION['user_id']) in 2FA path is explicit undo of loginUser() side effect — critical invariant: user_id must not be in session before OTP verified"
  - "login-otp page registered via INSERT ... ON DUPLICATE KEY UPDATE in database_schema.sql Phase 4 block — reproducible on fresh DB without separate migration step"
  - "Checkbox uses isset() not ?? 0 in POST handler — unchecked checkboxes absent from $_POST entirely; ?? 0 would never save 0 for explicit uncheck"

patterns-established:
  - "OTP form page mirrors login.php structure: custom_script, $page[] vars, ob_start/ob_get_clean pattern"
  - "Guard redirect at top of OTP page: empty(otp_pending_user_id) redirects to /login before any page setup"

requirements-completed: [SEC-05]

# Metrics
duration: 4min
completed: 2026-03-06
---

# Phase 4 Plan 04: Email OTP 2FA Login Flow Summary

**Email OTP second factor: login.php forks on require_2fa to send 6-digit code and block session until verified; login-otp.php handles OTP entry with expiry, attempt lockout, and session completion**

## Performance

- **Duration:** 4 min
- **Started:** 2026-03-06T22:38:20Z
- **Completed:** 2026-03-06T22:42:18Z
- **Tasks:** 2
- **Files modified:** 4

## Accomplishments

- Modified login.php POST handler to fork on require_2fa: generates OTP, stores in login_otp table, explicitly unsets session user_id (undoing loginUser() side effect), sends email template, redirects to /login-otp
- Created login-otp.php as a full custom_script page: GET renders Bootstrap OTP form; POST verifies code against expires_at and attempts filters, increments attempts on failure with lockout at 3, completes login with session_regenerate_id on success
- Added require_2fa checkbox to admin-users.php edit form with correct isset() POST handler and repopulation logic
- Registered /login-otp page in both live DB and database_schema.sql Phase 4 block for reproducibility

## Task Commits

1. **Task 1: Fork login.php for 2FA + create login-otp.php** - `b6ea9a8` (feat)
2. **Task 2: Add require_2fa checkbox to admin-users.php** - `969d122` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `functions/login.php` - Added require_2fa conditional branch in POST handler; 2FA path: generate OTP, unset user_id, set otp_pending_user_id, send email, redirect
- `functions/login-otp.php` - New OTP entry page: GET renders form, POST verifies with expiry/attempt guards and completes login
- `functions/admin-users.php` - require_2fa checkbox on edit form + dbUpdate field with isset() guard
- `database_schema.sql` - Phase 4 block: added INSERT for login-otp page registration

## Decisions Made

- 2FA fork placed in login.php, not auth.php loginUser(), to keep loginUser() reusable. The fork explicitly unstes session user_id set by loginUser() as a critical security invariant.
- login-otp page registered via INSERT ON DUPLICATE KEY UPDATE in database_schema.sql so the page is reproducible on a fresh install without a separate SQL step.
- Checkbox isset() pattern: unchecked HTML checkboxes are not submitted in POST data at all; using isset() correctly saves 0 when unchecked vs ?? 0 which would fail for the uncheck case.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- SEC-05 2FA flow is fully implemented end-to-end
- Admin can toggle require_2fa on any user via admin-users edit page
- Non-2FA login path completely unchanged (no regression risk)
- 04-05 (custom pages) can proceed without any dependency on this plan

---
*Phase: 04-extensibility*
*Completed: 2026-03-06*
