# PITFALLS — Zero-Dependency PHP LAMP Admin Framework

**Research type:** Project Research — Pitfalls dimension
**Date:** 2026-02-27
**Scope:** Row-level ACL, versioning/rollback, DB sessions, 2FA, CSRF, hooks, search/filter, staged activation, password policy, email templates, logging

---

## Overview

This document captures the critical mistakes that zero-dependency PHP LAMP admin framework projects commonly make. Each pitfall includes: the specific failure mode, warning signs to detect it early, a prevention strategy, and the phase in which it must be addressed. Security pitfalls are listed first.

---

## SECURITY PITFALLS

---

### PITFALL-S01: ACL Checked at Display Layer, Not Data Layer

**Domain:** Row-level ACL
**Severity:** Critical

**What goes wrong:**
Developers check group membership and row ownership only when rendering the UI (hiding buttons, suppressing rows in a list view). The underlying data-fetch queries do not enforce the same constraints. A crafted direct URL or API call to `admin.php?action=edit&id=42` bypasses every UI gate and returns or mutates a row the user has no permission to touch.

**Warning signs:**
- ACL logic lives in view templates or report files rather than in a central data-access layer
- `WHERE` clauses in queries do not include an ownership or group filter — they rely on a prior `if` check
- Edit/delete actions accept a raw `id` from `$_GET`/`$_POST` and fetch the record before validating permission on the fetched record
- "Insecure Direct Object Reference" (IDOR) is possible: incrementing the `id` parameter returns another user's row

**Prevention strategy:**
1. Enforce ACL at the query level: every SELECT/UPDATE/DELETE that operates on a user-owned or group-scoped row must include a `WHERE owner_group IN (...)` or equivalent join that is built from the authenticated session, never from user input.
2. Create a single `acl_assert_row($table, $id, $action)` function that re-fetches the row's ownership and checks it against the session before any mutation. Throw or redirect on failure — never just return false silently.
3. Write a test fixture that logs in as Group B and attempts to edit a row owned by Group A via direct URL. This test must fail at the data layer, not the UI layer.

**Phase:** Must be addressed in the ACL foundation phase, before any edit/delete routes are exposed.

---

### PITFALL-S02: Group Inheritance Creates Permission Escalation Paths

**Domain:** Row-level ACL
**Severity:** Critical

**What goes wrong:**
When groups can be nested (Group A is a member of Group B), the permission evaluation logic uses a shallow check: "is the user in Group X?" without traversing the full inheritance tree. Alternatively, the traversal is done recursively in PHP without cycle detection, causing infinite loops when a misconfigured group references itself or its ancestor.

**Warning signs:**
- Group membership is stored as a flat `user_group_id` column with no parent/child table
- Permission checks do a single-level `IN (group_ids)` with no transitive closure
- No cycle-detection guard exists in the group-resolution code
- Admin UI allows creating circular group references

**Prevention strategy:**
1. Decide at design time whether group inheritance is in scope. If not, enforce single-group membership and document the constraint explicitly.
2. If inheritance is required, compute the transitive closure of a user's groups once at login and store the resolved group list in the session. Do not re-traverse at every permission check.
3. Add a `CHECK CONSTRAINT` or application-level guard preventing a group from being its own parent. Validate the full ancestry chain on group save.
4. Cap traversal depth at a small constant (e.g., 5) and log a warning if it is hit.

**Phase:** ACL schema design phase. Hard to retrofit safely.

---

### PITFALL-S03: Session Fixation and Insufficient Session Regeneration

**Domain:** DB Sessions
**Severity:** Critical

**What goes wrong:**
PHP's `session_start()` will reuse a session ID supplied by the client if the server hasn't explicitly called `session_regenerate_id()`. An attacker who can plant a known session ID (via a link, XSS, or subdomain cookie) into a victim's browser before login will own the session after the victim authenticates — because the session ID never changed. When sessions are stored in a database table, the old session ID row persists alongside the new one, leaking authenticated state.

**Warning signs:**
- `session_regenerate_id(true)` is not called immediately after successful credential verification
- The DB sessions table still contains the pre-login session row after login
- Session cookies lack the `HttpOnly`, `Secure`, and `SameSite=Strict` (or `Lax`) attributes
- Session ID is logged in application logs (log sanitization absent)

**Prevention strategy:**
1. Call `session_regenerate_id(true)` (the `true` deletes the old session) immediately after any privilege change: login, logout, privilege elevation, 2FA confirmation.
2. In the custom DB session handler, implement `SessionHandlerInterface::destroy()` correctly so it actually removes the old row when `session_regenerate_id(true)` is called.
3. Set session cookie params before `session_start()`:
   ```php
   session_set_cookie_params([
       'lifetime' => 0,
       'path'     => '/',
       'secure'   => true,
       'httponly' => true,
       'samesite' => 'Strict',
   ]);
   ```
4. Validate session IP and User-Agent on every request (with a configurable strictness level — hashed UA, not raw string — to handle legitimate proxy changes).
5. Implement absolute session timeout and idle timeout separately in the DB sessions table.

**Phase:** DB sessions implementation phase, before any authentication work.

---

### PITFALL-S04: 2FA Bypass via Password Reset or Direct Route Access

**Domain:** 2FA
**Severity:** Critical

**What goes wrong:**
The 2FA check is added to the main login flow but developers forget that password-reset links, "remember this device" cookies, or direct navigation to post-login pages can bypass it. A user who resets their password via email is typically re-authenticated automatically — if that re-auth path skips the 2FA gate, 2FA is effectively useless for account takeover via phishing.

**Warning signs:**
- Password reset completion directly sets `$_SESSION['authenticated'] = true` without requiring 2FA
- There is a `remember_me` cookie that restores a full authenticated session without 2FA
- A `$_SESSION['user_id']` check is used as the auth guard rather than a two-stage flag like `$_SESSION['2fa_passed']`
- Any route that checks "is the user logged in?" uses a single boolean rather than a two-stage state machine

**Prevention strategy:**
1. Model authentication as a state machine with explicit states: `anonymous`, `credentials_verified`, `2fa_required`, `fully_authenticated`. Store the state in the session. Every protected route checks for `fully_authenticated` — not merely `credentials_verified`.
2. Password reset should log the user out of all existing sessions and require a full fresh login including 2FA.
3. "Remember device" tokens must be stored as a separate, scoped token (HMAC-signed device fingerprint) that bypasses only the TOTP prompt — not the full 2FA requirement — and must expire.
4. Apply the auth state check in a single central dispatcher, not scattered across individual pages.

**Phase:** Authentication and 2FA implementation phase.

---

### PITFALL-S05: TOTP Window Too Wide / Replay Attack Not Prevented

**Domain:** 2FA
**Severity:** High

**What goes wrong:**
TOTP codes are time-based and valid for 30-second windows. Many implementations accept codes from multiple windows (e.g., ±2 windows = 90 seconds of validity) to tolerate clock skew. Without storing used codes, the same TOTP code can be replayed within its validity window. Additionally, brute-forcing 6-digit codes (1-in-1,000,000 per attempt) is feasible if there is no rate limiting.

**Warning signs:**
- The TOTP implementation does not record used codes in a `totp_used_codes` table or equivalent
- The acceptance window is larger than ±1 window (30 seconds on each side)
- There is no lockout or rate limiting on the 2FA verification endpoint
- TOTP secret is stored in plaintext in the `users` table

**Prevention strategy:**
1. Store every successfully validated TOTP code with its window timestamp in a short-lived table. Reject any code that appears in this table (replay prevention). Purge entries older than the max window.
2. Limit the acceptance window to ±1 period (accommodating up to 30 seconds of clock skew).
3. Apply an exponential backoff or hard lockout after 5 failed TOTP attempts, separate from the password lockout.
4. Encrypt the TOTP secret at rest using a key derived from a server-side secret (not the user's password, which changes). Store only the encrypted form in the DB.
5. Provide recovery codes (pre-generated, single-use, stored as bcrypt hashes) for lockout scenarios.

**Phase:** 2FA implementation phase.

---

### PITFALL-S06: CSRF Token Tied to Session but Not to Action/Form

**Domain:** CSRF protection
**Severity:** High

**What goes wrong:**
A single per-session CSRF token is generated once and reused for every form on every page. This creates two problems: (1) XSS that leaks the token compromises all future requests until the session expires; (2) "double-submit cookie" patterns or token reuse across tabs/forms allow confused-deputy attacks. In AJAX-heavy admin UIs, the token is often embedded in the page DOM and read by JavaScript — which makes XSS-to-CSRF trivial.

**Warning signs:**
- One `$_SESSION['csrf_token']` is generated at login and never rotated
- The same token value appears in every form on every page load
- Token is not tied to a specific action (e.g., `delete_user`, `edit_record`)
- Token validation is skipped for GET requests that cause side effects (e.g., `?action=delete&id=5`)

**Prevention strategy:**
1. Generate a per-action or per-form token: `HMAC(session_id + action_name + timestamp, server_secret)`. Validate both the HMAC and that the action name matches.
2. Rotate the token on each successful validation (synchronized token pattern).
3. Never use GET requests for state-changing operations. All mutations must be POST.
4. For multi-tab support, maintain a small rolling window of valid tokens per session (e.g., last 5) rather than a single token.
5. Add `SameSite=Strict` on session cookies as a defense-in-depth measure — but do not treat it as a CSRF replacement, since it does not protect against same-site requests.

**Phase:** Core request handling and form rendering phase.

---

### PITFALL-S07: CSRF Double Validation Gap on File Upload and Multipart Forms

**Domain:** CSRF protection
**Severity:** High

**What goes wrong:**
File upload forms use `multipart/form-data` encoding. If the CSRF token is only checked via `$_POST['csrf_token']`, it is still present in the multipart body — but developers sometimes forget to include the hidden field in file upload form templates, or the file upload handler is a separate code path that doesn't go through the central CSRF middleware.

**Warning signs:**
- File upload routes have separate handling code that bypasses the central request dispatcher
- CSRF token is absent from file upload form templates
- The multipart handler reads `$_FILES` before checking `$_POST`

**Prevention strategy:**
1. Ensure all POST handlers (including file upload) pass through a single CSRF validation function before any processing.
2. Use a template helper function `csrf_field()` that always outputs the hidden input, and audit all forms to ensure it is called.
3. Consider also accepting the token as a custom HTTP header (`X-CSRF-Token`) for AJAX requests, validated alongside the POST body.

**Phase:** Core request handling phase, revisited during file/upload feature work.

---

### PITFALL-S08: Hook System Allows Arbitrary Code Injection via User-Controlled Data

**Domain:** Hooks
**Severity:** High

**What goes wrong:**
A hook system that allows registering callbacks by name (e.g., storing `'MyPlugin::onSave'` in the database) and then calling them via `call_user_func()` creates a remote code execution vector if any user-controlled data can influence what gets stored as a hook name. Even without that, hooks that receive the raw database row as their argument and can write back to the database create privilege escalation paths.

**Warning signs:**
- Hook handlers are stored as strings in a database table that admin users can edit
- `call_user_func($stored_string, $args)` is used without a whitelist of allowed callables
- Hook arguments include the raw PDO connection or session object
- Hooks can modify the row being saved before ACL checks have completed

**Prevention strategy:**
1. Register hooks in PHP code only (e.g., via a `hooks.php` configuration file), never from database-stored strings. The database can store hook *names* from a fixed registry, not arbitrary callable strings.
2. Maintain an explicit whitelist of registered hooks. Validate that any hook name from config exists in the whitelist before resolving to a callable.
3. Hook callbacks receive a sanitized data transfer object, not raw superglobals or the DB connection.
4. Define clear hook execution order and document it. Hooks that mutate data must run before validation; hooks that send notifications must run after a successful commit.
5. Wrap each hook invocation in a try/catch to prevent one failing hook from breaking the entire request.

**Phase:** Hook system design phase. Architecture decision — very hard to change later.

---

### PITFALL-S09: Versioning/Rollback Breaks Referential Integrity

**Domain:** Row-level version control
**Severity:** High

**What goes wrong:**
A version history table stores JSON snapshots of rows. On rollback, the snapshot is re-applied by overwriting the current row. If the snapshot references foreign key values (e.g., `category_id = 7`) that have since been deleted or changed, the rollback silently creates orphaned references or constraint violations. Worse, if cascade-delete has been used, rolling back a parent row does not restore the deleted child rows.

**Warning signs:**
- The version snapshot stores only the row's own columns, not the state of related tables
- No foreign key validation is run before applying a rollback
- The rollback operation uses a simple `UPDATE ... SET col = ?` without checking current FK validity
- Rollback is allowed on rows whose related records have been permanently deleted

**Prevention strategy:**
1. Before applying a rollback, validate that all foreign key references in the snapshot still exist in their target tables. Present the user with a detailed conflict report rather than silently failing or succeeding.
2. Store diffs rather than (or in addition to) full snapshots for large rows, but always store the full snapshot for the version that will be used for rollback.
3. Consider whether rollback should restore child records (a "deep rollback") or only the parent row. Document the decision and enforce it consistently.
4. Rollback must be wrapped in a transaction. If any FK check fails, the entire rollback is aborted.
5. Add a `rolled_back_from_version` column to the versions table so the audit trail shows rollbacks as explicit events.

**Phase:** Versioning implementation phase.

---

### PITFALL-S10: Versioning Creates Unbounded Storage Growth

**Domain:** Row-level version control
**Severity:** Medium

**What goes wrong:**
Every save creates a new version row containing a full JSON snapshot of the record. For tables with large text fields or frequent edits, the versions table grows without bound. This is especially acute if the hook system or bulk operations trigger saves in loops, creating hundreds of versions per record in seconds.

**Warning signs:**
- No `MAX_VERSIONS_PER_ROW` constant or pruning policy exists
- Bulk operations (import, mass update) create individual version rows per record
- The versions table has no index on `(table_name, row_id, created_at)`
- No monitoring or alerting on versions table size

**Prevention strategy:**
1. Define a retention policy at design time: keep N most recent versions per row (e.g., 50), prune older ones during the save operation.
2. For bulk operations, add a `skip_versioning` flag or batch them into a single "bulk import" version entry.
3. Index the versions table on `(table_name, row_id, created_at DESC)` for efficient pruning and display queries.
4. Consider storing diffs (JSON Patch / RFC 6902) instead of full snapshots for rows where fields change incrementally.

**Phase:** Versioning implementation phase.

---

## PERFORMANCE PITFALLS

---

### PITFALL-P01: N+1 Queries from Per-Row ACL Checks

**Domain:** Row-level ACL
**Severity:** High

**What goes wrong:**
The list view fetches N rows and then, for each row, makes a separate query to check whether the current user has permission to view/edit it. This is the classic N+1 problem, but it is especially common in row-level ACL implementations where the permission check is written as a function call (`can_user_access($row_id)`) that hides a DB query inside.

**Warning signs:**
- The ACL check function issues a `SELECT` statement
- The list-view loop calls `can_user_access()` inside a `foreach` over query results
- Page load time scales linearly with the number of rows displayed
- Query logging shows repeated identical queries differing only by the `id` parameter

**Prevention strategy:**
1. Enforce ACL at the query level: the initial `SELECT` query joins the permissions/ownership table and filters results to only rows the user can access. Zero per-row overhead.
2. If a function-based check is needed for single-row views, ensure it is only called once per request for that row — never inside a loop.
3. For list views with complex permission matrices, pre-fetch all accessible row IDs for the current user's groups in one query, then use `WHERE id IN (...)` in the main query.
4. Enable MySQL general query log during development and assert that list views produce a bounded number of queries regardless of row count.

**Phase:** Must be validated at the ACL query design phase and re-validated when list views are built.

---

### PITFALL-P02: Search/Filter Generates Unbounded Dynamic SQL

**Domain:** Search/filter
**Severity:** High

**What goes wrong:**
The search/filter system builds a `WHERE` clause dynamically by concatenating user-supplied field names and values. Even when values are parameterized, field/column names are often taken directly from `$_GET['filter_field']` without validation against a whitelist. This allows column enumeration attacks and, in the worst case, SQL injection via column name injection if any interpolation is done.

**Warning signs:**
- Filter field names come from request parameters without validation
- The dynamic WHERE builder does string interpolation for column names: `"WHERE $field = ?"` where `$field` is from user input
- No whitelist of filterable columns exists per table
- LIKE queries are constructed without escaping `%` and `_` wildcard characters in the search term

**Prevention strategy:**
1. Define a per-table whitelist of filterable columns. Reject any `filter_field` value not in the whitelist with a 400 error.
2. Column names must never be interpolated from user input. Map allowed filter names to safe column name constants.
3. For LIKE searches, escape the search term's `%` and `_` characters before binding: `str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term)`.
4. Paginate all filtered results. Never allow `LIMIT ALL` or unlimited result sets.
5. Add `SQL_CALC_FOUND_ROWS` or a separate COUNT query for pagination, but cache the count for repeat page navigations within the same filter session.

**Phase:** Search/filter implementation phase.

---

### PITFALL-P03: Table Snapshots Lock the Table During Export

**Domain:** Table snapshots
**Severity:** Medium

**What goes wrong:**
A "table snapshot" that dumps all rows to a file or archive table using `SELECT *` without a transaction isolation level can read an inconsistent state. Conversely, using `LOCK TABLES` for consistency blocks all writes during the snapshot, which is catastrophic for an admin framework where other users may be active.

**Warning signs:**
- Snapshot code uses `LOCK TABLES tbl WRITE` or similar
- Snapshot is done outside a transaction
- No `START TRANSACTION WITH CONSISTENT SNAPSHOT` or equivalent
- Snapshots of large tables are done synchronously in the web request

**Prevention strategy:**
1. Use `START TRANSACTION WITH CONSISTENT SNAPSHOT` (InnoDB) to get a point-in-time consistent read without locking.
2. For large tables, implement snapshots as a background process (write a flag file, process on next cron tick) rather than in the web request.
3. Store snapshots as JSON or CSV files with a manifest (timestamp, row count, table schema version) so integrity can be verified on restore.
4. Test snapshot + restore round-trips as part of the QA process — not just snapshot creation.

**Phase:** Table snapshot implementation phase.

---

## ARCHITECTURE/DESIGN PITFALLS

---

### PITFALL-A01: Staged Activation Has No Atomic Swap

**Domain:** Staged activation
**Severity:** High

**What goes wrong:**
Staged activation (draft → review → active) is implemented by updating a `status` column on the row. If two admin users activate different versions of the same record concurrently, the last `UPDATE` wins without detection. There is no optimistic locking, no compare-and-swap, and the loser's activation is silently overwritten — potentially pushing stale data live.

**Warning signs:**
- The activation `UPDATE` is `WHERE id = ?` without a version or timestamp check
- No `version` or `updated_at` column is compared during the update
- Concurrent activation of the same record produces no error or conflict notification
- No audit log entry records which user's activation "won"

**Prevention strategy:**
1. Use optimistic locking: `UPDATE tbl SET status='active', version=version+1 WHERE id=? AND version=?`. If `rowCount() === 0`, the activation was lost — show a conflict error to the user.
2. Display a last-modified timestamp and editor name on the activation UI so admins can see whether a record was edited since they loaded it.
3. Log every activation attempt (successful or conflicted) with user, timestamp, and version numbers.
4. Consider a pessimistic lock (a `locked_by` column) for high-contention records, with a timeout.

**Phase:** Staged activation implementation phase.

---

### PITFALL-A02: Hook Execution Order is Undefined, Causing Race Conditions on Shared State

**Domain:** Hooks
**Severity:** High

**What goes wrong:**
Multiple hooks registered for the same event (e.g., `before_save`) modify the same row data. Without a defined priority/order, the order of execution depends on registration order (which is file-include order — effectively undefined). Hook A might undo Hook B's changes, or Hook B might see stale data that Hook A already modified.

**Warning signs:**
- Two or more hooks registered for the same event that both modify the row payload
- Hook priority is not configurable (all hooks have equal priority)
- No mechanism to halt hook chain execution (short-circuit) on error or veto
- Hooks are not documented as to what data they read/write

**Prevention strategy:**
1. Assign a numeric priority to each hook registration (lower number = earlier execution). Provide constants like `HOOK_PRIORITY_EARLY = 10`, `HOOK_PRIORITY_NORMAL = 50`, `HOOK_PRIORITY_LATE = 90`.
2. Support a "halt" return value: if a hook returns a specific sentinel (e.g., `false` or a `HookVeto` object), stop the chain and abort the operation with the hook's error message.
3. Document all built-in hooks with their priority, the data shape they receive, and any shared state they modify.
4. Log hook execution order in debug mode to make ordering issues visible during development.

**Phase:** Hook system design phase.

---

### PITFALL-A03: Password Policy Enforcement Only at Set-Time, Not at Login-Time

**Domain:** Password policy
**Severity:** Medium

**What goes wrong:**
Password complexity rules are enforced when a user sets their password, but the policy can be tightened after the fact. Users who set their password before the new policy was enacted continue to log in successfully with passwords that would no longer be accepted. This is expected behavior but is often surprises security auditors. Worse: if the policy check is client-side only (JavaScript), users can bypass it by submitting the form directly.

**Warning signs:**
- Password policy validation is only in the frontend JavaScript
- No server-side policy re-check exists
- There is no `password_set_at` timestamp for forcing periodic resets
- No mechanism to force re-enrollment when policy changes

**Prevention strategy:**
1. All password policy checks must be enforced server-side. JavaScript validation is UX only.
2. Store `password_set_at` in the users table. On login, if `NOW() - password_set_at > max_age_days`, redirect to forced password change before fully authenticating.
3. Store the policy version that was in effect when the password was set (`password_policy_version` column). On login, if `current_policy_version > password_policy_version`, force re-enrollment.
4. Minimum requirements: length, uppercase, lowercase, digit, special char — each enforced as a separate regex so error messages can be specific.
5. Implement a "have I been pwned" style local banned-password list (a flat file of common passwords) as an optional check.

**Phase:** Authentication implementation phase.

---

### PITFALL-A04: Email Template Injection via Unsanitized Variables

**Domain:** Email templates
**Severity:** High

**What goes wrong:**
Email templates contain placeholders like `{{user_name}}` that are replaced with values from the database or user input. If the replacement is done with a simple `str_replace()` without output encoding, an attacker who controls a field value (e.g., their display name) can inject:
- Additional template variables that expose other users' data
- HTML that changes the email layout (for HTML emails)
- Newline characters in headers (`\r\n`) that enable email header injection for spam relaying

**Warning signs:**
- Template variable replacement uses `str_replace()` on user-supplied values without escaping
- Email `From:`, `Reply-To:`, or `Subject:` headers are built from user-supplied data without stripping `\r\n`
- HTML email templates do not HTML-encode variable values
- Template engine supports nested variable interpolation (e.g., `{{user.admin_notes}}`)

**Prevention strategy:**
1. Strip all `\r\n\t` from any value that goes into an email header (From, To, Cc, Subject, Reply-To).
2. For HTML emails, HTML-encode all variable values before substitution: `htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8')`.
3. For plain-text emails, ensure variable values cannot contain the placeholder syntax (strip `{{` and `}}` from any user-supplied value before substitution).
4. Use PHP's built-in `mail()` with the additional parameters set to pass `-f sender@domain` to prevent header injection via the fifth parameter. Better: use a simple internal SMTP-over-socket implementation with explicit header building.
5. Limit which template variables are available in each template context — do not expose the full user object.

**Phase:** Email template implementation phase.

---

### PITFALL-A05: Logging Captures Sensitive Data (Passwords, Tokens, PII)

**Domain:** Logging
**Severity:** High

**What goes wrong:**
Application logs record incoming POST parameters, session data, or SQL queries. When the login form is submitted, `$_POST` contains the plaintext password. When a password reset link is used, the token appears in `$_GET`. When a TOTP code is submitted, it appears in the POST body. Logging these values means passwords and auth tokens are stored in plaintext in log files with potentially weaker access controls than the database.

**Warning signs:**
- The log function accepts `$_POST` or `$_GET` as a whole array
- SQL query logging logs the full query with bound parameters expanded
- Log files are world-readable or stored in a web-accessible directory
- Log rotation is not configured (unbounded log growth)

**Prevention strategy:**
1. Define an explicit list of fields that must never be logged: `password`, `password_confirm`, `totp_code`, `reset_token`, `csrf_token`, `session_id`. Redact these before any logging.
2. Log queries with placeholders shown, not with parameter values expanded.
3. Store log files outside the web root (`/var/log/app/` not `public/logs/`).
4. Implement log rotation with size and age limits from day one.
5. Treat log files with the same access control as the database credentials.

**Phase:** Core logging infrastructure phase.

---

### PITFALL-A06: Custom Table Provisioning Allows Schema Injection

**Domain:** Custom table provisioning
**Severity:** Critical

**What goes wrong:**
The custom table creation feature accepts user-supplied column names, types, and constraints. If these values are interpolated into a `CREATE TABLE` SQL statement without strict whitelisting, an attacker with admin access can inject arbitrary SQL: adding triggers, creating additional tables, or exfiltrating data. Even a trusted admin making a typo can corrupt the schema.

**Warning signs:**
- Column names are taken from form input and interpolated directly into DDL: `"ALTER TABLE $table ADD COLUMN $col_name $col_type"`
- Column type validation is a client-side dropdown that can be bypassed
- No server-side whitelist of allowed column types exists
- Generated DDL is not previewed/logged before execution

**Prevention strategy:**
1. Whitelist all valid column types as PHP constants: `['VARCHAR(255)', 'INT', 'TEXT', 'TINYINT(1)', 'DATETIME', 'DECIMAL(10,2)']`. Reject anything not in the list.
2. Validate column names against a strict regex: `/^[a-z][a-z0-9_]{0,63}$/i`. Reject names with spaces, hyphens, or SQL keywords.
3. Preview the generated DDL statement in the admin UI and require explicit confirmation before executing.
4. Log every DDL operation with the user, timestamp, and full SQL statement.
5. Run DDL in a transaction where possible (MySQL does not support transactional DDL, so take a snapshot before and provide rollback instructions).

**Phase:** Custom table provisioning phase — already in existing work; audit immediately.

---

### PITFALL-A07: DB Session Handler Race Condition on Concurrent Requests

**Domain:** DB Sessions
**Severity:** Medium

**What goes wrong:**
A user's browser makes multiple simultaneous AJAX requests. Each request calls `session_start()`, which reads the session row from the DB. Each request reads the same session data, modifies it independently, and writes it back. The last write wins, silently discarding the other writes. This causes session data corruption — for example, a CSRF token written by request A is overwritten by request B's write of the old token value.

**Warning signs:**
- Session handler does not use `SELECT ... FOR UPDATE` or an equivalent lock
- Concurrent AJAX requests from the same browser produce inconsistent session state
- CSRF validation occasionally fails for no apparent reason (intermittent failures)
- Session data contains stale values after rapid successive operations

**Prevention strategy:**
1. In the session handler's `read()` method, use `SELECT ... FOR UPDATE` (inside a transaction) to acquire a row-level lock. Release it in `write()` or `close()`.
2. Alternatively, use PHP's built-in file-based session locking model as inspiration: the session lock must be exclusive for the duration of the request.
3. Keep session write scope minimal: write only changed keys, not the entire session blob, where possible.
4. Set `session.lazy_write = 1` (PHP 7.0+) to skip the write if the session data hasn't changed, reducing lock contention.

**Phase:** DB sessions implementation phase.

---

### PITFALL-A08: Rollback Does Not Invalidate Derived/Cached State

**Domain:** Row-level version control
**Severity:** Medium

**What goes wrong:**
A row is rolled back to a previous version. But the system has derived state from the current version: search index entries, computed columns, cached aggregate values, or external system syncs. After rollback, the row is now in the old state but all derived state still reflects the version that was rolled back. Searches return the rolled-back row with stale indexed content; computed values are wrong.

**Warning signs:**
- No post-rollback hook exists to trigger re-indexing or re-computation
- Rollback only issues an `UPDATE` on the main table, nothing else
- There is a search index table (for search/filter) that is not rebuilt after rollback
- External email notifications or webhooks were sent based on the rolled-back version and are not retracted

**Prevention strategy:**
1. The rollback operation must fire the same `after_save` hooks that a normal save fires, so all downstream effects are re-triggered with the rolled-back data.
2. Document all derived state that depends on row content and ensure each is listed in the rollback checklist.
3. For external side effects (emails sent, webhooks dispatched), rollback cannot retract them — document this limitation explicitly in the admin UI.
4. If a search index table exists, include a re-index step in the rollback transaction.

**Phase:** Versioning implementation phase; revisit when hooks and search/filter are integrated.

---

### PITFALL-A09: Staged Activation Status Machine Has No Guard Against Skipped States

**Domain:** Staged activation
**Severity:** Medium

**What goes wrong:**
The staged activation workflow (draft → pending_review → approved → active) is implemented by setting a `status` column. Any admin can POST `status=active` directly, skipping the review step entirely. There is no state machine enforcement — the `UPDATE` simply sets whatever status is requested.

**Warning signs:**
- Status transitions are validated only in the UI (hidden/disabled buttons)
- The backend `UPDATE` accepts any status value without checking the current status
- No transition table or allowed-transitions map exists in PHP code
- Audit log does not record the `from_status` → `to_status` transition

**Prevention strategy:**
1. Define an explicit transition map in PHP:
   ```php
   const ALLOWED_TRANSITIONS = [
       'draft'          => ['pending_review'],
       'pending_review' => ['approved', 'draft'],
       'approved'       => ['active', 'pending_review'],
       'active'         => ['draft'],
   ];
   ```
2. Before any status update, look up the row's current status and verify the requested transition is in the allowed list. Return a 403 if not.
3. Log every transition with `from_status`, `to_status`, user, and timestamp.
4. Tie transitions to role permissions: only users in the "reviewer" group can transition from `pending_review` to `approved`.

**Phase:** Staged activation implementation phase.

---

### PITFALL-A10: Zero-Dependency Constraint Leads to Hand-Rolled Crypto — Incorrectly

**Domain:** 2FA, password hashing, session tokens
**Severity:** Critical

**What goes wrong:**
The "zero external library" constraint causes developers to implement cryptographic primitives by hand: rolling their own TOTP, building custom HMAC-based token generators, or — worst of all — using `md5()` or `sha1()` for password hashing because `password_hash()` "feels like a library." This produces subtly broken security implementations that are indistinguishable from correct ones until they are exploited.

**Warning signs:**
- Any use of `md5()`, `sha1()`, `base64_encode()`, or `crc32()` for security-sensitive values
- Custom TOTP implementation rather than using PHP's built-in `hash_hmac('sha1', ...)`
- Token generation using `rand()` or `mt_rand()` instead of `random_bytes()`
- Custom constant-time comparison instead of `hash_equals()`

**Prevention strategy:**
1. "Zero external library" means zero Composer packages — it does not mean avoiding PHP's built-in security functions. Use:
   - `password_hash()` / `password_verify()` for passwords (bcrypt/argon2)
   - `random_bytes()` / `bin2hex()` for tokens
   - `hash_hmac('sha256', ...)` for HMAC operations
   - `hash_equals()` for constant-time comparison
   - `openssl_encrypt()` / `openssl_decrypt()` for symmetric encryption if needed
2. The TOTP algorithm (RFC 6238) can be implemented correctly in pure PHP using `hash_hmac` and `pack()` — document the reference implementation and test it against RFC 6238 test vectors.
3. Write explicit tests for each cryptographic operation using known test vectors.
4. Conduct a security review of all crypto usage before launch.

**Phase:** Authentication phase and throughout all security feature work.

---

## QUICK-REFERENCE SUMMARY

| ID | Domain | Severity | Phase |
|----|--------|----------|-------|
| S01 | Row-level ACL bypass (IDOR) | Critical | ACL foundation |
| S02 | Group permission escalation | Critical | ACL schema design |
| S03 | Session fixation | Critical | DB sessions |
| S04 | 2FA bypass via password reset | Critical | Auth/2FA |
| S05 | TOTP replay attack | High | 2FA |
| S06 | CSRF token not action-scoped | High | Core request handling |
| S07 | CSRF gap on file upload | High | Core/upload |
| S08 | Hook system code injection | High | Hook design |
| S09 | Rollback breaks referential integrity | High | Versioning |
| S10 | Versioning unbounded storage | Medium | Versioning |
| P01 | N+1 queries from per-row ACL | High | ACL query design |
| P02 | Search filter dynamic SQL injection | High | Search/filter |
| P03 | Snapshot table locking | Medium | Snapshots |
| A01 | Staged activation no atomic swap | High | Staged activation |
| A02 | Hook execution order undefined | High | Hook design |
| A03 | Password policy only at set-time | Medium | Auth |
| A04 | Email template injection | High | Email templates |
| A05 | Logging captures sensitive data | High | Logging |
| A06 | Custom table schema injection | Critical | Table provisioning |
| A07 | DB session race condition | Medium | DB sessions |
| A08 | Rollback doesn't invalidate cached state | Medium | Versioning/hooks |
| A09 | Staged activation skips states | Medium | Staged activation |
| A10 | Hand-rolled crypto | Critical | Auth and throughout |
