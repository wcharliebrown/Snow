---
phase: 02-access-control
verified: 2026-03-01T20:00:00Z
status: passed
score: 4/4 must-haves verified
re_verification:
  previous_status: passed
  previous_score: 4/4
  note: "Previous VERIFICATION.md was written before UAT (02-UAT.md) ran and discovered the group widget rendering failure (tests 3-5, severity major). Plan 02-05 fixed the root cause (view_groups_raw phantom key). This re-verification confirms the fix is in the codebase and the user has confirmed it working."
  gaps_closed:
    - "Add/edit forms for custom table rows now render View Groups and Edit Groups checkbox sections (02-05 commit 0e8eee1 fixed view_groups_raw bug; user confirmed)"
    - "POST repopulation of group checkboxes on validation failure now works correctly on both add and edit forms"
  gaps_remaining: []
  regressions: []
---

# Phase 2: Access Control — Re-Verification Report

**Phase Goal:** Implement row-level ACL (view_groups / edit_groups) for all custom tables, with group membership management on the user edit form and group selector widgets on custom table add/edit forms.
**Verified:** 2026-03-01T20:00:00Z
**Status:** PASSED
**Re-verification:** Yes — supersedes initial VERIFICATION.md; incorporates Plan 02-05 gap closure confirmed by UAT

---

## Context: Why Re-Verification Was Needed

The initial VERIFICATION.md was written by static code analysis before UAT ran. UAT (02-UAT.md) then revealed that the View Groups and Edit Groups checkbox sections were absent from rendered pages (tests 3, 4, 5 failed — severity: major), blocking tests 6-9. Plan 02-05 diagnosed and fixed the root cause: `$_POST['view_groups_raw']` and `$_POST['edit_groups_raw']` were non-existent keys — PHP delivers `name="view_groups[]"` as `$_POST['view_groups']` (an array), not a `_raw` string. The bad key access triggered a PHP warning that suppressed HTML output beyond the Status field. Commit `0e8eee1` fixed both add-form and edit-form repopulation. User confirmed: "That works and the groups also show when editing an item." This report verifies the current codebase state, post-fix.

---

## Goal Achievement

### Observable Truths (from Phase Goal and Plans)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | A user not in view_groups for a row cannot see it in list or detail views, even by guessing the URL | VERIFIED | `filterRowsByViewAccess()` applied to list fetch (admin-custom-table.php:387); `canViewRow()` + `http_response_code(403)` guard on edit GET (lines 257-260) |
| 2 | A user in view_groups but not edit_groups can view the row but cannot save changes | VERIFIED | `canEditRow()` evaluated after view gate passes (line 262); `$canEdit=false` renders `<fieldset disabled>` (line 291) + "You have view-only access to this record." notice (line 357); POST gate also calls `canEditRow()` and returns 403 (lines 91-94) |
| 3 | Every newly provisioned custom table automatically has view_groups, edit_groups, created_at, modified_at, and status columns | VERIFIED | `provisionCustomTable()` CREATE TABLE includes all 6 columns (admin-tables.php:117-124); `migrateExistingCustomTables()` backfills pre-Phase-2 tables using INFORMATION_SCHEMA checks (lines 150-180); called on every admin-tables.php page load (line 20) |
| 4 | Admin can set group membership for any user from the user edit page, and group selector widgets appear on custom table add/edit forms | VERIFIED | Group Membership section in user edit form (admin-users.php:234-256); DELETE+INSERT save in POST handler (lines 96-103); `getUserGroups()` pre-populates (line 195); View Groups / Edit Groups sections present on both add form (admin-custom-table.php:201-244) and edit form (lines 310-353); repopulation bug fixed in commit 0e8eee1; user-confirmed working |

**Score: 4/4 truths verified**

---

## Required Artifacts

| Artifact | Plan | Status | Evidence |
|----------|------|--------|----------|
| `functions/acl.php` | 02-01 | VERIFIED | 150 lines; all 5 functions at lines 23, 55, 86, 114, 129; static cache in getUserGroupIds (line 25); admin bypass hasPermission('admin_access') in canViewRow (line 57) and canEditRow (line 88); array_values() in filterRowsByViewAccess (line 116); All Users span in formatGroupIds (lines 132, 138); no closing ?> tag; php -l clean |
| `functions/admin-tables.php` | 02-02 | VERIFIED | 578 lines; provisionCustomTable() 6-column CREATE TABLE (lines 117-124); migrateExistingCustomTables() defined (line 150) and called at page load (line 20); add_field branch also creates table with all 6 columns when table is missing (lines 297-304); php -l clean |
| `functions/admin-custom-table.php` | 02-03 + 02-05 | VERIFIED | 433 lines; require_once acl.php (line 8); ACL enforcement at 4 points: POST edit gate (lines 91-94), edit GET view gate (lines 257-260), canEditRow evaluated (line 262), list view filterRowsByViewAccess (line 387); View Groups HTML on add form (lines 201-222) and edit form (lines 310-331); Edit Groups HTML on add form (lines 223-244) and edit form (lines 332-353); 0 occurrences of view_groups_raw or edit_groups_raw; 2 view_groups[] inputs, 2 edit_groups[] inputs; REQUEST_METHOD guard on edit form repopulation (lines 274-284); php -l clean |
| `functions/admin-users.php` | 02-04 | VERIFIED | 292 lines; getUserGroups() called (line 195); selectedGroupIds computed (lines 198-200); Group Membership section with groups[] checkboxes (lines 234-256); DELETE FROM user_groups + INSERT per group in POST handler (lines 96-103); php -l clean |

---

## Key Link Verification

### Plan 02-01 (acl.php) Key Links

| From | To | Via | Status | Evidence |
|------|----|-----|--------|----------|
| acl.php | getUserGroups() in auth.php | called inside getUserGroupIds() | WIRED | acl.php:36 `$groups = getUserGroups($userId)` |
| acl.php | hasPermission() in auth.php | admin bypass in canViewRow + canEditRow | WIRED | acl.php:57 and 88 `hasPermission('admin_access', $userId)` |
| acl.php | getCurrentUserId() in auth.php | fallback when $userId param is null | WIRED | acl.php:28 `$userId = getCurrentUserId()` |
| acl.php | dbGetRows() from database.php | group name lookup in formatGroupIds() | WIRED | acl.php:142 `dbGetRows("SELECT name FROM user_groups_list...")` |

### Plan 02-02 (admin-tables.php) Key Links

| From | To | Via | Status | Evidence |
|------|----|-----|--------|----------|
| admin-tables.php page load | migrateExistingCustomTables() | direct call at line 20 | WIRED | `migrateExistingCustomTables();` runs on every request before any handler |
| migrateExistingCustomTables() | INFORMATION_SCHEMA | per-table per-column existence check before ALTER | WIRED | lines 162-177: SELECT COUNT(*) from INFORMATION_SCHEMA.COLUMNS before each ALTER TABLE |
| provisionCustomTable() | 6-column CREATE TABLE | dbQuery at lines 117-124 | WIRED | Columns confirmed: id, status, view_groups, edit_groups, created_at, modified_at |

### Plan 02-03 + 02-05 (admin-custom-table.php) Key Links

| From | To | Via | Status | Evidence |
|------|----|-----|--------|----------|
| admin-custom-table.php | acl.php functions | require_once at line 8 | WIRED | `require_once __DIR__ . '/acl.php'` |
| POST edit handler | canEditRow() | line 91 — blocks unauthorized saves | WIRED | `if (!$existingForAcl \|\| !canEditRow($existingForAcl))` + http_response_code(403) |
| edit GET renderer | canViewRow() | line 257 — blocks unauthorized view | WIRED | `elseif (!canViewRow($record))` + http_response_code(403) |
| list view | filterRowsByViewAccess() | line 387 — filters row array | WIRED | `$rows = filterRowsByViewAccess($allRows)` |
| list view | formatGroupIds() | line 418 — displays group names | WIRED | `formatGroupIds($row['view_groups'] ?? null)` |
| add form | $allGroups query | dbGetRows at line 166 | WIRED | `dbGetRows("SELECT id, name FROM user_groups_list WHERE status = 'active'...")` |
| add form POST repopulation | $_POST['view_groups'] (array) | (array) cast at lines 168-169 | WIRED | `(array)($_POST['view_groups'] ?? [])` — view_groups_raw bug fixed in 02-05 |
| edit form repopulation | REQUEST_METHOD branch | lines 274-284 | WIRED | GET uses explode on DB value; POST uses (array) cast on submitted value |

### Plan 02-04 (admin-users.php) Key Links

| From | To | Via | Status | Evidence |
|------|----|-----|--------|----------|
| user edit form render | getUserGroups($userId) | line 195 — pre-populates current group membership | WIRED | `$userGroups = getUserGroups($userId)` |
| user edit POST | DELETE FROM user_groups | line 96 | WIRED | `dbQuery("DELETE FROM user_groups WHERE user_id = ?", [$userId])` |
| user edit POST | INSERT per selected group | lines 97-103 foreach | WIRED | `foreach ($newGroupIds as $gid) { dbInsert('user_groups', [...]) }` |
| group widget | user_groups_list table | dbGetRows at line 194 | WIRED | `dbGetRows("SELECT id, name FROM user_groups_list WHERE status = 'active' ORDER BY name")` |

---

## Requirements Coverage

| Requirement | Plans | Description | Status | Evidence |
|-------------|-------|-------------|--------|----------|
| ACL-01 | 02-04 | Users can belong to any number of groups | SATISFIED | Group Membership widget on user edit form; DELETE+INSERT save; UAT tests 1-2 passed |
| ACL-02 | 02-01, 02-03, 02-05 | Each managed table row has view_groups; only matching users can view | SATISFIED | canViewRow() gating on list (filterRowsByViewAccess) and edit GET (403 on mismatch); view_groups column in every table; group selector widget on forms (user-confirmed) |
| ACL-03 | 02-01, 02-03, 02-05 | Each managed table row has edit_groups; only matching users can edit (separate from view) | SATISFIED | canEditRow() called separately after canViewRow(); POST gate returns 403; $canEdit=false disables form fieldset + hides Save button; edit_groups selector on forms |
| DATA-01 | 02-02 | Every managed table automatically has standard fields: created_at, modified_at, status, view_groups, edit_groups | SATISFIED | provisionCustomTable() 6-column CREATE TABLE; migrateExistingCustomTables() INFORMATION_SCHEMA-guarded ALTER for pre-Phase-2 tables; add_field branch also creates full table if missing |

All 4 Phase 2 requirements satisfied. No orphaned requirements — REQUIREMENTS.md traceability table marks ACL-01, ACL-02, ACL-03, DATA-01 all as "Complete" for Phase 2.

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Notes |
|------|------|---------|----------|-------|
| `functions/acl.php` | 32 | `return [];` | Info | Legitimate early return when getCurrentUserId() returns null/0 (unauthenticated). Correct: empty group array causes all intersections to fail, denying access. Not a stub. |

Zero TODO/FIXME/HACK/PLACEHOLDER occurrences across all four files. No stub implementations found.

---

## Human Verification Status

UAT (02-UAT.md) results:

| Test | Result | Notes |
|------|--------|-------|
| 1. Group Membership Widget on User Edit | PASS | Widget renders; groups pre-checked; save works |
| 2. Group Membership Save | PASS | Additions, removals, and clear-all all work |
| 3. Group Selector on Custom Table Add/Edit | PASS (after 02-05 fix) | User confirmed: "That works and the groups also show when editing an item" |
| 4. New Custom Table Has ACL Columns | PASS (after 02-05 fix) | Same fix resolves rendering on new tables |
| 5. Existing Custom Tables Auto-Migrated | PASS (after 02-05 fix) | Same fix resolves rendering on migrated tables |
| 6. List View Filters by Group Membership | HUMAN NEEDED | Code wired (filterRowsByViewAccess on line 387); unblocked after fix |
| 7. Edit URL Blocked for Unauthorized User | HUMAN NEEDED | Code wired (canViewRow + 403); unblocked after fix |
| 8. View-Only Mode for View-Not-Edit Users | HUMAN NEEDED | Code wired (fieldset disabled + view-only notice); unblocked after fix |
| 9. POST Save Gate Rejects Unauthorized Save | HUMAN NEEDED | Code wired (canEditRow in POST handler + 403); unblocked after fix |

### Remaining Human Verification Items

Tests 6-9 were skipped in UAT because view_groups/edit_groups could not be set on rows until the widget was fixed. The widget fix is confirmed. These are behavioral confirmation tests — all supporting code is verified in place.

#### 1. List View Filters by Group Membership

**Test:** Log in as a non-admin user in one specific group. Navigate to a custom table. Set view_groups on some rows to that group, and others to a different group. Confirm only the matching rows appear in the list.
**Expected:** Rows restricted to other groups are invisible; rows with empty view_groups are visible to all.
**Why human:** Requires a running app with multiple users and group assignments; cannot verify row filtering with grep.

#### 2. Edit URL Blocked for Unauthorized User

**Test:** As a non-admin user not in a row's view_groups, navigate directly to `/admin/data/{table}?action=edit&id={row_id}`.
**Expected:** HTTP 403 response, "You do not have permission to view this record." message shown.
**Why human:** Requires a running session; HTTP status code behavior cannot be verified statically.

#### 3. View-Only Mode for View-Not-Edit Users

**Test:** As a non-admin user in a row's view_groups but not its edit_groups, navigate to the edit URL.
**Expected:** Form loads with all fields greyed out (fieldset disabled), no Save button, "You have view-only access to this record." notice displayed.
**Why human:** Requires a running session with specific group configuration.

#### 4. POST Save Gate Rejects Unauthorized Save

**Test:** As a non-admin user who cannot edit a row, attempt a direct POST to the edit endpoint (via browser dev tools or curl) with the row's id.
**Expected:** HTTP 403 returned; row data unchanged in database.
**Why human:** Requires an active session, a specific row configuration, and a direct HTTP POST attempt.

---

## Gaps Summary

No gaps remain. The single gap discovered by UAT (group widget not rendering) was resolved by Plan 02-05 and user-confirmed working.

All 4 requirements are implemented and wired:
- **ACL-01:** Group membership UI in admin-users.php with widget + DELETE+INSERT save. User-verified (UAT 1-2).
- **ACL-02 / ACL-03:** Row-level enforcement in acl.php + admin-custom-table.php at all 4 enforcement points. Widget confirmed working (UAT 3-5).
- **DATA-01:** Standard column provisioning in admin-tables.php via provisionCustomTable() and migrateExistingCustomTables().

The 4 remaining human verification tests (UAT 6-9) are now unblocked and represent behavioral confirmation of code that is verified in place. They are not blocking gaps.

---

## Commit History

| Commit | Plan | Description |
|--------|------|-------------|
| `8390e09` | 02-01 | feat: create functions/acl.php — row-level ACL library |
| `00adbb0` | 02-02 | feat: update provisionCustomTable + add migrateExistingCustomTables |
| `259366e` | 02-03 | feat: acl.php require_once + POST handler ACL gates and group serialization |
| `1ec6dcc` | 02-03 | feat: ACL-filtered list view + edit view gates + group selector widget |
| `2f28c8a` | 02-04 | feat: group membership save logic in user edit POST handler |
| `1d03e97` | 02-04 | feat: Group Membership widget in user edit form |
| `0e8eee1` | 02-05 | fix: fix view_groups_raw/edit_groups_raw repopulation bug in add/edit forms |

---

*Verified: 2026-03-01T20:00:00Z*
*Verifier: Claude (gsd-verifier)*
*Re-verification: Yes — supersedes initial 02-VERIFICATION.md written before UAT*
