---
phase: 01-security-foundations
plan: 03
subsystem: auth
tags: [password-policy, password-history, expiry, reuse-prevention, php]

# Dependency graph
requires:
  - phase: 01-01
    provides: password_policy table, password_history table, users.password_changed_at column
provides:
  - Configurable password policy with min_length, max_age_days, prevent_reuse_count
  - validatePasswordStrength() for all password-setting points
  - isPasswordExpired() for post-login expiry enforcement
  - isPasswordReused() checking against password_history
  - recordPasswordChange() writing to password_history and updating password_changed_at
  - Policy enforcement hooked into loginUser(), changePassword(), completePasswordReset()
affects: [Plan 06 password policy admin UI, any future password-setting flows]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Single policy row upsert pattern: check for existing row, update or insert accordingly"
    - "Policy functions return empty array for success, non-empty array for validation errors"
    - "changePassword() returns false (auth failure) | array (validation errors) | true (success)"
    - "Expiry check uses NULL = not-expired convention for backward compat with existing users"

key-files:
  created:
    - functions/password-policy.php
  modified:
    - functions/auth.php
    - functions/admin-users.php

key-decisions:
  - "NULL password_changed_at treated as not-expired to avoid forcing password changes on all existing users when feature is first deployed"
  - "changePassword() returns array of errors (not false) so callers can display specific messages rather than a generic failure"
  - "require_once guard pattern for password-policy.php in auth.php prevents double-loading when files are loaded in different orders"
  - "minlength HTML attribute updated dynamically from getPasswordPolicy() so browser-side validation matches server-side policy"

patterns-established:
  - "Policy-driven validation: all password validation goes through validatePasswordStrength() — no hardcoded length checks in application code"
  - "History tracking on every password change via recordPasswordChange() called from all change/reset paths"

requirements-completed: [SEC-03]

# Metrics
duration: 3min
completed: 2026-02-28
---

# Phase 1 Plan 03: Password Policy Summary

**Configurable password policy (min_length, max_age, reuse prevention) enforced at all login and password-change points via functions/password-policy.php**

## Performance

- **Duration:** 3 min
- **Started:** 2026-02-28T14:01:51Z
- **Completed:** 2026-02-28T14:04:05Z
- **Tasks:** 2
- **Files modified:** 3 (1 created, 2 modified)

## Accomplishments
- Created `functions/password-policy.php` with 6 functions: getPasswordPolicy, savePasswordPolicy, validatePasswordStrength, isPasswordExpired, isPasswordReused, recordPasswordChange
- Hooked isPasswordExpired into loginUser() — sets `$_SESSION['require_password_change']` flag when expired
- Rewrote changePassword() to validate strength, check reuse history, and call recordPasswordChange() on success
- Added recordPasswordChange() call to completePasswordReset() so password resets are tracked in history
- Replaced both hardcoded `strlen($password) < 8` checks in admin-users.php with validatePasswordStrength()
- Updated HTML minlength attributes to reflect dynamic policy value from getPasswordPolicy()

## Task Commits

Each task was committed atomically:

1. **Task 1: Create functions/password-policy.php** - `4c42981` (feat)
2. **Task 2: Wire policy enforcement into auth.php and admin-users.php** - `57501de` (feat)

**Note:** auth.php policy changes were applied correctly and are present in the working file. The `git add` for auth.php showed no diff because Plan 02's execution had already written the full auth.php with these changes pre-included (both plans modified auth.php and Plan 02 was committed with the full final state of auth.php including Plan 03 content).

**Plan metadata:** (final docs commit — see below)

## Files Created/Modified
- `functions/password-policy.php` - All password policy functions (getPasswordPolicy, savePasswordPolicy, validatePasswordStrength, isPasswordExpired, isPasswordReused, recordPasswordChange)
- `functions/auth.php` - Added require_once guard, isPasswordExpired check in loginUser(), full changePassword() rewrite with policy enforcement, recordPasswordChange() in completePasswordReset()
- `functions/admin-users.php` - Added require_once, replaced both strlen checks with validatePasswordStrength(), dynamic minlength HTML attributes

## Decisions Made
- NULL `password_changed_at` treated as not-expired — avoids forcing immediate password changes on all existing users when the expiry feature is first deployed
- `changePassword()` returns an array of error strings (not false) so callers can display specific validation messages
- Used `require_once` guard (`if (!function_exists('getPasswordPolicy'))`) in auth.php to safely load password-policy.php without double-loading errors
- HTML `minlength` attribute uses `getPasswordPolicy()['min_length']` dynamically so browser validation always matches server-side policy

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- The auth.php changes for Plan 03 were already present in the committed state from Plan 02 (Plan 02 executor had written the full auth.php including Plan 03 hooks). No code was missing — this is a git history artifact, not a functional issue.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- Password policy functions are complete and hooked into all auth flows
- Plan 06 can now build the admin UI for configuring the password_policy table values
- Any new password-setting flows (e.g., self-service registration) should call validatePasswordStrength(), isPasswordReused(), and recordPasswordChange()

## Self-Check: PASSED

- functions/password-policy.php: FOUND
- .planning/phases/01-security-foundations/01-03-SUMMARY.md: FOUND
- Commit 4c42981 (Task 1): FOUND
- Commit 57501de (Task 2): FOUND

---
*Phase: 01-security-foundations*
*Completed: 2026-02-28*
