# Project Research Summary

**Project:** Snow
**Domain:** Zero-dependency PHP LAMP admin framework
**Researched:** 2026-02-27
**Confidence:** HIGH

## Executive Summary

Snow is a zero-dependency PHP LAMP admin framework with a firm constraint: no Composer, no npm, no build step. The foundation is already working — PHP 8.4-FPM, MySQL 8.0, Apache, Bootstrap 5 CDN, PDO with prepared statements, file-based sessions, a file-backed report system, custom table provisioning, user/group/permission management, AES-256-CBC field encryption, and a file-based logging system. Research confirms that every remaining feature on the roadmap is implementable entirely with PHP built-ins (`random_bytes`, `hash_hmac`, `SessionHandlerInterface`, `json_encode`, `password_hash`, `hash_equals`) and standard MySQL 8.0 constructs (JSON columns, `FIND_IN_SET`, InnoDB transactions). There is no technical need to introduce any external library to reach full feature parity with mainstream admin frameworks.

The recommended build sequence follows a strict dependency order: security foundations first (DB sessions, CSRF), then access control (row-level ACL, password policy), then data integrity features (row versioning, snapshots), then extensibility (hooks, 2FA), then user-facing UX (search/filter, staged activation). This order is non-negotiable — DB sessions are a prerequisite for reliable 2FA state, ACL columns must exist on tables before versioning can record `changed_by` reliably, and hooks must be in place before versioning integrates cleanly without touching every call site. Skipping this order risks expensive rework.

The six critical pitfalls that must shape every implementation decision are: ACL checked at the display layer instead of the data/query layer (IDOR), session fixation from missed `session_regenerate_id(true)` calls, 2FA bypass via password-reset paths, schema injection via unsanitized column names in the custom table provisioner (already shipped — audit immediately), hand-rolled crypto using weak functions (`md5`, `rand`) under the mistaken belief that "zero dependency" excludes PHP built-in security functions, and hook systems that allow database-stored callable strings. All of these are addressed by patterns already documented in the research and require no external libraries to fix.

---

## Key Findings

### Recommended Stack

Snow's constraint (no Composer, no npm) is fully honored by PHP 8.4's built-in security primitives and MySQL 8.0's native features. Every remaining feature maps directly to a PHP built-in: TOTP uses `hash_hmac('sha1', ...)` per RFC 6238; CSRF uses `random_bytes(32)` + `hash_equals()`; DB sessions use `SessionHandlerInterface`; row versioning uses `json_encode`/`json_decode` with MySQL's native `JSON` column type; ACL uses `FIND_IN_SET()` for comma-separated group IDs. Bootstrap 5.3.3 via CDN with SRI hashes covers all UI needs without a build step. The only external HTTP call in the entire system is the optional Google Charts API URL for TOTP QR code display — which is a URL in HTML, not a code dependency.

**Core technologies:**
- PHP 8.4 `SessionHandlerInterface` — DB sessions — canonical zero-dep approach, stable since PHP 5.4
- PHP `random_bytes()` + `hash_hmac()` + `hash_equals()` — all security tokens and TOTP — OWASP-standard, no library needed
- PHP `json_encode/decode` + MySQL `JSON` type — row versioning snapshots — MySQL 8.0 native, zero deps
- MySQL `FIND_IN_SET()` — row-level ACL group checks — fast for <100k rows, zero deps
- Bootstrap 5.3.3 CDN + SRI hashes — all UI — no build step, XSS-resistant via integrity hashes
- PHP `file_exists()` + `include` — hook dispatch — consistent with existing architecture

**What not to use:** PHPMailer/SwiftMailer (Composer), Redis/Memcached sessions (extra infrastructure), MySQL triggers for versioning (cannot access PHP session context), JWT (cannot be server-side invalidated without a DB anyway), `md5`/`sha1`/`rand`/`mt_rand` for any security purpose.

### Expected Features

See [FEATURES.md](FEATURES.md) for full feature taxonomy with complexity estimates and dependency map.

**Must have — table stakes not yet built:**
- CSRF protection — every POST form; prerequisite for all other work
- DB-based session management — prerequisite for 2FA and session revocation UI
- Search / sort / filter on list views — users expect this in any data management tool
- Universal soft-delete (`status` field) — partially shipped, not yet enforced on all tables

**Must have — differentiators (Snow's competitive moat):**
- Group-based row-level view and edit ACL — pure PHP, no ORM required
- Row-level version control with rollback — full history per record per table
- Two-factor authentication (TOTP + email OTP + magic link) — pure PHP RFC 6238
- Hooks/callbacks on row CRUD events — file-based dispatch, no DB-stored callables
- Staged activation/deactivation (scheduled publish/unpublish per row)
- Table snapshots with actual data copy, diff tool, and restore

**Defer to v2+:**
- REST or GraphQL API — out of scope, adds complexity without serving admin use cases
- File upload management — opens security surface, not LAMP-native
- SMS-based 2FA — requires Twilio, violates zero-dependency constraint
- OAuth/SSO — external dependency; password + 2FA covers admin use cases
- CSV import — high edge-case complexity (encoding, column mapping)
- WYSIWYG/Markdown editor — requires external JS library

### Architecture Approach

The system is organized as a 5-layer dependency graph where each layer must be complete before the next begins. Layer 0 (foundation) is already shipped. New components follow the existing architecture patterns: each function in its own PHP file, admin pages are controllers not routers, `requirePermission()` is always the first meaningful line, all writes go through PDO prepared statements, soft deletes via `status` field, flash messages via GET redirect. The request lifecycle with all components active flows: DB session handler registration → session start → route → auth/ACL check → CSRF check → hook dispatch → version record → DB write → hook dispatch → log.

**Major components:**
1. `functions/session.php` — DB session handler via `SessionHandlerInterface`; transparent replacement for file sessions
2. `functions/csrf.php` — token generation and verification; touches only `$_SESSION`, no new tables
3. `functions/acl.php` — row-level view/edit checks via `FIND_IN_SET` on `view_groups`/`edit_groups` columns provisioned on every custom table
4. `functions/password-policy.php` — max age, reuse prevention; extends existing `validatePasswordStrength()`
5. `functions/versioning.php` — JSON snapshots in `row_versions` table; called before every write
6. `functions/snapshot.php` — full-table JSON export to files; diff and restore against live data
7. `functions/hooks.php` + `hooks/` directory — file-based hook dispatch registered from PHP files, never DB-stored callables
8. `functions/totp.php` + `functions/twofa.php` — RFC 6238 TOTP in pure PHP; 2FA state machine in session
9. Extended `functions/reports.php` — search/filter/sort via GET params, column whitelist enforced, PDO params only

**MySQL tables to create (in dependency order):**
- Phase 1: `sessions`, `activity_log`, `password_history`
- Phase 2: `view_groups`/`edit_groups` columns added to every provisioned custom table (no new tables)
- Phase 3: `row_versions`; `snapshots.file_path` column enforced
- Phase 4: `hooks`, `backup_codes`; `users` table gets `totp_secret`, `totp_enabled`, `email_otp`, `email_otp_expiry`
- Phase 5: `activate_at`/`deactivate_at` columns added to standard table column set

### Critical Pitfalls

Full pitfall catalog with 21 entries is in [PITFALLS.md](PITFALLS.md). The five that must shape every implementation decision:

1. **ACL at display layer, not data layer (PITFALL-S01, Critical)** — The ACL check must be inside the SQL `WHERE` clause, not in a PHP `if` after fetching all rows. IDOR attacks trivially bypass UI gates. Use `filterRowsByViewAccess()` at the query level; never check permission on already-fetched data in a loop.

2. **Session fixation from missing `session_regenerate_id(true)` (PITFALL-S03, Critical)** — Call `session_regenerate_id(true)` (the `true` deletes the old session row) immediately after every privilege change: login, logout, 2FA confirmation, password reset completion. The DB session handler's `destroy()` method must actually delete the old row.

3. **2FA bypass via password reset or direct route access (PITFALL-S04, Critical)** — Model auth as a state machine: `anonymous` → `credentials_verified` → `2fa_required` → `fully_authenticated`. Every protected route checks `fully_authenticated`. Password reset must log out all existing sessions and require a full fresh login including 2FA.

4. **Hand-rolled crypto confusion (PITFALL-A10, Critical)** — "Zero external library" means no Composer packages, not avoiding PHP's built-in security functions. `md5`/`sha1`/`rand`/`mt_rand` must never appear in security contexts. Use `password_hash`, `random_bytes`, `hash_hmac`, `hash_equals`, `openssl_encrypt`.

5. **Schema injection in custom table provisioner (PITFALL-A06, Critical)** — The provisioner is already shipped. Audit immediately: column names must be validated against `/^[a-z][a-z0-9_]{0,63}$/i`, column types against a PHP whitelist constant, and every DDL statement must be logged before execution.

**Additional high-severity pitfalls to address per phase:**
- N+1 ACL queries on list views (PITFALL-P01) — enforce at query level, not per-row function call
- Hook system must not allow DB-stored callable strings (PITFALL-S08) — hooks registered in PHP files only
- Versioning rollback must validate FK references before applying (PITFALL-S09) — wrap in transaction
- TOTP replay attack (PITFALL-S05) — store used codes, limit window to ±1, rate-limit the verify endpoint
- Email header injection (PITFALL-A04) — strip `\r\n` from all header values, HTML-encode template vars

---

## Implications for Roadmap

Research across all four dimensions converges on a 5-phase structure that respects the dependency graph and front-loads security foundations. The ordering is driven by three constraints: (a) DB sessions must precede 2FA, (b) ACL columns must exist on tables before versioning records `changed_by` consistently, (c) hooks must exist before versioning integrates cleanly. The architecture research explicitly maps these dependencies; the features research provides the same recommended build order independently.

### Phase 1: Security Foundations

**Rationale:** CSRF protection and DB sessions are prerequisites for everything else. CSRF must be in place before any new POST forms are added so they are built correctly from the start. DB sessions unblock 2FA (which needs multi-step session state) and session revocation UI. Password policy extends existing partially-built validation. Logging DB tier makes all subsequent security events queryable. None of these have external dependencies.

**Delivers:** CSRF-protected forms sitewide; MySQL-backed sessions with session listing and force-logout; password age and reuse enforcement; queryable activity log in the admin.

**Addresses features:** CSRF protection, DB-based session management, session timeout enforcement, password policy (max age, reuse prevention), DB-tier logging.

**Avoids pitfalls:** S03 (session fixation — implement `session_regenerate_id(true)` correctly in handler), A05 (sensitive data in logs — redact passwords/tokens from day one), A10 (hand-rolled crypto — use only PHP built-ins), A06 (schema injection audit — do this in Phase 1, it's already shipped code).

**Research flag:** Standard patterns. Skip `/gsd:research-phase`. Implementation is fully specified in STACK.md and ARCHITECTURE.md.

---

### Phase 2: Access Control

**Rationale:** Row-level ACL requires stable group infrastructure (already shipped) and DB sessions (Phase 1) to know the current user reliably. The `view_groups`/`edit_groups` columns must be added to every custom table at provisioning time — this is a schema change that subsequent phases (versioning, hooks) depend on being present. Doing this after Phase 1 means session-based user identification is reliable.

**Delivers:** Row-level view and edit access control on all custom tables; group selectors on add/edit forms; ACL enforcement at the query level (not display layer).

**Addresses features:** Group-based row-level view ACL, group-based row-level edit ACL, standard table columns (`view_groups`, `edit_groups`, `status` universally).

**Avoids pitfalls:** S01 (ACL at data layer, not display layer — enforce in SQL WHERE), S02 (no group inheritance in scope — document constraint explicitly), P01 (N+1 queries — ACL must be in the initial SELECT, never per-row function calls in a loop).

**Uses stack:** `FIND_IN_SET(group_id, view_groups)` in MySQL; session-cached group IDs; `functions/acl.php`.

**Research flag:** Standard patterns. Skip `/gsd:research-phase`. ACL approach fully documented in STACK.md with specific MySQL and PHP patterns.

---

### Phase 3: Data Integrity

**Rationale:** Row-level versioning requires ACL columns to exist (Phase 2) so it can record `changed_by` against a reliable user identity. Snapshot data capture extends existing stub infrastructure. Both features are high-value differentiators and set up the hook system (Phase 4) to integrate cleanly — versioning becomes a `before_update` hook rather than manually added to every call site.

**Delivers:** Full version history per record with diff view and rollback; actual data snapshots with diff-vs-live and restore; complete audit trail for every write.

**Addresses features:** Row-level version control, rollback to any prior version, diff tool (version-to-version), table snapshots (actual data copy), snapshot diff, snapshot restore.

**Avoids pitfalls:** S09 (rollback FK integrity — validate references before applying, wrap in transaction), S10 (unbounded version storage — implement MAX_VERSIONS_PER_ROW retention policy from day one), P03 (snapshot table locking — use `START TRANSACTION WITH CONSISTENT SNAPSHOT`), A08 (rollback must fire `after_save` hooks so derived state is invalidated — plan this before hooks are built in Phase 4).

**Uses stack:** `json_encode/decode`, `array_diff_assoc`, MySQL `JSON` column type, `row_versions` table, JSON files for snapshots, `functions/versioning.php`, `functions/snapshot.php`.

**Research flag:** Needs `/gsd:research-phase` for the snapshot restore path. The FK integrity check before rollback and schema-drift handling (columns added/removed since snapshot was taken) require detailed implementation planning. Core versioning storage is standard.

---

### Phase 4: Extensibility

**Rationale:** The hook system is placed after versioning because the recommended architecture uses hooks to trigger version recording — building hooks first would require revisiting the integration order. 2FA is placed here (rather than Phase 1) because: (a) its admin setup pages should themselves be ACL-protected (Phase 2) and version-tracked (Phase 3), and (b) the 2FA pending state in sessions is more robust with DB sessions (Phase 1) already proven stable.

**Delivers:** File-based hook dispatch system with admin CRUD for hook registry; TOTP + email OTP + magic link 2FA; backup recovery codes; 2FA state machine in auth flow.

**Addresses features:** Hooks/callbacks on row events, plugin system runtime behavior, two-factor authentication (all three modes), backup codes.

**Avoids pitfalls:** S04 (2FA bypass — implement auth state machine with `fully_authenticated` check, not just `user_id`), S05 (TOTP replay — store used codes, limit window to ±1, rate-limit), S08 (hook code injection — hooks registered in PHP files only, never DB-stored callable strings), A02 (hook execution order — implement numeric priority from the start, support halt/veto return).

**Uses stack:** `hash_hmac('sha1', ...)` for TOTP, `random_bytes(20)` for TOTP secret, `random_int(100000, 999999)` for email OTP, Base32 encoder in `functions/totp.php`, `functions/twofa.php`, `functions/hooks.php`, `hooks/` directory.

**Research flag:** Needs `/gsd:research-phase` for TOTP implementation. The RFC 6238 algorithm with Base32 encoding, replay prevention, and rate limiting has implementation nuances. FEATURES.md estimates 8-12 hours for TOTP alone. Email OTP and magic link are standard patterns (skip research for those).

---

### Phase 5: User Experience

**Rationale:** Search/filter modifies `renderReport()` which every admin list view uses — it is safest to build last, after the data model (ACL columns, standard columns, versioning) is locked. Staged activation is an extension of the standard columns set (adds `activate_at`/`deactivate_at`) and belongs in the same phase as search/filter since both touch the report rendering layer.

**Delivers:** Column sort, multi-field search, filter dropdowns on all managed table list views; scheduled publish/unpublish per row; full report system with dynamic SQL controls.

**Addresses features:** Search/sort/filter UI on list views, staged activation/deactivation, report system extension (new optional `searchable_fields`, `filterable_fields`, `sortable_fields` methods on report classes).

**Avoids pitfalls:** P02 (dynamic SQL injection — column names validated against per-report whitelist, never interpolated from GET params; LIKE values must escape `%` and `_` wildcards), A01 (staged activation atomic swap — use optimistic locking `WHERE id=? AND version=?`), A09 (staged activation state machine — enforce allowed transitions in PHP, not just UI).

**Uses stack:** PDO prepared statements for all filter values; Bootstrap 5 filter UI components (selects, date inputs, search box); `functions/reports.php` extensions.

**Research flag:** Standard patterns for search/filter. Skip `/gsd:research-phase`. Staged activation state machine has some design nuances — document the allowed-transitions map before implementing.

---

### Phase Ordering Rationale

- **Security before features:** CSRF and DB sessions are prerequisites, not optional hardening. Building any POST-handling feature before CSRF is in place means retrofitting tokens into every form.
- **Dependency graph is real:** The architecture research independently derived the same 5-phase order as the features research. Both sources agree on the dependency chain: sessions → ACL → versioning → hooks → 2FA → search.
- **Schema first within each phase:** ACL columns on custom tables (Phase 2) must be added to the provisioner before any custom tables are created in production. Same for `activate_at`/`deactivate_at` in Phase 5.
- **Pitfall mitigation by phase:** Each phase's pitfalls are addressed within that phase, not deferred. The most common failure mode is deferring security checks to "after launch" — the research documents exactly where each pitfall strikes.
- **Audit existing code in Phase 1:** PITFALL-A06 (schema injection in table provisioner) affects already-shipped code. Phase 1 must include an audit of `admin-tables.php` column name and type validation, even though no new feature is being added.

### Research Flags

Phases needing deeper research during planning:
- **Phase 3 (snapshot restore):** FK integrity validation before rollback, schema-drift handling between snapshot time and restore time. The diff algorithm is straightforward; the restore path has edge cases requiring detailed planning.
- **Phase 4 (TOTP):** RFC 6238 implementation with Base32 encoding, replay prevention table, and rate limiting. Test against RFC 6238 known test vectors before shipping. Email OTP and magic link do not need research (standard patterns already documented).

Phases with standard patterns (skip research-phase):
- **Phase 1:** All patterns fully specified in STACK.md (`SessionHandlerInterface`, CSRF via `random_bytes`+`hash_equals`, logging schema). No ambiguity.
- **Phase 2:** ACL via `FIND_IN_SET` is fully specified in STACK.md with exact PHP and MySQL patterns. No ambiguity.
- **Phase 5:** Search/filter extension to `renderReport()` is fully specified in ARCHITECTURE.md. Staged activation state machine needs documentation but not research.

---

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | Every technology recommendation references specific PHP built-in functions and MySQL features with confirmed version availability. No library dependencies to evaluate. |
| Features | HIGH | Feature taxonomy derived from analysis of WordPress Admin, Directus, Strapi, Filament, and Nova. Complexity estimates and dependency map are detailed and internally consistent. |
| Architecture | HIGH | Component boundaries, data flows, and build order are specified with concrete file names, function signatures, and MySQL schemas. Both FEATURES.md and ARCHITECTURE.md independently converge on the same 5-layer dependency order. |
| Pitfalls | HIGH | 21 pitfalls documented with specific failure modes, warning signs, and prevention strategies. Severity ratings align with OWASP and standard security literature. |

**Overall confidence:** HIGH

### Gaps to Address

- **QR code delivery for TOTP setup:** Three options documented (Google Charts API URL, pure-PHP renderer, URL-only text display). Decision deferred to implementation. Recommendation: start with URL-only text display (zero deps, works immediately), add QR as an enhancement in a later iteration.

- **FIND_IN_SET performance at scale:** Documented as MEDIUM confidence for tables with >100k rows. Acceptable for admin use cases; if a specific table is expected to exceed this, add an index-backed junction table approach for that table only. Flag during implementation if any table is anticipated to be large.

- **Search across joined tables:** The current report SQL is single-table. Search/filter across joined data (e.g., products with category name from categories table) is deferred. Document this limitation explicitly in the search/filter admin UI.

- **Snapshot restore schema drift:** If columns are added or removed from a table after a snapshot is taken, restore behavior is undefined. Requires implementation-time decision: reject restore if schema has changed, attempt partial restore, or prompt user to map columns manually.

- **Hook error handling policy:** PITFALL-A02 notes that `before_*` hooks should be able to abort a write (return false = cancel), while `after_*` hooks should never abort (log error, continue). This contract must be documented before Phase 4 implementation and enforced in `fireHook()`.

---

## Sources

### Primary (HIGH confidence)
- PHP 8.4 documentation — `SessionHandlerInterface`, `random_bytes`, `hash_hmac`, `hash_equals`, `password_hash`, `json_encode`, `openssl_encrypt`
- MySQL 8.0 documentation — `FIND_IN_SET`, `JSON` column type, `START TRANSACTION WITH CONSISTENT SNAPSHOT`, `SELECT ... FOR UPDATE`
- RFC 6238 — TOTP algorithm specification (referenced in STACK.md and ARCHITECTURE.md)
- OWASP — CSRF synchronizer token pattern, session fixation prevention (referenced in PITFALLS.md)
- Bootstrap 5.3.3 — CDN with SRI hashes (pinned version, integrity hash verified)

### Secondary (MEDIUM confidence)
- WordPress Admin, Directus, Strapi, Filament, Nova — feature taxonomy baseline for FEATURES.md
- Google Charts API — QR code URL generation for TOTP enrollment (external HTTP call, not code dependency)

### Tertiary (LOW confidence)
- Pure-PHP QR code renderer (~200 LOC) — existence confirmed, specific implementation not evaluated; treat as implementation-time research if URL-only approach is rejected

---

*Research completed: 2026-02-27*
*Ready for roadmap: yes*
