---
phase: 3
slug: data-integrity
status: draft
nyquist_compliant: false
wave_0_complete: false
created: 2026-03-05
---

# Phase 3 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | Custom SnowTestRunner (zero external deps) |
| **Config file** | None — tests are standalone PHP scripts |
| **Quick run command** | `php tests/test_all.php` |
| **Full suite command** | `php tests/test_all.php` |
| **Estimated runtime** | ~5 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php tests/test_all.php`
- **After every plan wave:** Run `php tests/test_all.php`
- **Before `/gsd:verify-work`:** Full suite must be green
- **Max feedback latency:** ~5 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 3-01-01 | 01 | 0 | VER-01,VER-02 | unit | `php tests/test_all.php` | ❌ W0 | ⬜ pending |
| 3-01-02 | 01 | 1 | VER-01 | unit | `php tests/test_all.php` | ✅ W0 | ⬜ pending |
| 3-01-03 | 01 | 1 | VER-01 | unit | `php tests/test_all.php` | ✅ W0 | ⬜ pending |
| 3-02-01 | 02 | 2 | VER-02 | unit | `php tests/test_all.php` | ✅ W0 | ⬜ pending |
| 3-02-02 | 02 | 2 | VER-02 | unit | `php tests/test_all.php` | ✅ W0 | ⬜ pending |
| 3-03-01 | 03 | 3 | VER-03 | integration | `php tests/test_all.php` | ✅ W0 | ⬜ pending |
| 3-03-02 | 03 | 3 | VER-03 | unit | `php tests/test_all.php` | ✅ W0 | ⬜ pending |
| 3-04-01 | 04 | 3 | VER-04 | unit | `php tests/test_all.php` | ✅ W0 | ⬜ pending |
| 3-05-01 | 04 | 4 | VER-05 | integration | `php tests/test_all.php` | ✅ W0 | ⬜ pending |
| 3-05-02 | 04 | 4 | VER-05 | integration | `php tests/test_all.php` | ✅ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `tests/test_data_integrity.php` — stubs for VER-01 through VER-05 (unit + integration tests for version capture, revert, snapshot create/diff/restore)

*Existing `test_all.php` covers database/auth/template/logging — no versioning tests present.*

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Version diff selectors appear on edit form only when history exists | VER-01 | UI rendering requires browser | Edit a custom table row, save, then open edit again — verify diff/revert selectors appear |
| Snapshot diff page renders two rows per changed pair with yellow highlight | VER-04 | HTML visual rendering | Create snapshot, edit a row, open diff — verify changed fields highlighted in warning-yellow |
| Restore confirmation page shows schema-drift warning when columns differ | VER-05 | Conditional UI warning | Not easily automated for v1 — manual test with a snapshot taken before a column was added |

---

## Validation Sign-Off

- [ ] All tasks have `<automated>` verify or Wave 0 dependencies
- [ ] Sampling continuity: no 3 consecutive tasks without automated verify
- [ ] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [ ] Feedback latency < 10s
- [ ] `nyquist_compliant: true` set in frontmatter

**Approval:** pending
