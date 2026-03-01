---
status: diagnosed
phase: 02-access-control
source: 02-01-SUMMARY.md, 02-02-SUMMARY.md, 02-03-SUMMARY.md, 02-04-SUMMARY.md, 02-05-SUMMARY.md
started: 2026-03-01T17:45:00Z
updated: 2026-03-01T20:00:00Z
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
result: pass
note: Re-verified after plan 02-05 fix — confirmed by user during execute-phase checkpoint

### 4. New Custom Table Has ACL Columns
expected: Provision a new custom table via /admin/tables (add a new table or add a field). When you open the add/edit form for that table, it includes the Status, View Groups, and Edit Groups fields from the start — no manual migration needed.
result: pass
note: Re-verified after plan 02-05 fix — confirmed by user during execute-phase checkpoint

### 5. Existing Custom Tables Auto-Migrated
expected: Open a custom table that was created before Phase 2. The add/edit form for rows in that table now shows Status, View Groups, and Edit Groups fields — the migration ran automatically on page load and added those columns.
result: pass
note: Re-verified after plan 02-05 fix — confirmed by user during execute-phase checkpoint

### 6. List View Filters by Group Membership
expected: Log in as a non-admin user who belongs to a specific group. Navigate to a custom table list. Only rows where that user's group is listed in view_groups (or view_groups is NULL/empty = open to all) appear in the list. Rows restricted to other groups are invisible.
result: issue
reported: "When I log in as a non-admin, I get sent to /admin and get an 'Access Denied' message. If I go directly to the edit of an item in testlist1 I get refused that as well. The items show group permissions but the custom table itself doesn't have a way to modify. Perhaps the custom table permissions are still too restrictive but I don't have a way to modify."
severity: major

### 7. Edit URL Blocked for Unauthorized User
expected: Attempt to open the edit URL for a custom table row that has view_groups set to a group you do not belong to (as a non-admin user). Instead of the edit form, you receive a 403 error message — the row is not accessible.
result: skipped
reason: Blocked by same issue as test 6 — non-admin users can't reach custom table data pages at all

### 8. View-Only Mode for View-Not-Edit Users
expected: As a non-admin user who can view a row but is not in its edit_groups, navigate to the edit URL. The form loads but all fields are disabled (greyed out, not editable). There is no Save button and no Delete option — only a notice indicating the record is view-only.
result: skipped
reason: Blocked by same issue as test 6 — non-admin users can't reach custom table data pages at all

### 9. POST Save Gate Rejects Unauthorized Save
expected: As a non-admin user who cannot edit a row, attempt to POST a save (e.g., via browser dev tools or curl). The server returns a 403 response and the record is unchanged in the database — the gate blocks even direct HTTP POST attempts.
result: skipped
reason: Blocked by same issue as test 6 — non-admin users can't reach custom table data pages at all

## Summary

total: 9
passed: 5
issues: 1
pending: 0
skipped: 3

## Gaps

- truth: "Non-admin users with group membership can access custom table data pages and see only rows matching their groups"
  status: failed
  reason: "User reported: non-admin users get Access Denied on /admin and when navigating directly to custom table edit URLs. The table_management permission gate in admin-custom-table.php blocks all non-admin access, making row-level ACL filtering non-functional for its intended audience."
  severity: major
  test: 6
  root_cause: "Two compounding gates both require table_management: (1) provisionCustomTable() registers admin/data/{table} pages with required_permission='table_management' in the pages table — the framework checks this before the script loads; (2) admin-custom-table.php line 10 has requirePermission('table_management') as an inner guard. No lower-level permission exists. Non-admin users hit both gates and never reach the ACL filtering logic."
  artifacts:
    - path: "functions/admin-tables.php"
      issue: "provisionCustomTable() line 135: savePage() sets required_permission='table_management'; already-registered pages in DB are not updated on re-provision (skipped if page exists)"
    - path: "functions/admin-custom-table.php"
      issue: "line 10: requirePermission('table_management') inner gate — must match the page-level permission"
    - path: "database_schema.sql"
      issue: "No table_data_access permission exists in seed data; only table_management is defined for this purpose"
  missing:
    - "New table_data_access permission added to permissions table and assigned to appropriate groups"
    - "provisionCustomTable() updated to use table_data_access for admin/data pages"
    - "admin-custom-table.php line 10 gate changed to table_data_access"
    - "Migration UPDATE for already-provisioned pages in the pages table"
    - "Group selector widget (View Groups/Edit Groups) gated on hasPermission('table_management') so data-access users can't set ACL config"
  debug_session: ""
