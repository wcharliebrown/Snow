# Snow

## What This Is

Snow is a zero-dependency PHP web application platform built on LAMP (Linux, Apache, MySQL, PHP) with Bootstrap 5. It provides a complete admin framework where any database table — custom or built-in — gets ACL, versioning, CRUD, search, and reporting out of the box. No Composer, no npm, no magic.

## Core Value

Any table in the database can be managed by content managers through a consistent, ACL-controlled admin interface with full versioning and rollback — without writing boilerplate for each new table.

## Requirements

### Validated

- ✓ File-based report system (reports/*.php) — existing
- ✓ Custom table provisioning (MySQL table + admin page + report) — existing
- ✓ Admin list views rendered via report templates — existing

### Active

**Auth & Security**
- [ ] DB-based session management (highly performant)
- [ ] Customizable password policy (min length, max age, reuse control)
- [ ] Two-factor authentication (Google OTP, 6-digit code, email magic links)
- [ ] Automatic CSRF protection
- [ ] Multi-level logging (logins, errors, outbound emails)

**ACL**
- [ ] Group-based ACL (users can belong to any number of groups)
- [ ] Row-level view ACL (groups allowed to view each row)
- [ ] Row-level edit ACL (groups allowed to edit each row, separate from view)

**Data Management**
- [ ] Custom table field creation (add fields to any custom table)
- [ ] Standard fields on every table (created_at, modified_at, status, view_groups, edit_groups)
- [ ] Customizable edit forms per table
- [ ] Customizable search, sort, and filter per table
- [ ] Staged activation/deactivation/deletion on a specific date for any row

**Versioning & Snapshots**
- [ ] Row-level version control (track which user changed what)
- [ ] Rollback of row changes to any prior version
- [ ] Table snapshots (duplicate a table for backup/comparison)
- [ ] Diff tool (show changes to a table since last snapshot)
- [ ] Snapshot restore

**Content & Extensibility**
- [ ] Custom web pages (simple and fast creation)
- [ ] Email templates for sending customized messages
- [ ] Hooks (custom PHP code executes on row create/modify for any table)

**Admin**
- [ ] Any number of content-manager user accounts

### Out of Scope

- External dependencies (Composer packages, npm) — zero deps is a core constraint
- Real-time features (WebSockets, SSE) — not a LAMP fit
- REST/GraphQL API — admin UI focus, not API-first
- Mobile app — web-first platform

## Context

Building on existing work: the report system, custom table provisioning, and admin list views are already implemented. The remaining features (ACL, versioning, full auth stack, hooks, etc.) are the next phase of construction.

Architecture principle: each function lives in its own PHP file to prevent monolithic files. This keeps the codebase navigable and functions single-purpose.

## Constraints

- **Tech Stack**: PHP + MySQL + Apache only — no Composer, no npm, no Node
- **UI**: Bootstrap 5 — no other CSS frameworks, no build step
- **File structure**: Each function in its own PHP file

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| No Composer/npm | Portability and simplicity — deploys to any LAMP host without a build step | — Pending |
| Bootstrap 5 for UI | Standard, well-documented, no build step required | — Pending |
| Function-per-file architecture | Prevents monolithic files, enforces single-responsibility | — Pending |
| DB-based sessions | Performance and fine-grained control over session state | — Pending |
| Reports-as-admin-UI | Admin pages are report templates — one pattern for all tables | ✓ Good |

---
*Last updated: 2026-02-27 after initialization*
