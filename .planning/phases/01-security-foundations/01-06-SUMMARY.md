---
phase: 01-security-foundations
plan: 06
subsystem: auth
tags: [php, sessions, password-policy, admin-ui, bootstrap]

# Dependency graph
requires:
  - phase: 01-02
    provides: getActiveSessions(), forceLogoutSession(), forceLogoutUser() in session-handler.php
  - phase: 01-03
    provides: getPasswordPolicy(), savePasswordPolicy() in password-policy.php
  - phase: 01-04
    provides: csrfField() helper and CSRF enforcement in pages.php
provides:
  - Admin UI page for viewing active sessions and force-logging out individual sessions
  - Admin UI page for reading and saving the site password policy
  - Both pages registered in the pages table (admin/sessions, admin/password-policy)
affects: [future-admin-phases, sec-02, sec-03]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Custom script pattern: admin pages are PHP files included by renderPage() via pages.custom_script; handle POST first, render HTML below
    - Force-logout by numeric id: look up session_id string from sessions.id, then call forceLogoutSession()
    - Flash success pattern: set $_SESSION['flash_success'] before redirect, display and unset on next render

key-files:
  created:
    - functions/admin-sessions.php
    - functions/admin-password-policy.php
  modified: []

key-decisions:
  - "Force-logout by numeric row id: query sessions.session_id WHERE id=?, then call forceLogoutSession(string) — avoids exposing raw session token in HTML"
  - "Both admin pages use admin_page_template.html and require_auth=1, matching all existing admin pages"
  - "admin/sessions uses admin_access permission (super-admin only); admin/password-policy uses user_management (same as user admin)"

patterns-established:
  - "Admin page pattern: POST handler at top with requirePermission(), redirect-after-post via Location header; GET render below"
  - "Flash message pattern: $_SESSION['flash_success'] set before redirect, displayed and unset at render time"

requirements-completed: [SEC-02, SEC-03]

# Metrics
duration: 15min
completed: 2026-02-28
---

# Phase 01 Plan 06: Admin Sessions and Password Policy UI Summary

**Bootstrap admin pages for force-logout (SEC-02) and password policy config (SEC-03) registered in pages table at admin/sessions and admin/password-policy**

## Performance

- **Duration:** 15 min
- **Started:** 2026-02-28T18:25:08Z
- **Completed:** 2026-02-28T18:40:43Z
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments

- Created `functions/admin-sessions.php` — renders active session table with user name, IP, browser, start time, expiry; force-logout POST handler looks up session_id by numeric row id and calls forceLogoutSession(); highlights current admin session
- Created `functions/admin-password-policy.php` — form with current policy values; POST handler validates input ranges (min_length 1-128, max_age >= 0, reuse 0-50) and calls savePasswordPolicy(); logs changes via logInfo()
- Registered both pages in the pages table: admin/sessions (id=15, admin_access) and admin/password-policy (id=16, user_management) with admin_page_template.html

## Task Commits

Each task was committed atomically:

1. **Task 1: Create admin-sessions.php — active session list with force-logout** - `65d67cd` (feat)
2. **Task 2: Create admin-password-policy.php and register both admin pages in DB** - `c0fb878` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified

- `functions/admin-sessions.php` - Admin page: active sessions table with per-session force-logout and bulk force-logout-user support
- `functions/admin-password-policy.php` - Admin page: password policy form; validates and saves min_length, max_age_days, prevent_reuse_count

## Decisions Made

- Force-logout by numeric row id: `SELECT session_id FROM sessions WHERE id = ?` before calling `forceLogoutSession(string)` — avoids exposing raw session tokens in HTML hidden inputs
- Both pages use the same `admin_page_template.html` as all existing admin pages, and `require_auth=1`
- Permission split: `admin_access` for sessions (only super-admins should terminate sessions), `user_management` for password policy (same level as user admin)

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered

None.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- SEC-02 and SEC-03 admin visibility/control surfaces are complete
- Phase 01 (Security Foundations) is now complete — all 6 plans executed
- Phase 02 can begin (ACL/permissions layer or next planned phase per ROADMAP.md)

## Self-Check: PASSED

All files present and both commits verified (65d67cd, c0fb878).

---
*Phase: 01-security-foundations*
*Completed: 2026-02-28*
