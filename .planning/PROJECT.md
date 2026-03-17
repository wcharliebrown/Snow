# Snow

## What This Is

Snow is a zero-dependency PHP web application platform built on LAMP (Linux, Apache, MySQL, PHP) with Bootstrap 5. It provides a complete admin framework where any database table — custom or built-in — gets ACL, versioning, CRUD, search, and reporting out of the box. No Composer, no npm, no magic.

v1.0 ships a complete admin framework: CSRF protection, DB-backed sessions, password policy, 2FA (email OTP), row-level ACL, group management, full version history with rollback, table snapshots with diff and restore, PHP hook extensibility, custom pages, email templates, search/sort/filter, and scheduled row lifecycle.

## Core Value

Any table in the database can be managed by content managers through a consistent, ACL-controlled admin interface with full versioning and rollback — without writing boilerplate for each new table.

## Requirements

### Validated

- ✓ File-based report system (reports/*.php) — existing
- ✓ Custom table provisioning (MySQL table + admin page + report) — existing
- ✓ Admin list views rendered via report templates — existing
- ✓ DB-based session management (SELECT FOR UPDATE locking, fixation prevention) — v1.0
- ✓ Customizable password policy (min length, max age, reuse control) — v1.0
- ✓ Two-factor authentication (email OTP, 6-digit code, per-user opt-in) — v1.0
- ✓ Automatic CSRF protection (per-session tokens, central enforcement in renderPage()) — v1.0
- ✓ Multi-level logging (logins, errors, outbound emails; DB + flat-file redundancy) — v1.0
- ✓ Group-based ACL (users can belong to any number of groups) — v1.0
- ✓ Row-level view ACL (view_groups field; NULL = open to all data users) — v1.0
- ✓ Row-level edit ACL (edit_groups field; separate from view) — v1.0
- ✓ Standard fields on every managed table (created_at, modified_at, status, view_groups, edit_groups) — v1.0
- ✓ Custom table field creation (add fields via admin UI) — v1.0
- ✓ Customizable edit forms per table (col_width-driven layout) — v1.0
- ✓ Customizable search, sort, and filter per table (no per-table code) — v1.0
- ✓ Staged activation/deactivation/deletion on a specific date (activate_at, deactivate_at, delete_at) — v1.0
- ✓ Row-level version control (JSON before-image, user + timestamp) — v1.0
- ✓ Rollback of row changes to any prior version — v1.0
- ✓ Table snapshots (CREATE TABLE AS SELECT) — v1.0
- ✓ Diff tool (row-level changes since last snapshot) — v1.0
- ✓ Snapshot restore (atomic RENAME TABLE with pre-restore auto-snapshot safety net) — v1.0
- ✓ Custom web pages (path + PHP/HTML content served by framework) — v1.0
- ✓ Email templates (named variables, {{token}} substitution) — v1.0
- ✓ PHP hooks (before/after create/update per table, silently logged errors) — v1.0

### Active

*(None — v1.0 is complete. Next milestone requirements defined in /gsd:new-milestone.)*

### Out of Scope

- External dependencies (Composer packages, npm) — zero deps is a core constraint
- Real-time features (WebSockets, SSE) — not a LAMP fit
- REST/GraphQL API — admin UI focus, not API-first
- Mobile app — web-first platform
- TOTP/Google Authenticator — deferred to v2 (AUTH-V2-01)
- Email magic link login — deferred to v2 (AUTH-V2-02)
- Field-level encryption — deferred to v2 (ADV-V2-01)
- Bulk import/export — deferred to v2 (ADV-V2-02)
- Role-based permission templates — deferred to v2 (ADV-V2-03)

## Context

**v1.0 shipped 2026-03-17.** ~10,887 PHP LOC across 129 files changed over 23 days (2026-02-22 → 2026-03-17).

Architecture principle: each function lives in its own PHP file. This held through all 5 phases and kept the codebase navigable.

Two permission levels emerged in practice: `table_management` (schema + ACL) vs `table_data_access` (data only). This separation proved essential for content-manager workflows.

The provisioning pattern (provisionCustomTable / migrateExistingCustomTables) absorbed every new standard column — schedule columns, ACL columns — cleanly via INFORMATION_SCHEMA guards. This is a structural asset for future phases.

## Constraints

- **Tech Stack**: PHP + MySQL + Apache only — no Composer, no npm, no Node
- **UI**: Bootstrap 5 — no other CSS frameworks, no build step
- **File structure**: Each function in its own PHP file

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| No Composer/npm | Portability and simplicity — deploys to any LAMP host without a build step | ✓ Good — zero external friction |
| Bootstrap 5 for UI | Standard, well-documented, no build step required | ✓ Good |
| Function-per-file architecture | Prevents monolithic files, enforces single-responsibility | ✓ Good — held through 5 phases |
| DB-based sessions | Performance and fine-grained control over session state | ✓ Good — SELECT FOR UPDATE solved concurrency |
| Reports-as-admin-UI | Admin pages are report templates — one pattern for all tables | ✓ Good |
| Per-session CSRF tokens | Avoids back-button and multi-tab breakage | ✓ Good |
| Central CSRF enforcement in renderPage() | One insertion protects every page | ✓ Good |
| table_data_access as separate permission | Data editors should not touch schema or ACL | ✓ Good — emerged during Phase 2 |
| RENAME TABLE for snapshot restore | MySQL DDL auto-commits; two-pair atomicity is sufficient | ✓ Good |
| Pre-restore auto-snapshot | Safety net before destructive rename | ✓ Good |
| Email OTP only for 2FA (not TOTP) | Simpler implementation, no shared-secret management | ✓ Good — TOTP deferred to v2 |
| Hook errors silently logged | Redirect proceeds regardless — hooks are optional extensions | ✓ Good |
| Per-table schedule columns | Keeps EXT-04 lifecycle independent of core schema | ✓ Good |

---
*Last updated: 2026-03-17 after v1.0 milestone*
