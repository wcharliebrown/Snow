---
phase: 01-security-foundations
plan: 04
subsystem: auth
tags: [csrf, security, sessions, php, forms]

# Dependency graph
requires:
  - phase: 01-02
    provides: DB-backed sessions ($_SESSION persists reliably across requests)

provides:
  - Per-session CSRF token generation via random_bytes(32) + bin2hex
  - Timing-safe token validation via hash_equals()
  - Central CSRF enforcement gate in renderPage() before any POST handler
  - csrfField() hidden input injected into all POST forms across the framework

affects:
  - All future phases that add new POST forms (must call csrfField())
  - Phase 2+ (ACL) - CSRF protection now active on all state-changing requests

# Tech tracking
tech-stack:
  added: []
  patterns:
    - Per-session CSRF token (not per-request) to preserve multi-tab and back-button compatibility
    - Central enforcement in renderPage() via require_once + requireCsrf() before custom_script include
    - csrfField() helper for embedding hidden token fields inside any form

key-files:
  created:
    - functions/csrf.php
  modified:
    - functions/pages.php
    - templates/login_page_template.html
    - functions/profile.php
    - functions/admin-users.php
    - functions/admin-tables.php
    - functions/admin-custom-table.php
    - functions/admin-logs.php
    - functions/admin-plugins.php
    - functions/admin-groups.php
    - functions/admin-reports.php
    - functions/admin-snapshots.php
    - functions/admin-pages.php
    - functions/admin-emails.php

key-decisions:
  - "Per-session tokens (not per-request) — avoids back-button and multi-tab breakage in traditional PHP form apps"
  - "Central enforcement in renderPage() via require_once + requireCsrf() before custom_script include — one insertion protects all pages"
  - "Extended csrfField() to all POST forms found in codebase (plugins, groups, reports, snapshots, pages, emails) beyond plan's explicit list — plan instructs grep-all-forms coverage"

patterns-established:
  - "csrfField() pattern: call <?= csrfField() ?> as first child of every <form method='post'>"
  - "requireCsrf() pattern: centrally enforced in renderPage(), not per-handler"

requirements-completed: [SEC-01]

# Metrics
duration: 10min
completed: 2026-02-28
---

# Phase 1 Plan 04: CSRF Protection Summary

**Per-session CSRF tokens via random_bytes(32) with central enforcement in renderPage() and csrfField() injected into all 25 POST forms across the Snow framework**

## Performance

- **Duration:** 10 min
- **Started:** 2026-02-28T16:17:31Z
- **Completed:** 2026-02-28T16:27:00Z
- **Tasks:** 2
- **Files modified:** 14 (1 created, 13 modified)

## Accomplishments
- Created `functions/csrf.php` with 4 functions: generateCsrfToken, validateCsrfToken, csrfField, requireCsrf
- Wired `requireCsrf()` centrally into `renderPage()` in pages.php — all pages now protected with one hook
- Injected `csrfField()` into every POST form across the framework (login, profile, all 11 admin handlers)
- Any POST without a valid CSRF token now returns HTTP 403 with a clear error message

## Task Commits

Each task was committed atomically:

1. **Task 1: Create functions/csrf.php** - `3726a61` (feat)
2. **Task 2: Wire requireCsrf() and inject csrfField() into all POST forms** - `38c052c` (feat)

**Plan metadata:** (docs commit follows)

## Files Created/Modified
- `functions/csrf.php` - New file: generateCsrfToken, validateCsrfToken, csrfField, requireCsrf
- `functions/pages.php` - Added require_once csrf.php + requireCsrf() before custom_script include
- `templates/login_page_template.html` - csrfField() in /login POST form
- `functions/profile.php` - csrfField() in profile update form
- `functions/admin-users.php` - csrfField() in add and edit user forms
- `functions/admin-tables.php` - csrfField() in add table, edit table, delete-field, add-field forms (4 forms)
- `functions/admin-custom-table.php` - csrfField() in add, edit, delete record forms (3 forms)
- `functions/admin-logs.php` - csrfField() in all 5 clear-log forms (4 per-level + 1 clear-all)
- `functions/admin-plugins.php` - csrfField() in add plugin form
- `functions/admin-groups.php` - csrfField() in add and edit group forms
- `functions/admin-reports.php` - csrfField() in add, edit, duplicate report forms (3 forms)
- `functions/admin-snapshots.php` - csrfField() in create snapshot form
- `functions/admin-pages.php` - csrfField() in add and edit page forms
- `functions/admin-emails.php` - csrfField() in add and edit email template forms

## Decisions Made
- Per-session tokens (not per-request) — avoids back-button and multi-tab breakage; correct for traditional form-based PHP apps
- Central enforcement in renderPage() before custom_script include — one insertion protects every page in the framework
- Extended coverage to all POST forms found in codebase (plugins, groups, reports, snapshots, pages, emails) beyond the plan's explicit list, per the plan's grep-all-forms instruction

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Missing Critical] Extended csrfField() to 8 additional admin handlers not listed in plan**
- **Found during:** Task 2 (Inject csrfField() into all POST forms)
- **Issue:** Plan explicitly listed admin-users, admin-tables, admin-custom-table, admin-logs, profile, login — but grep revealed additional POST forms in admin-plugins, admin-groups, admin-reports, admin-snapshots, admin-pages, admin-emails
- **Fix:** Added csrfField() to all POST forms found by grep as instructed in plan Step B: "For each result found beyond the listed files above, add csrfField() as the first child of that form too"
- **Files modified:** functions/admin-plugins.php, functions/admin-groups.php, functions/admin-reports.php, functions/admin-snapshots.php, functions/admin-pages.php, functions/admin-emails.php
- **Verification:** grep -rn csrfField shows all POST form files covered; all lint clean
- **Committed in:** 38c052c (Task 2 commit)

---

**Total deviations:** 1 auto-applied (plan-instructed coverage of all discovered forms)
**Impact on plan:** Extended scope to full application coverage as plan intended. No unintended scope creep.

## Issues Encountered
None - all modifications straightforward. The plan's grep-all-forms instruction made comprehensive coverage explicit.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- CSRF protection is active across all pages and forms
- Any new POST form added in future phases must include `<?= csrfField() ?>` as first child element
- The login flow now requires a session before the login form renders (token in session), which is correct: renderPage() generates the token before login.php runs
- Ready for Plan 05 (next in phase 01-security-foundations)

---
*Phase: 01-security-foundations*
*Completed: 2026-02-28*
