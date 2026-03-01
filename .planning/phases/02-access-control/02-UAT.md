---
status: complete
phase: 02-access-control
source: 02-01-SUMMARY.md, 02-02-SUMMARY.md, 02-03-SUMMARY.md, 02-04-SUMMARY.md
started: 2026-03-01T17:45:00Z
updated: 2026-03-01T18:10:00Z
---

## Current Test

[testing complete]

## Tests

### 1. Group Membership Widget on User Edit
expected: Open any user's edit page (/admin/users/edit/{id}). A "Group Membership" section appears below the other fields, showing one checkbox per active group. Groups the user already belongs to are pre-checked. If no groups exist yet, a fallback message appears with a link to /admin/groups.
result: pass

### 2. Group Membership Save
expected: On the user edit form, check or uncheck some group checkboxes and save. Navigate away and return to the same user's edit page. The checkboxes reflect the groups you selected — additions are checked, removals are unchecked. Clearing all checkboxes removes the user from all groups.
result: pass

### 3. Group Selector on Custom Table Add/Edit Forms
expected: Open a custom table's add page or edit an existing row. The form includes a "Status" select field plus "View Groups" and "Edit Groups" sections — each showing one checkbox per active group. These fields control who can see/edit the row.
result: issue
reported: "The Status field shows but the 'View Groups' and 'Edit Groups' are missing"
severity: major

### 4. New Custom Table Has ACL Columns
expected: Provision a new custom table via /admin/tables (add a new table or add a field). When you open the add/edit form for that table, it includes the Status, View Groups, and Edit Groups fields from the start — no manual migration needed.
result: issue
reported: "View Groups, and Edit Groups fields are missing"
severity: major

### 5. Existing Custom Tables Auto-Migrated
expected: Open a custom table that was created before Phase 2. The add/edit form for rows in that table now shows Status, View Groups, and Edit Groups fields — the migration ran automatically on page load and added those columns.
result: issue
reported: "same, View Groups and Edit Groups are missing"
severity: major

### 6. List View Filters by Group Membership
expected: Log in as a non-admin user who belongs to a specific group. Navigate to a custom table. Only rows where that user's group is listed in view_groups (or view_groups is empty/NULL = open to all) appear in the list. Rows restricted to other groups are invisible.
result: skipped
reason: Depends on group selector widget (tests 3-5 bug) — view_groups can't be set on rows until fixed

### 7. Edit URL Blocked for Unauthorized User
expected: Attempt to open the edit URL for a custom table row that has view_groups set to a group you do not belong to (as a non-admin user). Instead of the edit form, you receive a 403 error message — the row is not accessible.
result: skipped
reason: Depends on group selector widget (tests 3-5 bug) — view_groups can't be set on rows until fixed

### 8. View-Only Mode for View-Not-Edit Users
expected: As a non-admin user who can view a row but is not in its edit_groups, navigate to the edit URL. The form loads but all fields are disabled (greyed out, not editable). There is no Save button and no Delete option — only a notice indicating the record is view-only.
result: skipped
reason: Depends on group selector widget (tests 3-5 bug) — edit_groups can't be set on rows until fixed

### 9. POST Save Gate Rejects Unauthorized Save
expected: As a non-admin user who cannot edit a row, attempt to POST a save (e.g., via browser dev tools or curl). The server returns a 403 response and the record is unchanged in the database — the gate blocks even direct HTTP POST attempts.
result: skipped
reason: Depends on group selector widget (tests 3-5 bug) — edit_groups can't be set on rows until fixed

## Summary

total: 9
passed: 2
issues: 3
pending: 0
skipped: 4

## Gaps

- truth: "Add/edit forms for custom table rows include View Groups and Edit Groups checkbox sections"
  status: failed
  reason: "User reported: The Status field shows but the 'View Groups' and 'Edit Groups' are missing"
  severity: major
  test: 3
  root_cause: ""
  artifacts: []
  missing: []
  debug_session: ""

- truth: "Add/edit forms for existing (pre-Phase-2) custom table rows include View Groups and Edit Groups checkbox sections after auto-migration"
  status: failed
  reason: "User reported: same, View Groups and Edit Groups are missing"
  severity: major
  test: 5
  root_cause: ""
  artifacts: []
  missing: []
  debug_session: ""

- truth: "Add/edit forms for new custom table rows include View Groups and Edit Groups checkbox sections"
  status: failed
  reason: "User reported: View Groups, and Edit Groups fields are missing"
  severity: major
  test: 4
  root_cause: ""
  artifacts: []
  missing: []
  debug_session: ""
