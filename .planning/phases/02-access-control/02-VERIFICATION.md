---
phase: 02-access-control
verified: 2026-03-01T18:00:00Z
status: passed
score: 4/4 must-haves verified
re_verification: false
---

# Phase 2: Access Control Verification Report

**Phase Goal:** Every managed table row has view and edit group controls; only users in matching groups can see or modify a row; the provisioner adds these controls to every new table automatically.
**Verified:** 2026-03-01
**Status:** PASSED
**Re-verification:** No — initial verification

---

## Goal Achievement

### Observable Truths (from ROADMAP.md Success Criteria)

| # | Truth | Status | Evidence |
|---|-------|--------|----------|
| 1 | A user not in view_groups for a row cannot see it in list or detail views, even by guessing the URL | VERIFIED | `filterRowsByViewAccess()` applied to list fetch (admin-custom-table.php:379); `canViewRow()` + `http_response_code(403)` guard on edit GET (line 257-260) |
| 2 | A user in view_groups but not edit_groups can view the row but cannot save changes | VERIFIED | `canEditRow()` checked after `canViewRow()` passes (line 262); `$canEdit=false` → `<fieldset disabled>` + view-only notice (lines 283, 347-349); POST gate also checks `canEditRow()` and returns 403 (lines 90-95) |
| 3 | Every newly provisioned custom table automatically has view_groups, edit_groups, created_at, modified_at, and status columns | VERIFIED | `provisionCustomTable()` CREATE TABLE includes all 6 columns (admin-tables.php:117-124); `migrateExistingCustomTables()` backfills pre-Phase-2 tables (lines 150-180); called on every admin-tables.php page load (line 20) |
| 4 | Admin can set group membership for any user from the user edit page | VERIFIED | Group Membership checkbox widget rendered in edit form (admin-users.php:234-256); DELETE+INSERT save logic in POST handler (lines 95-103); `getUserGroups()` pre-populates current selections (line 195) |

**Score: 4/4 truths verified**

---

## Required Artifacts

| Artifact | Provided By | Status | Details |
|----------|------------|--------|---------|
| `functions/acl.php` | Plan 02-01 | VERIFIED | 151 lines; all 5 functions present; no closing `?>` tag; no syntax errors |
| `functions/admin-tables.php` | Plan 02-02 | VERIFIED | `provisionCustomTable()` 6-column CREATE TABLE; `migrateExistingCustomTables()` defined and called; `updated_at` absent from CREATE statements (only in a docblock comment) |
| `database_schema.sql` | Plan 02-02 | VERIFIED | `grep -c "Phase 2: Access Control"` returns 1; all 5 standard columns documented |
| `functions/admin-custom-table.php` | Plan 02-03 | VERIFIED | ACL enforcement at all 4 points; `renderReport()` not called in list view; group selector widget on add+edit forms |
| `functions/admin-users.php` | Plan 02-04 | VERIFIED | Group Membership section present; DELETE+INSERT save logic wired; `getUserGroups()` used |

---

## Key Link Verification

### Plan 02-01 (acl.php) Key Links

| From | To | Via | Status | Detail |
|------|----|-----|--------|--------|
| acl.php | auth.php `getUserGroups()` | called in `getUserGroupIds()` line 36 | WIRED | `$groups = getUserGroups($userId)` |
| acl.php | auth.php `hasPermission()` | called in `canViewRow()` and `canEditRow()` | WIRED | `hasPermission('admin_access', $userId)` at top of both functions — 2 occurrences confirmed |
| acl.php | auth.php `getCurrentUserId()` | called in `getUserGroupIds()` line 28 | WIRED | `$userId = getCurrentUserId()` |
| acl.php | database.php `dbGetRows()` | called in `formatGroupIds()` line 142 | WIRED | `dbGetRows("SELECT name FROM user_groups_list ...")` |

### Plan 02-02 (admin-tables.php) Key Links

| From | To | Via | Status | Detail |
|------|----|-----|--------|--------|
| provisionCustomTable() | POST handler (add table) | direct call line 221 | WIRED | `provisionCustomTable(['table_name' => $tableName, ...])` |
| migrateExistingCustomTables() | page load | call at line 20 (before POST handler) | WIRED | Called after `requirePermission('table_management')` |
| migrateExistingCustomTables() | custom_tables table | `dbGetRows("SELECT table_name FROM custom_tables ...")` | WIRED | Reads all active tables; INFORMATION_SCHEMA guards each ALTER |

### Plan 02-03 (admin-custom-table.php) Key Links

| From | To | Via | Status | Detail |
|------|----|-----|--------|--------|
| admin-custom-table.php | acl.php | `require_once __DIR__ . '/acl.php'` line 8 | WIRED | Confirmed; count = 1 |
| list view | filterRowsByViewAccess() | line 379: `$rows = filterRowsByViewAccess($allRows)` | WIRED | `$visibleCount = count($rows)` uses post-filter count |
| edit GET | canViewRow() | lines 257-260 | WIRED | 403 + error message on denial |
| edit GET | canEditRow() | line 262 | WIRED | `$canEdit` flag controls fieldset and Save button |
| edit POST | canEditRow() | lines 90-95 | WIRED | Fetches existing row, 403 if denied, sets `$hasError` |
| add/edit forms | view_groups[]/edit_groups[] | checkbox inputs + POST serialization | WIRED | NULL stored in DB when no boxes checked (lines 76-77, 114-115) |

### Plan 02-04 (admin-users.php) Key Links

| From | To | Via | Status | Detail |
|------|----|-----|--------|--------|
| edit form render | getUserGroups($userId) | line 195 | WIRED | Pre-populates `$currentGroupIds` |
| edit form render | user_groups_list | `dbGetRows("SELECT id, name FROM user_groups_list ...")` line 194 | WIRED | All active groups loaded for widget |
| edit POST handler | user_groups table | DELETE + foreach INSERT (lines 96-103) | WIRED | `DELETE FROM user_groups WHERE user_id = ?` then `dbInsert('user_groups', ...)` per selection |

---

## Requirements Coverage

| Requirement | Source Plan | Description | Status | Evidence |
|-------------|------------|-------------|--------|----------|
| ACL-01 | 02-04 | Users can belong to any number of groups | SATISFIED | Group Membership widget in user edit form; DELETE+INSERT save logic; `getUserGroups()` populates pre-checked state |
| ACL-02 | 02-01, 02-03 | Each managed table row has view_groups; only users in matching groups can view | SATISFIED | `canViewRow()` in acl.php with admin bypass, NULL=open, group intersection; `filterRowsByViewAccess()` in list; 403 gate in edit GET |
| ACL-03 | 02-01, 02-03 | Each managed table row has edit_groups; only users in matching groups can edit | SATISFIED | `canEditRow()` in acl.php mirroring canViewRow semantics; fieldset[disabled] + no Save for view-only; POST gate with 403 |
| DATA-01 | 02-02 | Every managed table automatically has created_at, modified_at, status, view_groups, edit_groups | SATISFIED | `provisionCustomTable()` 6-column CREATE TABLE; `migrateExistingCustomTables()` backfills pre-Phase-2 tables; documented in database_schema.sql |

All 4 Phase 2 requirements are satisfied. No orphaned requirements detected — every requirement mapped to this phase in REQUIREMENTS.md appears in at least one plan's `requirements` frontmatter field.

---

## Anti-Patterns Found

| File | Line | Pattern | Severity | Impact |
|------|------|---------|----------|--------|
| `functions/acl.php` | 32 | `return [];` | Info | Legitimate early return when no authenticated user — not a stub. Correct behavior for unauthenticated state. |

No blockers or warnings found. The `return [];` is intentional: when `getCurrentUserId()` returns null/0 (unauthenticated), returning an empty group ID array correctly causes all group intersection checks to fail (deny). The `placeholder=` attributes found by the grep in admin-tables.php are HTML input placeholder text, not code stubs.

---

## Human Verification Required

The following behaviors require a running environment to confirm end-to-end:

### 1. List view ACL filtering — live session

**Test:** Log in as a non-admin user who belongs to Group A. Navigate to a custom table list view where some rows have `view_groups = NULL` (open) and some have `view_groups` set to Group B only.
**Expected:** Only the NULL (open) rows appear in the list. The Group B rows are invisibly excluded — no error, no indication they exist.
**Why human:** Requires live DB rows, active session, and group membership that the codebase itself cannot simulate.

### 2. View-only read-only form rendering

**Test:** Log in as a user in a row's `view_groups` but NOT in its `edit_groups`. Navigate to that row's edit URL directly (e.g. `?action=edit&id=5`).
**Expected:** The edit form loads with all fields disabled (`<fieldset disabled>`), a yellow alert reads "You have view-only access to this record.", and no Save button or Delete form appears.
**Why human:** The `$canEdit` flag logic controls HTML output that cannot be verified by static grep alone.

### 3. Unauthorized direct URL access (403 gate)

**Test:** Log in as a user NOT in a row's `view_groups`. Manually navigate to `?action=edit&id=N` for that row.
**Expected:** HTTP 403 status and an error message "You do not have permission to view this record." — no form is rendered.
**Why human:** HTTP response code setting via `http_response_code(403)` requires live HTTP headers to confirm.

### 4. POST 403 rejection — unauthorized save attempt

**Test:** As a view-only user (in view_groups, not edit_groups), craft a POST to the edit URL (or use browser devtools to re-enable the disabled fieldset).
**Expected:** The server rejects the POST with 403 and displays the permission error; the DB row is unchanged.
**Why human:** The POST gate logic is in code but the actual rejection behavior needs a live request to confirm the 403 header is returned and the DB is untouched.

### 5. Group membership save roundtrip

**Test:** Navigate to a user's edit page. Add them to a group by checking a checkbox. Save. Navigate back to their edit page.
**Expected:** The group checkbox is still checked (DB persisted). Remove the group and save again. Verify it's unchecked on reload.
**Why human:** Requires live DB to confirm DELETE+INSERT roundtrip.

### 6. New table provisioning — standard columns

**Test:** Create a new custom table via admin/tables. Then inspect the MySQL schema for that table (via admin or phpMyAdmin).
**Expected:** The table has exactly: `id`, `status`, `view_groups`, `edit_groups`, `created_at`, `modified_at` columns (no `updated_at`).
**Why human:** Requires live MySQL to run SHOW COLUMNS.

---

## Detailed Artifact Analysis

### functions/acl.php

- **Exists:** Yes (151 lines)
- **Substantive:** Yes — full implementations of all 5 functions; no stubs
- **Wired:** Yes — `require_once __DIR__ . '/acl.php'` in admin-custom-table.php (line 8); all 5 functions called from admin-custom-table.php
- **PSR compliance:** No closing `?>` tag (correct)
- **Key behaviors confirmed in code:**
  - Static `$cache[]` in `getUserGroupIds()` — one DB round-trip per user per request
  - Admin bypass (`hasPermission('admin_access')`) checked first in both `canViewRow` and `canEditRow` — 2 occurrences confirmed
  - NULL/empty `view_groups`/`edit_groups` returns `true` (open to all)
  - `filterRowsByViewAccess()` wraps in `array_values()` for sequential keys
  - `formatGroupIds()` returns `<span class="text-muted">All Users</span>` for null/empty

### functions/admin-tables.php

- **Exists:** Yes (578 lines)
- **Substantive:** Yes
- **Key behaviors confirmed:**
  - `provisionCustomTable()` CREATE TABLE: 6 columns (id, status, view_groups, edit_groups, created_at, modified_at) — no `updated_at`
  - Fallback CREATE TABLE in `add_field` handler also uses 6-column definition (lines 297-304)
  - `migrateExistingCustomTables()`: defined and called on page load (line 20); uses INFORMATION_SCHEMA idempotent checks
  - `updated_at` appears only in a docblock comment (line 147) — not in any SQL statement
  - `modified_at` count = 3 (provisionCustomTable, fallback CREATE TABLE, migrateExistingCustomTables standardCols)

### functions/admin-custom-table.php

- **Exists:** Yes (425 lines)
- **Substantive:** Yes
- **Key behaviors confirmed:**
  - `require_once __DIR__ . '/acl.php'` at line 8 (before any ACL call)
  - List view: `filterRowsByViewAccess($allRows)` at line 379; visible count uses `count($rows)` post-filter
  - `renderReport()` count = 0 in this file (list view renders directly)
  - Edit GET: `canViewRow()` count = 1; `canEditRow()` count = 2 (GET + POST)
  - `http_response_code(403)` count = 2 (view gate + POST gate)
  - `fieldset disabled` count = 1 (view-only form wrapping)
  - `view_groups[]` count = 2 (add form + edit form)
  - `edit_groups[]` count = 2 (add form + edit form)
  - NULL stored for empty group selection: `!empty($viewGroupIds) ? implode(',', ...) : null` in both add and edit branches
  - `hasError` flow in edit POST: ACL denial sets `$hasError = true` before `if (!isset($hasError)) { $hasError = false; }` — correctly preserves the denial state and skips `dbUpdate`

### functions/admin-users.php

- **Exists:** Yes (292 lines)
- **Substantive:** Yes
- **Key behaviors confirmed:**
  - `DELETE FROM user_groups WHERE user_id = ?` count = 1 (before INSERT loop)
  - `getUserGroups($userId)` count = 1 (pre-populates `$currentGroupIds`)
  - `groups[]` count = 1 (checkbox name attribute)
  - `Group Membership` string count = 1 (section heading)
  - Group widget inside `<form>` element (before submit button div)
  - `$selectedGroupIds` uses `$_POST['groups']` on validation failure to preserve user input

### database_schema.sql

- **Exists:** Yes
- **Phase 2 section:** Present (`grep -c "Phase 2: Access Control"` returns 1)
- **Documents:** All 5 standard columns (status, view_groups, edit_groups, created_at, modified_at) with NULL semantics and the `updated_at` legacy coexistence note

---

## Commit Verification

All commits referenced in SUMMARY files exist in git history:

| Commit | Plan | Description |
|--------|------|-------------|
| `8390e09` | 02-01 | feat: create functions/acl.php |
| `00adbb0` | 02-02 | feat: update provisionCustomTable + add migrateExistingCustomTables |
| `fef29e1` | 02-02 | docs: Phase 2 standard columns in database_schema.sql |
| `259366e` | 02-03 | feat: acl.php require_once + POST handler ACL gates |
| `1ec6dcc` | 02-03 | feat: ACL-filtered list view + edit gates + group selector widget |
| `2f28c8a` | 02-04 | feat: group membership save logic in user edit POST handler |
| `1d03e97` | 02-04 | feat: Group Membership widget in user edit form |

---

## Overall Assessment

Phase 2 goal is achieved. The codebase — not just the summaries — contains working implementations of:

1. A complete ACL library (`functions/acl.php`) with correct semantics: admin bypass, NULL=open, group intersection
2. Standard column provisioning for all custom tables, new and existing
3. Four-point ACL enforcement in the primary CRUD handler (list filter, GET view gate, GET edit gate, POST save gate)
4. Group membership management in the user admin UI

No stubs, no placeholder implementations, no broken wiring was found. All four Phase 2 requirements (ACL-01, ACL-02, ACL-03, DATA-01) have implementation evidence in the actual files.

---

_Verified: 2026-03-01_
_Verifier: Claude (gsd-verifier)_
