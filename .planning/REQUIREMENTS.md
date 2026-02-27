# Requirements: Snow

**Defined:** 2026-02-27
**Core Value:** Any table in the database can be managed through a consistent, ACL-controlled admin interface with full versioning and rollback — without writing boilerplate for each new table.

## v1 Requirements

Requirements for initial release. Each maps to a roadmap phase.

### Security

- [ ] **SEC-01**: System automatically applies CSRF protection to all POST forms
- [ ] **SEC-02**: Sessions are stored in MySQL (DB-backed SessionHandlerInterface with SELECT FOR UPDATE locking)
- [ ] **SEC-03**: Admin can configure password policy (minimum length, maximum age, reuse control)
- [ ] **SEC-04**: System logs logins, errors, and outbound emails to database (multi-level, structured)
- [ ] **SEC-05**: User can complete login using a short-term 6-digit code sent to their email address (2FA)

### Access Control

- [ ] **ACL-01**: Users can belong to any number of groups
- [ ] **ACL-02**: Each managed table row has a view_groups field; only users in matching groups can view the row
- [ ] **ACL-03**: Each managed table row has an edit_groups field; only users in matching groups can edit the row (separate from view)

### Data Management

- [ ] **DATA-01**: Every managed table automatically has standard fields: created_at, modified_at, status, view_groups, edit_groups
- [ ] **DATA-02**: Admin can add custom fields to any custom table via admin UI
- [ ] **DATA-03**: Admin can customize the edit form layout for each table
- [ ] **DATA-04**: User can search, sort, and filter records on any list view using UI controls

### Versioning

- [ ] **VER-01**: System records which user modified each row and what changed (JSON snapshot before each edit)
- [ ] **VER-02**: Admin can roll back any row to any prior version
- [ ] **VER-03**: Admin can create a snapshot (full data copy) of any table
- [ ] **VER-04**: Admin can view a diff showing row-level changes to a table since its last snapshot
- [ ] **VER-05**: Admin can restore a table to its state at the time of any snapshot

### Extensibility

- [ ] **EXT-01**: Admin can register PHP hook files that execute automatically on row create/modify for any table
- [ ] **EXT-02**: Admin can create custom web pages served by the framework
- [ ] **EXT-03**: Admin can create email templates for customized outbound messages
- [ ] **EXT-04**: Admin can schedule row activation, deactivation, or deletion to occur on a specific future date

## v2 Requirements

Deferred to future release. Tracked but not in current roadmap.

### Authentication

- **AUTH-V2-01**: User can authenticate with Google Authenticator / TOTP (RFC 6238)
- **AUTH-V2-02**: User can authenticate via one-click email magic link

### Advanced Features

- **ADV-V2-01**: Field-level encryption for sensitive columns
- **ADV-V2-02**: Bulk import/export for custom tables
- **ADV-V2-03**: Role-based permission templates for quick group setup

## Out of Scope

| Feature | Reason |
|---------|--------|
| External dependencies (Composer, npm) | Core constraint — zero deps for portability |
| REST/GraphQL API | Admin UI focus; not API-first |
| Mobile app | Web-first platform |
| Real-time features (WebSockets, SSE) | Not a LAMP fit |
| OAuth/SSO login | Email/password + 2FA sufficient for admin platform |
| WYSIWYG editors | Would require JS libraries, violates zero-dep constraint |
| File upload handling | Storage complexity, defer to custom hooks |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| SEC-01 | — | Pending |
| SEC-02 | — | Pending |
| SEC-03 | — | Pending |
| SEC-04 | — | Pending |
| SEC-05 | — | Pending |
| ACL-01 | — | Pending |
| ACL-02 | — | Pending |
| ACL-03 | — | Pending |
| DATA-01 | — | Pending |
| DATA-02 | — | Pending |
| DATA-03 | — | Pending |
| DATA-04 | — | Pending |
| VER-01 | — | Pending |
| VER-02 | — | Pending |
| VER-03 | — | Pending |
| VER-04 | — | Pending |
| VER-05 | — | Pending |
| EXT-01 | — | Pending |
| EXT-02 | — | Pending |
| EXT-03 | — | Pending |
| EXT-04 | — | Pending |

**Coverage:**
- v1 requirements: 21 total
- Mapped to phases: 0
- Unmapped: 21 ⚠️ (roadmap pending)

---
*Requirements defined: 2026-02-27*
*Last updated: 2026-02-27 after initial definition*
