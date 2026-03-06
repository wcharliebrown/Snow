# Roadmap: Snow

## Overview

Snow's foundation is already working: report system, custom table provisioning, and admin list views are shipped. The remaining work builds outward from security foundations (sessions, CSRF) through access control and data integrity into extensibility (hooks, 2FA) and finally user-facing UX polish (search, staged activation). Each phase completes a coherent capability that the next phase depends on — the order is not arbitrary, it is driven by the dependency graph.

## Phases

**Phase Numbering:**
- Integer phases (1, 2, 3): Planned milestone work
- Decimal phases (2.1, 2.2): Urgent insertions (marked with INSERTED)

Decimal phases appear between their surrounding integers in numeric order.

- [ ] **Phase 1: Security Foundations** - CSRF protection, DB-backed sessions, password policy, and structured activity logging
- [x] **Phase 2: Access Control** - Row-level view and edit ACL on all managed tables, standard columns enforced at provisioning (completed 2026-03-01)
- [x] **Phase 3: Data Integrity** - Row-level version history with rollback, table snapshots with diff and restore (completed 2026-03-06)
- [ ] **Phase 4: Extensibility** - File-based hook system, 2FA (email OTP), custom web pages, email templates
- [ ] **Phase 5: User Experience** - Search, sort, filter on all list views; staged row activation and deactivation

## Phase Details

### Phase 1: Security Foundations
**Goal**: The application's security baseline is in place — every POST form is CSRF-protected, sessions are stored in MySQL with proper fixation prevention, password policy is enforced, and all security events are queryable in the admin.
**Depends on**: Nothing (first phase)
**Requirements**: SEC-01, SEC-02, SEC-03, SEC-04
**Success Criteria** (what must be TRUE):
  1. Submitting any POST form without a valid CSRF token returns an error; the request is rejected
  2. Admin can view active sessions in the admin and force-logout any session
  3. Admin can configure minimum password length, maximum age, and reuse prevention; users are prompted to change expired or reused passwords
  4. Admin can query the activity log by event type (login, error, outbound email) and see structured entries with timestamps and user context
**Plans**: 6 plans

Plans:
- [x] 01-01-PLAN.md — DB schema migration (sessions, activity_log, password_policy, password_history tables)
- [ ] 01-02-PLAN.md — DB-backed session handler (SnowSessionHandler) + session fixation prevention
- [ ] 01-03-PLAN.md — Password policy functions + enforcement in auth.php and admin-users.php
- [ ] 01-04-PLAN.md — CSRF protection (csrf.php + central enforcement + form injection)
- [ ] 01-05-PLAN.md — DB activity logging (logging.php + admin-logs.php update)
- [ ] 01-06-PLAN.md — Admin UI: session viewer + password policy config form

### Phase 2: Access Control
**Goal**: Every managed table row has view and edit group controls; only users in matching groups can see or modify a row; the provisioner adds these controls to every new table automatically.
**Depends on**: Phase 1
**Requirements**: ACL-01, ACL-02, ACL-03, DATA-01
**Success Criteria** (what must be TRUE):
  1. A user not in the view_groups for a row cannot see that row in list or detail views, even by guessing the URL
  2. A user in view_groups but not edit_groups for a row can view it but cannot save changes
  3. Every newly provisioned custom table automatically has view_groups, edit_groups, created_at, modified_at, and status columns
  4. Admin can set group membership for any user from the user edit page
**Plans**: 6 plans

Plans:
- [x] 02-01-PLAN.md — DB schema: standard ACL columns + group membership junction
- [x] 02-02-PLAN.md — provisionCustomTable() + migrateExistingCustomTables() standard column enforcement
- [x] 02-03-PLAN.md — admin-custom-table.php: list ACL filter + edit view-only mode + POST gate
- [x] 02-04-PLAN.md — admin-users.php: group membership widget on user edit page
- [x] 02-05-PLAN.md — Fix view_groups_raw/edit_groups_raw phantom key bug in add/edit forms
- [ ] 02-06-PLAN.md — Gap closure: table_data_access permission; non-admin users can reach custom table data pages

### Phase 3: Data Integrity
**Goal**: Every write to a managed table is recorded with before/after state and author; admins can roll back any row to any prior version; full-table snapshots can be created, diffed against live data, and restored.
**Depends on**: Phase 2
**Requirements**: VER-01, VER-02, VER-03, VER-04, VER-05
**Success Criteria** (what must be TRUE):
  1. After editing a row, admin can view a version history showing who changed it, when, and what fields changed
  2. Admin can restore any row to an older version with a single action; the current state is preserved as a new version entry
  3. Admin can create a named snapshot of any custom table that captures all current row data
  4. Admin can view a diff between a snapshot and the current table state showing added, removed, and changed rows
  5. Admin can restore a table to the state of any prior snapshot
**Plans**: 6 plans

Plans:
- [ ] 03-01-PLAN.md — Test scaffold: test_data_integrity.php stubs for VER-01 through VER-05
- [ ] 03-02-PLAN.md — DB migration: row_versions table + snapshot_table column on snapshots
- [ ] 03-03-PLAN.md — Row version capture in admin-custom-table.php edit POST handler (VER-01)
- [ ] 03-04-PLAN.md — Snapshot create fix (CREATE TABLE AS SELECT) + diff view (VER-03, VER-04)
- [ ] 03-05-PLAN.md — Row diff/revert UI on edit form + revert POST handler (VER-02)
- [ ] 03-06-PLAN.md — Snapshot restore: confirmation page + atomic rename sequence (VER-05)

### Phase 4: Extensibility
**Goal**: Admins can register PHP hook files that fire on row events for any table; users can complete login with an email OTP as a second factor; admins can create custom web pages and email templates served by the framework.
**Depends on**: Phase 3
**Requirements**: EXT-01, EXT-02, EXT-03, SEC-05
**Success Criteria** (what must be TRUE):
  1. Admin can register a hook PHP file for any table event (before_create, after_create, before_update, after_update); the file executes automatically when that event fires
  2. After entering correct credentials, a user receives a 6-digit code by email and cannot complete login until the correct code is entered
  3. Admin can create a custom web page with a URL path and PHP/HTML content that is served by the framework
  4. Admin can create an email template with named variables; sending code can call the template by name with variable values to produce a rendered outbound email
**Plans**: 5 plans

Plans:
- [ ] 04-01-PLAN.md — Test scaffold: test_extensibility.php stubs for EXT-01, SEC-05, EXT-02, EXT-03
- [ ] 04-02-PLAN.md — DB migration: pre_edit_php_filename, post_edit_php_filename, require_2fa, login_otp table, login_otp email template seed
- [ ] 04-03-PLAN.md — EXT-01: Hook execution in admin-custom-table.php + hook filename fields in admin-tables.php
- [ ] 04-04-PLAN.md — SEC-05: 2FA login fork in login.php + login-otp.php OTP entry page + require_2fa checkbox in admin-users.php
- [ ] 04-05-PLAN.md — EXT-02/EXT-03: Verify admin-pages.php and admin-emails.php; human verification checkpoint

### Phase 5: User Experience
**Goal**: Admins can search, sort, and filter records on any managed table list view; any row can be scheduled for automatic activation, deactivation, or deletion on a future date.
**Depends on**: Phase 4
**Requirements**: DATA-02, DATA-03, DATA-04, EXT-04
**Success Criteria** (what must be TRUE):
  1. On any managed table list view, user can type a search term, click column headers to sort, and apply dropdown filters; results update without writing any per-table code
  2. Admin can customize the visible columns and filter controls for each table's list view from the admin UI
  3. Admin can customize the field layout of the edit form for any custom table
  4. Admin can set an activate_at or deactivate_at date on any row; the system automatically changes the row's status on that date without manual intervention
**Plans**: TBD

## Progress

**Execution Order:**
Phases execute in numeric order: 1 → 2 → 3 → 4 → 5

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Security Foundations | 4/6 | In Progress|  |
| 2. Access Control | 6/6 | Complete   | 2026-03-01 |
| 3. Data Integrity | 6/6 | Complete   | 2026-03-06 |
| 4. Extensibility | 4/5 | In Progress|  |
| 5. User Experience | 0/TBD | Not started | - |

---
*Roadmap created: 2026-02-27*
*Coverage: 21/21 v1 requirements mapped*
