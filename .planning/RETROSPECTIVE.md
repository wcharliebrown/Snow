# Project Retrospective

*A living document updated after each milestone. Lessons feed forward into future planning.*

## Milestone: v1.0 — MVP

**Shipped:** 2026-03-17
**Phases:** 5 | **Plans:** 28

### What Was Built

- CSRF protection via per-session tokens centrally enforced in renderPage() — every page protected with one insertion
- DB-backed sessions (SnowSessionHandler with SELECT FOR UPDATE locking) + session fixation prevention
- Password policy enforcement (min length, max age, reuse control) with expiry prompts at login
- Multi-level activity logging (DB + flat-file redundancy for resilience when DB is down)
- Admin UI for active session viewer (force-logout) and password policy config form
- Row-level ACL via view_groups/edit_groups on all custom tables; NULL = open to all data users
- table_data_access permission separating data editors from schema/ACL admins
- Full group membership management on user edit page; DELETE+INSERT pattern for clean saves
- Row version history with JSON before-images; single-action rollback to any prior version
- Table snapshots (CREATE TABLE AS SELECT); diff view showing added/removed/changed rows (10k row guard)
- Atomic snapshot restore via RENAME TABLE with pre-restore auto-snapshot safety net
- PHP hook system (before/after create/update per table); hook errors silently logged
- Email OTP 2FA login fork in login.php; require_2fa per-user checkbox; security invariant: explicit unset(user_id) in 2FA path
- Custom web page CRUD + raw HTML serving via processTokens str_replace
- Email template CRUD with {{token}} substitution; sendEmailTemplate() callable by name
- Search/sort/filter on all list views via WHERE builder (simple + advanced mutually exclusive); sort field allowlisted
- col_width-driven edit form layout (half/full); defaults to half for backward compat
- Scheduled row lifecycle: activate_at, deactivate_at, delete_at per-table columns; processScheduledActions() on page load

### What Worked

- **Dependency-driven phase order** — the phase sequence (security → ACL → versioning → hooks → UX) had zero rework because each phase built on a stable foundation. The dependency graph was designed upfront and held.
- **Provisioner as absorption layer** — provisionCustomTable() / migrateExistingCustomTables() with INFORMATION_SCHEMA guards absorbed every new standard column (ACL columns, schedule columns) cleanly. New columns never required manual table surgery.
- **Function-per-file discipline** — no monolithic files emerged despite the breadth of the milestone. Easy to navigate and extend.
- **Phase-delimited migration blocks** — labelled SQL blocks enabling targeted sed extraction for idempotent re-runs was a lifesaver for Phase 3/4/5 migrations.
- **Human verification checkpoints** — Phases 4 and 5 included explicit human-in-the-loop checkpoints. Phase 5 checkpoint verified all browser features with zero code changes needed — the plan was complete before the checkpoint ran.
- **Test-first stubs** — each phase started with test stubs (01-01-PLAN covers schema, then tests scaffolded). Kept test coverage honest across the milestone.

### What Was Inefficient

- **ROADMAP.md plan checkbox drift** — the progress table and phase plan checkboxes in ROADMAP.md fell out of sync (showed Phase 1 as 4/6 while all 6 SUMMARY.md files existed). This required manual correction at milestone close. The live ROADMAP should be updated in real-time as plans complete.
- **No milestone audit** — v1.0 was archived without running `/gsd:audit-milestone` first. Proceeding on clear requirements coverage, but the audit step adds confidence about cross-phase integration and E2E flows.
- **STATE.md performance metrics** — the velocity table only tracked early phases and stopped updating mid-milestone. Not harmful but the data was incomplete.

### Patterns Established

- **INFORMATION_SCHEMA PREPARE/EXECUTE guard** — the MySQL 8.0–compatible pattern for conditional ALTER TABLE. Use this everywhere in migrations.
- **Sentinel null for optional group writes** — `$viewGroupIds = null` distinguishes "do not write" from empty array "open to all". Critical for POST handlers that mix group-aware and group-agnostic code paths.
- **Per-session CSRF tokens** — chosen over per-request tokens to avoid back-button and multi-tab breakage. This is the right choice for traditional PHP form apps.
- **Security fork in login.php, not auth.php** — keeps loginUser() reusable. The explicit `unset($_SESSION['user_id'])` in the 2FA path is a critical security invariant; document this in any future auth changes.
- **Hook errors as silent logError()** — hooks are optional extensions; the main flow must not be broken by hook failures. Log and continue.
- **uniqid() sentinels in EXT-04 tests** — handles tables without AUTO_INCREMENT on id. Generalizes to any test that needs to identify a specific inserted row in a messy test DB.

### Key Lessons

1. **Design the provisioner as a column absorption layer from day one.** Every standard column that gets added to custom tables (ACL columns, schedule columns, etc.) should flow through provisionCustomTable() and migrateExistingCustomTables() with idempotent guards. This pattern scaled from 3 standard columns to 8 without friction.
2. **Split permissions before the first user hits a gate.** table_data_access emerged as a gap during Phase 2 execution. It's easy to add, but the lesson is to think about permission granularity when designing the permission system — not when a content manager can't reach a data page.
3. **MySQL 8.0 strict mode will bite you on migrations.** Specifically: `ALTER TABLE ADD COLUMN IF NOT EXISTS` is MariaDB-only, and `LONGTEXT`/`BLOB` columns cannot have DEFAULT values. Write migrations against MySQL 8.0 from the start.
4. **RENAME TABLE is DDL and auto-commits in MySQL.** Don't wrap it in BEGIN/COMMIT — it will silently auto-commit, and rollback will return "no active transaction". Rely on MySQL's native two-pair atomicity for restore sequences.
5. **Verify framework API contracts before writing plans.** renderPage() expects a file path string, not an array. This caused an HTTP 500 during Phase 3 verification. Read the framework's calling conventions before specifying plan steps.

### Cost Observations

- Model: Claude Sonnet 4.6 throughout
- Sessions: ~20 (estimated across 23 days)
- Notable: Phase 5 human verification passed with zero code changes — the plan + implementation cycle was efficient enough that the verification checkpoint had nothing to catch.

---

## Cross-Milestone Trends

### Process Evolution

| Milestone | Phases | Plans | Key Change |
|-----------|--------|-------|------------|
| v1.0 MVP | 5 | 28 | Initial milestone — established all foundational patterns |

### Cumulative Quality

| Milestone | Test Files | Requirements | Zero-Dep Additions |
|-----------|-----------|-------------|-------------------|
| v1.0 | 4 (data_integrity, extensibility, user_experience, snow_users) | 21/21 | 0 |

### Top Lessons (Verified Across Milestones)

*(Populate after v1.1+)*
