# Milestones

## v1.0 MVP (Shipped: 2026-03-17)

**Phases completed:** 5 phases, 28 plans, 0 tasks

**Key accomplishments:**
1. Security Foundations: CSRF protection, DB-backed sessions (SELECT FOR UPDATE locking), password policy enforcement, and structured activity logging with flat-file redundancy
2. Access Control: Row-level view/edit ACL via view_groups/edit_groups, group membership management, table_data_access permission separating schema admins from data editors
3. Data Integrity: Row version history with JSON before-images, single-action rollback to any prior version, table snapshots with diff view (added/removed/changed rows)
4. Snapshot Restore: Atomic RENAME TABLE sequence with pre-restore auto-snapshot safety net and schema-drift warning on restore confirmation
5. Extensibility: PHP hook system for row events (before/after create/update), email OTP 2FA login fork, custom web pages and email template CRUD
6. User Experience: Search/sort/filter on all list views without per-table code, customizable column widths and edit form layouts, scheduled row lifecycle (activate_at, deactivate_at, delete_at)

**Git range:** feat(01-security-foundations-01) → docs(phase-05)
**Lines of code:** ~10,887 PHP across 129 files changed

---

