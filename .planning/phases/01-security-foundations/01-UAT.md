---
status: complete
phase: 01-security-foundations
source: 01-01-SUMMARY.md, 01-02-SUMMARY.md, 01-03-SUMMARY.md, 01-04-SUMMARY.md, 01-05-SUMMARY.md, 01-06-SUMMARY.md
started: 2026-02-28T00:00:00Z
updated: 2026-03-01T00:00:00Z
---

## Current Test
<!-- OVERWRITE each test - shows where we are -->

[testing complete]

## Tests

### 1. Login still works
expected: Navigate to login page, enter valid credentials, submit. Should land on admin dashboard with no errors. Session is now stored in MySQL instead of /tmp.
result: pass

### 2. CSRF token in login form
expected: On the login page, view page source (Ctrl+U). Search for "csrf_token". You should find a hidden input like `<input type="hidden" name="csrf_token" value="...64-char hex...">` inside the login form.
result: pass

### 3. Admin Sessions page loads
expected: Navigate to /admin/sessions (or find "Sessions" in the admin sidebar). The page should load and show a table listing active sessions — including at least your own current session with your username, IP address, browser info, and expiry time.
result: pass

### 4. Force-logout works
expected: On the /admin/sessions page, if there is another session visible (or open a second browser/incognito tab and log in again), click the Force Logout button next to it. The session row should disappear from the list. If you check in the other browser, it should be logged out on next page load.
result: pass

### 5. Password too short is rejected
expected: Go to the admin Users page and try to create a new user (or edit an existing user's password) with a password shorter than 8 characters (e.g., "abc"). The form should reject it with a validation error — something like "Password must be at least 8 characters". The user should NOT be created/updated.
result: pass

### 6. Admin Password Policy page loads and saves
expected: Navigate to /admin/password-policy. The page should show a form with three fields: Minimum Length, Maximum Age (days), and Prevent Reuse Count — pre-filled with current values (defaults: 8, 0, 0). Change the minimum length to 10, save. Reload the page — it should still show 10.
result: pass

### 7. Admin Logs page shows structured entries
expected: Navigate to /admin/logs. The log table should show entries from your recent login activity — with columns for Timestamp, Level (badge like INFO/ERROR), Event Type, User (name or "System"), IP Address, and Message. At minimum you should see login-related INFO entries.
result: pass

### 8. Activity log filter works
expected: On the /admin/logs page, use the filter dropdown to select "ERROR" and submit/filter. The table should reload showing only ERROR-level entries (or empty if none). Switch to "INFO" — should show INFO entries. Switching back to "All" should show all entries again.
result: pass

### 9. CSRF enforcement — invalid token blocked
expected: This tests the 403 gate. Open browser dev tools (Network tab). Submit any admin form (e.g., the password policy form). In the network tab, find the POST request and look at the form data — you should see the csrf_token field. If you manually craft a request without it (e.g., via curl: `curl -X POST https://yoursite/admin/password-policy -d "min_length=5"`), it should return HTTP 403. Alternatively: right-click the form, use browser inspector to delete the hidden csrf_token input, then submit — should get a 403 error page.
result: pass

## Summary

total: 9
passed: 9
issues: 0
pending: 0
skipped: 0

## Gaps

[none yet]
